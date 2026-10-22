<?php
// Detail pengajuan + panel aksi sesuai peran
// FR-06 pelacakan · FR-08 HRD · FR-09 Finance (berdampingan) · FR-10 Kasir · FR-11 riwayat
require 'config.php';
require 'partials/list.php';
$u = require_login();

$st = $pdo->prepare('SELECT c.*, u.nama, u.divisi, u.bank, u.no_rekening FROM claims c JOIN users u ON u.id = c.user_id WHERE c.id = ?');
$st->execute([(int)($_GET['id'] ?? 0)]);
$c = $st->fetch();
if (!$c || !boleh_lihat($u, $c)) {
    flash('Pengajuan tidak ditemukan atau bukan milikmu.', 'err');
    redirect(beranda_peran($u['role']));
}

$st = $pdo->prepare('SELECT * FROM claim_items WHERE claim_id = ? ORDER BY tanggal, id');
$st->execute([$c['id']]);
$items = $st->fetchAll();

$st = $pdo->prepare('SELECT h.*, u.nama, u.role FROM claim_histories h JOIN users u ON u.id = h.user_id WHERE h.claim_id = ? ORDER BY h.created_at, h.id');
$st->execute([$c['id']]);
$riwayat = $st->fetchAll();

$catatan_akhir = null;
if (in_array($c['status'], ['revisi', 'ditolak'], true)) {
    foreach (array_reverse($riwayat) as $h) if ($h['catatan']) { $catatan_akhir = $h; break; }
}

$step = STATUS[$c['status']]['step'];
$is_hrd     = $u['role'] === 'hrd' && $c['status'] === 'diajukan';
$is_finance = $u['role'] === 'finance' && $c['status'] === 'disetujui';
$is_kasir   = $u['role'] === 'kasir' && $c['status'] === 'terverifikasi';
$n_over = count(array_filter($items, fn($it) => over_limit($it['kategori'], $it['nominal'])));
$n_nota = count(array_filter($items, fn($it) => !empty($it['nota'])));
$back = $u['role'] === 'direktur' ? 'semua.php' : 'dashboard.php';
$warna_peran = ['pegawai' => '#2B59FF', 'hrd' => '#7C3AED', 'finance' => '#0369A1', 'kasir' => '#15803D', 'direktur' => '#C2410C'];

$judul_halaman = $c['kode'] . ' · ' . $c['judul'];
include 'partials/header.php';
?>
<a href="<?= $back ?>" class="back"><?= ico('back') ?>Kembali</a>

<section class="claim-head card">
  <div class="ch-txt">
    <small class="muted"><?= e($c['kode']) ?> · diajukan <?= $riwayat ? jam($riwayat[0]['created_at']) : '-' ?></small>
    <h1><?= e($c['judul']) ?></h1>
    <p class="muted"><?= e($c['nama']) ?> · <?= e($c['divisi']) ?> · <?= e($c['kota_tujuan']) ?> · <?= periode($c['tgl_berangkat'], $c['tgl_pulang']) ?></p>
    <p><?= e($c['keperluan']) ?></p>
  </div>
  <div class="ch-side">
    <span class="cap s-<?= e($c['status']) ?>" data-cap><?= e(STATUS[$c['status']]['cap']) ?><small><?= e(STATUS[$c['status']]['label']) ?></small></span>
    <b class="ch-total num"><?= rp($c['total']) ?></b>
  </div>
</section>

<?php if ($step > 0): ?>
  <ol class="track" aria-label="Tahap pengajuan">
    <?php foreach (['Diajukan', 'Manajer HRD', 'Finance', 'Dibayar'] as $i => $s):
      $cls = ($i < $step || $step === 4) ? 'done' : ($i === $step ? 'now' : ''); ?>
      <li class="<?= $cls ?>" style="--d:<?= $i * .18 ?>s"><i><?= $cls === 'done' ? ico('check') : $i + 1 ?></i><span><?= $s ?></span></li>
    <?php endforeach; ?>
  </ol>
<?php endif; ?>

<?php if ($catatan_akhir): ?>
  <div class="note <?= $c['status'] === 'ditolak' ? 'bad' : 'warn' ?>">
    <small><?= $c['status'] === 'ditolak' ? 'Alasan penolakan' : 'Catatan revisi' ?> dari <?= e($catatan_akhir['nama']) ?> (<?= e(ROLES[$catatan_akhir['role']]) ?>)</small>
    <p><?= e($catatan_akhir['catatan']) ?></p>
    <?php if ($u['role'] === 'pegawai' && $c['status'] === 'revisi'): ?>
      <a class="btn primary sm" href="ajukan.php?edit=<?= (int)$c['id'] ?>"><?= ico('pen') ?>Perbaiki &amp; ajukan ulang</a>
    <?php elseif ($u['role'] === 'pegawai'): ?>
      <small class="muted">Pengajuan yang ditolak bersifat final. Buat pengajuan baru jika perlu.</small>
    <?php endif; ?>
  </div>
<?php endif; ?>

<form method="post" action="aksi.php" id="faksi" enctype="multipart/form-data">
  <?= csrf_field() ?>
  <input type="hidden" name="id" value="<?= (int)$c['id'] ?>">

  <div class="review<?= $is_finance ? ' finance-mode' : '' ?>">
    <section class="card">
      <div class="card-head"><h2>Rincian biaya</h2><small class="muted"><?= count($items) ?> item · <?= $n_nota ?> nota<?= $n_over ? " · $n_over di atas batas" : '' ?></small></div>
      <?php if ($is_finance): ?><p class="muted small">Klik baris untuk melihat notanya di samping. Centang item yang notanya jelas dan nominalnya cocok.</p><?php endif; ?>
      <ul class="lines" role="list">
        <?php foreach ($items as $k => $it): $over = over_limit($it['kategori'], $it['nominal']); ?>
          <li class="line<?= $k === 0 ? ' sel' : '' ?>" tabindex="0" data-nota="<?= e($it['nota'] ?? '') ?>" data-label="<?= e($it['keterangan']) ?>">
            <?php if ($is_finance): ?><label class="chk"><input type="checkbox" name="cek[]" value="<?= (int)$it['id'] ?>" aria-label="Nota <?= e($it['keterangan']) ?> sesuai"><span></span></label><?php endif; ?>
            <span class="cat-ico"><?= ico(KATEGORI[$it['kategori']]['ico']) ?></span>
            <span class="line-txt"><b><?= e($it['keterangan']) ?></b><small><?= e(KATEGORI[$it['kategori']]['label']) ?> · <?= tgl_pendek($it['tanggal']) ?><?= $it['nota'] ? '' : ' · tanpa file (data contoh)' ?></small>
              <?php if ($over): ?><em class="flag">di atas batas <?= rp(KATEGORI[$it['kategori']]['limit']) ?></em><?php endif; ?></span>
            <span class="num line-amt"><?= rp($it['nominal']) ?></span>
          </li>
        <?php endforeach; ?>
      </ul>
      <div class="sum-total"><span>Total</span><b class="num"><?= rp($c['total']) ?></b></div>
    </section>

    <section class="viewer" id="viewer" aria-live="polite">
      <div class="viewer-bar"><span id="vLabel">Nota</span><button type="button" class="icon-btn dark" id="vZoom" aria-label="Perbesar nota" title="Perbesar"><?= ico('zoom') ?></button></div>
      <div class="viewer-body" id="vBody"></div>
      <span class="viewer-cap" id="vCap" hidden>TERVERIFIKASI</span>
    </section>
  </div>

  <?php $catatan = '<label class="field"><span>Catatan <small class="muted">(wajib untuk revisi atau tolak)</small></span><textarea name="catatan" rows="2" placeholder="mis. Nominal di nota makan tidak terbaca, mohon foto ulang"></textarea></label>'; ?>

  <?php if ($is_hrd): ?>
    <section class="card action">
      <h2 class="h-md">Keputusan Manajer HRD</h2>
      <p class="muted small">Pastikan perjalanan ini ada di surat tugas <?= e(nama_pendek($c['nama'])) ?>.<?= $n_over ? " Ada $n_over item di atas batas biaya." : '' ?></p>
      <?= $catatan ?>
      <div class="btnrow"><button class="btn ok" name="aksi" value="setuju"><?= ico('check') ?>Setujui</button><button class="btn warn" name="aksi" value="revisi">Minta revisi</button><button class="btn bad" name="aksi" value="tolak">Tolak</button></div>
    </section>
  <?php elseif ($is_finance): ?>
    <section class="card action">
      <h2 class="h-md">Verifikasi Finance</h2>
      <?= $catatan ?>
      <div class="btnrow"><button class="btn ok" name="aksi" value="verifikasi" id="btnVerif" disabled>Verifikasi</button><button class="btn warn" name="aksi" value="revisi">Minta revisi</button></div>
    </section>
  <?php elseif ($is_kasir): ?>
    <section class="card action pay">
      <div class="pay-head"><div><small class="muted">Bayar ke</small><h2 class="h-md"><?= e($c['nama']) ?></h2></div><b class="pay-amt num"><?= rp($c['total']) ?></b></div>
      <div class="pay-grid">
        <div class="bank-card">
          <div class="bc-top"><small>Rekening terdaftar</small><b><?= e($c['bank'] ?: '—') ?></b></div>
          <span class="bc-num num"><?= e($c['no_rekening'] ? trim(chunk_split(preg_replace('/\D/', '', $c['no_rekening']), 4, ' ')) : 'Belum diisi') ?></span>
          <div class="bc-foot"><span>a.n. <?= e($c['nama']) ?></span><?php if ($c['no_rekening']): ?><button type="button" class="bc-copy" data-copy="<?= e($c['no_rekening']) ?>">Salin</button><?php endif; ?></div>
        </div>
        <div class="grid2">
          <label class="field"><span>Metode</span><select name="metode">
            <?php if ($c['bank']): ?><option>Transfer <?= e($c['bank']) ?></option><?php endif; ?>
            <?php foreach (BANK as $b) if ($b !== $c['bank']) echo '<option>Transfer ' . e($b) . '</option>'; ?><option>Tunai</option></select></label>
          <label class="field"><span>No. referensi</span><input name="no_ref" class="num" value="TRF-<?= date('ymd') . '-' . random_int(100, 999) ?>" required></label>
          <label class="drop sm span2"><input type="file" name="bukti" accept=".jpg,.jpeg,.png,.webp,.pdf"><span class="drop-prev"></span><span class="drop-txt"><b>Unggah bukti transfer</b><small>opsional · JPG, PNG, PDF · maks 5 MB</small></span></label>
        </div>
      </div>
      <button class="btn ok block lg" name="aksi" value="bayar"><?= ico('check') ?>Konfirmasi sudah dibayar</button>
    </section>
  <?php endif; ?>
</form>

<?php if ($c['status'] === 'dibayar' && $c['metode_bayar']): ?>
  <section class="card paid">
    <span class="coin">Rp</span>
    <div><h2 class="h-md">Sudah dibayar</h2><p class="muted"><?= e($c['metode_bayar']) ?> · <span class="num"><?= e($c['no_referensi']) ?></span></p></div>
    <?php if ($c['bukti_transfer']): ?><a class="btn soft sm" href="<?= e($c['bukti_transfer']) ?>" target="_blank" rel="noopener"><?= ico('file') ?>Bukti transfer</a><?php endif; ?>
  </section>
<?php endif; ?>

<section class="card">
  <h2 class="h-md">Riwayat</h2>
  <ol class="timeline">
    <?php foreach ($riwayat as $h): ?>
      <li><i style="background:<?= $warna_peran[$h['role']] ?? '#86868B' ?>"></i><div>
        <div><b><?= e($h['aksi']) ?></b> oleh <?= e($h['nama']) ?> <span class="muted">(<?= e(ROLES[$h['role']]) ?>)</span></div>
        <time class="muted small" datetime="<?= e($h['created_at']) ?>"><?= jam($h['created_at']) ?></time>
        <?php if ($h['catatan']): ?><p class="bubble"><?= e($h['catatan']) ?></p><?php endif; ?>
      </div></li>
    <?php endforeach; ?>
  </ol>
</section>
<?php include 'partials/footer.php'; ?>

<?php
// FR-03 Form pengajuan · FR-04 Unggah nota · FR-05 Total & batas · FR-07 Perbaikan revisi
// DR-01 batas 14 hari · DR-02 tanggal nota dalam rentang dinas · DR-04 nota wajib
require 'config.php';
$u = require_role('pegawai');

$edit_id = (int)($_GET['edit'] ?? $_POST['edit_id'] ?? 0);
$claim = null;
$errors = [];
$hari_ini = date('Y-m-d');
$batas_awal = date('Y-m-d', strtotime("-" . BATAS_HARI_PENGAJUAN . " days"));

if ($edit_id) {
    $st = $pdo->prepare('SELECT * FROM claims WHERE id = ? AND user_id = ?');
    $st->execute([$edit_id, $u['id']]);
    $claim = $st->fetch();
    // NFR-05: hanya status revisi yang boleh diubah pegawai; DR-06 ditolak = final
    if (!$claim || $claim['status'] !== 'revisi') {
        flash('Pengajuan ini tidak bisa diubah. Hanya pengajuan berstatus "Perlu Revisi" yang bisa diperbaiki.', 'err');
        redirect('dashboard.php');
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $form = [
        'judul'         => trim($_POST['judul'] ?? ''),
        'kota_tujuan'   => trim($_POST['kota_tujuan'] ?? ''),
        'keperluan'     => trim($_POST['keperluan'] ?? ''),
        'tgl_berangkat' => $_POST['tgl_berangkat'] ?? '',
        'tgl_pulang'    => $_POST['tgl_pulang'] ?? '',
    ];
    $items = [];
    foreach (($_POST['items'] ?? []) as $idx => $it) {
        $items[$idx] = [
            'kategori'   => array_key_exists($it['kategori'] ?? '', KATEGORI) ? $it['kategori'] : 'lain',
            'keterangan' => trim($it['keterangan'] ?? ''),
            'tanggal'    => $it['tanggal'] ?? '',
            'nominal'    => (float)preg_replace('/[^0-9]/', '', $it['nominal'] ?? '0'),
            'nota'       => $it['nota_lama'] ?? '',
        ];
    }
    $ok_tgl = fn($d) => (bool)DateTime::createFromFormat('Y-m-d', $d);

    if ($form['judul'] === '' || $form['kota_tujuan'] === '' || $form['keperluan'] === '') $errors[] = 'Judul dinas, kota tujuan, dan keperluan wajib diisi.';
    if (!$ok_tgl($form['tgl_berangkat']) || !$ok_tgl($form['tgl_pulang'])) {
        $errors[] = 'Tanggal berangkat dan tanggal pulang wajib diisi.';
    } else {
        if ($form['tgl_pulang'] < $form['tgl_berangkat']) $errors[] = 'Tanggal pulang tidak boleh sebelum tanggal berangkat.';
        if ($form['tgl_pulang'] > $hari_ini) $errors[] = 'Reimburse diajukan setelah pulang dinas. Tanggal pulang tidak boleh melewati hari ini.';
        // DR-01 berlaku untuk pengajuan baru; pengajuan revisi tidak terkena karena sudah masuk tepat waktu
        if (!$claim && $form['tgl_pulang'] < $batas_awal)
            $errors[] = 'Batas pengajuan ' . BATAS_HARI_PENGAJUAN . ' hari setelah pulang dinas sudah lewat (pulang ' . tgl($form['tgl_pulang']) . '). Hubungi Manajer HRD.';
    }
    if (!$items) $errors[] = 'Tambahkan minimal satu item biaya.';

    $no = 0;
    foreach ($items as $idx => &$it) {
        $no++;
        if ($it['keterangan'] === '' || !$ok_tgl($it['tanggal']) || $it['nominal'] <= 0) { $errors[] = "Item $no: keterangan, tanggal, dan nominal wajib diisi."; continue; }
        if ($ok_tgl($form['tgl_berangkat']) && ($it['tanggal'] < $form['tgl_berangkat'] || $it['tanggal'] > $form['tgl_pulang']))
            $errors[] = "Item $no: tanggal nota (" . tgl($it['tanggal']) . ") di luar tanggal dinas.";
        [$path, $err] = simpan_upload(file_dari_array('nota', $idx), 'nota', EKSTENSI_NOTA, 'nota');
        if ($err) $errors[] = "Item $no: $err";
        if ($path) $it['nota'] = $path;
        if (!$it['nota'] && !$err) $errors[] = "Item $no belum ada notanya. Unggah foto atau PDF nota supaya Finance bisa memeriksa.";
    }
    unset($it);

    if (!$errors) {
        $total = array_sum(array_column($items, 'nominal'));
        $pdo->beginTransaction();
        try {
            if ($claim) {
                $pdo->prepare('UPDATE claims SET judul=?, kota_tujuan=?, keperluan=?, tgl_berangkat=?, tgl_pulang=?, total=?, status="diajukan" WHERE id=?')
                    ->execute([$form['judul'], $form['kota_tujuan'], $form['keperluan'], $form['tgl_berangkat'], $form['tgl_pulang'], $total, $claim['id']]);
                $pdo->prepare('DELETE FROM claim_items WHERE claim_id=?')->execute([$claim['id']]);
                $claim_id = $claim['id'];
                $kode = $claim['kode'];
                $aksi = 'Mengajukan ulang';
            } else {
                $kode = kode_baru($pdo);
                $pdo->prepare('INSERT INTO claims (kode, user_id, judul, kota_tujuan, keperluan, tgl_berangkat, tgl_pulang, total, status) VALUES (?,?,?,?,?,?,?,?,"diajukan")')
                    ->execute([$kode, $u['id'], $form['judul'], $form['kota_tujuan'], $form['keperluan'], $form['tgl_berangkat'], $form['tgl_pulang'], $total]);
                $claim_id = $pdo->lastInsertId();
                $aksi = 'Mengajukan';
            }
            $ins = $pdo->prepare('INSERT INTO claim_items (claim_id, kategori, keterangan, tanggal, nominal, nota) VALUES (?,?,?,?,?,?)');
            foreach ($items as $it) $ins->execute([$claim_id, $it['kategori'], $it['keterangan'], $it['tanggal'], $it['nominal'], $it['nota']]);
            catat_riwayat($pdo, $claim_id, $u['id'], $aksi);
            $pdo->commit();
        } catch (Throwable $ex) {
            $pdo->rollBack();
            throw $ex;
        }
        flash("$kode " . ($claim ? 'diajukan ulang ke Manajer HRD.' : 'terkirim ke Manajer HRD.'), 'ok', 'stamp');
        redirect('detail.php?id=' . $claim_id);
    }
} elseif ($claim) {
    $form = $claim;
    $st = $pdo->prepare('SELECT * FROM claim_items WHERE claim_id = ? ORDER BY tanggal, id');
    $st->execute([$claim['id']]);
    $items = $st->fetchAll();
} else {
    $form = ['judul' => '', 'kota_tujuan' => '', 'keperluan' => '', 'tgl_berangkat' => '', 'tgl_pulang' => ''];
    $items = [['kategori' => 'tiket', 'keterangan' => '', 'tanggal' => '', 'nominal' => '', 'nota' => '']];
}

$catatan_revisi = null;
if ($claim) {
    $st = $pdo->prepare('SELECT h.catatan, h.created_at, u.nama, u.role FROM claim_histories h JOIN users u ON u.id=h.user_id
                         WHERE h.claim_id=? AND h.aksi="Diminta revisi" ORDER BY h.id DESC LIMIT 1');
    $st->execute([$claim['id']]);
    $catatan_revisi = $st->fetch();
}

function item_row($idx, $it) {
    $opsi = '';
    foreach (KATEGORI as $k => $v) $opsi .= '<option value="' . $k . '"' . (($it['kategori'] ?? '') === $k ? ' selected' : '') . '>' . $v['label'] . '</option>';
    $nota = $it['nota'] ?? '';
    $nominal = ($it['nominal'] ?? '') === '' ? '' : number_format((float)$it['nominal'], 0, ',', '.');
    $prev = '';
    if ($nota) $prev = is_pdf($nota) ? '<span class="pdf-chip">PDF</span>' : '<img src="' . e($nota) . '" alt="Nota">';
    return '<fieldset class="item" data-idx="' . $idx . '">
      <legend class="sr-only">Item biaya</legend>
      <div class="item-grid">
        <label class="field"><span>Kategori</span><select name="items[' . $idx . '][kategori]" class="kat">' . $opsi . '</select></label>
        <label class="field"><span>Keterangan</span><input name="items[' . $idx . '][keterangan]" class="ket" value="' . e($it['keterangan'] ?? '') . '" placeholder="mis. Kereta Semarang–Yogyakarta PP" required></label>
        <label class="field"><span>Tanggal nota</span><input type="date" name="items[' . $idx . '][tanggal]" class="tg" value="' . e($it['tanggal'] ?? '') . '" required></label>
        <label class="field"><span>Nominal</span><span class="rp-in"><i>Rp</i><input inputmode="numeric" class="num nominal" name="items[' . $idx . '][nominal]" value="' . e($nominal) . '" placeholder="0" required></span></label>
      </div>
      <input type="hidden" name="items[' . $idx . '][nota_lama]" value="' . e($nota) . '" class="nota-lama">
      <div class="nota' . ($nota ? ' has' : '') . '">
        <label class="drop">
          <input type="file" class="nota-file" name="nota[' . $idx . ']" accept=".jpg,.jpeg,.png,.webp,.pdf,image/*,application/pdf">
          <span class="drop-prev">' . $prev . '</span>
          <span class="drop-txt"><b>' . ($nota ? 'Nota tersimpan' : 'Belum ada nota') . '</b><small>' . ($nota ? 'Foto ulang atau unggah untuk mengganti' : 'Tarik file ke sini, atau pilih cara di samping · maks 5 MB') . '</small></span>
        </label>
        <div class="nota-btns">
          <button type="button" class="btn soft cam-open">' . ico('camera') . 'Foto langsung</button>
          <button type="button" class="btn soft up-open">' . ico('up') . 'Unggah file</button>
        </div>
      </div>
      <div class="item-foot"><span class="limit-msg" aria-live="polite"></span><button type="button" class="btn ghost sm hapus">' . ico('x') . 'Hapus</button></div>
    </fieldset>';
}

$judul_halaman = $claim ? 'Perbaiki ' . $claim['kode'] : 'Ajukan reimburse';
include 'partials/header.php';
?>
<div class="page-head">
  <div>
    <?php if ($claim): ?><a class="back" href="detail.php?id=<?= (int)$claim['id'] ?>"><?= ico('back') ?>Kembali ke <?= e($claim['kode']) ?></a><?php endif; ?>
    <h1><?= $claim ? 'Perbaiki pengajuan' : 'Ajukan reimburse' ?></h1>
    <p>Satu pengajuan untuk satu perjalanan dinas, paling lambat <?= BATAS_HARI_PENGAJUAN ?> hari setelah pulang.</p>
  </div>
</div>

<form class="compose" method="post" enctype="multipart/form-data" id="cform" novalidate
      data-today="<?= $hari_ini ?>" data-min="<?= $claim ? '' : $batas_awal ?>">
  <?= csrf_field() ?>
  <?php if ($claim): ?><input type="hidden" name="edit_id" value="<?= (int)$claim['id'] ?>"><?php endif; ?>

  <div class="compose-main">
    <?php if ($errors): ?>
      <div class="alert bad shake" role="alert"><b>Pengajuan belum bisa dikirim:</b><ul><?php foreach ($errors as $er): ?><li><?= e($er) ?></li><?php endforeach; ?></ul></div>
    <?php endif; ?>
    <?php if ($catatan_revisi): ?>
      <div class="note warn"><small>Catatan revisi dari <?= e($catatan_revisi['nama']) ?> (<?= e(ROLES[$catatan_revisi['role']]) ?>) · <?= jam($catatan_revisi['created_at']) ?></small><p><?= e($catatan_revisi['catatan']) ?></p></div>
    <?php endif; ?>

    <section class="card">
      <h2 class="h-md">Perjalanan dinas</h2>
      <div class="grid2">
        <label class="field span2"><span>Judul dinas</span><input name="judul" id="fJudul" required value="<?= e($form['judul']) ?>" placeholder="mis. Audit stok cabang Malioboro"></label>
        <label class="field"><span>Kota tujuan</span><input name="kota_tujuan" id="fKota" required value="<?= e($form['kota_tujuan']) ?>" placeholder="mis. Yogyakarta"></label>
        <label class="field"><span>Keperluan</span><input name="keperluan" required value="<?= e($form['keperluan']) ?>" placeholder="mis. Audit stok gudang"></label>
        <label class="field"><span>Tanggal berangkat</span><input type="date" name="tgl_berangkat" id="fBerangkat" required value="<?= e($form['tgl_berangkat']) ?>" max="<?= $hari_ini ?>"></label>
        <label class="field"><span>Tanggal pulang</span><input type="date" name="tgl_pulang" id="fPulang" required value="<?= e($form['tgl_pulang']) ?>" max="<?= $hari_ini ?>"<?= $claim ? '' : ' min="' . $batas_awal . '"' ?>></label>
      </div>
      <p class="date-msg" id="dateMsg" aria-live="polite"></p>
    </section>

    <section class="card">
      <div class="card-head"><h2>Rincian biaya &amp; nota</h2><small class="muted">Batas per item: makan <?= rp(KATEGORI['makan']['limit']) ?> · penginapan <?= rp(KATEGORI['hotel']['limit']) ?> · lain-lain <?= rp(KATEGORI['lain']['limit']) ?></small></div>
      <div class="items" id="items">
        <?php $i = 0; foreach ($items as $it) echo item_row($i++, $it); ?>
      </div>
      <button type="button" class="btn dashed block" id="tambah"><?= ico('plus') ?>Tambah item biaya</button>
    </section>
  </div>

  <aside class="compose-side card">
    <small class="muted">Ringkasan</small>
    <div class="sum-lines" id="pvLines"></div>
    <div class="sum-total"><span>Total</span><b class="num" id="total">Rp 0</b></div>
    <ul class="checks" id="checks">
      <li data-c="tgl">Diajukan dalam <?= BATAS_HARI_PENGAJUAN ?> hari setelah pulang</li>
      <li data-c="rentang">Tanggal nota di dalam tanggal dinas</li>
      <li data-c="nota">Nota lengkap</li>
    </ul>
    <button class="btn primary block lg" type="submit" id="btnKirim"><?= $claim ? 'Ajukan ulang ke HRD' : 'Kirim ke Manajer HRD' ?></button>
    <p class="muted small center" id="kirimHint">Setelah dikirim, pengajuan tidak bisa diubah kecuali dikembalikan untuk revisi.</p>
  </aside>
</form>

<!-- kamera untuk memotret nota langsung -->
<div class="cam" id="cam" hidden role="dialog" aria-modal="true" aria-label="Foto nota">
  <div class="cam-box">
    <video id="camVideo" autoplay playsinline muted></video>
    <img id="camShot" alt="Hasil foto nota" hidden>
    <div class="cam-frame" aria-hidden="true"><i></i><i></i><i></i><i></i><span class="scanline"></span></div>
    <p class="cam-hint" id="camHint">Posisikan nota di dalam bingkai</p>
    <div class="cam-bar" id="camBar">
      <button type="button" class="cam-txt" id="camClose">Batal</button>
      <button type="button" class="shutter" id="camSnap" aria-label="Ambil foto"><span></span></button>
      <button type="button" class="cam-txt" id="camFlip" aria-label="Ganti kamera"><?= ico('flip') ?></button>
    </div>
    <div class="cam-bar" id="camAfter" hidden>
      <button type="button" class="btn glassy" id="camRetake">Foto ulang</button>
      <button type="button" class="btn ok" id="camUse"><?= ico('check') ?>Pakai foto ini</button>
    </div>
    <canvas id="camCanvas" hidden></canvas>
  </div>
</div>
<input type="file" id="camFallback" accept="image/*" capture="environment" hidden>

<template id="tpl"><?= item_row('__IDX__', ['kategori' => 'makan']) ?></template>
<script>
window.STRUKI_KATEGORI = <?= json_encode(array_map(fn($k) => ['limit' => $k['limit'], 'label' => $k['label']], KATEGORI)) ?>;
window.STRUKI_NEXT = <?= $i ?>;
</script>
<?php include 'partials/footer.php'; ?>

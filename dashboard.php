<?php
// FR-06 Beranda pegawai · FR-08/09/10 antrean · FR-15 riwayat tindakan
require 'config.php';
require 'partials/list.php';
$u = require_login();
if ($u['role'] === 'direktur') redirect('laporan.php');
$v = $_GET['v'] ?? '';

$judul_halaman = 'Beranda';

if ($u['role'] === 'pegawai') {
    // ================= PEGAWAI =================
    $st = $pdo->prepare(SQL_LIST . " WHERE c.user_id = ? ORDER BY last_at DESC");
    $st->execute([$u['id']]);
    $rows = $st->fetchAll();
    $sum = fn($sts) => array_sum(array_map(fn($r) => in_array($r['status'], $sts, true) ? (float)$r['total'] : 0, $rows));
    $cnt = fn($sts) => count(array_filter($rows, fn($r) => in_array($r['status'], $sts, true)));
    $proses = ['diajukan', 'disetujui', 'terverifikasi'];
    $avg = $pdo->prepare("SELECT AVG(TIMESTAMPDIFF(HOUR, a.awal, a.akhir))/24 FROM (
        SELECT h.claim_id, MIN(h.created_at) awal, MAX(CASE WHEN h.aksi='Dibayar' THEN h.created_at END) akhir
        FROM claim_histories h JOIN claims c ON c.id=h.claim_id WHERE c.user_id=? AND c.status='dibayar' GROUP BY h.claim_id) a");
    $avg->execute([$u['id']]);
    $avg = (float)$avg->fetchColumn();

    $aktif = null;
    foreach ($rows as $r) if (in_array($r['status'], $proses, true)) { $aktif = $r; break; }
    $revisi = null;
    foreach ($rows as $r) if ($r['status'] === 'revisi') { $revisi = $r; break; }
    $meja = ['diajukan' => 'Manajer HRD', 'disetujui' => 'Finance', 'terverifikasi' => 'Kasir'];
    if ($revisi)      $kalimat = 'Pengajuan "' . $revisi['judul'] . '" dikembalikan untuk revisi. Perbaiki lalu ajukan ulang.';
    elseif ($aktif)   $kalimat = 'Pengajuan "' . $aktif['judul'] . '" sedang di meja ' . $meja[$aktif['status']] . '. Kamu dapat kabar begitu berpindah.';
    elseif ($rows)    $kalimat = 'Semua pengajuanmu sudah selesai diproses. Habis dinas lagi? Ajukan dari sini.';
    else              $kalimat = 'Belum ada pengajuan. Habis dinas luar kota? Foto notanya dan ajukan dari sini.';

    include 'partials/header.php';
    ?>
    <section class="hello">
      <div class="hello-txt">
        <span><?= sapaan() ?>,</span>
        <h1><?= e($u['nama']) ?></h1>
        <p><?= e($kalimat) ?></p>
        <div class="hello-cta">
          <a class="btn white" href="ajukan.php"><?= ico('plus') ?>Ajukan reimburse</a>
          <?php if ($revisi): ?><a class="btn glassy" href="ajukan.php?edit=<?= (int)$revisi['id'] ?>">Perbaiki sekarang</a>
          <?php elseif ($aktif): ?><a class="btn glassy" href="detail.php?id=<?= (int)$aktif['id'] ?>">Lihat status</a><?php endif; ?>
        </div>
      </div>
      <div class="hello-art" aria-hidden="true">
        <div class="paper-back"></div>
        <div class="paper floaty"><b>KAI ACCESS</b>Smg–Yk PP<br>320.000<i></i><b>TOTAL 320.000</b><span class="mini-cap">LUNAS</span></div>
      </div>
    </section>

    <div class="tiles">
      <?= tile('Sedang diproses', $sum($proses), $cnt($proses) . ' pengajuan', 'clock', '#7C3AED', 'rp') ?>
      <?= tile('Sudah kembali ke rekening', $sum(['dibayar']), $u['bank'] ? $u['bank'] . ' ' . $u['no_rekening'] : $cnt(['dibayar']) . ' pengajuan', 'check', '#15803D', 'rp') ?>
      <?= tile('Perlu diperbaiki', $cnt(['revisi']), $cnt(['revisi']) ? 'buka lalu ajukan ulang' : 'tidak ada revisi', 'pen', '#C2410C') ?>
      <?= tile('Rata-rata sampai cair', round($avg, 1), $avg ? 'dari pengajuanmu' : 'belum ada yang dibayar', 'bolt', '#0369A1', 'dec', false, ' hari') ?>
    </div>

    <div class="cols">
      <section class="card grow">
        <div class="card-head"><h2>Pengajuan saya</h2><?php render_filter($rows); ?></div>
        <?php render_table($rows, 'Belum ada pengajuan. Foto nota dinasmu dan ajukan sekarang.', false, '<a class="btn primary" href="ajukan.php">' . ico('plus') . 'Ajukan reimburse</a>'); ?>
      </section>
      <div class="side-col">
        <?php if ($aktif): $step = STATUS[$aktif['status']]['step']; ?>
          <section class="card dark">
            <small class="muted-d"><?= e($aktif['kode']) ?> · pengajuan aktif</small>
            <h2><?= e($aktif['judul']) ?></h2>
            <ol class="vtrack">
              <?php foreach (['Diajukan', 'Manajer HRD', 'Finance', 'Dibayar ke ' . ($u['bank'] ?: 'rekening')] as $i => $s):
                $cls = $i < $step ? 'done' : ($i === $step ? 'now' : ''); ?>
                <li class="<?= $cls ?>"><i><?= $cls === 'done' ? ico('check') : '' ?></i><span><?= e($s) ?><?= $cls === 'now' ? '<small>sedang diperiksa</small>' : '' ?></span></li>
              <?php endforeach; ?>
            </ol>
          </section>
        <?php endif; ?>
        <section class="card">
          <h2 class="h-sm">Batas biaya per item</h2>
          <dl class="kv">
            <?php foreach (KATEGORI as $k): ?>
              <div><dt><?= e($k['label']) ?></dt><dd><?= $k['limit'] ? rp($k['limit']) : '<span class="muted">sesuai nota</span>' ?></dd></div>
            <?php endforeach; ?>
          </dl>
        </section>
      </div>
    </div>
    <?php
} elseif ($v === 'riwayat') {
    // ================= RIWAYAT TINDAKAN =================
    $st = $pdo->prepare(SQL_LIST . " WHERE c.id IN (SELECT claim_id FROM claim_histories WHERE user_id = ?) ORDER BY last_at DESC");
    $st->execute([$u['id']]);
    $rows = $st->fetchAll();
    $judul_halaman = $u['role'] === 'kasir' ? 'Riwayat pembayaran' : 'Riwayat tindakan';
    include 'partials/header.php';
    ?>
    <div class="page-head"><div><h1><?= e($judul_halaman) ?></h1><p>Pengajuan yang pernah kamu proses.</p></div></div>
    <section class="card">
      <div class="card-head"><h2><?= count($rows) ?> pengajuan</h2><?php render_filter($rows); ?></div>
      <?php render_table($rows, 'Belum ada pengajuan yang kamu proses.', true); ?>
    </section>
    <?php
} else {
    // ================= ANTREAN HRD / FINANCE / KASIR =================
    $status = ANTREAN[$u['role']];
    $st = $pdo->prepare(SQL_LIST . " WHERE c.status = ? ORDER BY last_at ASC");
    $st->execute([$status]);
    $rows = $st->fetchAll();
    $tunggu = $rows ? max(0, (int)floor((time() - strtotime($rows[0]['last_at'])) / 86400)) : 0;
    $bulan = $pdo->prepare("SELECT COUNT(DISTINCT claim_id) FROM claim_histories WHERE user_id = ? AND DATE_FORMAT(created_at,'%Y-%m') = DATE_FORMAT(NOW(),'%Y-%m')");
    $bulan->execute([$u['id']]);
    $bulan = (int)$bulan->fetchColumn();

    $teks = [
        'hrd'     => ['Antrean persetujuan', 'Cocokkan dengan surat tugas, lalu setujui, minta revisi, atau tolak.', 'Diputuskan bulan ini', '#7C3AED', 'inbox'],
        'finance' => ['Verifikasi nota', 'Cocokkan setiap nota dengan nominalnya, centang, lalu verifikasi.', 'Diverifikasi bulan ini', '#0369A1', 'docok'],
        'kasir'   => ['Siap dibayar', 'Transfer ke rekening pegawai, catat referensinya, lalu konfirmasi.', 'Dibayar bulan ini', '#15803D', 'card'],
    ][$u['role']];
    $judul_halaman = $teks[0];
    include 'partials/header.php';
    ?>
    <div class="page-head"><div><h1><?= e($teks[0]) ?></h1><p><?= e($teks[1]) ?> Yang paling lama menunggu ada di atas.</p></div><span class="today"><?= hari_ini() ?></span></div>
    <div class="tiles">
      <?= tile('Menunggu tindakanmu', count($rows), 'pengajuan', $teks[4], $teks[3], '', true) ?>
      <?= tile('Nilai antrean', array_sum(array_column($rows, 'total')), 'total semua pengajuan', 'chart', $teks[3], 'rp') ?>
      <?= tile('Paling lama menunggu', $tunggu, $rows ? $rows[0]['kode'] : 'antrean kosong', 'clock', $tunggu >= 3 ? '#C2410C' : $teks[3], '', false, ' hari') ?>
      <?= tile($teks[2], $bulan, 'pengajuan', 'check', '#15803D') ?>
    </div>
    <?php render_queue($rows, 'Antrean kosong. Semua pengajuan sudah kamu proses.'); ?>
    <?php
}
include 'partials/footer.php';

<?php
// FR-12 Laporan Direktur
require 'config.php';
$u = require_role('direktur');

$bulan = preg_match('/^\d{4}-\d{2}$/', $_GET['bulan'] ?? '') ? $_GET['bulan'] : '';
$w = $bulan ? " AND DATE_FORMAT(c.tgl_berangkat, '%Y-%m') = :b" : '';
$p = $bulan ? [':b' => $bulan] : [];
$q = function ($sql) use ($pdo, $p) { $st = $pdo->prepare($sql); $st->execute($p); return $st; };

$per_status = $q("SELECT c.status, COUNT(*) n, SUM(c.total) nilai FROM claims c WHERE 1=1 $w GROUP BY c.status")->fetchAll(PDO::FETCH_UNIQUE);
$dibayar = $per_status['dibayar'] ?? ['n' => 0, 'nilai' => 0];
$proses  = ['n' => 0, 'nilai' => 0];
foreach (['diajukan', 'disetujui', 'terverifikasi', 'revisi'] as $s) {
    $proses['n']     += $per_status[$s]['n'] ?? 0;
    $proses['nilai'] += $per_status[$s]['nilai'] ?? 0;
}
$avg = (float)$q("SELECT AVG(TIMESTAMPDIFF(HOUR, a.awal, a.akhir)) / 24 FROM (
    SELECT h.claim_id, MIN(h.created_at) awal, MAX(CASE WHEN h.aksi='Dibayar' THEN h.created_at END) akhir
    FROM claim_histories h JOIN claims c ON c.id = h.claim_id WHERE c.status='dibayar' $w GROUP BY h.claim_id) a")->fetchColumn();
$revisi = (int)$q("SELECT COUNT(*) FROM claim_histories h JOIN claims c ON c.id = h.claim_id WHERE h.aksi='Diminta revisi' $w")->fetchColumn();
$per_kategori = $q("SELECT i.kategori k, SUM(i.nominal) v FROM claim_items i JOIN claims c ON c.id = i.claim_id
    WHERE c.status <> 'ditolak' $w GROUP BY i.kategori ORDER BY v DESC")->fetchAll();
$per_divisi = $q("SELECT u.divisi k, SUM(c.total) v FROM claims c JOIN users u ON u.id = c.user_id
    WHERE c.status <> 'ditolak' $w GROUP BY u.divisi ORDER BY v DESC")->fetchAll();
$aktivitas = $q("SELECT h.aksi, h.created_at, u.nama, c.id, c.judul, c.total FROM claim_histories h
    JOIN users u ON u.id = h.user_id JOIN claims c ON c.id = h.claim_id WHERE 1=1 $w ORDER BY h.created_at DESC, h.id DESC LIMIT 6")->fetchAll();
$daftar_bulan = $pdo->query("SELECT DISTINCT DATE_FORMAT(tgl_berangkat, '%Y-%m') b FROM claims ORDER BY b DESC")->fetchAll(PDO::FETCH_COLUMN);

function bars($rows, $label_fn, $warna) {
    if (!$rows) return '<p class="muted">Belum ada data di periode ini.</p>';
    $max = max(array_map(fn($r) => (float)$r['v'], $rows)) ?: 1;
    $out = '';
    foreach ($rows as $i => $r) {
        $out .= '<div class="bar" style="--i:' . $i . '"><span>' . e($label_fn($r['k'])) . '</span><span class="bar-track"><i style="background:' . $warna . '" data-w="' . round($r['v'] / $max * 100, 1) . '%"></i></span><b class="num">' . angka($r['v']) . '</b></div>';
    }
    return $out;
}
$nama_bulan = fn($b) => BULAN_PANJANG[(int)substr($b, 5, 2)] . ' ' . substr($b, 0, 4);
$warna_aksi = ['Mengajukan' => '#2B59FF', 'Mengajukan ulang' => '#2B59FF', 'Disetujui HRD' => '#7C3AED', 'Diverifikasi Finance' => '#0369A1', 'Dibayar' => '#15803D', 'Diminta revisi' => '#C2410C', 'Ditolak' => '#B91C1C'];

$judul_halaman = 'Laporan';
include 'partials/header.php';
?>
<div class="page-head">
  <div><h1>Laporan reimburse</h1><p>Dihitung langsung dari data pengajuan<?= $bulan ? ' bulan ' . $nama_bulan($bulan) : '' ?>.</p></div>
  <form method="get" class="head-tools">
    <label class="inline-field">Periode
      <select name="bulan" onchange="this.form.submit()">
        <option value="">Semua periode</option>
        <?php foreach ($daftar_bulan as $b): ?><option value="<?= e($b) ?>" <?= $b === $bulan ? 'selected' : '' ?>><?= e($nama_bulan($b)) ?></option><?php endforeach; ?>
      </select></label>
    <a class="btn dark" href="export_csv.php?bulan=<?= e($bulan) ?>"><?= ico('dl') ?>Unduh CSV</a>
  </form>
</div>

<section class="hero-stat">
  <div class="hs-main">
    <span>Sudah ditransfer ke pegawai</span>
    <b class="grad-num" data-count="<?= (float)$dibayar['nilai'] ?>" data-type="rp"><?= rp($dibayar['nilai']) ?></b>
    <small><?= (int)$dibayar['n'] ?> pengajuan dibayar</small>
  </div>
  <div class="hs-mini">
    <div><span>Masih diproses</span><b class="num" data-count="<?= (float)$proses['nilai'] ?>" data-type="rp"><?= rp($proses['nilai']) ?></b><small><?= (int)$proses['n'] ?> pengajuan</small></div>
    <div><span>Rata-rata cair</span><b class="num" data-count="<?= round($avg, 1) ?>" data-type="dec" data-suffix=" hari"><?= number_format($avg, 1, ',', '.') ?> hari</b><small>diajukan → dibayar</small></div>
    <div><span>Revisi</span><b class="num" data-count="<?= $revisi ?>" data-suffix="×"><?= $revisi ?>×</b><small>nota kurang jelas</small></div>
  </div>
</section>

<div class="cols">
  <section class="card grow"><h2 class="h-md">Per kategori</h2><div class="bars"><?= bars($per_kategori, fn($k) => KATEGORI[$k]['label'], '#2B59FF') ?></div></section>
  <section class="card grow">
    <h2 class="h-md">Per divisi</h2><div class="bars"><?= bars($per_divisi, fn($k) => $k, '#8B5CF6') ?></div>
    <h2 class="h-md" style="margin-top:28px">Per status</h2>
    <div class="chips">
      <?php foreach (STATUS as $s => $info): ?>
        <a class="pill s-<?= $s ?> big" href="semua.php?status=<?= $s ?>"><?= e($info['label']) ?> <b><?= (int)($per_status[$s]['n'] ?? 0) ?></b></a>
      <?php endforeach; ?>
    </div>
  </section>
</div>

<section class="card">
  <h2 class="h-md">Aktivitas terbaru</h2>
  <?php if (!$aktivitas): ?><p class="muted">Belum ada aktivitas.</p><?php endif; ?>
  <ul class="feed">
    <?php foreach ($aktivitas as $a): ?>
      <li><i style="background:<?= $warna_aksi[$a['aksi']] ?? '#86868B' ?>"></i>
        <span class="feed-txt"><b><?= e(nama_pendek($a['nama'])) ?></b> · <?= e(strtolower($a['aksi'])) ?> · <a href="detail.php?id=<?= (int)$a['id'] ?>"><?= e($a['judul']) ?></a></span>
        <time class="muted"><?= jam_pendek($a['created_at']) ?></time><b class="num"><?= angka($a['total']) ?></b></li>
    <?php endforeach; ?>
  </ul>
</section>
<?php include 'partials/footer.php'; ?>

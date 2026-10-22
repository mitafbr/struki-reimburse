<?php
// FR-13 Semua pengajuan (Direktur)
require 'config.php';
require 'partials/list.php';
$u = require_role('direktur');

$status = $_GET['status'] ?? '';
$q      = trim($_GET['q'] ?? '');
$sql    = SQL_LIST . ' WHERE 1=1';
$param  = [];
if (isset(STATUS[$status])) { $sql .= ' AND c.status = ?'; $param[] = $status; }
if ($q !== '') { $sql .= ' AND (c.kode LIKE ? OR c.judul LIKE ? OR u.nama LIKE ? OR c.kota_tujuan LIKE ?)'; array_push($param, "%$q%", "%$q%", "%$q%", "%$q%"); }
$st = $pdo->prepare($sql . ' ORDER BY last_at DESC');
$st->execute($param);
$rows = $st->fetchAll();

$judul_halaman = 'Semua pengajuan';
include 'partials/header.php';
?>
<div class="page-head">
  <div><h1>Semua pengajuan</h1><p><?= count($rows) ?> pengajuan · total <?= rp(array_sum(array_column($rows, 'total'))) ?></p></div>
  <a class="btn dark" href="export_csv.php"><?= ico('dl') ?>Unduh CSV</a>
</div>
<form method="get" class="filter-bar">
  <label class="search"><?= ico('search') ?><input name="q" value="<?= e($q) ?>" placeholder="Cari kode, judul, nama, atau kota" aria-label="Cari pengajuan"></label>
  <select name="status" onchange="this.form.submit()" aria-label="Saring status">
    <option value="">Semua status</option>
    <?php foreach (STATUS as $k => $s): ?><option value="<?= $k ?>" <?= $k === $status ? 'selected' : '' ?>><?= e($s['label']) ?></option><?php endforeach; ?>
  </select>
  <button class="btn" type="submit">Cari</button>
</form>
<section class="card">
  <?php render_table($rows, 'Tidak ada pengajuan yang cocok. Coba kata kunci lain atau hapus filter status.', true); ?>
</section>
<?php include 'partials/footer.php'; ?>

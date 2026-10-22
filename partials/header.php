<?php
// Dipakai semua halaman. Set $judul_halaman, opsional $body_class dan $publik = true (tanpa sidebar).
require_once __DIR__ . '/logo.php';
$_hu = me();
$_hf = flash();
$_hal_aktif = basename($_SERVER['PHP_SELF']);
$_hv = $_GET['v'] ?? '';
$publik = $publik ?? false;

$n_antrean = 0;
if ($_hu && isset(ANTREAN[$_hu['role']])) {
    $_hq = $pdo->prepare('SELECT COUNT(*) FROM claims WHERE status = ?');
    $_hq->execute([ANTREAN[$_hu['role']]]);
    $n_antrean = (int)$_hq->fetchColumn();
}

// menu sidebar per peran: [href, label, ikon, aktif?, lencana]
$menu = [];
if ($_hu) {
    $_hdi = fn($file, $vv = '') => $_hal_aktif === $file && $_hv === $vv;
    switch ($_hu['role']) {
        case 'pegawai':
            $menu = [
                ['dashboard.php', 'Beranda', 'home', $_hdi('dashboard.php') || $_hal_aktif === 'detail.php', 0],
                ['ajukan.php', 'Ajukan reimburse', 'plusc', $_hal_aktif === 'ajukan.php', 0],
            ];
            break;
        case 'hrd':
            $menu = [
                ['dashboard.php', 'Antrean persetujuan', 'inbox', $_hdi('dashboard.php') || $_hal_aktif === 'detail.php', $n_antrean],
                ['dashboard.php?v=riwayat', 'Riwayat tindakan', 'clock', $_hdi('dashboard.php', 'riwayat'), 0],
                ['akun.php', 'Kelola akun', 'users', $_hal_aktif === 'akun.php', 0],
            ];
            break;
        case 'finance':
            $menu = [
                ['dashboard.php', 'Verifikasi nota', 'docok', $_hdi('dashboard.php') || $_hal_aktif === 'detail.php', $n_antrean],
                ['dashboard.php?v=riwayat', 'Riwayat tindakan', 'clock', $_hdi('dashboard.php', 'riwayat'), 0],
            ];
            break;
        case 'kasir':
            $menu = [
                ['dashboard.php', 'Siap dibayar', 'card', $_hdi('dashboard.php') || $_hal_aktif === 'detail.php', $n_antrean],
                ['dashboard.php?v=riwayat', 'Riwayat pembayaran', 'clock', $_hdi('dashboard.php', 'riwayat'), 0],
            ];
            break;
        case 'direktur':
            $menu = [
                ['laporan.php', 'Laporan', 'chart', $_hal_aktif === 'laporan.php', 0],
                ['semua.php', 'Semua pengajuan', 'doc', $_hal_aktif === 'semua.php' || $_hal_aktif === 'detail.php', 0],
            ];
            break;
    }
}
$shell = $_hu && !$publik;
?>
<!doctype html>
<html lang="id">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($judul_halaman ?? APP_NAME) ?> · <?= APP_NAME ?></title>
<meta name="description" content="Struki — sistem reimburse biaya dinas luar kota <?= APP_COMPANY ?>">
<meta name="theme-color" content="#F5F5F7">
<link rel="icon" href="assets/favicon.svg" type="image/svg+xml">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap">
<link rel="stylesheet" href="assets/style.css?v=20">
<script>
(function () {
  var t = null;
  try { t = localStorage.getItem('struki-theme'); } catch (e) {}
  if (t === 'dark' || t === 'light') document.documentElement.dataset.theme = t;
  document.documentElement.classList.add('js');
})();
</script>
</head>
<body class="<?= e(trim(($body_class ?? '') . ($_hu ? ' role-' . $_hu['role'] : ''))) ?>">
<a class="skip" href="#isi">Lewati ke konten</a>
<?php if ($shell): ?>
<div class="app">
  <aside class="side" id="side">
    <div class="side-top">
      <?= logo(beranda_peran($_hu['role'])) ?>
      <button class="icon-btn side-toggle" type="button" aria-label="Buka menu" aria-controls="sideNav" aria-expanded="false"><?= ico('menu') ?></button>
    </div>
    <nav class="side-nav" id="sideNav" aria-label="Menu <?= e(ROLES[$_hu['role']]) ?>">
      <?php foreach ($menu as [$href, $label, $ic, $on, $badge]): ?>
        <a class="nav-item<?= $on ? ' on' : '' ?>" href="<?= $href ?>"<?= $on ? ' aria-current="page"' : '' ?>><?= ico($ic) ?><span><?= e($label) ?></span><?= $badge ? '<b class="nav-badge">' . $badge . '</b>' : '' ?></a>
      <?php endforeach; ?>
      <?php if ($_hu['role'] === 'pegawai'): ?>
        <div class="side-tip"><b>Ingat batasnya</b><span>Ajukan paling lambat <?= BATAS_HARI_PENGAJUAN ?> hari setelah pulang dinas.</span></div>
      <?php endif; ?>
      <div class="side-foot">
        <div class="me">
          <span class="avatar r-<?= e($_hu['role']) ?>"><?= e(inisial($_hu['nama'])) ?></span>
          <span class="me-txt"><b><?= e($_hu['nama']) ?></b><small><?= e(ROLES[$_hu['role']]) ?> · <?= e($_hu['divisi']) ?></small></span>
        </div>
        <div class="side-actions">
          <button class="icon-btn theme-toggle" type="button" aria-label="Ganti tema terang/gelap" title="Tema"><?= ico('sun', 'i-sun') . ico('moon', 'i-moon') ?></button>
          <a class="icon-btn" href="logout.php" aria-label="Keluar" title="Keluar"><?= ico('out') ?></a>
        </div>
      </div>
    </nav>
  </aside>
  <main class="main" id="isi">
<?php else: ?>
  <main id="isi">
<?php endif; ?>
<?php if ($_hf): ?>
  <div class="toast t-<?= e($_hf['type']) ?>" role="status" data-extra="<?= e($_hf['extra'] ?? '') ?>">
    <span class="toast-ico"><?= ico($_hf['type'] === 'err' ? 'x' : 'check') ?></span>
    <span><?= e($_hf['msg']) ?></span>
  </div>
<?php endif; ?>

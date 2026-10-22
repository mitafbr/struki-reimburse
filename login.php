<?php
// FR-02 Login — satu halaman untuk semua peran, sistem mengarahkan sesuai peran akun
require 'config.php';
require_once 'partials/logo.php';
if (me()) redirect(beranda_peran(me()['role']));

$error = '';
$username = trim($_POST['username'] ?? ($_GET['u'] ?? ''));

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $st = $pdo->prepare('SELECT * FROM users WHERE username = ?');
    $st->execute([$username]);
    $user = $st->fetch();
    if ($user && password_verify($_POST['password'] ?? '', $user['password'])) {
        if (!(int)$user['aktif']) {
            $error = 'Akun ini sedang dinonaktifkan. Hubungi Manajer HRD.';
        } else {
            session_regenerate_id(true);
            unset($user['password']);
            $_SESSION['user'] = $user;
            flash('Halo, ' . nama_pendek($user['nama']) . '. Kamu masuk sebagai ' . ROLES[$user['role']] . '.');
            redirect(beranda_peran($user['role']));
        }
    } else {
        $error = 'Username atau password salah. Cek lagi huruf besar-kecilnya.';
    }
}

$akun = MODE_DEMO ? $pdo->query("SELECT username, nama, role FROM users WHERE aktif = 1 AND (role <> 'pegawai' OR username = 'rudi')
                     ORDER BY FIELD(role,'pegawai','hrd','finance','kasir','direktur'), id")->fetchAll() : [];
$label_pendek = ['pegawai' => 'Pegawai', 'hrd' => 'HRD', 'finance' => 'Finance', 'kasir' => 'Kasir', 'direktur' => 'Direktur'];

$judul_halaman = 'Masuk';
$body_class = 'auth';
$publik = true;
include 'partials/header.php';
?>
<div class="split">
  <section class="split-art" aria-hidden="true">
    <div class="split-brand"><?= logo_mark(36, true, 'white') ?><b>Struki</b></div>
    <h2>Nota masuk.<br>Uang kembali.</h2>
    <p>Sistem reimburse dinas <?= APP_COMPANY ?>, dari pengajuan sampai transfer.</p>
    <div class="fl f1">
      <small>RB-2026-0001 · Yogyakarta</small>
      <b>Audit stok cabang Malioboro</b>
      <span class="mini-bars"><i class="d"></i><i class="n"></i><i></i><i></i></span>
      <span class="pill s-diajukan">Menunggu HRD</span>
    </div>
    <div class="fl f2"><span class="ok-dot"><?= ico('check') ?></span><span><b>Dibayar Rp 318.000</b><small>BCA · barusan</small></span></div>
    <div class="fl f3 paper"><b>HOTEL MAWAR</b>2 malam<br>900.000<i></i><b>TOTAL 900.000</b></div>
  </section>

  <section class="split-form">
    <div class="split-inner">
      <a class="back" href="index.php"><?= ico('back') ?>Beranda</a>
      <h1>Masuk</h1>
      <p class="muted">Masuk dengan akun dari kantor. Sistem membawamu ke halaman sesuai peranmu.</p>
      <form method="post" class="stack" novalidate>
        <?= csrf_field() ?>
        <label class="field"><span>Username</span><input id="lu" name="username" value="<?= e($username) ?>" autocomplete="username" required autofocus></label>
        <label class="field"><span>Password</span>
          <span class="pw"><input id="lp" name="password" type="password" autocomplete="current-password" required>
          <button type="button" class="pw-eye" aria-label="Tampilkan password"><?= ico('eye') ?></button></span></label>
        <?php if ($error): ?><p class="alert bad shake" role="alert"><?= e($error) ?></p><?php endif; ?>
        <button class="btn primary block lg" type="submit">Masuk</button>
        <p class="center muted small">Pegawai baru? <a href="register.php">Daftar akun pegawai</a></p>
      </form>

      <?php if ($akun): ?>
      <div class="demo">
        <b>Satu halaman masuk, lima peran</b>
        <div class="demo-grid">
          <?php foreach ($akun as $a): ?>
            <button type="button" class="demo-acc" data-acc="<?= e($a['username']) ?>">
              <span class="avatar sm r-<?= e($a['role']) ?>"><?= e(inisial($a['nama'])) ?></span>
              <span><b><?= e($label_pendek[$a['role']]) ?></b><small><?= e($a['username']) ?></small></span>
            </button>
          <?php endforeach; ?>
        </div>
        <small class="muted">Akun contoh untuk mencoba. Password semua: <b>demo123</b></small>
      </div>
      <?php endif; ?>
    </div>
  </section>
</div>
<?php include 'partials/footer.php'; ?>

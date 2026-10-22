<?php
// FR-01 Registrasi akun pegawai (akun HRD/Finance/Kasir/Direktur sudah bawaan / dibuat HRD)
require 'config.php';
require_once 'partials/logo.php';
if (me()) redirect(beranda_peran(me()['role']));

$form = ['nama' => '', 'username' => '', 'divisi' => '', 'bank' => '', 'no_rekening' => ''];
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    foreach ($form as $k => $_) $form[$k] = trim($_POST[$k] ?? '');
    $form['username'] = strtolower($form['username']);
    $pw  = $_POST['password'] ?? '';
    $pw2 = $_POST['password2'] ?? '';

    if (mb_strlen($form['nama']) < 3)                                      $errors['nama'] = 'Isi nama lengkap (minimal 3 huruf).';
    if (!preg_match('/^[a-z0-9_.]{3,30}$/', $form['username']))            $errors['username'] = 'Username 3–30 karakter: huruf kecil, angka, titik, atau garis bawah.';
    if (!in_array($form['divisi'], DIVISI, true))                          $errors['divisi'] = 'Pilih divisi.';
    if (!in_array($form['bank'], BANK, true))                              $errors['bank'] = 'Pilih bank untuk pembayaran reimburse.';
    if (!preg_match('/^[0-9][0-9\- ]{5,28}[0-9]$/', $form['no_rekening'])) $errors['no_rekening'] = 'Nomor rekening hanya angka (boleh pakai tanda -), minimal 7 digit.';
    if (strlen($pw) < 6)                                                    $errors['password'] = 'Password minimal 6 karakter.';
    elseif ($pw !== $pw2)                                                   $errors['password2'] = 'Konfirmasi password belum sama.';

    if (!isset($errors['username'])) {
        $st = $pdo->prepare('SELECT 1 FROM users WHERE username = ?');
        $st->execute([$form['username']]);
        if ($st->fetchColumn()) $errors['username'] = 'Username "' . $form['username'] . '" sudah dipakai. Coba yang lain.';
    }

    if (!$errors) {
        $pdo->prepare("INSERT INTO users (username, password, nama, role, divisi, bank, no_rekening) VALUES (?,?,?,'pegawai',?,?,?)")
            ->execute([$form['username'], password_hash($pw, PASSWORD_DEFAULT), $form['nama'], $form['divisi'], $form['bank'], $form['no_rekening']]);
        $st = $pdo->prepare('SELECT * FROM users WHERE id = ?');
        $st->execute([$pdo->lastInsertId()]);
        $user = $st->fetch();
        unset($user['password']);
        session_regenerate_id(true);
        $_SESSION['user'] = $user;
        flash('Akun dibuat. Selamat datang di Struki, ' . $user['nama'] . '!', 'ok', 'confetti');
        redirect('dashboard.php');
    }
}

function err_of($errors, $k) { return isset($errors[$k]) ? '<small class="f-err">' . e($errors[$k]) . '</small>' : ''; }
function sel($opsi, $val) {
    $o = '<option value="">Pilih…</option>';
    foreach ($opsi as $x) $o .= '<option' . ($x === $val ? ' selected' : '') . '>' . e($x) . '</option>';
    return $o;
}

$judul_halaman = 'Daftar akun pegawai';
$body_class = 'auth';
$publik = true;
include 'partials/header.php';
?>
<div class="reg">
  <div class="reg-top"><?= logo('index.php', true, false) ?><a class="btn ghost sm" href="login.php">Masuk</a></div>

  <div class="reg-grid">
    <div>
      <h1 class="reg-h">Buat akun pegawai.</h1>
      <p class="muted lead-p">Nomor rekening dipakai Kasir untuk mentransfer uang reimburse. Akun Manajer HRD, Finance, Kasir, dan Direktur disiapkan oleh kantor.</p>

      <form class="card form-card grid2" method="post" novalidate>
        <?= csrf_field() ?>
        <label class="field span2<?= isset($errors['nama']) ? ' bad' : '' ?>"><span>Nama lengkap</span>
          <input name="nama" value="<?= e($form['nama']) ?>" data-live="nama" autocomplete="name" required><?= err_of($errors, 'nama') ?></label>
        <label class="field<?= isset($errors['username']) ? ' bad' : '' ?>"><span>Username</span>
          <input name="username" value="<?= e($form['username']) ?>" autocomplete="username" required><?= err_of($errors, 'username') ?></label>
        <label class="field<?= isset($errors['divisi']) ? ' bad' : '' ?>"><span>Divisi</span>
          <select name="divisi" data-live="divisi" required><?= sel(DIVISI, $form['divisi']) ?></select><?= err_of($errors, 'divisi') ?></label>
        <label class="field<?= isset($errors['bank']) ? ' bad' : '' ?>"><span>Bank</span>
          <select name="bank" data-live="bank" required><?= sel(BANK, $form['bank']) ?></select><?= err_of($errors, 'bank') ?></label>
        <label class="field<?= isset($errors['no_rekening']) ? ' bad' : '' ?>"><span>Nomor rekening</span>
          <input name="no_rekening" class="num" inputmode="numeric" value="<?= e($form['no_rekening']) ?>" data-live="rek" required><?= err_of($errors, 'no_rekening') ?></label>
        <label class="field<?= isset($errors['password']) ? ' bad' : '' ?>"><span>Password</span>
          <span class="pw"><input name="password" type="password" id="pw1" autocomplete="new-password" minlength="6" required>
          <button type="button" class="pw-eye" aria-label="Tampilkan password"><?= ico('eye') ?></button></span>
          <span class="meter" aria-hidden="true"><i></i></span><small class="meter-txt muted">Minimal 6 karakter</small><?= err_of($errors, 'password') ?></label>
        <label class="field<?= isset($errors['password2']) ? ' bad' : '' ?>"><span>Ulangi password</span>
          <input name="password2" type="password" id="pw2" autocomplete="new-password" required><?= err_of($errors, 'password2') ?></label>
        <button class="btn primary block lg span2" type="submit">Buat akun</button>
        <p class="span2 center muted small">Sudah punya akun? <a href="login.php">Masuk</a></p>
      </form>
    </div>

    <aside class="idcard-wrap" aria-label="Pratinjau kartu pegawai">
      <span class="muted small">Kartu ikut terisi saat kamu mengetik</span>
      <div class="idcard tilt">
        <div class="idc-top"><?= logo_mark(22, false, 'white') ?><span>Struki · <?= APP_COMPANY ?></span><em>Pegawai</em></div>
        <b class="idc-name" data-out="nama"><?= e($form['nama'] ?: 'Nama kamu') ?></b>
        <span class="idc-div" data-out="divisi"><?= e($form['divisi'] ?: 'Divisi') ?></span>
        <div class="idc-bank"><b data-out="bank"><?= e($form['bank'] ?: 'Bank') ?></b><span class="num" data-out="rek"><?= e($form['no_rekening'] ?: '•••• •••• ••••') ?></span></div>
      </div>
    </aside>
  </div>
</div>
<?php include 'partials/footer.php'; ?>

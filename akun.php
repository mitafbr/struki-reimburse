<?php
// Kelola akun (Manajer HRD): lihat semua akun, buat akun staf, aktif/nonaktifkan akun
require 'config.php';
require 'partials/list.php';
$u = require_role('hrd');

$errors = [];
$form = ['nama' => '', 'username' => '', 'role' => 'finance', 'divisi' => 'Keuangan'];
$peran_staf = ['finance', 'kasir', 'direktur', 'hrd'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $aksi = $_POST['aksi'] ?? '';

    if ($aksi === 'toggle') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id === (int)$u['id']) {
            flash('Kamu tidak bisa menonaktifkan akunmu sendiri.', 'err');
        } else {
            $st = $pdo->prepare('SELECT nama, aktif FROM users WHERE id = ?');
            $st->execute([$id]);
            if ($x = $st->fetch()) {
                $baru = (int)!$x['aktif'];
                $pdo->prepare('UPDATE users SET aktif = ? WHERE id = ?')->execute([$baru, $id]);
                flash($x['nama'] . ($baru ? ' diaktifkan kembali.' : ' dinonaktifkan dan tidak bisa masuk lagi.'));
            }
        }
        redirect('akun.php');
    }

    if ($aksi === 'buat') {
        $form = [
            'nama'     => trim($_POST['nama'] ?? ''),
            'username' => strtolower(trim($_POST['username'] ?? '')),
            'role'     => $_POST['role'] ?? '',
            'divisi'   => $_POST['divisi'] ?? '',
        ];
        $pw = $_POST['password'] ?? '';
        if (mb_strlen($form['nama']) < 3) $errors[] = 'Isi nama lengkap (minimal 3 huruf).';
        if (!preg_match('/^[a-z0-9_.]{3,30}$/', $form['username'])) $errors[] = 'Username 3–30 karakter: huruf kecil, angka, titik, atau garis bawah.';
        if (!in_array($form['role'], $peran_staf, true)) $errors[] = 'Pilih peran.';
        if (!in_array($form['divisi'], array_merge(DIVISI, ['Direksi']), true)) $errors[] = 'Pilih divisi.';
        if (strlen($pw) < 6) $errors[] = 'Password sementara minimal 6 karakter.';
        if (!$errors) {
            $st = $pdo->prepare('SELECT 1 FROM users WHERE username = ?');
            $st->execute([$form['username']]);
            if ($st->fetchColumn()) $errors[] = 'Username "' . $form['username'] . '" sudah dipakai.';
        }
        if (!$errors) {
            $pdo->prepare('INSERT INTO users (username, password, nama, role, divisi) VALUES (?,?,?,?,?)')
                ->execute([$form['username'], password_hash($pw, PASSWORD_DEFAULT), $form['nama'], $form['role'], $form['divisi']]);
            flash('Akun ' . ROLES[$form['role']] . ' untuk ' . $form['nama'] . ' dibuat. Berikan username dan password sementaranya.');
            redirect('akun.php');
        }
    }
}

$cari = trim($_GET['q'] ?? '');
$sql = 'SELECT * FROM users' . ($cari !== '' ? ' WHERE nama LIKE ? OR username LIKE ?' : '') . " ORDER BY FIELD(role,'hrd','finance','kasir','direktur','pegawai'), nama";
$st = $pdo->prepare($sql);
$st->execute($cari !== '' ? ["%$cari%", "%$cari%"] : []);
$akun = $st->fetchAll();
$hitung = $pdo->query("SELECT role, COUNT(*) n FROM users WHERE aktif = 1 GROUP BY role")->fetchAll(PDO::FETCH_KEY_PAIR);
$warna = ['pegawai' => '#2B59FF', 'hrd' => '#7C3AED', 'finance' => '#0369A1', 'kasir' => '#15803D', 'direktur' => '#C2410C'];
$pw_saran = 'Struki-' . random_int(1000, 9999);

$judul_halaman = 'Kelola akun';
include 'partials/header.php';
?>
<div class="page-head"><div><h1>Kelola akun</h1><p>Semua akun <?= APP_COMPANY ?>. Pegawai mendaftar sendiri; akun Finance, Kasir, dan Direktur dibuat di sini.</p></div></div>

<div class="tiles five">
  <?php foreach (ROLES as $k => $lbl): ?>
    <?= tile($lbl, (int)($hitung[$k] ?? 0), 'akun aktif', $k === 'pegawai' ? 'users' : ($k === 'direktur' ? 'chart' : ($k === 'kasir' ? 'card' : ($k === 'finance' ? 'docok' : 'inbox'))), $warna[$k]) ?>
  <?php endforeach; ?>
</div>

<div class="cols">
  <section class="card grow">
    <div class="card-head">
      <h2>Daftar akun</h2>
      <form method="get" class="search sm"><?= ico('search') ?><input name="q" value="<?= e($cari) ?>" placeholder="Cari nama atau username" aria-label="Cari akun"></form>
    </div>
    <div class="table-wrap">
      <table class="tbl">
        <thead><tr><th>Nama</th><th>Peran</th><th>Divisi</th><th>Rekening</th><th>Aktif</th></tr></thead>
        <tbody>
        <?php foreach ($akun as $a): ?>
          <tr class="<?= $a['aktif'] ? '' : 'off' ?>">
            <td><span class="who-cell"><span class="avatar sm r-<?= e($a['role']) ?>"><?= e(inisial($a['nama'])) ?></span><span><b><?= e($a['nama']) ?></b><small><?= e($a['username']) ?><?= (int)$a['id'] === (int)$u['id'] ? ' · kamu' : '' ?></small></span></span></td>
            <td><span class="role-tag r-<?= e($a['role']) ?>"><?= e(ROLES[$a['role']]) ?></span></td>
            <td><?= e($a['divisi']) ?></td>
            <td class="num"><?= $a['no_rekening'] ? e($a['bank'] . ' ' . $a['no_rekening']) : '<span class="muted">—</span>' ?></td>
            <td>
              <form method="post"><?= csrf_field() ?><input type="hidden" name="aksi" value="toggle"><input type="hidden" name="id" value="<?= (int)$a['id'] ?>">
                <button class="switch<?= $a['aktif'] ? ' on' : '' ?>" role="switch" aria-checked="<?= $a['aktif'] ? 'true' : 'false' ?>" aria-label="Akun <?= e($a['nama']) ?> aktif"<?= (int)$a['id'] === (int)$u['id'] ? ' disabled' : '' ?>><i></i></button>
              </form>
            </td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </section>

  <form class="card side-col form-card" method="post" novalidate>
    <?= csrf_field() ?><input type="hidden" name="aksi" value="buat">
    <h2 class="h-md">Tambah akun staf</h2>
    <p class="muted small">Untuk Finance, Kasir, atau Direktur baru. Berikan username dan password sementara ke orangnya.</p>
    <?php if ($errors): ?><div class="alert bad shake"><?php foreach ($errors as $er): ?><div><?= e($er) ?></div><?php endforeach; ?></div><?php endif; ?>
    <label class="field"><span>Nama lengkap</span><input name="nama" value="<?= e($form['nama']) ?>" placeholder="mis. Ayu Pratiwi" required></label>
    <label class="field"><span>Username</span><input name="username" value="<?= e($form['username']) ?>" placeholder="mis. ayu" required></label>
    <div class="grid2">
      <label class="field"><span>Peran</span><select name="role">
        <?php foreach ($peran_staf as $r): ?><option value="<?= $r ?>" <?= $form['role'] === $r ? 'selected' : '' ?>><?= ROLES[$r] ?></option><?php endforeach; ?></select></label>
      <label class="field"><span>Divisi</span><select name="divisi">
        <?php foreach (array_merge(['Keuangan', 'Direksi'], array_diff(DIVISI, ['Keuangan'])) as $d): ?><option <?= $form['divisi'] === $d ? 'selected' : '' ?>><?= e($d) ?></option><?php endforeach; ?></select></label>
    </div>
    <label class="field"><span>Password sementara</span><input name="password" value="<?= e($pw_saran) ?>" class="num" required></label>
    <button class="btn primary block role-bg" type="submit"><?= ico('plus') ?>Buat akun</button>
  </form>
</div>
<?php include 'partials/footer.php'; ?>

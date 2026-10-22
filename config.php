<?php
// =========================================================
// Struki — Sistem Informasi Reimburse Dinas Luar Kota
// PT Angin Ribut · PHP native + MySQL (XAMPP)
// =========================================================

// ---------- koneksi database (default XAMPP) ----------
$DB_HOST = 'localhost';
$DB_NAME = 'db_struki';
$DB_USER = 'root';
$DB_PASS = '';            // XAMPP default: kosong

const APP_NAME    = 'Struki';
const APP_COMPANY = 'PT Angin Ribut';

// true  = halaman Masuk menampilkan tombol akun contoh (untuk presentasi/demo)
// false = tampilan produksi, akun contoh disembunyikan
const MODE_DEMO = true;

date_default_timezone_set('Asia/Jakarta');
session_start();

try {
    $pdo = new PDO("mysql:host=$DB_HOST;dbname=$DB_NAME;charset=utf8mb4", $DB_USER, $DB_PASS, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
    $pdo->exec("SET time_zone = '+07:00'");
} catch (PDOException $e) {
    http_response_code(500);
    die('<div style="font-family:-apple-system,Segoe UI,sans-serif;max-width:560px;margin:60px auto;padding:28px;border-radius:20px;background:#fff;box-shadow:0 20px 50px -20px rgba(0,0,0,.25)">'
      . '<h2 style="margin-top:0">Database belum siap</h2>'
      . '<p>Struki tidak bisa terhubung ke database <b>' . $DB_NAME . '</b>.</p>'
      . '<ol><li>Buka XAMPP Control Panel, klik <b>Start</b> pada Apache dan MySQL.</li>'
      . '<li>Buka <b>localhost/phpmyadmin</b> → tab <b>Import</b> → pilih <b>database.sql</b> → <b>Import</b>.</li>'
      . '<li>Muat ulang halaman ini.</li></ol>'
      . '<small style="color:#6e6e73">' . htmlspecialchars($e->getMessage()) . '</small></div>');
}

// =========================================================
// Data referensi (SKPL bab 4 & 7)
// =========================================================
const ROLES = [
    'pegawai'  => 'Pegawai',
    'hrd'      => 'Manajer HRD',
    'finance'  => 'Finance',
    'kasir'    => 'Kasir',
    'direktur' => 'Direktur',
];

// label, tahap progres, teks cap
const STATUS = [
    'diajukan'      => ['label' => 'Menunggu HRD',        'step' => 1,  'cap' => 'DIAJUKAN'],
    'disetujui'     => ['label' => 'Menunggu Finance',    'step' => 2,  'cap' => 'DISETUJUI'],
    'terverifikasi' => ['label' => 'Menunggu pembayaran', 'step' => 3,  'cap' => 'TERVERIFIKASI'],
    'dibayar'       => ['label' => 'Dibayar',             'step' => 4,  'cap' => 'LUNAS'],
    'revisi'        => ['label' => 'Perlu revisi',        'step' => 0,  'cap' => 'REVISI'],
    'ditolak'       => ['label' => 'Ditolak',             'step' => -1, 'cap' => 'DITOLAK'],
];

// DR-03: batas biaya per item (null = sesuai nota)
const KATEGORI = [
    'transport' => ['label' => 'Transportasi', 'limit' => null,   'ico' => 'car'],
    'tiket'     => ['label' => 'Tiket',        'limit' => null,   'ico' => 'ticket'],
    'hotel'     => ['label' => 'Penginapan',   'limit' => 600000, 'ico' => 'bed'],
    'makan'     => ['label' => 'Makan',        'limit' => 150000, 'ico' => 'food'],
    'lain'      => ['label' => 'Lain-lain',    'limit' => 200000, 'ico' => 'box'],
];

const DIVISI = ['Operasional', 'Pemasaran', 'Produksi', 'Gudang', 'IT', 'Keuangan', 'SDM'];
const BANK   = ['BCA', 'BRI', 'BNI', 'Mandiri', 'BSI', 'BTN', 'CIMB Niaga'];

const BATAS_HARI_PENGAJUAN = 14;              // DR-01
const MAKS_UKURAN_FILE     = 5 * 1024 * 1024; // FR-04: 5 MB
const EKSTENSI_NOTA        = ['jpg', 'jpeg', 'png', 'webp', 'pdf'];

// peran → status antrean yang diproses
const ANTREAN = [
    'hrd'     => 'diajukan',
    'finance' => 'disetujui',
    'kasir'   => 'terverifikasi',
];

// =========================================================
// Helper umum
// =========================================================
function e($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
function rp($n) { return 'Rp ' . number_format((float)$n, 0, ',', '.'); }
function angka($n) { return number_format((float)$n, 0, ',', '.'); }

const NAMA_BULAN = ['', 'Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'];
const NAMA_HARI  = ['Minggu', 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu'];
const BULAN_PANJANG = ['', 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];
function tgl($d) {
    if (!$d) return '-';
    $t = strtotime($d);
    return date('j', $t) . ' ' . NAMA_BULAN[(int)date('n', $t)] . ' ' . date('Y', $t);
}
function tgl_pendek($d) { $t = strtotime($d); return date('j', $t) . ' ' . NAMA_BULAN[(int)date('n', $t)]; }
function jam($d) { return tgl($d) . ', ' . date('H:i', strtotime($d)); }
function jam_pendek($d) { return tgl_pendek($d) . ', ' . date('H:i', strtotime($d)); }
function periode($a, $b) {
    if (!$b || $b === $a) return tgl($a);
    if (substr($a, 0, 7) === substr($b, 0, 7)) return date('j', strtotime($a)) . '–' . tgl($b);
    return tgl_pendek($a) . ' – ' . tgl($b);
}
function hari_ini() { return NAMA_HARI[(int)date('w')] . ', ' . date('j') . ' ' . BULAN_PANJANG[(int)date('n')] . ' ' . date('Y'); }
function sapaan() {
    $h = (int)date('G');
    return $h < 11 ? 'Selamat pagi' : ($h < 15 ? 'Selamat siang' : ($h < 18 ? 'Selamat sore' : 'Selamat malam'));
}
function nama_pendek($nama) { return preg_replace('/^(Bu|Pak)\s+/i', '', $nama); }

// status sebagai pil berwarna
function pill($status, $pulse = false) {
    $s = STATUS[$status] ?? ['label' => $status];
    return '<span class="pill s-' . e($status) . '">' . ($pulse ? '<i class="dot-live"></i>' : '') . e($s['label']) . '</span>';
}

function over_limit($kategori, $nominal) {
    $l = KATEGORI[$kategori]['limit'] ?? null;
    return $l && $nominal > $l;
}

function inisial($nama) {
    $p = preg_split('/\s+/', nama_pendek(trim($nama)));
    return strtoupper(mb_substr($p[0], 0, 1) . (isset($p[1]) ? mb_substr($p[1], 0, 1) : ''));
}

function is_pdf($path) { return strtolower(pathinfo((string)$path, PATHINFO_EXTENSION)) === 'pdf'; }

function flash($msg = null, $type = 'ok', $extra = null) {
    if ($msg !== null) { $_SESSION['flash'] = ['msg' => $msg, 'type' => $type, 'extra' => $extra]; return null; }
    $f = $_SESSION['flash'] ?? null;
    unset($_SESSION['flash']);
    return $f;
}

function redirect($url) { header("Location: $url"); exit; }

function me() { return $_SESSION['user'] ?? null; }

function beranda_peran($role) { return $role === 'direktur' ? 'laporan.php' : 'dashboard.php'; }

function require_login() {
    global $pdo;
    $u = me();
    if (!$u) redirect('login.php');
    // akun yang dinonaktifkan HRD langsung keluar
    $st = $pdo->prepare('SELECT aktif FROM users WHERE id = ?');
    $st->execute([$u['id']]);
    if ((int)$st->fetchColumn() !== 1) {
        $_SESSION = [];
        flash('Akunmu dinonaktifkan. Hubungi Manajer HRD.', 'err');
        redirect('login.php');
    }
    return $u;
}

function require_role(...$roles) {
    $u = require_login();
    if (!in_array($u['role'], $roles, true)) {
        flash('Halaman itu khusus untuk ' . implode(' / ', array_map(fn($r) => ROLES[$r], $roles)) . '.', 'err');
        redirect(beranda_peran($u['role']));
    }
    return $u;
}

// ---------- NFR-02: token anti CSRF ----------
function csrf_token() {
    if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(16));
    return $_SESSION['csrf'];
}
function csrf_field() { return '<input type="hidden" name="csrf" value="' . csrf_token() . '">'; }
function csrf_check() {
    if (!hash_equals($_SESSION['csrf'] ?? '', $_POST['csrf'] ?? '')) {
        http_response_code(400);
        die('Sesi kedaluwarsa. Muat ulang halaman lalu coba lagi.');
    }
}

// ---------- NFR-06: jejak audit ----------
function catat_riwayat(PDO $pdo, $claim_id, $user_id, $aksi, $catatan = null) {
    $st = $pdo->prepare('INSERT INTO claim_histories (claim_id, user_id, aksi, catatan, created_at) VALUES (?,?,?,?,NOW())');
    $st->execute([$claim_id, $user_id, $aksi, $catatan ?: null]);
}

// ---------- DR-07: kode unik RB-tahun-urut ----------
function kode_baru(PDO $pdo) {
    $th = date('Y');
    $st = $pdo->prepare("SELECT COALESCE(MAX(CAST(SUBSTRING(kode, 9) AS UNSIGNED)),0) FROM claims WHERE kode LIKE ?");
    $st->execute(["RB-$th-%"]);
    return 'RB-' . $th . '-' . str_pad((int)$st->fetchColumn() + 1, 4, '0', STR_PAD_LEFT);
}

function boleh_lihat(array $u, array $claim) {
    if ($u['role'] === 'pegawai') return (int)$claim['user_id'] === (int)$u['id'];
    return isset(ROLES[$u['role']]);
}

// ---------- FR-04: simpan file unggahan dengan aman ----------
// return [path|null, pesan_error|null]
function simpan_upload(array $f, string $folder, array $ext_ok, string $prefix) {
    if (($f['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) return [null, null];
    if ($f['error'] !== UPLOAD_ERR_OK) return [null, 'file gagal diunggah (kemungkinan lebih dari 5 MB).'];
    if ($f['size'] > MAKS_UKURAN_FILE) return [null, 'ukuran file maksimal 5 MB.'];
    $ext = strtolower(pathinfo($f['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, $ext_ok, true)) return [null, 'format harus ' . strtoupper(implode(', ', $ext_ok)) . '.'];
    if ($ext === 'pdf') {
        $head = file_get_contents($f['tmp_name'], false, null, 0, 5);
        if ($head !== '%PDF-') return [null, 'file PDF tidak valid.'];
    } elseif (!@getimagesize($f['tmp_name'])) {
        return [null, 'file bukan gambar yang valid.'];
    }
    $dir = __DIR__ . "/uploads/$folder";
    if (!is_dir($dir)) mkdir($dir, 0775, true);
    $nama = $prefix . '_' . date('Ymd_His') . '_' . bin2hex(random_bytes(4)) . '.' . ($ext === 'jpeg' ? 'jpg' : $ext);
    if (!move_uploaded_file($f['tmp_name'], "$dir/$nama")) return [null, 'file gagal disimpan. Cek izin folder uploads.'];
    return ["uploads/$folder/$nama", null];
}

function file_dari_array($key, $idx) {
    return [
        'name'     => $_FILES[$key]['name'][$idx] ?? '',
        'tmp_name' => $_FILES[$key]['tmp_name'][$idx] ?? '',
        'error'    => $_FILES[$key]['error'][$idx] ?? UPLOAD_ERR_NO_FILE,
        'size'     => $_FILES[$key]['size'][$idx] ?? 0,
    ];
}

// ---------- ikon garis (SVG inline) ----------
function ico($nama, $cls = '') {
    $p = [
        'home'   => '<path d="M3 11l9-7 9 7v9a1 1 0 0 1-1 1h-5v-6H9v6H4a1 1 0 0 1-1-1z"/>',
        'plusc'  => '<circle cx="12" cy="12" r="9"/><path d="M12 8v8M8 12h8"/>',
        'doc'    => '<path d="M7 3h7l5 5v13H7zM14 3v5h5M10 13h6M10 17h4"/>',
        'docok'  => '<path d="M7 3h7l5 5v13H7zM14 3v5h5M10 15l2 2 4-4"/>',
        'inbox'  => '<path d="M4 13h4l2 3h4l2-3h4M4 13l2-8h12l2 8v6H4z"/>',
        'clock'  => '<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/>',
        'users'  => '<circle cx="9" cy="8" r="3.5"/><path d="M2.5 20a6.5 6.5 0 0 1 13 0M16 4.5a3.5 3.5 0 0 1 0 7M18 14.5a6.5 6.5 0 0 1 3.5 5.5"/>',
        'chart'  => '<path d="M4 20V10M10 20V4M16 20v-7M22 20H2"/>',
        'card'   => '<rect x="3" y="6" width="18" height="13" rx="2"/><path d="M3 10h18M7 15h3"/>',
        'bank'   => '<path d="M3 10l9-6 9 6M5 10v8M9 10v8M15 10v8M19 10v8M3 20h18"/>',
        'car'    => '<path d="M5 16h14M6 16l1.4-5.2A2 2 0 0 1 9.3 9h5.4a2 2 0 0 1 1.9 1.8L18 16v3h-2v-1.5H8V19H6z"/><circle cx="8.5" cy="14" r=".6"/><circle cx="15.5" cy="14" r=".6"/>',
        'ticket' => '<path d="M4 8a2 2 0 0 0 2-2h12a2 2 0 0 0 2 2v2a2 2 0 0 0 0 4v2a2 2 0 0 0-2 2H6a2 2 0 0 0-2-2v-2a2 2 0 0 0 0-4z"/><path d="M14 7v10" stroke-dasharray="1.5 2"/>',
        'bed'    => '<path d="M3 18V7M3 14h18v4M21 14v-2a3 3 0 0 0-3-3h-7v5"/><circle cx="7" cy="11" r="1.6"/>',
        'food'   => '<path d="M7 3v8M5 3v5a2 2 0 0 0 4 0V3M7 11v10M16 21V3c2 1 3 3.5 3 6.5V13h-3"/>',
        'box'    => '<path d="M4 8l8-4 8 4v8l-8 4-8-4zM4 8l8 4 8-4M12 12v8"/>',
        'up'     => '<path d="M12 16V4M7 9l5-5 5 5M5 20h14"/>',
        'camera' => '<path d="M4 8h3l2-3h6l2 3h3v11H4z"/><circle cx="12" cy="13" r="3.5"/>',
        'file'   => '<path d="M7 3h7l5 5v13H7zM14 3v5h5"/>',
        'check'  => '<path d="M5 12.5l4.5 4.5L19 7"/>',
        'x'      => '<path d="M6 6l12 12M18 6L6 18"/>',
        'back'   => '<path d="M15 6l-6 6 6 6"/>',
        'go'     => '<path d="M9 6l6 6-6 6"/>',
        'plus'   => '<path d="M12 5v14M5 12h14"/>',
        'out'    => '<path d="M15 4h3a2 2 0 0 1 2 2v12a2 2 0 0 1-2 2h-3M10 16l4-4-4-4M14 12H4"/>',
        'sun'    => '<circle cx="12" cy="12" r="4"/><path d="M12 2v2M12 20v2M4.9 4.9l1.4 1.4M17.7 17.7l1.4 1.4M2 12h2M20 12h2M4.9 19.1l1.4-1.4M17.7 6.3l1.4-1.4"/>',
        'moon'   => '<path d="M20 14.5A8 8 0 1 1 9.5 4a6.5 6.5 0 0 0 10.5 10.5z"/>',
        'search' => '<circle cx="11" cy="11" r="6"/><path d="M20 20l-4.5-4.5"/>',
        'dl'     => '<path d="M12 4v12M7 11l5 5 5-5M5 20h14"/>',
        'eye'    => '<path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7S2 12 2 12z"/><circle cx="12" cy="12" r="3"/>',
        'zoom'   => '<circle cx="11" cy="11" r="6"/><path d="M20 20l-4.5-4.5M11 8v6M8 11h6"/>',
        'pen'    => '<path d="M4 20h4L19 9l-4-4L4 16zM13 7l4 4"/>',
        'bolt'   => '<path d="M13 3L4 14h7l-1 7 9-11h-7z"/>',
        'menu'   => '<path d="M4 7h16M4 12h16M4 17h16"/>',
        'flip'   => '<path d="M4 7h12l-3-3M20 17H8l3 3"/>',
    ][$nama] ?? '';
    return '<svg class="ico ' . $cls . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' . $p . '</svg>';
}

<?php
// Memproses tombol aksi di halaman detail: setuju, revisi, tolak, verifikasi, bayar
// NFR-04: peran & status dicek ulang di server · NFR-05/06: satu transaksi + jejak audit
require 'config.php';
$u = require_login();
if ($_SERVER['REQUEST_METHOD'] !== 'POST') redirect('dashboard.php');
csrf_check();

$id      = (int)($_POST['id'] ?? 0);
$aksi    = $_POST['aksi'] ?? '';
$catatan = trim($_POST['catatan'] ?? '');

$st = $pdo->prepare('SELECT * FROM claims WHERE id = ?');
$st->execute([$id]);
$c = $st->fetch();
if (!$c) { flash('Pengajuan tidak ditemukan.', 'err'); redirect('dashboard.php'); }

$balik = 'detail.php?id=' . $id;
$gagal = function ($msg) use ($balik) { flash($msg, 'err'); redirect($balik); };

// siapa boleh melakukan apa, dari status apa, ke status apa
$aturan = [
    'setuju'     => ['role' => ['hrd'],            'dari' => 'diajukan',      'ke' => 'disetujui',     'label' => 'Disetujui HRD'],
    'tolak'      => ['role' => ['hrd'],            'dari' => 'diajukan',      'ke' => 'ditolak',       'label' => 'Ditolak'],
    'revisi'     => ['role' => ['hrd', 'finance'], 'dari' => null,            'ke' => 'revisi',        'label' => 'Diminta revisi'],
    'verifikasi' => ['role' => ['finance'],        'dari' => 'disetujui',     'ke' => 'terverifikasi', 'label' => 'Diverifikasi Finance'],
    'bayar'      => ['role' => ['kasir'],          'dari' => 'terverifikasi', 'ke' => 'dibayar',       'label' => 'Dibayar'],
];
if (!isset($aturan[$aksi])) $gagal('Aksi tidak dikenal.');
$r = $aturan[$aksi];
if (!in_array($u['role'], $r['role'], true)) $gagal('Peranmu tidak bisa melakukan aksi ini.');

// status asal: revisi diminta HRD dari "diajukan", oleh Finance dari "disetujui"
$dari = $r['dari'] ?? ANTREAN[$u['role']];
if ($c['status'] !== $dari) $gagal('Status pengajuan sudah berubah, jadi aksi dibatalkan. Muat ulang untuk melihat status terbaru.');

if (in_array($aksi, ['revisi', 'tolak'], true) && $catatan === '') $gagal('Tulis catatan dulu supaya pegawai tahu alasannya.');

if ($aksi === 'verifikasi') {
    $n = $pdo->prepare('SELECT id FROM claim_items WHERE claim_id = ?');
    $n->execute([$id]);
    $semua = array_map('intval', $n->fetchAll(PDO::FETCH_COLUMN));
    $dicek = array_map('intval', $_POST['cek'] ?? []);
    if (array_diff($semua, $dicek)) $gagal('Centang semua item yang notanya sudah dicek sebelum verifikasi.');
    if ($catatan === '') $catatan = 'Semua nota sesuai.';
}

$extra = null;
$pdo->beginTransaction();
try {
    if ($aksi === 'bayar') {
        $metode = mb_substr(trim($_POST['metode'] ?? 'Transfer'), 0, 50);
        $ref    = mb_substr(trim($_POST['no_ref'] ?? ''), 0, 50);
        if ($ref === '') throw new RuntimeException('Isi nomor referensi pembayaran.');
        [$bukti, $err] = simpan_upload($_FILES['bukti'] ?? ['error' => UPLOAD_ERR_NO_FILE], 'transfer', EKSTENSI_NOTA, 'trf');
        if ($err) throw new RuntimeException("Bukti transfer: $err");
        $pdo->prepare('UPDATE claims SET status=?, metode_bayar=?, no_referensi=?, bukti_transfer=? WHERE id=?')
            ->execute([$r['ke'], $metode, $ref, $bukti, $id]);
        $catatan = "$metode · Ref $ref";
        $extra = 'confetti';
    } else {
        $pdo->prepare('UPDATE claims SET status=? WHERE id=?')->execute([$r['ke'], $id]);
        $extra = 'stamp';
    }
    catat_riwayat($pdo, $id, $u['id'], $r['label'], $catatan);
    $pdo->commit();
} catch (RuntimeException $ex) {
    $pdo->rollBack();
    $gagal($ex->getMessage());
}

flash($c['kode'] . ': ' . strtolower($r['label']) . '.', 'ok', $extra);
redirect($balik);

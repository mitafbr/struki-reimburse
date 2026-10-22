<?php
// FR-14 Ekspor CSV (dibuka di Excel)
require 'config.php';
require_role('direktur');

$bulan = preg_match('/^\d{4}-\d{2}$/', $_GET['bulan'] ?? '') ? $_GET['bulan'] : '';
$sql = "SELECT c.kode, u.nama, u.divisi, c.judul, c.kota_tujuan, c.tgl_berangkat, c.tgl_pulang, c.status, c.total, c.metode_bayar, c.no_referensi
        FROM claims c JOIN users u ON u.id = c.user_id";
$param = [];
if ($bulan) { $sql .= " WHERE DATE_FORMAT(c.tgl_berangkat, '%Y-%m') = ?"; $param[] = $bulan; }
$st = $pdo->prepare($sql . ' ORDER BY c.tgl_berangkat');
$st->execute($param);

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="struki-laporan' . ($bulan ? "-$bulan" : '') . '.csv"');
$out = fopen('php://output', 'w');
fwrite($out, "\xEF\xBB\xBF"); // BOM supaya Excel membaca UTF-8
fputcsv($out, ['Kode', 'Pegawai', 'Divisi', 'Judul', 'Kota', 'Berangkat', 'Pulang', 'Status', 'Total', 'Metode Bayar', 'No. Referensi'], ';');
while ($r = $st->fetch()) {
    $r['status'] = STATUS[$r['status']]['label'];
    $r['total']  = (int)$r['total'];
    fputcsv($out, array_values($r), ';');
}
fclose($out);

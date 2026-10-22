<?php
// Query dasar daftar pengajuan + dua cara menampilkan: tabel dan kartu antrean
const SQL_LIST = "SELECT c.*, u.nama, u.divisi,
    (SELECT MAX(h.created_at) FROM claim_histories h WHERE h.claim_id = c.id) AS last_at,
    (SELECT COUNT(*) FROM claim_items i WHERE i.claim_id = c.id) AS n_item
  FROM claims c JOIN users u ON u.id = c.user_id";

function render_kosong($teks, $aksi = '') {
    echo '<div class="empty">' . logo_mark(52) . '<p>' . e($teks) . '</p>' . $aksi . '</div>';
}

// tabel: pegawai, direktur, riwayat tindakan
function render_table(array $rows, string $kosong, bool $tampil_nama = false, string $aksi_kosong = '') {
    if (!$rows) { render_kosong($kosong, $aksi_kosong); return; }
    echo '<div class="table-wrap"><table class="tbl"><thead><tr><th>Pengajuan</th>' . ($tampil_nama ? '<th>Pegawai</th>' : '')
       . '<th>Tanggal dinas</th><th>Status</th><th class="num">Total</th></tr></thead><tbody>';
    foreach ($rows as $c) {
        $live = in_array($c['status'], ['diajukan', 'disetujui', 'terverifikasi'], true);
        echo '<tr data-href="detail.php?id=' . (int)$c['id'] . '" data-status="' . e($c['status']) . '">'
           . '<td><a class="row-link" href="detail.php?id=' . (int)$c['id'] . '"><b>' . e($c['judul']) . '</b></a><small>' . e($c['kode']) . ' · ' . e($c['kota_tujuan']) . ' · ' . (int)$c['n_item'] . ' item</small></td>'
           . ($tampil_nama ? '<td><span class="who-cell"><span class="avatar sm r-pegawai">' . e(inisial($c['nama'])) . '</span>' . e($c['nama']) . '</span></td>' : '')
           . '<td class="muted">' . periode($c['tgl_berangkat'], $c['tgl_pulang']) . '</td>'
           . '<td>' . pill($c['status'], $live) . '</td>'
           . '<td class="num"><b class="' . ($c['status'] === 'ditolak' ? 'strike' : '') . '">' . rp($c['total']) . '</b></td></tr>';
    }
    echo '</tbody></table></div>';
}

// kartu antrean: HRD, Finance, Kasir
function render_queue(array $rows, string $kosong) {
    if (!$rows) { render_kosong($kosong); return; }
    echo '<div class="queue">';
    foreach ($rows as $i => $c) {
        $hari = max(0, (int)floor((time() - strtotime($c['last_at'])) / 86400));
        $lama = $hari === 0 ? 'hari ini' : "$hari hari";
        echo '<a class="q-card' . ($hari >= 3 ? ' late' : '') . '" href="detail.php?id=' . (int)$c['id'] . '" style="--i:' . $i . '">'
           . '<span class="avatar r-pegawai">' . e(inisial($c['nama'])) . '</span>'
           . '<span class="q-body"><span class="q-top"><span>' . e($c['nama']) . ' · ' . e($c['divisi']) . '</span><span class="q-wait">menunggu ' . $lama . '</span></span>'
           . '<b class="q-title">' . e($c['judul']) . '</b>'
           . '<span class="q-meta">' . e($c['kode']) . ' · ' . e($c['kota_tujuan']) . ' · ' . (int)$c['n_item'] . ' item</span></span>'
           . '<span class="q-amt">' . rp($c['total']) . ico('go') . '</span></a>';
    }
    echo '</div>';
}

// chip saring status (disaring di browser)
function render_filter(array $rows) {
    if (count($rows) < 3) return;
    $hit = array_count_values(array_column($rows, 'status'));
    echo '<div class="seg" role="toolbar" aria-label="Saring status"><button type="button" class="on" data-filter="">Semua <b>' . count($rows) . '</b></button>';
    foreach (STATUS as $k => $s) if (!empty($hit[$k])) echo '<button type="button" data-filter="' . $k . '">' . e($s['label']) . ' <b>' . $hit[$k] . '</b></button>';
    echo '</div>';
}

// kartu angka ringkas
function tile($label, $nilai, $sub, $ikon, $warna, $type = '', $lead = false, $suffix = '') {
    $angka = ($type === 'rp' ? rp($nilai) : ($type === 'dec' ? number_format((float)$nilai, 1, ',', '.') : (string)$nilai)) . $suffix;
    return '<div class="tile' . ($lead ? ' lead' : '') . '" style="--c:' . $warna . '">'
         . '<span class="tile-ico">' . ico($ikon) . '</span>'
         . '<span class="tile-lbl">' . e($label) . '</span>'
         . '<b class="tile-v" ' . (is_numeric($nilai) ? 'data-count="' . (float)$nilai . '" data-type="' . $type . '" data-suffix="' . e($suffix) . '"' : '') . '>' . e($angka) . '</b>'
         . '<small>' . e($sub) . '</small></div>';
}

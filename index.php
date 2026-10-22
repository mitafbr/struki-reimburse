<?php
// Halaman depan untuk pengunjung yang belum masuk
require 'config.php';
require_once 'partials/logo.php';
if (me()) redirect(beranda_peran(me()['role']));

$n     = (int)$pdo->query("SELECT COUNT(*) FROM claims")->fetchColumn();
$bayar = (float)$pdo->query("SELECT COALESCE(SUM(total),0) FROM claims WHERE status='dibayar'")->fetchColumn();
$avg   = (float)$pdo->query("SELECT AVG(TIMESTAMPDIFF(HOUR, a.awal, a.akhir))/24 FROM (
   SELECT claim_id, MIN(created_at) awal, MAX(CASE WHEN aksi='Dibayar' THEN created_at END) akhir
   FROM claim_histories GROUP BY claim_id) a WHERE a.akhir IS NOT NULL")->fetchColumn();

$peran = [
  ['Pegawai', 'Mengisi rincian biaya, lalu memotret atau mengunggah nota per item.', '#2B59FF', 'doc'],
  ['Manajer HRD', 'Mencocokkan dengan tugas dinas. Setuju, minta revisi, atau tolak.', '#7C3AED', 'check'],
  ['Finance', 'Memeriksa nota satu per satu, berdampingan dengan rinciannya.', '#0369A1', 'search'],
  ['Kasir', 'Mentransfer ke rekening pegawai dan menyimpan buktinya.', '#15803D', 'card'],
  ['Direktur', 'Melihat laporan biaya per divisi dan kategori kapan saja.', '#C2410C', 'chart'],
];

$judul_halaman = 'Reimburse dinas tanpa kejar berkas';
$body_class = 'landing';
$publik = true;
include 'partials/header.php';
?>
<div class="progress" aria-hidden="true"><span id="progress"></span></div>

<header class="lnav">
  <nav class="lnav-in" aria-label="Navigasi utama">
    <?= logo('index.php', true, false) ?>
    <div class="lnav-links"><a href="#alur">Alur</a><a href="#fitur">Fitur</a><a href="#aturan">Aturan</a></div>
    <button class="icon-btn theme-toggle" type="button" aria-label="Ganti tema terang/gelap"><?= ico('sun', 'i-sun') . ico('moon', 'i-moon') ?></button>
    <a class="btn dark sm pill-btn" href="login.php">Masuk</a>
  </nav>
</header>

<!-- HERO -->
<section class="hero">
  <p class="hero-kicker rise">Struki</p>
  <h1 class="hero-h rise d1">Reimburse dinas.<br>Tanpa kejar berkas.</h1>
  <p class="hero-p rise d2">Foto nota dari HP, kirim. Manajer HRD menyetujui, Finance mencocokkan, Kasir mentransfer. Kamu tahu posisinya setiap saat.</p>
  <div class="hero-cta rise d3">
    <a class="btn primary lg pill-btn magnet" href="register.php">Daftar sebagai pegawai</a>
    <a class="link-more" href="#alur">Lihat alurnya ›</a>
  </div>

  <div class="showcase rise d4" id="showcase">
    <div class="window">
      <div class="win-bar"><i></i><i></i><i></i><span>localhost/struki</span></div>
      <div class="win-body">
        <div class="win-list">
          <div class="win-h"><b>Pengajuan saya</b><small>4 pengajuan</small></div>
          <div class="win-row"><span class="win-ic">YK</span><span><b>Audit stok cabang Malioboro</b><small>RB-2026-0001 · Yogyakarta · 5 item</small></span><span class="pill s-diajukan">Menunggu HRD</span><b class="num">Rp 1.478.500</b></div>
          <div class="win-row"><span class="win-ic">KD</span><span><b>Kunjungan vendor kemasan</b><small>RB-2026-0003 · Kudus · 2 item</small></span><span class="pill s-terverifikasi">Menunggu pembayaran</span><b class="num">Rp 282.000</b></div>
          <div class="win-row"><span class="win-ic">MG</span><span><b>Rapat koordinasi distributor</b><small>RB-2026-0006 · Magelang · 2 item</small></span><span class="pill s-dibayar">Dibayar</span><b class="num">Rp 318.000</b></div>
        </div>
        <div class="win-track">
          <small>RB-2026-0001</small>
          <b>Sedang di meja Manajer HRD</b>
          <ol><li class="done"><i></i>Diajukan · 16 Okt, 09:12</li><li class="now"><i></i>Manajer HRD</li><li><i></i>Finance</li><li><i></i>Kasir</li></ol>
        </div>
      </div>
    </div>
    <div class="float-note floaty"><span class="ok-dot"><?= ico('check') ?></span><span><b>Dana sudah ditransfer</b><small>BCA · Rp 318.000</small></span></div>
    <div class="phone">
      <div class="phone-in">
        <span class="notch"></span>
        <b>Ajukan reimburse</b>
        <div class="ph-field"><small>Kategori</small><b>Makan</b></div>
        <div class="ph-field"><small>Nominal</small><b>Rp 145.000</b></div>
        <div class="ph-cam"><?= ico('camera') ?>Foto nota</div>
        <span class="ph-btn">Kirim ke HRD</span>
      </div>
    </div>
  </div>
</section>

<!-- ALUR -->
<section class="sec white" id="alur">
  <div class="sec-in">
    <h2 class="sec-h reveal">Lima peran.<br>Satu alur yang jelas.</h2>
    <p class="sec-p reveal">Setiap orang hanya melihat yang perlu dikerjakannya. Pengajuan pindah meja tanpa satu lembar kertas.</p>
    <ol class="roles">
      <?php foreach ($peran as $i => [$nama, $isi, $warna, $ic]): ?>
        <li class="role-card reveal tilt" style="--c:<?= $warna ?>;--i:<?= $i ?>">
          <span class="rc-top"><span class="rc-ico"><?= ico($ic) ?></span><b><?= $i + 1 ?></b></span>
          <h3><?= e($nama) ?></h3>
          <p><?= e($isi) ?></p>
        </li>
      <?php endforeach; ?>
    </ol>
  </div>
</section>

<!-- STATUS (gelap) -->
<section class="sec black">
  <div class="sec-in center">
    <h2 class="sec-h reveal">Tidak ada lagi<br>“berkas saya di mana?”</h2>
    <p class="sec-p reveal">Garis status terisi sendiri setiap kali pengajuanmu pindah meja. Saat dana cair, kamu yang pertama tahu.</p>
    <div class="steps-line" id="stepsLine">
      <div><span></span><b>Diajukan</b><small>16 Okt, 09:12</small></div>
      <div><span></span><b>Disetujui HRD</b><small>16 Okt, 13:30</small></div>
      <div><span></span><b>Diverifikasi Finance</b><small>17 Okt, 10:05</small></div>
      <div><span></span><b>Dibayar</b><small>17 Okt, 11:10</small></div>
    </div>
    <p class="big-grad num" data-count="1478500" data-type="rp">Rp 1.478.500</p>
    <p class="sec-p">kembali ke rekening pegawai, sehari setelah diajukan.</p>
  </div>
</section>

<!-- FITUR -->
<section class="sec" id="fitur">
  <div class="sec-in">
    <h2 class="sec-h reveal">Dibuat untuk setiap meja.</h2>
    <div class="bento">
      <div class="bx wide reveal">
        <div><span class="bx-tag" style="color:#0369A1">Finance</span><h3>Rincian dan nota, berdampingan.</h3><p>Klik satu baris, notanya langsung tampil di samping. Tombol verifikasi baru aktif setelah semua item dicentang.</p></div>
        <div class="bx-demo">
          <div class="mini-checks"><span class="on">Kereta PP</span><span class="on">Hotel 2 malam</span><span>Makan</span></div>
          <div class="mini-paper">KAI ACCESS<br>Smg–Yk PP<br>320.000<br>————<br>TOTAL 320.000</div>
        </div>
      </div>
      <div class="bx dark reveal"><div><span class="bx-tag" style="color:#FBBF24">Pegawai</span><h3>Foto nota langsung dari kamera.</h3></div><div class="bx-cam"><?= ico('camera') ?><span>Bingkai pemandu membantu nota terbaca jelas.</span></div></div>
      <div class="bx reveal"><span class="bx-tag" style="color:#7C3AED">Manajer HRD</span><h3>Revisi selalu dengan alasan tertulis.</h3><div class="bubble-demo">Nominal di nota makan tidak terbaca, mohon foto ulang.</div></div>
      <div class="bx green reveal"><span class="bx-tag" style="color:#15803D">Kasir</span><h3>Rekening pegawai sudah ada di layar.</h3><div class="acct-demo"><b>BCA</b> · <span class="num">8410293377</span><small>a.n. Rudi Hartono</small></div></div>
      <div class="bx reveal"><span class="bx-tag" style="color:#C2410C">Direktur</span><h3>Laporan jadi sendiri. Unduh ke Excel.</h3><div class="bars-demo"><i style="--h:100%"></i><i style="--h:68%"></i><i style="--h:46%"></i><i style="--h:42%"></i><i style="--h:12%"></i></div></div>
    </div>
  </div>
</section>

<!-- ATURAN -->
<section class="sec white" id="aturan">
  <div class="sec-in">
    <h2 class="sec-h reveal">Aturan dinas dijaga sistem, bukan diingat-ingat.</h2>
    <div class="rules">
      <div class="reveal"><b><?= BATAS_HARI_PENGAJUAN ?> hari</b><p>Batas mengajukan setelah tanggal pulang dinas.</p></div>
      <div class="reveal"><b>1 nota</b><p>Wajib untuk setiap item. Foto atau PDF, maksimal 5 MB.</p></div>
      <div class="reveal"><b>Rp 150 rb</b><p>Batas makan per item. Penginapan Rp 600 rb, lain-lain Rp 200 rb.</p></div>
      <div class="reveal"><b>Final</b><p>Pengajuan yang ditolak tidak bisa diedit. Buat pengajuan baru.</p></div>
    </div>
    <div class="stats-row">
      <div><b class="num" data-count="<?= $n ?>"><?= $n ?></b><span>pengajuan tercatat</span></div>
      <div><b class="num" data-count="<?= $bayar ?>" data-type="rp"><?= rp($bayar) ?></b><span>sudah ditransfer</span></div>
      <div><b class="num" data-count="<?= round($avg, 1) ?>" data-type="dec" data-suffix=" hari"><?= number_format($avg, 1, ',', '.') ?> hari</b><span>rata-rata sampai cair</span></div>
    </div>
  </div>
</section>

<!-- CTA -->
<section class="sec cta">
  <div class="sec-in center">
    <span class="cta-mark"><?= logo_mark(76, true) ?></span>
    <h2 class="sec-h">Punya nota yang belum diganti?</h2>
    <p class="sec-p">Daftar dengan nomor rekeningmu. Pengajuan pertama selesai dalam lima menit.</p>
    <div class="hero-cta"><a class="btn primary lg pill-btn magnet" href="register.php">Daftar sebagai pegawai</a><a class="link-more" href="login.php">Sudah punya akun? Masuk ›</a></div>
  </div>
</section>

<footer class="lfoot"><span>Struki · Sistem reimburse dinas <?= APP_COMPANY ?></span><span>Tugas Rekayasa Perangkat Lunak · <?= date('Y') ?></span></footer>
<?php include 'partials/footer.php'; ?>

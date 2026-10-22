# Struki — Sistem Reimburse Dinas Luar Kota

Studi kasus **PT Angin Ribut** untuk tugas Rekayasa Perangkat Lunak.
Dibangun dengan **PHP 8 native + MySQL** (XAMPP), tanpa framework, mengikuti dokumen SKPL/SRS Struki.

Alur: **Pegawai → Manajer HRD → Finance → Kasir → Direktur (laporan)**

```
diajukan ──HRD setuju──▶ disetujui ──Finance verifikasi──▶ terverifikasi ──Kasir bayar──▶ dibayar
    │                         │
    ├── revisi (pegawai perbaiki, kembali ke HRD)
    └── ditolak (final)
```

## Cara menjalankan di XAMPP

1. Salin folder `struki` ke `C:\xampp\htdocs\` sehingga menjadi `C:\xampp\htdocs\struki`.
2. Buka **XAMPP Control Panel**, lalu **Start** pada **Apache** dan **MySQL**.
3. Buka `http://localhost/phpmyadmin`, pilih tab **Import**, pilih file `database.sql`, lalu klik **Import**.
   Database `db_struki` beserta tabel dan data contohnya akan dibuat otomatis.
4. Buka `http://localhost/struki`.

Kalau MySQL kamu memakai password, ubah `$DB_PASS` di `config.php`.

## Akun

Semua akun bawaan memakai password **`demo123`**.

| Username | Nama | Peran |
|---|---|---|
| `hrd` | Bu Rina Kusuma | Manajer HRD |
| `finance` | Bu Sari Wulandari | Finance |
| `kasir` | Pak Budi Santoso | Kasir |
| `direktur` | Pak Hendra Wijaya | Direktur |
| `rudi` | Rudi Hartono | Pegawai (contoh) |
| `siti` | Siti Aminah | Pegawai (contoh) |

- **Pegawai mendaftar sendiri** lewat halaman *Daftar* (nama, divisi, bank, nomor rekening).
- **Akun staf** (Finance, Kasir, Direktur, HRD) dibuat oleh Manajer HRD di menu **Kelola akun**. HRD juga bisa menonaktifkan akun; akun nonaktif tidak bisa masuk.
- Di `config.php` ada `const MODE_DEMO = true;` yang menampilkan daftar akun demo di halaman login. Ubah ke `false` kalau sistem dipakai sungguhan.

## Fitur utama

- **Foto nota langsung dari kamera** (dengan bingkai pemandu) **atau unggah file** (JPG, PNG, WEBP, PDF, maks 5 MB) untuk setiap item biaya.
  Kamera browser hanya jalan di `localhost` atau `https://`. Kalau dibuka lewat IP (mis. dari HP ke laptop), tombol kamera otomatis memakai kamera bawaan HP.
- Ringkasan biaya dan cek aturan berjalan langsung saat mengisi form.
- Manajer HRD bisa menyetujui, meminta revisi, atau menolak. Revisi dan penolakan wajib diberi catatan.
- Finance memeriksa nota berdampingan dengan rinciannya. Tombol verifikasi baru aktif setelah semua item dicentang.
- Kasir melihat rekening pegawai, bisa menyalin nomornya, dan mengunggah bukti transfer.
- Direktur melihat laporan per kategori dan divisi, daftar semua pengajuan, dan bisa mengekspor CSV untuk Excel.
- Tersedia mode gelap, tampilan responsif untuk HP, dan animasi interaktif.

## Aturan bisnis (Domain Rules)

| Kode | Aturan |
|---|---|
| DR-01 | Pengajuan paling lambat 14 hari setelah tanggal pulang dinas |
| DR-02 | Tanggal nota harus di dalam rentang tanggal dinas |
| DR-03 | Batas per item: penginapan Rp 600.000, makan Rp 150.000, lain-lain Rp 200.000 (di atas batas tetap bisa diajukan tapi ditandai) |
| DR-04 | Setiap item wajib punya satu nota |
| DR-05 | Hanya pengajuan berstatus revisi yang boleh diubah pegawai |
| DR-06 | Pengajuan yang ditolak bersifat final |
| DR-07 | Kode pengajuan berformat `RB-YYYY-NNNN` |

## Peta kebutuhan fungsional → file

| Kebutuhan | File |
|---|---|
| FR-01 Registrasi pegawai | `register.php` |
| FR-02 Login & arahkan sesuai peran | `login.php`, `logout.php` |
| FR-03 s.d. FR-05 Form pengajuan, nota (kamera/unggah), total & batas | `ajukan.php` |
| FR-06 Beranda & pelacakan status | `dashboard.php`, `detail.php` |
| FR-07 Perbaikan revisi | `ajukan.php?edit=ID` |
| FR-08 Persetujuan Manajer HRD | `dashboard.php`, `detail.php`, `aksi.php` |
| FR-09 Verifikasi Finance | `detail.php`, `aksi.php` |
| FR-10 Pembayaran Kasir | `detail.php`, `aksi.php` |
| FR-11 Riwayat / jejak audit | `detail.php` (tabel `claim_histories`) |
| FR-12 Laporan Direktur | `laporan.php` |
| FR-13 Semua pengajuan | `semua.php` |
| FR-14 Ekspor CSV | `export_csv.php` |
| FR-15 Riwayat tindakan staf | `dashboard.php?v=riwayat` |
| FR-16 Kelola akun staf (HRD) | `akun.php` |

## Struktur folder

```
struki/
├── index.php          halaman depan
├── login.php / register.php / logout.php
├── dashboard.php      beranda tiap peran
├── ajukan.php         form pengajuan + kamera nota
├── detail.php         detail, review, tindakan
├── aksi.php           proses setuju/revisi/tolak/verifikasi/bayar
├── akun.php           kelola akun staf (HRD)
├── laporan.php / semua.php / export_csv.php   (Direktur)
├── config.php         koneksi DB, konstanta, fungsi bantu
├── database.sql       skema + data contoh
├── partials/          header, footer, logo, komponen daftar
├── assets/            style.css, app.js, favicon.svg
└── uploads/           nota & bukti transfer (dilindungi .htaccess)
```

## Keamanan

- Password disimpan dengan `password_hash`, query memakai PDO prepared statement.
- Setiap form memakai token CSRF.
- Peran dan status selalu dicek ulang di server sebelum tindakan diproses.
- File unggahan dicek jenis dan isinya, diberi nama acak, dan folder `uploads/` memblokir eksekusi skrip.

## Upload ke GitHub

```bash
cd C:\xampp\htdocs\struki
git init
git add .
git commit -m "Struki: sistem reimburse dinas PT Angin Ribut"
git branch -M main
git remote add origin https://github.com/USERNAME/struki-reimburse.git
git push -u origin main
```

Buat repository kosong dulu di GitHub (tanpa README) dengan nama `struki-reimburse`, lalu ganti `USERNAME` dengan username GitHub kamu.

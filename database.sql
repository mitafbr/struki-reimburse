-- =========================================================
-- Struki — Database Sistem Reimburse Dinas (PT Angin Ribut)
-- Cara pakai: phpMyAdmin > tab "Import" > pilih file ini > Import
-- Akun bawaan memakai password: demo123
-- =========================================================

SET NAMES utf8mb4;
CREATE DATABASE IF NOT EXISTS db_struki CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE db_struki;

SET FOREIGN_KEY_CHECKS = 0;
DROP TABLE IF EXISTS claim_histories;
DROP TABLE IF EXISTS claim_items;
DROP TABLE IF EXISTS claims;
DROP TABLE IF EXISTS users;
SET FOREIGN_KEY_CHECKS = 1;

-- ---------- users: semua aktor ----------
CREATE TABLE users (
  id           INT AUTO_INCREMENT PRIMARY KEY,
  username     VARCHAR(30)  NOT NULL UNIQUE,
  password     VARCHAR(255) NOT NULL,
  nama         VARCHAR(100) NOT NULL,
  role         ENUM('pegawai','hrd','finance','kasir','direktur') NOT NULL DEFAULT 'pegawai',
  divisi       VARCHAR(50)  NOT NULL,
  bank         VARCHAR(30)  NULL,
  no_rekening  VARCHAR(30)  NULL,
  aktif        TINYINT(1)   NOT NULL DEFAULT 1,
  created_at   TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- ---------- claims: satu baris per pengajuan ----------
CREATE TABLE claims (
  id              INT AUTO_INCREMENT PRIMARY KEY,
  kode            VARCHAR(20)  NOT NULL UNIQUE,
  user_id         INT NOT NULL,
  judul           VARCHAR(150) NOT NULL,
  kota_tujuan     VARCHAR(80)  NOT NULL,
  keperluan       VARCHAR(255) NOT NULL,
  tgl_berangkat   DATE NOT NULL,
  tgl_pulang      DATE NOT NULL,
  status          ENUM('diajukan','disetujui','terverifikasi','dibayar','revisi','ditolak') NOT NULL DEFAULT 'diajukan',
  total           DECIMAL(14,2) NOT NULL DEFAULT 0,
  metode_bayar    VARCHAR(50)  NULL,
  no_referensi    VARCHAR(50)  NULL,
  bukti_transfer  VARCHAR(255) NULL,
  created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_claims_user FOREIGN KEY (user_id) REFERENCES users(id)
) ENGINE=InnoDB;

-- ---------- claim_items: rincian biaya + nota ----------
CREATE TABLE claim_items (
  id          INT AUTO_INCREMENT PRIMARY KEY,
  claim_id    INT NOT NULL,
  kategori    ENUM('transport','tiket','hotel','makan','lain') NOT NULL,
  keterangan  VARCHAR(150) NOT NULL,
  tanggal     DATE NOT NULL,
  nominal     DECIMAL(14,2) NOT NULL,
  nota        VARCHAR(255) NULL,
  CONSTRAINT fk_items_claim FOREIGN KEY (claim_id) REFERENCES claims(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ---------- claim_histories: jejak audit ----------
CREATE TABLE claim_histories (
  id          INT AUTO_INCREMENT PRIMARY KEY,
  claim_id    INT NOT NULL,
  user_id     INT NOT NULL,
  aksi        VARCHAR(50) NOT NULL,
  catatan     TEXT NULL,
  created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_hist_claim FOREIGN KEY (claim_id) REFERENCES claims(id) ON DELETE CASCADE,
  CONSTRAINT fk_hist_user  FOREIGN KEY (user_id)  REFERENCES users(id)
) ENGINE=InnoDB;

-- =========================================================
-- DATA AWAL — password semua akun: demo123
-- HRD, Finance, Kasir, Direktur = akun bawaan.
-- Pegawai biasanya daftar sendiri; rudi & siti hanya contoh.
-- =========================================================
INSERT INTO users (id, username, password, nama, role, divisi, bank, no_rekening) VALUES
(1, 'hrd',      '$2y$10$zkUSnIJXDt4p8pAyA8e/ceahbAFyynxejRbRvpaiwaK.MR9VgRyXu', 'Bu Rina Kusuma',    'hrd',      'SDM',         NULL, NULL),
(2, 'finance',  '$2y$10$zkUSnIJXDt4p8pAyA8e/ceahbAFyynxejRbRvpaiwaK.MR9VgRyXu', 'Bu Sari Wulandari', 'finance',  'Keuangan',    NULL, NULL),
(3, 'kasir',    '$2y$10$zkUSnIJXDt4p8pAyA8e/ceahbAFyynxejRbRvpaiwaK.MR9VgRyXu', 'Pak Budi Santoso',  'kasir',    'Keuangan',    NULL, NULL),
(4, 'direktur', '$2y$10$zkUSnIJXDt4p8pAyA8e/ceahbAFyynxejRbRvpaiwaK.MR9VgRyXu', 'Pak Hendra Wijaya', 'direktur', 'Direksi',     NULL, NULL),
(5, 'rudi',     '$2y$10$zkUSnIJXDt4p8pAyA8e/ceahbAFyynxejRbRvpaiwaK.MR9VgRyXu', 'Rudi Hartono',      'pegawai',  'Operasional', 'BCA', '8410293377'),
(6, 'siti',     '$2y$10$zkUSnIJXDt4p8pAyA8e/ceahbAFyynxejRbRvpaiwaK.MR9VgRyXu', 'Siti Aminah',       'pegawai',  'Pemasaran',   'BRI', '0021-01-556271-50-3');

-- satu contoh di setiap status
INSERT INTO claims (id, kode, user_id, judul, kota_tujuan, keperluan, tgl_berangkat, tgl_pulang, status, total, metode_bayar, no_referensi) VALUES
(1, 'RB-2026-0001', 5, 'Audit stok cabang Malioboro',  'Yogyakarta', 'Audit stok gudang cabang',         '2026-10-13', '2026-10-15', 'diajukan',      1478500, NULL, NULL),
(2, 'RB-2026-0002', 6, 'Pameran UMKM Solo',            'Surakarta',  'Jaga booth pameran produk',        '2026-10-06', '2026-10-07', 'disetujui',      485000, NULL, NULL),
(3, 'RB-2026-0003', 5, 'Kunjungan vendor kemasan',     'Kudus',      'Negosiasi harga kemasan',          '2026-09-29', '2026-09-29', 'terverifikasi',  282000, NULL, NULL),
(4, 'RB-2026-0004', 6, 'Pelatihan digital marketing',  'Jakarta',    'Workshop pemasaran digital',       '2026-09-15', '2026-09-16', 'dibayar',       1170000, 'Transfer BRI', 'TRF-260918-213'),
(5, 'RB-2026-0005', 6, 'Survei lokasi outlet',         'Ungaran',    'Survei calon lokasi outlet baru',  '2026-10-09', '2026-10-09', 'revisi',         205000, NULL, NULL),
(6, 'RB-2026-0006', 5, 'Rapat koordinasi distributor', 'Magelang',   'Koordinasi target kuartal IV',     '2026-09-22', '2026-09-22', 'dibayar',        318000, 'Transfer BCA', 'TRF-260925-087'),
(7, 'RB-2026-0007', 5, 'Studi banding gudang',         'Surabaya',   'Studi banding sistem gudang',      '2026-09-08', '2026-09-09', 'ditolak',        960000, NULL, NULL);

INSERT INTO claim_items (claim_id, kategori, keterangan, tanggal, nominal, nota) VALUES
(1, 'tiket',     'Kereta Semarang–Yogyakarta PP', '2026-10-13', 320000, NULL),
(1, 'hotel',     'Hotel 2 malam',                 '2026-10-13', 900000, NULL),
(1, 'makan',     'Makan siang & malam',           '2026-10-14', 145000, NULL),
(1, 'transport', 'Taksi online ke gudang',        '2026-10-14',  68000, NULL),
(1, 'lain',      'Air mineral & snack',           '2026-10-14',  45500, NULL),
(2, 'transport', 'Bensin + tol',                  '2026-10-06', 210000, NULL),
(2, 'makan',     'Konsumsi tim booth',            '2026-10-06', 180000, NULL),
(2, 'lain',      'Cetak brosur tambahan',         '2026-10-07',  95000, NULL),
(3, 'transport', 'Travel Semarang–Kudus PP',      '2026-09-29', 150000, NULL),
(3, 'makan',     'Makan siang dengan vendor',     '2026-09-29', 132000, NULL),
(4, 'tiket',     'Kereta Semarang–Jakarta PP',    '2026-09-15', 620000, NULL),
(4, 'hotel',     'Hotel 1 malam',                 '2026-09-15', 480000, NULL),
(4, 'transport', 'Ojek online',                   '2026-09-16',  70000, NULL),
(5, 'transport', 'Bensin',                        '2026-10-09', 120000, NULL),
(5, 'makan',     'Makan siang',                   '2026-10-09',  85000, NULL),
(6, 'transport', 'Bensin + parkir',               '2026-09-22', 188000, NULL),
(6, 'makan',     'Makan siang rapat',             '2026-09-22', 130000, NULL),
(7, 'tiket',     'Pesawat Semarang–Surabaya PP',  '2026-09-08', 960000, NULL);

INSERT INTO claim_histories (claim_id, user_id, aksi, catatan, created_at) VALUES
(1, 5, 'Mengajukan', NULL, '2026-10-16 09:12:00'),
(2, 6, 'Mengajukan', NULL, '2026-10-08 10:02:00'),
(2, 1, 'Disetujui HRD', 'Sesuai agenda pameran.', '2026-10-09 08:40:00'),
(3, 5, 'Mengajukan', NULL, '2026-09-30 08:15:00'),
(3, 1, 'Disetujui HRD', NULL, '2026-09-30 13:30:00'),
(3, 2, 'Diverifikasi Finance', 'Semua nota sesuai.', '2026-10-01 10:05:00'),
(4, 6, 'Mengajukan', NULL, '2026-09-17 09:00:00'),
(4, 1, 'Disetujui HRD', NULL, '2026-09-17 15:20:00'),
(4, 2, 'Diverifikasi Finance', 'Semua nota sesuai.', '2026-09-18 09:45:00'),
(4, 3, 'Dibayar', 'Transfer BRI · Ref TRF-260918-213', '2026-09-18 11:10:00'),
(5, 6, 'Mengajukan', NULL, '2026-10-10 09:10:00'),
(5, 1, 'Disetujui HRD', NULL, '2026-10-10 14:00:00'),
(5, 2, 'Diminta revisi', 'Nominal di nota makan tidak terbaca. Mohon foto ulang notanya.', '2026-10-12 10:30:00'),
(6, 5, 'Mengajukan', NULL, '2026-09-23 08:30:00'),
(6, 1, 'Disetujui HRD', NULL, '2026-09-23 11:00:00'),
(6, 2, 'Diverifikasi Finance', 'Semua nota sesuai.', '2026-09-24 09:20:00'),
(6, 3, 'Dibayar', 'Transfer BCA · Ref TRF-260925-087', '2026-09-25 10:00:00'),
(7, 5, 'Mengajukan', NULL, '2026-09-10 08:00:00'),
(7, 1, 'Ditolak', 'Studi banding tidak ada di surat tugas bulan ini.', '2026-09-10 13:45:00');

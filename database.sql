-- SQL Dump untuk PT Agrotamex Sumindo Abadi
-- Sistem Informasi Pemantauan Produktivitas Karyawan

CREATE DATABASE IF NOT EXISTS `db_agrotamex` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `db_agrotamex`;

-- Drop tables if exists to clean setup
SET FOREIGN_KEY_CHECKS = 0;
DROP TABLE IF EXISTS `work_reports`;
DROP TABLE IF EXISTS `assignments`;
DROP TABLE IF EXISTS `karyawan`;
DROP TABLE IF EXISTS `mandor`;
DROP TABLE IF EXISTS `manajer`;
SET FOREIGN_KEY_CHECKS = 1;

-- --------------------------------------------------------

-- Struktur dari tabel `manajer`
CREATE TABLE `manajer` (
  `id_manajer` int(11) NOT NULL AUTO_INCREMENT,
  `username` varchar(50) NOT NULL UNIQUE,
  `password` varchar(255) NOT NULL,
  `nama` varchar(100) NOT NULL,
  PRIMARY KEY (`id_manajer`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Dumping data untuk tabel `manajer`
-- Password: password123
INSERT INTO `manajer` (`id_manajer`, `username`, `password`, `nama`) VALUES
(1, 'manajer', '$2y$10$68y5PKCzztDnGDpXQ1YbMufp5PJh4HtFVvSWcZtqPPGJMgt8MzJ.C', 'Junior Estate Manager (JEM)');

-- --------------------------------------------------------

-- Struktur dari tabel `mandor`
CREATE TABLE `mandor` (
  `id_mandor` int(11) NOT NULL AUTO_INCREMENT,
  `username` varchar(50) NOT NULL UNIQUE,
  `password` varchar(255) NOT NULL,
  `nama` varchar(100) NOT NULL,
  `spesialisasi` enum('Pemanenan','Penyemprotan','Pemupukan') NOT NULL,
  PRIMARY KEY (`id_mandor`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Dumping data untuk tabel `mandor`
-- Password: password123
INSERT INTO `mandor` (`id_mandor`, `username`, `password`, `nama`, `spesialisasi`) VALUES
(1, 'mandor1', '$2y$10$68y5PKCzztDnGDpXQ1YbMufp5PJh4HtFVvSWcZtqPPGJMgt8MzJ.C', 'Mandor Budi', 'Pemanenan'),
(2, 'mandor2', '$2y$10$68y5PKCzztDnGDpXQ1YbMufp5PJh4HtFVvSWcZtqPPGJMgt8MzJ.C', 'Mandor Slamet', 'Pemupukan'),
(3, 'mandor3', '$2y$10$68y5PKCzztDnGDpXQ1YbMufp5PJh4HtFVvSWcZtqPPGJMgt8MzJ.C', 'Mandor Roni', 'Penyemprotan');

-- --------------------------------------------------------

-- Struktur dari tabel `karyawan`
CREATE TABLE `karyawan` (
  `id_karyawan` int(11) NOT NULL AUTO_INCREMENT,
  `username` varchar(50) NOT NULL UNIQUE,
  `password` varchar(255) NOT NULL,
  `nama` varchar(100) NOT NULL,
  `status_aktif` tinyint(4) DEFAULT 1,
  PRIMARY KEY (`id_karyawan`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Dumping data untuk tabel `karyawan`
-- Password: password123
INSERT INTO `karyawan` (`id_karyawan`, `username`, `password`, `nama`, `status_aktif`) VALUES
(1, 'karyawan1', '$2y$10$68y5PKCzztDnGDpXQ1YbMufp5PJh4HtFVvSWcZtqPPGJMgt8MzJ.C', 'Agung B.', 1),
(2, 'karyawan2', '$2y$10$68y5PKCzztDnGDpXQ1YbMufp5PJh4HtFVvSWcZtqPPGJMgt8MzJ.C', 'Endra P.', 1),
(3, 'karyawan3', '$2y$10$68y5PKCzztDnGDpXQ1YbMufp5PJh4HtFVvSWcZtqPPGJMgt8MzJ.C', 'Feri I.', 1),
(4, 'karyawan4', '$2y$10$68y5PKCzztDnGDpXQ1YbMufp5PJh4HtFVvSWcZtqPPGJMgt8MzJ.C', 'Herman', 1),
(5, 'karyawan5', '$2y$10$68y5PKCzztDnGDpXQ1YbMufp5PJh4HtFVvSWcZtqPPGJMgt8MzJ.C', 'Ismail', 1);

-- --------------------------------------------------------

-- Struktur dari tabel `assignments`
CREATE TABLE `assignments` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `tanggal` date NOT NULL,
  `aktivitas` varchar(50) NOT NULL,
  `target_jumlah` decimal(10,2) NOT NULL,
  `unit` varchar(20) NOT NULL,
  `id_mandor` int(11) NOT NULL,
  `id_karyawan` int(11) NOT NULL,
  `id_manajer` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `id_mandor` (`id_mandor`),
  KEY `id_karyawan` (`id_karyawan`),
  KEY `id_manajer` (`id_manajer`),
  CONSTRAINT `assignments_ibfk_1` FOREIGN KEY (`id_mandor`) REFERENCES `mandor` (`id_mandor`) ON DELETE CASCADE,
  CONSTRAINT `assignments_ibfk_2` FOREIGN KEY (`id_karyawan`) REFERENCES `karyawan` (`id_karyawan`) ON DELETE CASCADE,
  CONSTRAINT `assignments_ibfk_3` FOREIGN KEY (`id_manajer`) REFERENCES `manajer` (`id_manajer`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Dumping data untuk tabel `assignments`
-- Penugasan Harian (Kemarin dan Hari Ini)
INSERT INTO `assignments` (`id`, `tanggal`, `aktivitas`, `target_jumlah`, `unit`, `id_mandor`, `id_karyawan`, `id_manajer`) VALUES
(1, DATE_SUB(CURDATE(), INTERVAL 1 DAY), 'Pemanenan', 1.50, 'Ton', 1, 1, 1),
(2, DATE_SUB(CURDATE(), INTERVAL 1 DAY), 'Pemanenan', 1.50, 'Ton', 1, 2, 1),
(3, DATE_SUB(CURDATE(), INTERVAL 1 DAY), 'Pemupukan', 10.00, 'Karung', 2, 3, 1),
(4, CURDATE(), 'Pemanenan', 1.50, 'Ton', 1, 1, 1),
(5, CURDATE(), 'Penyemprotan', 2.00, 'Hektar', 3, 4, 1),
(6, CURDATE(), 'Pemupukan', 12.00, 'Karung', 2, 5, 1);

-- --------------------------------------------------------

-- Struktur dari tabel `work_reports`
CREATE TABLE `work_reports` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `id_assignment` int(11) NOT NULL,
  `id_karyawan` int(11) NOT NULL,
  `jumlah_realisasi` decimal(10,2) NOT NULL,
  `foto_bukti` varchar(255) DEFAULT NULL,
  `catatan_karyawan` text DEFAULT NULL,
  `status` enum('pending_mandor','verified_by_mandor','approved','rejected','pending_manajer_tolak') DEFAULT 'pending_mandor',
  `catatan_mandor` text DEFAULT NULL,
  `tanggal_verifikasi_mandor` datetime DEFAULT NULL,
  `catatan_manajer` text DEFAULT NULL,
  `tanggal_verifikasi_manajer` datetime DEFAULT NULL,
  `bonus_diterima` decimal(15,2) DEFAULT 0.00,
  `potongan_penalti` decimal(5,2) DEFAULT 0.00,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `id_assignment` (`id_assignment`),
  KEY `id_karyawan` (`id_karyawan`),
  CONSTRAINT `work_reports_ibfk_1` FOREIGN KEY (`id_assignment`) REFERENCES `assignments` (`id`) ON DELETE CASCADE,
  CONSTRAINT `work_reports_ibfk_2` FOREIGN KEY (`id_karyawan`) REFERENCES `karyawan` (`id_karyawan`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Dumping data untuk tabel `work_reports`
-- Realisasi pekerjaan kemarin
INSERT INTO `work_reports` (`id`, `id_assignment`, `id_karyawan`, `jumlah_realisasi`, `foto_bukti`, `catatan_karyawan`, `status`, `catatan_mandor`, `tanggal_verifikasi_mandor`, `catatan_manajer`, `tanggal_verifikasi_manajer`) VALUES
(1, 1, 1, 1.65, 'assets/uploads/dummy_panen1.jpg', 'Kemarin cuaca cerah, hasil panen melimpah.', 'approved', 'Pekerjaan bagus, buah sawit bersih dan matang.', DATE_SUB(NOW(), INTERVAL 20 HOUR), 'Disetujui. Pertahankan performa kerja!', DATE_SUB(NOW(), INTERVAL 19 HOUR)),
(2, 2, 2, 1.40, 'assets/uploads/dummy_panen2.jpg', 'Target kurang sedikit karena mobil traksi terlambat.', 'verified_by_mandor', 'Fisik buah sudah sesuai takaran timbangan.', DATE_SUB(NOW(), INTERVAL 21 HOUR), NULL, NULL),
(3, 3, 3, 10.00, 'assets/uploads/dummy_pemupukan1.jpg', 'Pemupukan Blok B selesai tepat waktu.', 'pending_mandor', NULL, NULL, NULL, NULL);

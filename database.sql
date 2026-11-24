-- ============================================================
-- DATABASE SCHEMA: Sistem Informasi Manajemen Pondok Pesantren
-- Database Name: db_pondok (Default)
-- ============================================================

SET FOREIGN_KEY_CHECKS = 0;

CREATE DATABASE IF NOT EXISTS `db_pondok` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `db_pondok`;

-- ------------------------------------------------------------
-- 1. Table structure for `users`
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `users` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `username` VARCHAR(50) NOT NULL UNIQUE,
  `password` VARCHAR(255) NOT NULL,
  `nama_lengkap` VARCHAR(100) NOT NULL,
  `role` ENUM('admin', 'pengurus_1', 'pengurus_2') NOT NULL DEFAULT 'pengurus_1',
  `permissions` TEXT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Seed default user accounts (Password default: admin123 & pengurus123)
-- Hash generated using password_hash('admin123', PASSWORD_DEFAULT)
INSERT INTO `users` (`username`, `password`, `nama_lengkap`, `role`, `permissions`) VALUES
('admin', '$2y$10$EFSipE0So9dLLOR.TQBWtu/d0AvSFJYZJsSz6EUzwKKzJ4uGDNXdy', 'Administrator Utama', 'admin', '["santri","perizinan","pelanggaran","spp","pengurus"]'),
('pengurus', '$2y$10$tYPf7hNhEUCZ0pfZ9exKouyFirB.noFtIJwCLto4WN/15pddPvE56', 'Ustadz Pengurus', 'pengurus_1', '["perizinan","pelanggaran"]')
ON DUPLICATE KEY UPDATE `username`=`username`;

-- ------------------------------------------------------------
-- 2. Table structure for `santri`
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `santri` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `nis` VARCHAR(20) NOT NULL UNIQUE,
  `nama` VARCHAR(100) NOT NULL,
  `kamar` VARCHAR(50) NOT NULL,
  `status` ENUM('Aktif', 'Alumni', 'Non-Aktif') NOT NULL DEFAULT 'Aktif',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Seed default data santri
INSERT INTO `santri` (`nis`, `nama`, `kamar`, `status`) VALUES
('2024001', 'Ahmad Fauzi', 'Al-Ghazali 01', 'Aktif'),
('2024002', 'Muhammad Rizky', 'Al-Ghazali 02', 'Aktif'),
('2024003', 'Siti Nurhaliza', 'Aisyah 01', 'Aktif'),
('2024004', 'Bilal Bin Rabah', 'Ibnu Sina 03', 'Aktif'),
('2024005', 'Fatimah Az-Zahra', 'Aisyah 02', 'Aktif')
ON DUPLICATE KEY UPDATE `nis`=`nis`;

-- ------------------------------------------------------------
-- 3. Table structure for `perizinan`
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `perizinan` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `santri_id` INT NOT NULL,
  `jenis_izin` ENUM('Pulang', 'Sakit', 'Acara') NOT NULL,
  `keterangan` TEXT NOT NULL,
  `tanggal` DATE NOT NULL,
  `tanggal_selesai` DATE NOT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`santri_id`) REFERENCES `santri`(`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Seed default data perizinan
INSERT INTO `perizinan` (`santri_id`, `jenis_izin`, `keterangan`, `tanggal`, `tanggal_selesai`) VALUES
(1, 'Pulang', 'Izin menjenguk keluarga di kampung halaman', '2026-07-20', '2026-07-22'),
(3, 'Sakit', 'Istirahat di ruang UKS karena demam', '2026-07-22', '2026-07-24');

-- ------------------------------------------------------------
-- 4. Table structure for `pelanggaran`
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `pelanggaran` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `santri_id` INT NOT NULL,
  `jenis_pelanggaran` VARCHAR(255) NOT NULL,
  `poin` INT NOT NULL DEFAULT 0,
  `tanggal` DATE NOT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`santri_id`) REFERENCES `santri`(`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Seed default data pelanggaran (Sample data for Rule-Based: Aman, Ringan, Sedang, Berat)
INSERT INTO `pelanggaran` (`santri_id`, `jenis_pelanggaran`, `poin`, `tanggal`) VALUES
(2, 'Terlambat mengikuti shalat berjamaah Subuh', 5, '2026-07-15'),
(2, 'Keluar area pondok tanpa izin pengurus', 15, '2026-07-18'),
(4, 'Membawa barang elektronik tanpa izin pengurus', 15, '2026-07-20'),
(4, 'Terlambat kembali ke pondok pasca libur', 20, '2026-07-22'),
(1, 'Keluar area pondok tanpa izin malam hari', 30, '2026-07-19'),
(1, 'Merokok di area asrama santri', 25, '2026-07-21'),
(3, 'Membawa smartphone ilegal di asrama', 25, '2026-07-16'),
(3, 'Terlibat perkelahian dengan sesama santri', 50, '2026-07-22');

SET FOREIGN_KEY_CHECKS = 1;

-- ------------------------------------------------------------
-- 5. Table structure for `tagihan_spp`
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `tagihan_spp` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `santri_id` INT NOT NULL,
  `bulan` VARCHAR(30) NOT NULL,
  `nominal` DECIMAL(12,2) NOT NULL DEFAULT 500000.00,
  `status` ENUM('Lunas', 'Belum Lunas') NOT NULL DEFAULT 'Belum Lunas',
  `tanggal_bayar` DATE NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`santri_id`) REFERENCES `santri`(`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Seed default data SPP
INSERT INTO `tagihan_spp` (`santri_id`, `bulan`, `nominal`, `status`, `tanggal_bayar`) VALUES
(1, 'Juli 2026', 500000.00, 'Lunas', '2026-07-05'),
(2, 'Juli 2026', 500000.00, 'Belum Lunas', NULL),
(3, 'Juli 2026', 500000.00, 'Lunas', '2026-07-08'),
(4, 'Juli 2026', 500000.00, 'Belum Lunas', NULL),
(5, 'Juli 2026', 500000.00, 'Lunas', '2026-07-10');

-- ------------------------------------------------------------
-- 6. Table structure for `pengaturan` (Ambang Batas Rule-Based)
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `pengaturan` (
  `kunci` VARCHAR(50) PRIMARY KEY,
  `nilai` VARCHAR(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Seed default batas poin aturan pelanggaran
INSERT INTO `pengaturan` (`kunci`, `nilai`) VALUES
('poin_ringan', '15'),
('poin_sedang', '25'),
('poin_berat', '50')
ON DUPLICATE KEY UPDATE `nilai`=`nilai`;

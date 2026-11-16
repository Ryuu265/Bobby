-- =====================================================================
-- QENGAMESTATION — SKEMA DATABASE (MySQL / MariaDB)
-- Sistem Informasi Penyewaan dan Pemesanan PlayStation
--
-- Cara pakai:
--   mysql -u root -p < schema.sql
--   (atau import lewat phpMyAdmin)
--
-- Urutan eksekusi: schema.sql  ->  seed.sql
-- Charset  : utf8mb4 (mendukung emoji & huruf daerah)
-- Engine   : InnoDB (butuh foreign key + transaction)
-- =====================================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;
SET sql_mode = 'STRICT_TRANS_TABLES,NO_ENGINE_SUBSTITUTION';

CREATE DATABASE IF NOT EXISTS `qengamestation`
  DEFAULT CHARACTER SET utf8mb4
  DEFAULT COLLATE utf8mb4_unicode_ci;
USE `qengamestation`;

DROP TABLE IF EXISTS `activity_logs`;
DROP TABLE IF EXISTS `payments`;
DROP TABLE IF EXISTS `bookings`;
DROP TABLE IF EXISTS `unit_games`;
DROP TABLE IF EXISTS `packages`;
DROP TABLE IF EXISTS `games`;
DROP TABLE IF EXISTS `units`;
DROP TABLE IF EXISTS `settings`;
DROP TABLE IF EXISTS `users`;

-- =====================================================================
-- 1. USERS
-- Menyimpan akun pelanggan maupun admin/operator. Pembeda hak akses
-- ada di kolom `role`. Password disimpan sebagai hash bcrypt
-- (password_hash / PASSWORD_BCRYPT), TIDAK PERNAH plaintext.
-- =====================================================================
CREATE TABLE `users` (
  `id`             INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `nama`           VARCHAR(100) NOT NULL,
  `email`          VARCHAR(150) NOT NULL,
  `telepon`        VARCHAR(20)  DEFAULT NULL,
  `password`       VARCHAR(255) NOT NULL COMMENT 'Hash bcrypt dari password_hash()',
  `role`           ENUM('pelanggan','operator','admin') NOT NULL DEFAULT 'pelanggan',
  `status`         ENUM('aktif','nonaktif') NOT NULL DEFAULT 'aktif',
  `alamat`         VARCHAR(255) DEFAULT NULL,
  `last_login_at`  DATETIME     DEFAULT NULL,
  `created_at`     DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`     DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_users_email` (`email`),
  KEY `idx_users_role_status` (`role`, `status`),
  KEY `idx_users_telepon` (`telepon`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================================
-- 2. UNITS
-- Satu baris = satu konsol/ruang bermain fisik yang bisa disewa.
-- `status` adalah status SAAT INI (cache cepat untuk katalog);
-- status sebenarnya selalu diverifikasi ulang lewat tabel bookings.
-- =====================================================================
CREATE TABLE `units` (
  `id`             INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `kode_unit`      VARCHAR(20)  NOT NULL COMMENT 'Kode unik, contoh: PS5-01',
  `nama`           VARCHAR(100) NOT NULL,
  `tipe`           ENUM('PS3','PS4','PS5') NOT NULL,
  `kategori`       ENUM('reguler','vip','ruangan') NOT NULL DEFAULT 'reguler',
  `harga_per_jam`  DECIMAL(12,2) NOT NULL,
  `kapasitas`      TINYINT UNSIGNED NOT NULL DEFAULT 2 COMMENT 'Maks pemain',
  `status`         ENUM('tersedia','dipakai','dipesan','maintenance') NOT NULL DEFAULT 'tersedia',
  `deskripsi`      VARCHAR(255) DEFAULT NULL,
  `foto_path`      VARCHAR(255) DEFAULT NULL COMMENT 'Path relatif, contoh: uploads/units/ps5-01.jpg',
  `created_at`     DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`     DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_units_kode` (`kode_unit`),
  KEY `idx_units_status` (`status`),
  KEY `idx_units_tipe_harga` (`tipe`, `harga_per_jam`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================================
-- 3. GAMES
-- Katalog game yang bisa dimainkan. Satu game dapat terpasang di
-- banyak unit melalui tabel relasi `unit_games` (many-to-many).
-- =====================================================================
CREATE TABLE `games` (
  `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `judul`       VARCHAR(150) NOT NULL,
  `platform`    ENUM('PS3','PS4','PS5','Multi') NOT NULL DEFAULT 'Multi',
  `genre`       VARCHAR(60)  NOT NULL DEFAULT 'Umum',
  `cover_path`  VARCHAR(255) DEFAULT NULL,
  `rating`      ENUM('3+','7+','12+','16+','18+') NOT NULL DEFAULT '7+' COMMENT 'Batas usia konten',
  `deskripsi`   TEXT         DEFAULT NULL,
  `status`      ENUM('aktif','nonaktif') NOT NULL DEFAULT 'aktif',
  `created_at`  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_games_judul_platform` (`judul`, `platform`),
  KEY `idx_games_platform` (`platform`),
  KEY `idx_games_genre` (`genre`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================================
-- 4. UNIT_GAMES (relasi many-to-many units <-> games)
-- =====================================================================
CREATE TABLE `unit_games` (
  `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `unit_id`     INT UNSIGNED NOT NULL,
  `game_id`     INT UNSIGNED NOT NULL,
  `created_at`  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_unit_game` (`unit_id`, `game_id`),
  KEY `idx_ug_game` (`game_id`),
  CONSTRAINT `fk_unit_games_unit` FOREIGN KEY (`unit_id`) REFERENCES `units` (`id`)
    ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_unit_games_game` FOREIGN KEY (`game_id`) REFERENCES `games` (`id`)
    ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================================
-- 5. PACKAGES (paket / promo)
-- Paket memotong total harga booking. `diskon_nilai` dipakai bila
-- `tipe_diskon` = 'nominal', dan diabaikan bila 'persen'.
-- =====================================================================
CREATE TABLE `packages` (
  `id`            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `nama`          VARCHAR(120) NOT NULL,
  `deskripsi`     VARCHAR(255) DEFAULT NULL,
  `durasi_jam`    SMALLINT UNSIGNED NOT NULL COMMENT 'Lama sewa yang termasuk paket',
  `tipe_diskon`   ENUM('persen','nominal') NOT NULL DEFAULT 'nominal',
  `diskon_persen` DECIMAL(5,2) NOT NULL DEFAULT 0.00,
  `diskon_nilai`  DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `harga`         DECIMAL(12,2) NOT NULL DEFAULT 0.00 COMMENT 'Harga akhir paket (0 = hitung dari diskon)',
  `berlaku_mulai` DATE          DEFAULT NULL,
  `berlaku_akhir` DATE          DEFAULT NULL,
  `status`        ENUM('aktif','nonaktif') NOT NULL DEFAULT 'aktif',
  `created_at`    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_packages_status_tanggal` (`status`, `berlaku_mulai`, `berlaku_akhir`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================================
-- 6. BOOKINGS (pesanan sewa)
-- Inti aplikasi. Kolom harga disimpan sebagai SNAPSHOT
-- (harga_per_jam, tarif_controller) supaya perubahan tarif unit di
-- kemudian hari tidak mengubah harga pesanan lama.
--
-- SIKLUS STATUS:
--   menunggu -> dikonfirmasi -> berlangsung -> selesai
--          \            \-> dibatalkan
--            \-> dibatalkan
-- Anti bentrok jadwal: lihat prosedur stored `sp_booking_cek_slot`
-- dan indeks `idx_bookings_cek_bentrok` di bawah.
-- =====================================================================
CREATE TABLE `bookings` (
  `id`               INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `invoice_number`   VARCHAR(30)  NOT NULL,
  `user_id`          INT UNSIGNED NOT NULL,
  `unit_id`          INT UNSIGNED NOT NULL,
  `package_id`       INT UNSIGNED DEFAULT NULL,
  `booking_date`     DATE         NOT NULL,
  `start_time`       TIME         NOT NULL,
  `end_time`         TIME         NOT NULL,
  `durasi_jam`       SMALLINT UNSIGNED NOT NULL,
  `harga_per_jam`    DECIMAL(12,2) NOT NULL COMMENT 'Snapshot tarif unit saat memesan',
  `controller_tambahan` TINYINT UNSIGNED NOT NULL DEFAULT 0,
  `tarif_controller` DECIMAL(12,2) NOT NULL DEFAULT 0.00 COMMENT 'Snapshot biaya per controller/jam',
  `biaya_controller` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `subtotal`         DECIMAL(12,2) NOT NULL,
  `diskon`           DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `total_harga`      DECIMAL(12,2) NOT NULL,
  `metode_bayar`     ENUM('transfer','bayar_di_tempat') NOT NULL DEFAULT 'transfer',
  `status_pesanan`   ENUM('menunggu','dikonfirmasi','berlangsung','selesai','dibatalkan')
                     NOT NULL DEFAULT 'menunggu',
  `status_bayar`     ENUM('belum','menunggu_verifikasi','lunas','ditolak')
                     NOT NULL DEFAULT 'belum',
  `catatan_pelanggan` VARCHAR(255) DEFAULT NULL,
  `catatan_admin`    VARCHAR(255) DEFAULT NULL,
  `konfirmasi_oleh`  INT UNSIGNED DEFAULT NULL COMMENT 'FK -> users.id (admin yang mengonfirmasi)',
  `dikonfirmasi_pada` DATETIME    DEFAULT NULL,
  `dimulai_pada`     DATETIME     DEFAULT NULL,
  `selesai_pada`     DATETIME     DEFAULT NULL,
  `dibatalkan_pada`  DATETIME     DEFAULT NULL,
  `alasan_batal`     VARCHAR(255) DEFAULT NULL,
  `created_at`       DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`       DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_bookings_invoice` (`invoice_number`),
  KEY `idx_bookings_user` (`user_id`),
  KEY `idx_bookings_tanggal_status` (`booking_date`, `status_pesanan`),
  KEY `idx_bookings_bayar` (`status_bayar`),
  -- Indeks utama untuk cek bentrok jadwal & laporan harian per unit
  KEY `idx_bookings_cek_bentrok` (`unit_id`, `booking_date`, `start_time`, `end_time`, `status_pesanan`),
  CONSTRAINT `fk_bookings_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)
    ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `fk_bookings_unit` FOREIGN KEY (`unit_id`) REFERENCES `units` (`id`)
    ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `fk_bookings_package` FOREIGN KEY (`package_id`) REFERENCES `packages` (`id`)
    ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_bookings_admin` FOREIGN KEY (`konfirmasi_oleh`) REFERENCES `users` (`id`)
    ON DELETE SET NULL ON UPDATE CASCADE,
  -- Jaga konsistensi waktu dasar (didukung MySQL 8.0.16+ & MariaDB 10.3.9+)
  CONSTRAINT `ck_bookings_durasi` CHECK (`end_time` > `start_time`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================================
-- 7. PAYMENTS
-- Satu booking boleh punya lebih dari satu riwayat percobaan bayar
-- (mis. bukti pertama ditolak lalu upload ulang).
-- =====================================================================
CREATE TABLE `payments` (
  `id`            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `booking_id`    INT UNSIGNED NOT NULL,
  `user_id`       INT UNSIGNED NOT NULL,
  `metode`        ENUM('transfer','bayar_di_tempat') NOT NULL DEFAULT 'transfer',
  `jumlah`        DECIMAL(12,2) NOT NULL,
  `nomor_referensi` VARCHAR(60)  DEFAULT NULL COMMENT 'No. ref transfer dari pelanggan',
  `bukti_path`    VARCHAR(255)   DEFAULT NULL COMMENT 'Path foto/pdf bukti transfer',
  `status`        ENUM('menunggu_verifikasi','lunas','ditolak') NOT NULL DEFAULT 'menunggu_verifikasi',
  `catatan_admin` VARCHAR(255)   DEFAULT NULL,
  `verifikasi_oleh` INT UNSIGNED DEFAULT NULL,
  `verifikasi_pada` DATETIME     DEFAULT NULL,
  `created_at`    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_payments_booking` (`booking_id`),
  KEY `idx_payments_status` (`status`),
  CONSTRAINT `fk_payments_booking` FOREIGN KEY (`booking_id`) REFERENCES `bookings` (`id`)
    ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_payments_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)
    ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `fk_payments_verifier` FOREIGN KEY (`verifikasi_oleh`) REFERENCES `users` (`id`)
    ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================================
-- 8. ACTIVITY LOGS (laporan/jejak audit)
-- Mencatat aksi penting admin maupun pelanggan untuk audit trail.
-- =====================================================================
CREATE TABLE `activity_logs` (
  `id`          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id`     INT UNSIGNED DEFAULT NULL,
  `modul`       VARCHAR(40)  NOT NULL COMMENT 'unit, game, booking, payment, paket, user, auth',
  `aksi`        VARCHAR(40)  NOT NULL COMMENT 'buat, ubah, hapus, konfirmasi, tolak, mulai, selesai, bayar, dll',
  `entitas`     VARCHAR(40)  NOT NULL,
  `entitas_id`  INT UNSIGNED DEFAULT NULL,
  `deskripsi`   VARCHAR(255) NOT NULL,
  `nilai_lama`  JSON          DEFAULT NULL,
  `nilai_baru`  JSON          DEFAULT NULL,
  `ip_address`  VARCHAR(45)   DEFAULT NULL,
  `created_at`  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_logs_tanggal` (`created_at`),
  KEY `idx_logs_modul` (`modul`, `aksi`),
  CONSTRAINT `fk_logs_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)
    ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================================
-- 9. SETTINGS (key-value)
-- Jam operasional, lokasi, rekening transfer, tarif controller, dan
-- data lain yang dibutuhkan landing page tanpa hardcode di view.
-- =====================================================================
CREATE TABLE `settings` (
  `key_name`   VARCHAR(60) NOT NULL,
  `value_text` TEXT        DEFAULT NULL,
  `tipe`       ENUM('teks','angka','paragraf') NOT NULL DEFAULT 'teks',
  `label`      VARCHAR(100) NOT NULL,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`key_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================================
-- 10. VIEWS untuk laporan (mempercepat query agregasi admin)
-- =====================================================================

-- V_LAPORAN_HARIAN: omzet & jumlah pesanan per hari (hanya pesanan lunas/selesai)
DROP VIEW IF EXISTS `v_laporan_harian`;
CREATE VIEW `v_laporan_harian` AS
SELECT
  b.booking_date                                   AS tanggal,
  COUNT(*)                                         AS jumlah_pesanan,
  SUM(b.durasi_jam)                                AS total_jam,
  SUM(b.total_harga)                               AS omzet,
  SUM(b.diskon)                                    AS total_diskon,
  SUM(b.biaya_controller)                          AS omzet_controller
FROM bookings b
WHERE b.status_pesanan IN ('dikonfirmasi','berlangsung','selesai')
  AND b.status_bayar IN ('lunas','menunggu_verifikasi')
GROUP BY b.booking_date;

-- V_LAPORAN_UNIT: kinerja penyewaan per unit
DROP VIEW IF EXISTS `v_laporan_unit`;
CREATE VIEW `v_laporan_unit` AS
SELECT
  u.id                                             AS unit_id,
  u.kode_unit,
  u.tipe,
  u.harga_per_jam,
  COUNT(b.id)                                      AS jumlah_pesanan,
  COALESCE(SUM(b.durasi_jam), 0)                   AS total_jam,
  COALESCE(SUM(b.total_harga), 0)                  AS omzet
FROM units u
LEFT JOIN bookings b
  ON b.unit_id = u.id
 AND b.status_pesanan IN ('dikonfirmasi','berlangsung','selesai')
GROUP BY u.id, u.kode_unit, u.tipe, u.harga_per_jam;

-- V_PESANAN_AKTIF: pesanan yang sedang berjalan (dipakai dashboard & monitor timer)
DROP VIEW IF EXISTS `v_pesanan_aktif`;
CREATE VIEW `v_pesanan_aktif` AS
SELECT
  b.id,
  b.invoice_number,
  u.kode_unit,
  u.tipe,
  n.nama                                           AS nama_pelanggan,
  b.booking_date,
  b.start_time,
  b.end_time,
  b.durasi_jam,
  b.total_harga,
  b.status_pesanan,
  b.status_bayar,
  TIMESTAMPDIFF(SECOND, NOW(), b.end_time)         AS sisa_detik
FROM bookings b
JOIN units u ON u.id = b.unit_id
JOIN users n ON n.id = b.user_id
WHERE b.status_pesanan = 'berlangsung';

-- =====================================================================
-- 11. STORED PROCEDURE: cek bentrok jadwal
-- Dipanggil model Booking sebelum menyimpan pesanan. Menggunakan
-- FOR UPDATE agar aman dari kondisi race (dua orang memesan slot sama).
-- Mengembalikan 1 baris bila slot SUDAH terisi.
-- =====================================================================
DROP PROCEDURE IF EXISTS `sp_booking_cek_bentrok`;
DELIMITER $$
CREATE PROCEDURE `sp_booking_cek_bentrok`(
  IN p_unit_id  INT UNSIGNED,
  IN p_tanggal  DATE,
  IN p_mulai    TIME,
  IN p_selesai  TIME,
  IN p_kecuali_id INT UNSIGNED
)
BEGIN
  SELECT id, invoice_number, start_time, end_time
    FROM bookings
   WHERE unit_id = p_unit_id
     AND booking_date = p_tanggal
     AND status_pesanan IN ('menunggu', 'dikonfirmasi', 'berlangsung')
     AND (
           (start_time <  p_selesai AND end_time > p_mulai)
        OR (start_time = p_mulai AND end_time = p_selesai)
     )
     AND (p_kecuali_id IS NULL OR id <> p_kecuali_id)
     FOR UPDATE;
END$$
DELIMITER ;

SET FOREIGN_KEY_CHECKS = 1;

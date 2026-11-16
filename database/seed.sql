-- =====================================================================
-- QENGAMESTATION — DATA AWAL (SEED)
-- Jalankan SETELAH schema.sql:
--   mysql -u root -p qengamestation < seed.sql
--
-- PENTING — PASSWORD AKUN BAWAAN
-- Semua akun di bawah memakai password:  password
-- Value kolom `password` adalah hash bcrypt (password_hash PASSWORD_BCRYPT).
-- SEGERA ganti setelah login pertama, atau buat hash baru dengan:
--   php tools/hash.php "password_anda"
-- lalu salin output-nya ke kolom `password`.
-- =====================================================================

USE `qengamestation`;

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;
TRUNCATE TABLE `activity_logs`;
TRUNCATE TABLE `payments`;
TRUNCATE TABLE `bookings`;
TRUNCATE TABLE `unit_games`;
TRUNCATE TABLE `packages`;
TRUNCATE TABLE `games`;
TRUNCATE TABLE `units`;
TRUNCATE TABLE `settings`;
TRUNCATE TABLE `users`;
SET FOREIGN_KEY_CHECKS = 1;

-- ---------------------------------------------------------------------
-- USERS: 1 admin, 1 operator, 4 pelanggan
-- ---------------------------------------------------------------------
INSERT INTO `users` (`nama`, `email`, `telepon`, `password`, `role`, `status`, `alamat`) VALUES
('Qengamestation Admin', 'admin@qengamestation.id',  '081200000001', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin',     'aktif', 'Jl. Merdeka No. 1, Bandung'),
('Rizky Operator',       'operator@qengamestation.id','081200000002', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'operator',  'aktif', 'Jl. Merdeka No. 1, Bandung'),
('Budi Santoso',         'budi@example.com',         '081311122233', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'pelanggan', 'aktif', 'Jl. Cihampelas No. 88, Bandung'),
('Siti Aminah',          'siti@example.com',         '081322233344', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'pelanggan', 'aktif', 'Jl. Dago No. 12, Bandung'),
('Andi Saputra',         'andi@example.com',         '081333344455', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'pelanggan', 'aktif', 'Jl. Buahbatu No. 45, Bandung'),
('Dewi Lestari',         'dewi@example.com',         '081344455566', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'pelanggan', 'nonaktif', 'Jl. Setiabudi No. 7, Bandung');

-- ---------------------------------------------------------------------
-- UNITS: 10 unit PlayStation
-- ---------------------------------------------------------------------
INSERT INTO `units` (`kode_unit`, `nama`, `tipe`, `kategori`, `harga_per_jam`, `kapasitas`, `status`, `deskripsi`) VALUES
('PS5-01', 'PS5 Slot 1 — Couch VIP',   'PS5', 'vip',     20000.00, 2, 'tersedia',    'DualSense, TV 55 inch 4K, ruang kedap suara.'),
('PS5-02', 'PS5 Slot 2 — Couch VIP',   'PS5', 'vip',     20000.00, 2, 'dipakai',     'DualSense, TV 55 inch 4K, ruang kedap suara.'),
('PS5-03', 'PS5 Slot 3 — Reguler',     'PS5', 'reguler', 17000.00, 2, 'tersedia',    'DualSense, TV 43 inch 4K.'),
('PS4-01', 'PS4 Slot 1 — Reguler',     'PS4', 'reguler', 12000.00, 2, 'tersedia',    'DualShock 4, TV 43 inch Full HD.'),
('PS4-02', 'PS4 Slot 2 — Reguler',     'PS4', 'reguler', 12000.00, 2, 'dipesan',     'DualShock 4, TV 43 inch Full HD.'),
('PS4-03', 'PS4 Slot 3 — Bareng 4',    'PS4', 'ruangan', 30000.00, 4, 'tersedia',    'Ruang rombongan, 2 console, 4 controller.'),
('PS4-04', 'PS4 Slot 4 — Reguler',     'PS4', 'reguler', 12000.00, 2, 'maintenance', 'Kipas perlu dibersihkan, sedang service.'),
('PS3-01', 'PS3 Slot 1 — Klasik',      'PS3', 'reguler', 8000.00,  2, 'tersedia',    'Pilihan game klasik, TV 32 inch.'),
('PS3-02', 'PS3 Slot 2 — Klasik',      'PS3', 'reguler', 8000.00,  2, 'tersedia',    'Pilihan game klasik, TV 32 inch.'),
('PS3-03', 'PS3 Slot 3 — Hemat',       'PS3', 'reguler', 7000.00,  1, 'tersedia',    'Slot single player, harga pelajar.');

-- ---------------------------------------------------------------------
-- GAMES: katalog game
-- ---------------------------------------------------------------------
INSERT INTO `games` (`judul`, `platform`, `genre`, `rating`, `deskripsi`, `status`) VALUES
('EA FC 25',              'PS5',   'Olahraga',      '3+',  'Sepak bola terbaru, mendukung local co-op 2 pemain.', 'aktif'),
('God of War Ragnarok',   'PS5',   'Action',        '18+', 'Petualangan Kratos dan Atreus.',                'aktif'),
('Spider-Man 2',          'PS5',   'Action',        '12+', 'Open world New York bersama dua Spider-Man.',  'aktif'),
('Gran Turismo 7',        'PS5',   'Racing',        '3+',  'Simulator balap paling realistis.',            'aktif'),
('Ratchet & Clank Rift',  'PS5',   'Platformer',    '7+',  'Co-op seru dua pemain.',                       'aktif'),
('Mortal Kombat 1',       'PS5',   'Fighting',      '18+', 'Duel satu lawan satu.',                        'aktif'),
('FIFA 23',               'PS4',   'Olahraga',      '3+',  'Pilihan populer untuk main bareng.',           'aktif'),
('Tekken 8',              'PS4',   'Fighting',      '12+', 'Turnamen fighting favorit rental.',            'aktif'),
('Uncharted 4',           'PS4',   'Action',        '16+', 'Petualangan Nathan Drake.',                    'aktif'),
('Need for Speed Heat',   'PS4',   'Racing',        '7+',  'Balap liar malam hari.',                       'aktif'),
('Crash Bandicoot 4',     'PS4',   'Platformer',    '7+',  'Platformer kasual semua umur.',                'aktif'),
('Naruto Ultimate Ninja 5','PS3',  'Fighting',      '12+', 'Game klasik PS3 yang masih dicari.',           'aktif'),
('Winning Eleven 2020',   'PS3',   'Olahraga',      '3+',  'Pilihan hemat untuk main berdua.',             'aktif'),
('GTA V',                 'PS4',   'Action',        '18+', 'Open world, tersedia mode cerita co-op.',      'nonaktif');

-- ---------------------------------------------------------------------
-- UNIT_GAMES: game yang terpasang di tiap unit
-- (id unit: 1..10 sesuai urutan insert di atas; id game: 1..14)
-- ---------------------------------------------------------------------
INSERT INTO `unit_games` (`unit_id`, `game_id`) VALUES
(1,1),(1,2),(1,3),(1,4),(1,5),(1,6),
(2,1),(2,3),(2,4),(2,6),(2,8),
(3,1),(3,2),(3,4),(3,5),
(4,7),(4,8),(4,9),(4,10),(4,11),
(5,7),(5,8),(5,9),(5,11),
(6,7),(6,8),(6,10),(6,11),
(7,7),(7,9),
(8,12),(8,13),(8,7),
(9,12),(9,13),
(10,12),(10,13);

-- ---------------------------------------------------------------------
-- PACKAGES: paket & promo
-- ---------------------------------------------------------------------
INSERT INTO `packages` (`nama`, `deskripsi`, `durasi_jam`, `tipe_diskon`, `diskon_persen`, `diskon_nilai`, `harga`, `berlaku_mulai`, `berlaku_akhir`, `status`) VALUES
('Paket 3 Jam Santai',  'Main 3 jam penuh, hemat untuk bermain sendiri atau berdua.', 3, 'persen', 10.00, 0.00, 0.00, '2026-01-01', '2026-12-31', 'aktif'),
('Paket 5 Jam Marathon','Cocok untuk sesi mabar panjang di akhir pekan.',              5, 'persen', 15.00, 0.00, 0.00, '2026-01-01', '2026-12-31', 'aktif'),
('Paket Harian 8 Jam',  'Sewa seharian, termasuk 1 controller tambahan.',              8, 'nominal', 0.00, 25000.00, 0.00, '2026-01-01', '2026-12-31', 'aktif'),
('Promo Pelajar Weekday','Khusus Senin–Jumat sebelum jam 15.00, tunjukkan kartu pelajar.', 2, 'persen', 20.00, 0.00, 0.00, '2026-01-01', '2026-06-30', 'aktif'),
('Promo Subuh Begadang',   'Diskon dini hari untuk sesi begadang (belum dipublikasikan).',     4, 'persen', 25.00, 0.00, 0.00, '2026-01-01', '2026-12-31', 'nonaktif');

-- ---------------------------------------------------------------------
-- SETTINGS: data operasional untuk landing page & perhitungan biaya
-- ---------------------------------------------------------------------
INSERT INTO `settings` (`key_name`, `value_text`, `tipe`, `label`) VALUES
('nama_rental',        'Qengamestation', 'teks', 'Nama rental'),
('tagline',            'Sewa PS5, PS4, dan PS3 termurah dan paling nyaman di Bandung. Main santai, bayar hemat.', 'paragraf', 'Tagline landing page'),
('alamat',             'Jl. Merdeka No. 1, Bandung, Jawa Barat 40111', 'paragraf', 'Alamat lokasi'),
('telepon',            '0812-0000-0001', 'teks', 'Nomor telepon / WhatsApp'),
('email',              'halo@qengamestation.id', 'teks', 'Email kontak'),
('jam_hari_biasa',     'Senin - Jumat: 10.00 - 23.00 WIB', 'teks', 'Jam operasional hari biasa'),
('jam_akhir_pekan',    'Sabtu - Minggu & Libur: 09.00 - 24.00 WIB', 'teks', 'Jam operasional akhir pekan'),
('jam_operasional_buka',  '10', 'angka', 'Jam buka paling awal (format 24 jam)'),
('jam_operasional_tutup', '24', 'angka', 'Jam tutup paling akhir (24 = tengah malam)'),
('rekening_bank',      'BCA 1234567890 a.n. Qengamestation', 'teks', 'Nomor rekening transfer'),
('tarif_controller',   '3000', 'angka', 'Biaya controller tambahan per jam (Rp)'),
('batas_menit_batal',  '60', 'angka', 'Batas menit pembatalan sebelum jadwal'),
('deskripsi_tempat',   'Ruang ber-AC, kursi gaming, area tunggu dengan colokan charger, dan gratis minum untuk paket VIP.', 'paragraf', 'Deskripsi fasilitas'),
('peta_url',           'https://maps.google.com/?q=Bandung', 'teks', 'Tautan peta lokasi');

-- ---------------------------------------------------------------------
-- BOOKINGS: contoh transaksi untuk mengisi dashboard & laporan
-- ---------------------------------------------------------------------
INSERT INTO `bookings`
(`invoice_number`, `user_id`, `unit_id`, `package_id`, `booking_date`, `start_time`, `end_time`, `durasi_jam`,
 `harga_per_jam`, `controller_tambahan`, `tarif_controller`, `biaya_controller`, `subtotal`, `diskon`, `total_harga`,
 `metode_bayar`, `status_pesanan`, `status_bayar`, `catatan_pelanggan`, `dikonfirmasi_pada`, `dimulai_pada`, `selesai_pada`, `created_at`) VALUES
-- Selesai + lunas (hari ini)
('QS-20261116-0001', 3, 4, NULL, CURDATE(), '10:00:00', '12:00:00', 2, 12000, 1, 3000, 6000, 30000, 0, 30000,
 'transfer', 'selesai', 'lunas', 'Tolong siapkan FIFA 23.', NOW() - INTERVAL 6 HOUR, NOW() - INTERVAL 5 HOUR, NOW() - INTERVAL 3 HOUR, NOW() - INTERVAL 7 HOUR),
('QS-20261116-0002', 4, 1, 1,    CURDATE(), '13:00:00', '16:00:00', 3, 20000, 0, 3000, 0, 60000, 6000, 54000,
 'bayar_di_tempat', 'selesai', 'lunas', NULL, NOW() - INTERVAL 4 HOUR, NOW() - INTERVAL 3 HOUR, NOW() - INTERVAL 1 HOUR, NOW() - INTERVAL 5 HOUR),
-- Sedang berlangsung (dipakai monitor/timer)
('QS-20261116-0003', 5, 2, NULL, CURDATE(), '19:00:00', '22:00:00', 3, 20000, 2, 3000, 18000, 78000, 0, 78000,
 'transfer', 'berlangsung', 'lunas', 'Mortal Kombat 1 ya kak.', NOW() - INTERVAL 2 HOUR, NOW() - INTERVAL 1 HOUR, NULL, NOW() - INTERVAL 3 HOUR),
('QS-20261116-0004', 3, 5, NULL, CURDATE(), '20:00:00', '21:30:00', 2, 12000, 0, 3000, 0, 24000, 0, 24000,
 'bayar_di_tempat', 'dikonfirmasi', 'belum', 'Datang terlambat 10 menit.', NOW() - INTERVAL 1 HOUR, NULL, NULL, NOW() - INTERVAL 90 MINUTE),
-- Menunggu verifikasi pembayaran
('QS-20261116-0005', 4, 3, 2,    CURDATE() + INTERVAL 1 DAY, '15:00:00', '20:00:00', 5, 17000, 1, 3000, 15000, 100000, 15000, 100000,
 'transfer', 'menunggu', 'menunggu_verifikasi', 'Transfer sebelum jam 3 sore.', NULL, NULL, NULL, NOW() - INTERVAL 30 MINUTE),
-- Riwayat hari-hari sebelumnya untuk grafik laporan
('QS-20261115-0001', 5, 6, NULL, CURDATE() - INTERVAL 1 DAY, '14:00:00', '18:00:00', 4, 30000, 0, 3000, 0, 120000, 0, 120000,
 'transfer', 'selesai', 'lunas', NULL, NOW() - INTERVAL 30 DAY, NOW() - INTERVAL 29 DAY, NOW() - INTERVAL 25 DAY, NOW() - INTERVAL 31 DAY),
('QS-20261114-0001', 3, 8, NULL, CURDATE() - INTERVAL 2 DAY, '16:00:00', '18:00:00', 2, 8000, 1, 3000, 6000, 22000, 0, 22000,
 'bayar_di_tempat', 'selesai', 'lunas', NULL, NOW() - INTERVAL 50 DAY, NOW() - INTERVAL 49 DAY, NOW() - INTERVAL 47 DAY, NOW() - INTERVAL 50 DAY),
('QS-20261113-0001', 4, 1, 1,    CURDATE() - INTERVAL 3 DAY, '18:00:00', '21:00:00', 3, 20000, 0, 3000, 0, 60000, 6000, 54000,
 'transfer', 'selesai', 'lunas', NULL, NOW() - INTERVAL 74 DAY, NOW() - INTERVAL 73 DAY, NOW() - INTERVAL 70 DAY, NOW() - INTERVAL 74 DAY),
('QS-20261112-0001', 5, 4, NULL, CURDATE() - INTERVAL 4 DAY, '13:00:00', '15:00:00', 2, 12000, 0, 3000, 0, 24000, 0, 24000,
 'transfer', 'dibatalkan', 'belum', NULL, NULL, NULL, NULL, NOW() - INTERVAL 98 HOUR);

UPDATE `bookings` SET `konfirmasi_oleh` = 1 WHERE `status_pesanan` IN ('dikonfirmasi','berlangsung','selesai');
UPDATE `bookings` SET `alasan_batal` = 'Pelanggan berubah rencana' WHERE `status_pesanan` = 'dibatalkan';
UPDATE `bookings` SET `dibatalkan_pada` = NOW() - INTERVAL 90 HOUR WHERE `status_pesanan` = 'dibatalkan';

-- ---------------------------------------------------------------------
-- PAYMENTS: bukti & verifikasi pembayaran
-- ---------------------------------------------------------------------
INSERT INTO `payments` (`booking_id`, `user_id`, `metode`, `jumlah`, `nomor_referensi`, `bukti_path`, `status`, `catatan_admin`, `verifikasi_oleh`, `verifikasi_pada`, `created_at`) VALUES
(1, 3, 'transfer', 30000, 'TRX-8891-AA', NULL, 'lunas', 'Sesuai nominal.', 1, NOW() - INTERVAL 6 HOUR + INTERVAL 10 MINUTE, NOW() - INTERVAL 6 HOUR + INTERVAL 5 MINUTE),
(3, 5, 'transfer', 78000, 'TRX-1200-BB', NULL, 'lunas', 'Sesuai nominal.', 1, NOW() - INTERVAL 2 HOUR, NOW() - INTERVAL 2 HOUR + INTERVAL 5 MINUTE),
(5, 4, 'transfer', 100000, 'TRX-5566-CC', NULL, 'menunggu_verifikasi', NULL, NULL, NULL, NOW() - INTERVAL 25 MINUTE),
(4, 4, 'transfer', 54000, 'TRX-0000-DD', NULL, 'ditolak', 'Nominal kurang Rp14.000, silakan upload ulang.', 2, NOW() - INTERVAL 3 DAY, NOW() - INTERVAL 3 DAY + INTERVAL 15 MINUTE);

-- ---------------------------------------------------------------------
-- ACTIVITY LOGS: contoh jejak audit
-- ---------------------------------------------------------------------
INSERT INTO `activity_logs` (`user_id`, `modul`, `aksi`, `entitas`, `entitas_id`, `deskripsi`, `ip_address`) VALUES
(1, 'auth',    'login',      'users',    1, 'Admin masuk ke sistem.', '127.0.0.1'),
(1, 'unit',    'buat',       'units',    1, 'Menambah unit PS5-01.', '127.0.0.1'),
(1, 'booking', 'konfirmasi', 'bookings', 1, 'Mengonfirmasi pesanan QS-20261116-0001.', '127.0.0.1'),
(2, 'payment', 'tolak',      'payments', 4, 'Menolak bukti transfer QS-20261114-0001: nominal kurang.', '127.0.0.1'),
(3, 'booking', 'buat',       'bookings', 1, 'Pelanggan membuat pesanan QS-20261116-0001.', '127.0.0.1');

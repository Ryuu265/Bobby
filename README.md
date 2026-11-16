# Qengamestation — Sistem Informasi Penyewaan & Pemesanan PlayStation

Aplikasi web untuk rental PlayStation **Qengamestation**: pelanggan dapat melihat katalog
unit dan game, memeriksa ketersediaan jadwal secara langsung, melakukan pemesanan,
mengunggah bukti transfer, dan memantau status pesanannya. Admin/operator mengelola data
master, memverifikasi pembayaran, menjalankan sesi sewa, serta membaca laporan pendapatan.

Seluruh antarmuka berbahasa Indonesia. Kode tanpa framework dan tanpa dependensi Composer.

---

## 1. Teknologi

| Lapisan | Pilihan | Keterangan |
|---|---|---|
| Backend | **PHP Native 8.1+** | Front controller tunggal + router/model/view buatan sendiri (bukan Laravel) |
| Database | **MySQL / MariaDB** (InnoDB) | 9 tabel, 3 view, 1 stored procedure |
| Frontend | HTML + **Tailwind CSS (Play CDN)** + JavaScript vanilla | Tema dark neon soft, token CSS sebagai sumber kebenaran |
| Autentikasi | Session + `password_hash()` bcrypt | Validasi input terpusat, token CSRF per sesi |

Konsistensi pilihan: **PHP Native + MySQL** dipakai di seluruh proyek, tidak ada campuran
Laravel/React.

---

## 2. Struktur folder

```
qengamestation/
├── .env.example              Contoh konfigurasi lingkungan (salin menjadi .env)
├── README.md                 Berkas ini
├── app/
│   ├── bootstrap.php         Autoloader PSR-4, pembaca .env, zona waktu, session, folder upload
│   ├── config/config.php     Seluruh nilai konfigurasi (dapat ditimpa .env)
│   ├── Core/                 Kerangka aplikasi
│   │   ├── Auth.php          Login/logout, verifikasi bcrypt, rehash otomatis
│   │   ├── Controller.php    Basis view, validasi, JSON, proteksi kepemilikan
│   │   ├── Csrf.php          Token + verifikasi hash_equals()
│   │   ├── Database.php      PDO singleton, transaksi, prepared statement
│   │   ├── Error.php         Halaman 400/403/404/500 + logger
│   │   ├── Exporter.php      Unduhan CSV (BOM UTF-8, anti formula injection)
│   │   ├── Logger.php        Jejak aktivitas ke tabel activity_logs
│   │   ├── Middleware.php    Gerbang tamu / auth / admin
│   │   ├── Model.php         Basis CRUD, $fillable, pagination aman
│   │   ├── Pricing.php       Rumus total harga + diskon paket
│   │   ├── Request.php       Input, file upload, URI, method override
│   │   ├── Router.php        Rute bernama, parameter {id}, group + prefix
│   │   ├── Schedule.php      Slot jam, deteksi bentrok (FOR UPDATE), status unit real-time
│   │   ├── Uploader.php      sniff MIME (finfo), nama acak, batas ukuran
│   │   ├── Validator.php     Aturan required/email/unique/dates/numeric/dll.
│   │   ├── View.php          Render layout + section + shared data
│   │   └── helpers.php       e(), url(), route(), rupiah(), jam(), status_label(), flash(), dll.
│   ├── Models/               User, Unit, Game, UnitGame, Package, Booking, Payment,
│   │                         ActivityLog, Setting
│   ├── Controllers/          Public (Home, Catalog, Booking, Payment, History, Account, Auth)
│   │   └── Admin/            Dashboard, Monitor, Unit, Game, Package, Booking, Payment,
│   │                         Customer, Report, Setting
│   └── Views/
│       ├── layouts/          app.php (publik/pelanggan), admin.php (dashboard)
│       ├── partials/         navbar, sidebar, footer, flash, pagination
│       ├── pages/            landing, auth/, katalog/, booking/, riwayat/, profil/, error
│       └── admin/            dashboard/, monitoring/, unit/, game/, paket/, pesanan/,
│                             pembayaran/, pelanggan/, laporan/, pengaturan/, _partial/
├── database/
│   ├── schema.sql            Create database + 9 tabel + 3 view + 1 procedure
│   ├── seed.sql              Data contoh: 6 akun, 10 unit, 14 game, 5 paket, 9 pesanan
│   └── ERD.md                Diagram relasi, kardinalitas, ON DELETE/UPDATE, kamus ENUM
├── public/                   *Document root web server*
│   ├── index.php             Front controller
│   ├── .htaccess             Rewrite Apache + header keamanan
│   ├── assets/css/           tokens.css (design token), app.css (komponen)
│   ├── assets/js/            tailwind.config.js, app.js, booking.js, dashboard.js
│   ├── assets/img/           favicon.svg, placeholder-unit.svg
│   └── uploads/              units/, games/, payments/ (dilindungi .htaccess penolak PHP)
├── routes/web.php            65 rute / 42 rute bernama
├── storage/logs/             php-error.log (dibuat otomatis saat APP_DEBUG=false)
└── tools/
    ├── hash.php              Pembuat hash bcrypt: php tools/hash.php "password"
    └── cek-konsistensi.js    Pemeriksa statis lintas file (Node, tanpa PHP)
```

---

## 3. Instalasi & menjalankan

### 3.1 Syarat

- PHP **8.1 or newer** dengan ekstensi `pdo_mysql`, `mbstring`, `fileinfo`, `json`.
- MySQL **5.7+** atau MariaDB **10.4+**.
- Apache + `mod_rewrite` (XAMPP/Laragon) **atau** PHP built-in server untuk pengembangan.

### 3.2 Cara A — XAMPP / Laragon (Apache)

1. Salin folder `qengamestation` ke `C:\xampp\htdocs\` (atau `laragon\www\`).
2. Buat file konfigurasi:
   ```
   copy .env.example .env
   ```
   Sesuaikan `DB_NAME`, `DB_USER`, `DB_PASS`, dan **`APP_URL`**.
3. Import database lewat phpMyAdmin (urutan penting):
   - `database/schema.sql`
   - `database/seed.sql`

   Atau lewat terminal:
   ```
   mysql -u root -p < database/schema.sql
   mysql -u root -p < database/seed.sql
   ```
4. Pastikan `mod_rewrite` aktif dan `AllowOverride All` berlaku untuk folder tersebut.
   Document root ideal adalah `qengamestation/public`; bila tidak mungkin (htdocs biasa),
   biarkan `APP_URL` menunjuk ke `.../qengamestation/public` seperti nilai bawaan.
5. Buka **http://localhost/qengamestation/public**

### 3.3 Cara B — Server bawaan PHP (pengembangan tanpa Apache)

```
cd qengamestation/public
php -S localhost:8000 index.php
```

Lalu set di `.env`:

```
APP_URL=http://localhost:8000
```

Aset statis (`.css`, `.js`, `.svg`, hasil upload) dilayani otomatis oleh server bawaan PHP;
semua URL aplikasi diteruskan ke `index.php`.

### 3.4 Izin tulis folder upload

`app/bootstrap.php` otomatis membuat `public/uploads/{units,games,payments}` dan
`storage/logs`. Pada Linux/Mac berikan izin tulis:

```
chmod -R 775 public/uploads storage
```

### 3.5 Akun bawaan (dari `seed.sql`)

| Peran | Email | Password |
|---|---|---|
| Admin | `admin@qengamestation.id` | `password` |
| Operator | `operator@qengamestation.id` | `password` |
| Pelanggan | `budi@example.com` | `password` |
| Pelanggan | `siti@example.com` | `password` |
| Pelanggan (nonaktif) | `dewi@example.com` | `password` |

> **Segera ganti password ini di instalasi nyata.** Buat hash baru dengan
> `php tools/hash.php "PasswordBaru"` lalu tempel ke kolom `password`, atau ubah lewat
> halaman **Profil** / **Admin → Pelanggan → Atur ulang password**.

### 3.6 Pengaturan operasional (tabel `settings`)

Nilai ini dibaca langsung oleh halaman publik & mesin perhitungan, diubah dari
**Admin → Pengaturan** (khusus role `admin`):

| Kunci | Arti | Bawaan |
|---|---|---|
| `jam_operasional_buka` / `_tutup` | Batas slot jam pemesanan (24 = tengah malam) | 10 / 24 |
| `tarif_controller` | Biaya controller tambahan per jam | 3000 |
| `batas_menit_batal` | Batas menit pembatalan sebelum jadwal | 60 |
| `rekening_bank` | Nomor rekening di halaman pembayaran | BCA 1234567890 |
| `nama_rental`, `tagline`, `alamat`, `telepon`, `email`, `jam_hari_biasa`, `jam_akhir_pekan`, `deskripsi_tempat`, `peta_url` | Konten landing page | lihat `seed.sql` |

---

## 4. Halaman & rute

### Publik & pelanggan

| URL | Nama rute | Isi |
|---|---|---|
| `/` | `beranda` | Hero, keunggulan, paket, harga, jam operasional, lokasi |
| `/katalog` | `katalog` | Katalog unit (filter tipe/status) + badge status real-time |
| `/katalog/unit/{kode}` | `katalog.unit` | Detail unit, daftar game, grid ketersediaan |
| `/katalog/game` | `katalog.game` | Katalog game (cari, filter genre/platform) |
| `/katalog/game/{id}` | `katalog.game.detail` | Detail game + unit yang menyediakannya |
| `/jadwal/{unit_id}` | `jadwal` | JSON slot jam (dipakai AJAX form booking) |
| `/harga` | `kalkulasi` | JSON perkiraan total harga |
| `/daftar`, `/masuk` | `register.form`, `login.form` | Registrasi & login (validasi + CSRF) |
| `/booking` | `booking.form` | Form pemesanan: unit, tanggal, slot, durasi, controller, paket, metode bayar |
| `/booking/{id}` | `booking.detail` | Detail pesanan + unggah bukti transfer |
| `/riwayat` | `riwayat` | Riwayat & status pesanan, pembatalan |
| `/profil` | `profil` | Ubah identitas & password |

### Admin / operator (`/admin`, middleware `admin`)

`dashboard` · `monitoring` (+ `/monitoring/data` JSON) · `unit` (CRUD + ubah status) ·
`game` (CRUD + sinkron unit) · `paket` (CRUD) · `pesanan` (daftar, detail, konfirmasi/mulai/selesai/batal, catatan) ·
`pembayaran` (verifikasi bukti) · `pelanggan` (daftar, detail, aktif/nonaktif, reset password) ·
`laporan` (`harian`, `bulanan`, `unit`, `aktivitas`, `ekspor` CSV, `cetak` PDF) · `pengaturan` (khusus admin).

Status pesanan: **Menunggu → Dikonfirmasi → Berlangsung → Selesai**, bercabang ke
**Dibatalkan**. Transisi dijaga `Booking::bolehTransisi()`; pesanan `menunggu` yang lewat
jadwal dibatalkan otomatis, sesi `berlangsung` yang habis jam ditutup otomatis
(`Booking::kedaluwarsaOtomatis()` / `selesaikanOtomatis()` dipanggil di dashboard & monitoring).

---

## 5. Design system

- **`public/assets/css/tokens.css`** — satu-satunya sumber warna, tipografi, radius,
  spasi, durasi, dan bayangan.
- **`public/assets/css/app.css`** — kelas komponen (`.qs-card`, `.qs-panel`, `.btn`,
  `.badge`, `.field`, `.table`, `.slot`, `.bar`, `.timer`, `.side-link`, `.stat`, `.modal`).
- **`public/assets/js/tailwind.config.js`** — memetakan token ke Tailwind lewat
  `rgb(var(--x-rgb) / <alpha-value>)` sehingga modifier `/50` tetap konsisten.

Aturan tema yang dipatuhi seluruh halaman:

| Aspek | Nilai |
|---|---|
| Latar | `#0F1117` (page), `#151823` (alternatif) |
| Permukaan kartu | `#1B1F2E` + border `rgba(255,255,255,.06)` |
| Aksen neon (desaturasi) | cyan `#4FD1E0`, violet `#8B7CF6`, pink `#E879A8` (pink hanya secukupnya) |
| Teks | utama `#E6E8EF`, sekunder `#9AA0B4` — **tanpa putih murni** |
| Glow | opacity maksimum **0.20–0.25**, blur kecil, hanya tombol primer / kartu hover / elemen aktif |
| Tipografi | Inter (body) + Orbitron/Rajdhani (judul besar saja) |
| Radius / spasi / transisi | 12–16 px, skala 4 px, 200–300 ms |
| Aksesibilitas | kontras minimum WCAG AA (rasio dicatat di `tokens.css`), `:focus-visible` jelas, `.skip-link`, `prefers-reduced-motion`, tanpa animasi berkedip |

---

## 6. Database

Lihat **`database/ERD.md`** untuk diagram crow's-foot lengkap dan tabel relasi.

| Tabel | Fungsi | Kunci |
|---|---|---|
| `users` | Akun pelanggan/admin/operator | PK `id`; `email` UNIQUE; `role`, `status` ENUM; `password` bcrypt |
| `units` | Konsol/ruang sewa | PK `id`; `kode_unit` UNIQUE; FK tak langsung ke tarif & status |
| `games` | Katalog game | PK `id`; `judul`, `platform`, `genre`, `status` |
| `unit_games` | Relasi many-to-many unit ⇄ game | PK komposit (`unit_id`,`game_id`); FK `CASCADE` |
| `packages` | Paket/promo diskon | PK `id`; `tipe_diskon`, `diskon_persen/nilai`, jendela `berlaku_*` |
| `bookings` | Pesanan sewa | PK `id`; `invoice_number` UNIQUE; FK `user_id` `RESTRICT`, `unit_id` `RESTRICT`, `package_id` `SET NULL`; CHECK `end_time > start_time`; snapshot harga |
| `payments` | Bukti & verifikasi pembayaran | PK `id`; FK `booking_id` `CASCADE`, `user_id` `CASCADE`, `verifikasi_oleh` `SET NULL` |
| `activity_logs` | Jejak audit + login | PK `id`; index `modul`, `created_at`; IP & diff nilai |
| `settings` | Konfigurasi key-value | PK `id`; `key_name` UNIQUE; `tipe` untuk merender form |

View: `v_laporan_harian`, `v_laporan_unit`, `v_pesanan_aktif`.
Procedure: `sp_booking_cek_bentrok(p_unit_id, p_tanggal, p_mulai, p_selesai, p_kecuali_id)`
— versi SQL mentah dari pemeriksaan bentrok jadwal (`SELECT ... FOR UPDATE`).

Dua keputusan desain yang perlu diketahui:

1. **Snapshot harga.** Kolom `harga_per_jam`, `tarif_controller`, `subtotal`, `diskon`,
   `total_harga` pada `bookings` adalah salinan saat memesan. Mengubah tarif unit tidak
   pernah menulis ulang riwayat.
2. **Status unit dihitung ulang.** Kolom `units.status` hanya menyatakan kondisi
   operasional manual (`aktif`/`maintenance`). Badge `Tersedia/Dipakai/Dipesan` dihitung
   dari pesanan nyata oleh `Schedule::statusUnit()`.

---

## 7. Keamanan yang sudah diterapkan

- Password bcrypt (`password_hash`, cost 10) + `password_verify`, waktu respons disamakan
  saat email tidak ditemukan, dan rehash otomatis bila biaya berubah.
- Token CSRF per sesi diverifikasi `hash_equals()` pada **semua** POST/PUT/DELETE
  (`Router` memasang pemeriksaan otomatis).
- PDO prepared statement di seluruh query (`ATTR_EMULATE_PREPARES = false`); urutan
  `ORDER BY` disaring whitelist (`Model::safeOrder()`).
- Escape HTML lewat `e()` di semua view; atribut URL hasil upload ikut di-escape.
- Validasi sisi server untuk setiap formulir (required, email, unique, rentang tanggal,
  durasi, nominal, ukuran & MIME file) memakai `App\Core\Validator`.
- Upload: sniff `finfo`, daftar ekstensi whitelist, nama file acak, folder `uploads/`
  diblokir dari eksekusi PHP oleh `public/uploads/.htaccess`.
- Header `X-Content-Type-Options`, `X-Frame-Options`, `Referrer-Policy`; cookie sesi
  `HttpOnly` + `SameSite=Lax` (+ `Secure` lewat `SESSION_SECURE`); id sesi diganti saat login.
- Proteksi kepemilikan: `Controller::authorizeOwner()` menjamin pelanggan hanya membuka
  pesanannya sendiri; area admin dijaga middleware `admin`; reset password/ubah status
  ditolak untuk akun non-pelanggan sehingga operator tidak bisa naik hak akses.
- Ekspor CSV dibersihkan dari formula injection (`=`, `+`, `-`, `@` di awal sel).

---

## 8. Verifikasi kode

PHP tidak terpasang di mesin tempat proyek ini ditulis, jadi disediakan pemeriksa statis
berbasis Node yang membaca seluruh file dan memastikan sambungan antar lapisan konsisten:

```
node tools/cek-konsistensi.js
```

Yang diperiksa: setiap rute menunjuk metode controller yang ada; setiap nama `route()`
terdaftar; setiap view yang dirender benar-benar ada; metode model yang dipanggil view
terdefinisi; tidak ada literal warna hex di markup (harus lewat token); kurung `{}` seimbang.
Keluaran saat bersih:

```
Rute terdeteksi: 65, nama rute: 42
Controller: 17, View: 41, Model: 9
OK - tidak ada inkonsistensi yang terdeteksi.
```

**Catatan penting:** pemeriksaan ini bersifat statis. Sebelum dipakai nyata, jalankan
minimal satu siklus manual: `daftar → masuk → pesan → konfirmasi admin → mulai → selesai →
verifikasi pembayaran → ekspor CSV`.

---

## 9. Pemecahan masalah

| Gejala | Penyebab & solusi |
|---|---|
| Semua URL kecuali beranda 404 | `mod_rewrite` mati / `AllowOverride None`. Aktifkan rewrite atau pakai `php -S ... index.php` di folder `public`. |
| Aset (CSS/JS) tidak termuat / tampilan polos | `APP_URL` tidak cocok dengan alamat akses. Isi harus sama persis, termasuk `/public` bila document root belum dipindah. |
| `SQLSTATE[HY000] [2002]` | Kredensial `DB_*` di `.env` salah atau MySQL belum berjalan. |
| `There is no such table` | `schema.sql`/`seed.sql` belum diimport, atau `DB_NAME` berbeda dari nama database hasil import (`qengamestation`). |
| Gagal unggah bukti transfer | Folder `public/uploads` tidak dapat ditulis (lihat 3.4), atau file melebihi 2 MB / bukan jpg-png-webp-pdf. |
| Login gagal padahal password benar | Baris akun dinonaktifkan (`users.status = nonaktif`), atau hash belum diganti dari bawaan. |
| Slot jam kosong di form booking | Tanggal terpilih di luar rentang H+14, atau seluruh slot hari itu sudah terisi/terlewat. |

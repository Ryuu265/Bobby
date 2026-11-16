# ERD — Qengamestation

Rancangan relasi entitas (Database: `qengamestation`, MySQL/MariaDB InnoDB).

## 1. Diagram Ringkas (Crow's Foot)

```
                         ┌────────────────────┐
                         │      USERS         │
                         ├────────────────────┤
                         │ PK id              │
                         │    nama            │
                         │ UK email           │
                         │    telepon         │
                         │    password (hash) │
                         │    role enum       │
                         │    status enum     │
                         │    alamat          │
                         │    last_login_at   │
                         └─────────┬──────────┘
              1                    │                    1
        ┌──────────────────────────┼──────────────────────────┐
        │ N                        │ N                        │ N
┌───────┴────────┐        ┌────────┴─────────┐       ┌────────┴────────┐
│    BOOKINGS    │        │     PAYMENTS     │       │  ACTIVITY_LOGS  │
├────────────────┤        ├──────────────────┤       ├─────────────────┤
│ PK id          │        │ PK id            │       │ PK id           │
│ UK invoice_no  │◀───┐   │ FK booking_id    │──1:N──│ FK user_id NULL │
│ FK user_id     │    │   │ FK user_id       │       │    modul, aksi  │
│ FK unit_id     │    │   │    metode enum   │       │    entitas/id   │
│ FK package_id  │    │   │    jumlah        │       │    nilai_lama   │
│ FK konfirmasi_ │    │   │    nomor_ref     │       │    nilai_baru   │
│   oleh (users) │    │   │    bukti_path    │       │    ip_address   │
│    booking_date│    │   │    status enum   │       │    created_at   │
│    start_time  │    │   │ FK verifikasi_   │       └─────────────────┘
│    end_time    │    │   │   oleh (users)   │
│    durasi_jam  │    │   └──────────────────┘
│    harga_per_jam   │
│    controller_*│    │
│    subtotal    │    │
│    diskon      │    │
│    total_harga │    │
│    metode_bayar│    │
│    status_pesanan │ │
│    status_bayar│    │
└───────┬────────┘    │
        │ N           │ 1
        │             └── PAYMENTS.booking_id
        │
┌───────┴────────┐       ┌──────────────────┐        ┌──────────────────┐
│     UNITS      │       │   UNIT_GAMES     │        │      GAMES       │
├────────────────┤       ├──────────────────┤        ├──────────────────┤
│ PK id          │──1:N──│ PK id            │──N:1──▶│ PK id            │
│ UK kode_unit   │       │ FK unit_id       │        │    judul         │
│    nama        │       │ FK game_id       │        │ UK(judul,platform)
│    tipe enum   │       │    (uq unit,game)│        │    platform enum │
│    kategori    │       └──────────────────┘        │    genre         │
│    harga_per_jam│                                  │    cover_path    │
│    kapasitas   │       ┌──────────────────┐        │    rating        │
│    status enum │       │    PACKAGES      │        │    status        │
│    deskripsi   │       ├──────────────────┤        └──────────────────┘
│    foto_path   │       │ PK id            │
└────────────────┘       │    nama          │
        ▲                │    durasi_jam    │
        │ N              │    tipe_diskon   │
        │                │    diskon_*      │
        └────────────────│    harga         │
         BOOKINGS.unit_id│    berlaku_*     │
                         │    status        │
                         └──────────────────┘
                                   ▲
                                   │ 1
                            BOOKINGS.package_id (N:1, boleh NULL)

┌──────────────────┐
│    SETTINGS      │  (key-value, tanpa relasi FK)
├──────────────────┤
│ PK key_name      │
│    value_text    │
│    tipe, label   │
└──────────────────┘
```

## 2. Tabel Relasi

| Relasi | Tipe | FK | ON DELETE | ON UPDATE | Makna |
|---|---|---|---|---|---|
| `users` → `bookings` | 1:N | `bookings.user_id` | RESTRICT | CASCADE | Satu pelanggan punya banyak pesanan; pesanan tidak boleh hilang saat pelanggan dihapus |
| `units` → `bookings` | 1:N | `bookings.unit_id` | RESTRICT | CASCADE | Unit tidak bisa dihapus jika masih punya riwayat pesanan |
| `packages` → `bookings` | 1:N | `bookings.package_id` | SET NULL | CASCADE | Pesanan tetap ada walau paket dihapus |
| `users` → `bookings` (konfirmasi) | 1:N | `bookings.konfirmasi_oleh` | SET NULL | CASCADE | Jejak admin yang mengonfirmasi |
| `bookings` → `payments` | 1:N | `payments.booking_id` | CASCADE | CASCADE | Satu pesanan bisa punya beberapa percobaan pembayaran |
| `users` → `payments` | 1:N | `payments.user_id` | RESTRICT | CASCADE | Pemilik pembayaran |
| `users` → `payments` (verifikasi) | 1:N | `payments.verifikasi_oleh` | SET NULL | CASCADE | Jejak admin yang memverifikasi |
| `units` ↔ `games` | N:M | `unit_games.unit_id`, `unit_games.game_id` | CASCADE | CASCADE | Satu unit punya banyak game, satu game bisa ada di banyak unit |
| `users` → `activity_logs` | 1:N | `activity_logs.user_id` | SET NULL | CASCADE | Audit tetap tersimpan walau akun dihapus |

## 3. Kardinalitas Bisnis

- **1 unit ↔ banyak pesanan** (`bookings`) — jadwal per unit per tanggal.
- **1 pesanan ↔ 1 atau lebih pembayaran** (`payments`) — upload ulang bukti dimungkinkan.
- **1 pesanan ↔ 0..1 paket** (`packages`) — paket bersifat opsional.
- **1 unit ↔ banyak game** melalui `unit_games` (unique pair mencegah duplikasi).

## 4. Aturan Anti-Bentrok Jadwal

MySQL tidak mendukung *exclusion constraint* seperti PostgreSQL, sehingga bentrok
dijaga di dua lapisan:

1. **Indeks** `idx_bookings_cek_bentrok (unit_id, booking_date, start_time, end_time, status_pesanan)`.
2. **Stored procedure** `sp_booking_cek_bentrok(...)` dengan `SELECT ... FOR UPDATE`
   di dalam transaksi, dipanggil model `Booking::tabrakan()` sebelum insert/update.

Slot dianggap bentrok bila pesanan lama berstatus `menunggu`, `dikonfirmasi`,
atau `berlangsung` dan irisan waktunya memenuhi:

```
lama.start < baru.end AND lama.end > baru.start
```

## 5. Snapshot Harga (keputusan desain)

`bookings.harga_per_jam`, `bookings.tarif_controller`, dan `bookings.subtotal`
disalin saat pesanan dibuat. Perubahan tarif `units.harga_per_jam` di kemudian
hari **tidak** mengubah nilai pesanan lama maupun laporan yang sudah dicetak.

## 6. Kamus Data ENUM

| Kolom | Nilai |
|---|---|
| `users.role` | `pelanggan`, `operator`, `admin` |
| `units.status` | `tersedia`, `dipakai`, `dipesan`, `maintenance` |
| `bookings.status_pesanan` | `menunggu`, `dikonfirmasi`, `berlangsung`, `selesai`, `dibatalkan` |
| `bookings.status_bayar` | `belum`, `menunggu_verifikasi`, `lunas`, `ditolak` |
| `payments.status` | `menunggu_verifikasi`, `lunas`, `ditolak` |

## 7. Views

| View | Isi | Dipakai di |
|---|---|---|
| `v_laporan_harian` | Omzet, jumlah pesanan, total jam, diskon, omzet controller per tanggal | Laporan harian/bulanan, grafik dashboard |
| `v_laporan_unit` | Kinerja penyewaan per unit | Laporan & halaman kelola unit |
| `v_pesanan_aktif` | Pesanan `berlangsung` + `sisa_detik` | Dashboard & halaman monitoring/timer |

<?php
/**
 * Booking — pusat logika pesanan sewa.
 *
 * Semua operasi tulis yang menyentuh jadwal dibungkus transaksi + baris lock
 * (SELECT ... FOR UPDATE) supaya dua orang tidak bisa memesan slot yang sama
 * pada waktu bersamaan (race condition).
 */

namespace App\Models;

use App\Core\Auth;
use App\Core\Database;
use App\Core\Logger;
use App\Core\Model;
use App\Core\Pricing;
use App\Core\Schedule;
use RuntimeException;

class Booking extends Model
{
    protected string $table = 'bookings';
    protected array $fillable = [
        'invoice_number', 'user_id', 'unit_id', 'package_id', 'booking_date',
        'start_time', 'end_time', 'durasi_jam', 'harga_per_jam',
        'controller_tambahan', 'tarif_controller', 'biaya_controller',
        'subtotal', 'diskon', 'total_harga', 'metode_bayar', 'status_pesanan',
        'status_bayar', 'catatan_pelanggan', 'catatan_admin', 'konfirmasi_oleh',
        'dikonfirmasi_pada', 'dimulai_pada', 'selesai_pada', 'dibatalkan_pada', 'alasan_batal',
    ];
    protected array $dates = ['dikonfirmasi_pada', 'dimulai_pada', 'selesai_pada', 'dibatalkan_pada'];

    /* =================================================================
     * PEMBENTUKAN NOMOR FAKTUR
     * ================================================================= */

    /** Format: QS-YYYYMMDD-NNNN (NNNN urutan hari itu). */
    public static function buatNomorFaktur(string $tanggal): string
    {
        $prefix = 'QS-' . date('Ymd', strtotime($tanggal)) . '-';
        $jumlah = (int) (Database::instance()->scalar(
            'SELECT COUNT(*) FROM bookings WHERE invoice_number LIKE ?',
            [$prefix . '%']
        ) ?? 0);

        // Ulangi bila bertabrakan (unik karena kolom UNIQUE invoice_number)
        do {
            $jumlah++;
            $nomor = $prefix . str_pad((string) $jumlah, 4, '0', STR_PAD_LEFT);
            $ada = Database::instance()->scalar('SELECT id FROM bookings WHERE invoice_number = ? LIMIT 1', [$nomor]);
        } while ($ada !== null);

        return $nomor;
    }

    /* =================================================================
     * MEMBUAT PESANAN
     * ================================================================= */

    /**
     * @param array $input ['user_id','unit_id','booking_date','start_time','durasi_jam',
     *                      'controller_tambahan','package_id','metode_bayar','catatan_pelanggan']
     * @return array baris pesanan yang baru dibuat
     * @throws RuntimeException berisi pesan Bahasa Indonesia yang siap ditampilkan
     */
    public static function pesan(array $input): array
    {
        $db = Database::instance();
        $unit = (new Unit())->find((int) $input['unit_id']);
        if ($unit === null) {
            throw new RuntimeException('Unit yang dipilih tidak ditemukan.');
        }
        if ($unit['status'] === 'maintenance') {
            throw new RuntimeException('Unit ' . $unit['kode_unit'] . ' sedang dalam perawatan.');
        }

        $tanggal = date('Y-m-d', strtotime((string) $input['booking_date']));
        $durasi = max(1, (int) $input['durasi_jam']);
        $mulaiMenit = Schedule::toMenit((string) $input['start_time']);
        $selesaiMenit = $mulaiMenit + $durasi * 60;

        ['buka' => $buka, 'tutup' => $tutup] = Schedule::jamOperasional($tanggal);
        if ($mulaiMenit < $buka * 60 || $selesaiMenit > $tutup * 60) {
            throw new RuntimeException(
                'Jadwal harus di dalam jam operasional ' . sprintf('%02d.00', $buka)
                . ' - ' . ($tutup === 24 ? '24.00' : sprintf('%02d.00', $tutup)) . '.'
            );
        }
        if ($tanggal === date('Y-m-d') && $mulaiMenit <= Schedule::toMenit(date('H:i'))) {
            throw new RuntimeException('Jam mulai sudah terlewat. Pilih jam yang akan datang.');
        }
        if ($durasi < (int) config('booking.min_durasi', 1) || $durasi > (int) config('booking.max_durasi', 8)) {
            throw new RuntimeException('Durasi sewa antara 1 sampai 8 jam.');
        }

        $harga = Pricing::hitung(
            $unit,
            $tanggal,
            $durasi,
            (int) ($input['controller_tambahan'] ?? 0),
            isset($input['package_id']) && $input['package_id'] !== '' ? (int) $input['package_id'] : null
        );

        $mulai = sprintf('%02d:%02d:00', intdiv($mulaiMenit, 60), $mulaiMenit % 60);
        $selesai = sprintf('%02d:%02d:00', intdiv($selesaiMenit, 60), $selesaiMenit % 60);

        return $db->transaction(function () use ($db, $unit, $input, $tanggal, $mulai, $selesai, $durasi, $harga): array {
            // KUNCI baris pada tanggal+unit ini agar tidak ada pesanan ganda
            $bentrok = self::kunciSlot((int) $unit['id'], $tanggal, $mulai, $selesai);
            if ($bentrok !== null) {
                throw new RuntimeException(
                    'Jadwal bentrok dengan pesanan ' . $bentrok['invoice_number']
                    . ' pukul ' . jam($bentrok['start_time']) . ' - ' . jam($bentrok['end_time']) . '.'
                );
            }

            $id = $db->insert('bookings', [
                'invoice_number'      => self::buatNomorFaktur($tanggal),
                'user_id'             => (int) $input['user_id'],
                'unit_id'             => (int) $unit['id'],
                // Paket dicatat HANYA bila benar-benar dipakai menghitung harga.
                // Pricing::hitung() menghapusnya saat paket nonaktif/kedaluwarsa,
                // menyimpan id-nya akan menampilkan diskon fiktif di riwayat.
                'package_id'          => $harga['paket']['id'] ?? null,
                'booking_date'        => $tanggal,
                'start_time'          => $mulai,
                'end_time'            => $selesai,
                'durasi_jam'          => $durasi,
                'harga_per_jam'       => $harga['harga_per_jam'],
                'controller_tambahan' => $harga['controller_tambahan'],
                'tarif_controller'    => $harga['tarif_controller'],
                'biaya_controller'    => $harga['biaya_controller'],
                'subtotal'            => $harga['subtotal'],
                'diskon'              => $harga['diskon'],
                'total_harga'         => $harga['total'],
                'metode_bayar'        => in_array($input['metode_bayar'] ?? '', ['transfer', 'bayar_di_tempat'], true)
                    ? $input['metode_bayar'] : 'transfer',
                'status_pesanan'      => 'menunggu',
                'status_bayar'        => 'belum',
                'catatan_pelanggan'   => $input['catatan_pelanggan'] ?? null,
            ]);

            Logger::write((int) $input['user_id'], 'booking', 'buat', 'bookings', $id,
                'Pesanan baru ' . $harga['kode_unit'] . ' ' . $tanggal . ' ' . jam($mulai) . '-' . jam($selesai));

            return (new self())->find($id) ?? [];
        });
    }

    /** SELECT ... FOR UPDATE: deteksi bentrok sekaligus mengunci rentang. */
    private static function kunciSlot(int $unitId, string $tanggal, string $mulai, string $selesai): ?array
    {
        return Database::instance()->one(
            'SELECT id, invoice_number, start_time, end_time FROM bookings
              WHERE unit_id = ? AND booking_date = ?
                AND status_pesanan IN ("menunggu","dikonfirmasi","berlangsung")
                AND start_time < ? AND end_time > ?
              ORDER BY start_time LIMIT 1 FOR UPDATE',
            [$unitId, $tanggal, $selesai, $mulai]
        );
    }

    /* =================================================================
     * PEMBACAAN
     * ================================================================= */

    public static function denganDetail(int $id): ?array
    {
        return Database::instance()->one(
            'SELECT b.*, u.nama AS nama_pelanggan, u.email, u.telepon, u.alamat,
                    un.kode_unit, un.nama AS nama_unit, un.tipe, un.foto_path, un.kapasitas,
                    pk.nama AS nama_paket,
                    ad.nama AS dikonfirmasi_oleh_nama
               FROM bookings b
               JOIN users u  ON u.id = b.user_id
               JOIN units un ON un.id = b.unit_id
               LEFT JOIN packages pk ON pk.id = b.package_id
               LEFT JOIN users ad    ON ad.id = b.konfirmasi_oleh
              WHERE b.id = ? LIMIT 1',
            [$id]
        );
    }

    /** Daftar pesanan milik satu pelanggan (riwayat + filter status). */
    public static function riwayat(int $userId, string $status = '', string $dari = '', string $sampai = '', int $halaman = 1, int $perHalaman = 10): array
    {
        $kondisi = ['b.user_id = ?'];
        $params = [$userId];

        if ($status !== '') {
            $kondisi[] = 'b.status_pesanan = ?';
            $params[] = $status;
        }
        if ($dari !== '') {
            $kondisi[] = 'b.booking_date >= ?';
            $params[] = $dari;
        }
        if ($sampai !== '') {
            $kondisi[] = 'b.booking_date <= ?';
            $params[] = $sampai;
        }

        $whereSql = 'WHERE ' . implode(' AND ', $kondisi);
        $offset = max(0, ($halaman - 1) * $perHalaman);

        $total = (int) (Database::instance()->scalar(
            "SELECT COUNT(*) FROM bookings b {$whereSql}", $params
        ) ?? 0);

        $rows = Database::instance()->all(
            "SELECT b.*, un.kode_unit, un.nama AS nama_unit, un.tipe,
                    u.nama AS nama_pelanggan,
                    (SELECT COUNT(*) FROM payments p WHERE p.booking_id = b.id) AS jumlah_bayar
               FROM bookings b
               JOIN units un ON un.id = b.unit_id
               JOIN users u  ON u.id = b.user_id
               {$whereSql}
              ORDER BY b.booking_date DESC, b.start_time DESC
              LIMIT {$perHalaman} OFFSET {$offset}",
            $params
        );

        return [
            'rows' => $rows, 'total' => $total, 'halaman' => $halaman,
            'per_halaman' => $perHalaman, 'terakhir' => max(1, (int) ceil($total / $perHalaman)),
        ];
    }

    /** Daftar pesanan sisi admin dengan filter lengkap. */
    public static function admin(string $status = '', string $tanggal = '', string $cari = '', string $tipe = '', int $halaman = 1, int $perHalaman = 15): array
    {
        $kondisi = ['1 = 1'];
        $params = [];

        if ($status !== '') {
            $kondisi[] = 'b.status_pesanan = ?';
            $params[] = $status;
        }
        if ($tanggal !== '') {
            $kondisi[] = 'b.booking_date = ?';
            $params[] = $tanggal;
        }
        if ($tipe !== '') {
            $kondisi[] = 'un.tipe = ?';
            $params[] = $tipe;
        }
        if ($cari !== '') {
            $kondisi[] = '(b.invoice_number LIKE ? OR u.nama LIKE ? OR un.kode_unit LIKE ? OR u.telepon LIKE ?)';
            $like = '%' . $cari . '%';
            array_push($params, $like, $like, $like, $like);
        }

        $whereSql = 'WHERE ' . implode(' AND ', $kondisi);
        $offset = max(0, ($halaman - 1) * $perHalaman);

        $total = (int) (Database::instance()->scalar(
            "SELECT COUNT(*) FROM bookings b
               JOIN units un ON un.id = b.unit_id
               JOIN users u  ON u.id = b.user_id {$whereSql}", $params
        ) ?? 0);

        $rows = Database::instance()->all(
            "SELECT b.*, un.kode_unit, un.nama AS nama_unit, un.tipe,
                    u.nama AS nama_pelanggan, u.telepon, u.email,
                    p.status AS status_bukti, p.id AS payment_id
               FROM bookings b
               JOIN units un ON un.id = b.unit_id
               JOIN users u  ON u.id = b.user_id
               LEFT JOIN payments p ON p.id = (
                   SELECT p2.id FROM payments p2 WHERE p2.booking_id = b.id ORDER BY p2.created_at DESC LIMIT 1
               )
               {$whereSql}
              ORDER BY b.booking_date DESC, b.start_time DESC, b.id DESC
              LIMIT {$perHalaman} OFFSET {$offset}",
            $params
        );

        return [
            'rows' => $rows, 'total' => $total, 'halaman' => $halaman,
            'per_halaman' => $perHalaman, 'terakhir' => max(1, (int) ceil($total / $perHalaman)),
        ];
    }

    /**
     * Pesanan untuk ekspor/laporan dalam rentang tanggal.
     * Rentang dimasukkan ke SQL (bukan disaring setelah LIMIT) supaya pesanan
     * di luar 500 baris terbaru tidak hilang dari berkas CSV.
     */
    public static function ekspor(string $dari, string $sampai, int $batas = 500): array
    {
        return Database::instance()->all(
            'SELECT b.*, un.kode_unit, un.nama AS nama_unit, un.tipe,
                    u.nama AS nama_pelanggan, u.telepon, u.email
               FROM bookings b
               JOIN units un ON un.id = b.unit_id
               JOIN users u  ON u.id = b.user_id
              WHERE b.booking_date BETWEEN ? AND ?
              ORDER BY b.booking_date DESC, b.start_time DESC, b.id DESC
              LIMIT ' . max(1, $batas),
            [$dari, $sampai]
        );
    }

    /* =================================================================
     * PERPINDAHAN STATUS
     * ================================================================= */

    /** Transisi yang diizinkan: dari => [tujuan, ...] */
    private const TRANSISI = [
        'menunggu'      => ['dikonfirmasi', 'dibatalkan'],
        'dikonfirmasi'  => ['berlangsung', 'dibatalkan'],
        'berlangsung'   => ['selesai'],
        'selesai'       => [],
        'dibatalkan'    => [],
    ];

    public static function bolehTransisi(string $dari, string $ke): bool
    {
        return in_array($ke, self::TRANSISI[$dari] ?? [], true);
    }

    /** Setelan kolom waktu untuk tiap perpindahan status. */
    public static function ubahStatus(int $id, string $statusBaru, array $extra = [], ?int $oleh = null): array
    {
        $b = (new self())->findOrFail($id);
        if (!self::bolehTransisi($b['status_pesanan'], $statusBaru)) {
            throw new RuntimeException(
                'Pesanan berstatus ' . status_label($b['status_pesanan']) . ' tidak bisa diubah menjadi '
                . status_label($statusBaru) . '.'
            );
        }

        $data = $extra;
        $data['status_pesanan'] = $statusBaru;

        switch ($statusBaru) {
            case 'dikonfirmasi':
                $data['konfirmasi_oleh'] = $oleh ?? Auth::id();
                $data['dikonfirmasi_pada'] = date('Y-m-d H:i:s');
                break;
            case 'berlangsung':
                $data['dimulai_pada'] = date('Y-m-d H:i:s');
                break;
            case 'selesai':
                $data['selesai_pada'] = date('Y-m-d H:i:s');
                // Sewa selesai => pembayaran harus menjadi lunas bila tunai
                if ($b['metode_bayar'] === 'bayar_di_tempat' && $b['status_bayar'] !== 'lunas') {
                    $data['status_bayar'] = 'lunas';
                }
                break;
            case 'dibatalkan':
                $data['dibatalkan_pada'] = date('Y-m-d H:i:s');
                $data['alasan_batal'] = $extra['alasan_batal'] ?? 'Dibatalkan sesuai permintaan pelanggan.';
                break;
        }

        Database::instance()->update('bookings', $id, $data);

        Logger::write($oleh, 'booking', $statusBaru, 'bookings', $id,
            'Status pesanan ' . $b['invoice_number'] . ' -> ' . $statusBaru);

        return (new self())->find($id) ?? [];
    }

    /** Apakah pelanggan masih boleh membatalkan sendiri? */
    public static function bisaDibatalkan(array $b): array
    {
        if (!in_array($b['status_pesanan'], ['menunggu', 'dikonfirmasi'], true)) {
            return [false, 'Pesanan berstatus ' . status_label($b['status_pesanan']) . ' tidak dapat dibatalkan sendiri. Hubungi admin.'];
        }

        $batasMenit = (int) Setting::get('batas_menit_batal', 60);
        $mulaiTs = strtotime($b['booking_date'] . ' ' . $b['start_time']);
        $sisaMenit = (int) round(($mulaiTs - time()) / 60);

        if ($sisaMenit < $batasMenit) {
            return [false, "Pembatalan mandiri ditutup {$batasMenit} menit sebelum jadwal. Silakan hubungi admin untuk bantuan."];
        }

        return [true, 'Pesanan dapat dibatalkan hingga ' . $sisaMenit . ' menit sebelum jadwal.'];
    }

    /**
     * Tutup otomatis pesanan yang sudah lewat jam selesainya tetapi statusnya
     * belum "selesai". Dipanggil di dashboard/monitor (tanpa cron).
     */
    public static function selesaikanOtomatis(): int
    {
        $rows = Database::instance()->all(
            'SELECT id, invoice_number FROM bookings
              WHERE status_pesanan = "berlangsung"
                AND (booking_date < CURDATE()
                  OR (booking_date = CURDATE() AND end_time <= CURTIME()))'
        );
        foreach ($rows as $row) {
            Database::instance()->update('bookings', (int) $row['id'], [
                'status_pesanan' => 'selesai',
                'selesai_pada'   => date('Y-m-d H:i:s'),
            ]);
            Logger::write(null, 'booking', 'selesai', 'bookings', (int) $row['id'],
                'Pesanan ' . $row['invoice_number'] . ' selesai otomatis (jam habis).');
        }
        return count($rows);
    }

    /** Bersihkan pesanan "menunggu" yang lewat jadwal (tidak diambil siapa-siapa). */
    public static function kedaluwarsaOtomatis(): int
    {
        $rows = Database::instance()->all(
            'SELECT id, invoice_number FROM bookings
              WHERE status_pesanan = "menunggu"
                AND (booking_date < CURDATE()
                  OR (booking_date = CURDATE() AND end_time <= CURTIME()))'
        );
        foreach ($rows as $row) {
            Database::instance()->update('bookings', (int) $row['id'], [
                'status_pesanan' => 'dibatalkan',
                'dibatalkan_pada' => date('Y-m-d H:i:s'),
                'alasan_batal'   => 'Melewat jam jadwal tanpa konfirmasi admin.',
            ]);
        }
        return count($rows);
    }

    /* =================================================================
     * STATISTIK & LAPORAN
     * ================================================================= */

    /**
     * Jumlah pesanan pada satu status. Dipakai badge sidebar karena jauh lebih
     * murah daripada Booking::admin() yang menggabungkan banyak tabel + agregat.
     */
    public static function hitungStatus(string $status): int
    {
        return (int) Database::instance()->scalar(
            'SELECT COUNT(*) FROM bookings WHERE status_pesanan = ?',
            [$status]
        );
    }

    public static function ringkasanDashboard(): array
    {
        $db = Database::instance();
        $hariIni = date('Y-m-d');

        $hari = $db->one(
            "SELECT COUNT(*) AS jumlah, COALESCE(SUM(total_harga), 0) AS omzet
               FROM bookings WHERE booking_date = ? AND status_pesanan <> 'dibatalkan'",
            [$hariIni]
        ) ?? ['jumlah' => 0, 'omzet' => 0];

        $bulan = $db->one(
            "SELECT COUNT(*) AS jumlah, COALESCE(SUM(total_harga), 0) AS omzet
               FROM bookings
              WHERE status_pesanan <> 'dibatalkan'
                AND booking_date >= DATE_FORMAT(CURDATE(), '%Y-%m-01')",
        ) ?? ['jumlah' => 0, 'omzet' => 0];

        return [
            'omzet_hari'     => (float) $hari['omzet'],
            'pesanan_hari'   => (int) $hari['jumlah'],
            'omzet_bulan'    => (float) $bulan['omzet'],
            'pesanan_bulan'  => (int) $bulan['jumlah'],
            'menunggu_konfi' => (int) ($db->scalar("SELECT COUNT(*) FROM bookings WHERE status_pesanan = 'menunggu'") ?? 0),
            'menunggu_verif' => (int) ($db->scalar("SELECT COUNT(*) FROM payments WHERE status = 'menunggu_verifikasi'") ?? 0),
            'berlangsung'    => (int) ($db->scalar("SELECT COUNT(*) FROM bookings WHERE status_pesanan = 'berlangsung'") ?? 0),
            'rata_rata'      => (float) ($db->scalar(
                "SELECT COALESCE(AVG(total_harga), 0) FROM bookings
                  WHERE status_pesanan <> 'dibatalkan' AND booking_date >= DATE_FORMAT(CURDATE(), '%Y-%m-01')"
            ) ?? 0),
        ];
    }

    /** Deret pendapatan harian untuk grafik dashboard (default 14 hari). */
    public static function grafikPendapatan(int $jumlahHari = 14): array
    {
        $dari = date('Y-m-d', strtotime('-' . ($jumlahHari - 1) . ' day'));
        $rows = Database::instance()->all(
            "SELECT booking_date AS tanggal, COALESCE(SUM(total_harga), 0) AS omzet, COUNT(*) AS pesanan
               FROM bookings
              WHERE booking_date BETWEEN ? AND CURDATE() AND status_pesanan <> 'dibatalkan'
              GROUP BY booking_date ORDER BY booking_date",
            [$dari]
        );

        // Isi tanggal yang kosong dengan 0 agar grafik tidak melompat
        $petakan = array_column($rows, null, 'tanggal');
        $hasil = [];
        for ($i = $jumlahHari - 1; $i >= 0; $i--) {
            $tanggal = date('Y-m-d', strtotime("-{$i} day"));
            $hasil[] = [
                'tanggal' => $tanggal,
                'label'   => date('d/m', strtotime($tanggal)),
                'omzet'   => (float) ($petakan[$tanggal]['omzet'] ?? 0),
                'pesanan' => (int) ($petakan[$tanggal]['pesanan'] ?? 0),
            ];
        }
        return $hasil;
    }

    /** Laporan harian per tanggal (omzet, jam, pesanan, per metode bayar). */
    public static function laporanHarian(string $dari, string $sampai, int $limit = 60): array
    {
        return Database::instance()->all(
            "SELECT booking_date AS tanggal,
                    COUNT(*) AS jumlah_pesanan,
                    SUM(status_pesanan = 'dibatalkan') AS jumlah_batal,
                    COALESCE(SUM(durasi_jam), 0) AS total_jam,
                    COALESCE(SUM(total_harga), 0) AS omzet,
                    COALESCE(SUM(diskon), 0) AS total_diskon,
                    COALESCE(SUM(biaya_controller), 0) AS omzet_controller,
                    COALESCE(SUM(metode_bayar = 'transfer'), 0) AS bayar_transfer,
                    COALESCE(SUM(metode_bayar = 'bayar_di_tempat'), 0) AS bayar_tempat,
                    COALESCE(SUM(status_bayar = 'lunas'), 0) AS sudah_lunas
               FROM bookings
              WHERE booking_date BETWEEN ? AND ? AND status_pesanan <> 'dibatalkan'
              GROUP BY booking_date ORDER BY booking_date DESC LIMIT " . max(1, $limit),
            [$dari, $sampai]
        );
    }

    /** Laporan bulanan (per YYYY-MM). */
    public static function laporanBulanan(string $dari, string $sampai): array
    {
        return Database::instance()->all(
            "SELECT DATE_FORMAT(booking_date, '%Y-%m') AS bulan,
                    COUNT(*) AS jumlah_pesanan,
                    COALESCE(SUM(durasi_jam), 0) AS total_jam,
                    COALESCE(SUM(total_harga), 0) AS omzet,
                    COALESCE(SUM(diskon), 0) AS total_diskon,
                    COALESCE(SUM(status_pesanan = 'selesai'), 0) AS jumlah_selesai
               FROM bookings
              WHERE booking_date BETWEEN ? AND ? AND status_pesanan <> 'dibatalkan'
              GROUP BY bulan ORDER BY bulan DESC",
            [$dari, $sampai]
        );
    }

    /** Pesanan yang sedang berjalan untuk halaman monitoring/timer. */
    public static function dipantau(): array
    {
        return Database::instance()->all(
            "SELECT b.*, un.kode_unit, un.nama AS nama_unit, un.tipe, u.nama AS nama_pelanggan, u.telepon,
                    TIMESTAMPDIFF(SECOND, NOW(), CONCAT(b.booking_date, ' ', b.end_time)) AS sisa_detik,
                    TIMESTAMPDIFF(SECOND, CONCAT(b.booking_date, ' ', b.start_time), NOW()) AS berjalan_detik
               FROM bookings b
               JOIN units un ON un.id = b.unit_id
               JOIN users u  ON u.id = b.user_id
              WHERE b.status_pesanan = 'berlangsung'
                 OR (b.status_pesanan = 'dikonfirmasi' AND b.booking_date = CURDATE()
                     AND b.start_time <= CURTIME() AND b.end_time > CURTIME())
              ORDER BY b.end_time ASC"
        );
    }

    /** Rekap pesanan pelanggan untuk halaman riwayat (kartu ringkasan). */
    public static function rekapPelanggan(int $userId): array
    {
        return Database::instance()->one(
            "SELECT COUNT(*) AS total,
                    COALESCE(SUM(status_pesanan = 'menunggu'), 0)      AS menunggu,
                    COALESCE(SUM(status_pesanan = 'dikonfirmasi'), 0)  AS dikonfirmasi,
                    COALESCE(SUM(status_pesanan = 'berlangsung'), 0)   AS berlangsung,
                    COALESCE(SUM(status_pesanan = 'selesai'), 0)       AS selesai,
                    COALESCE(SUM(status_pesanan = 'dibatalkan'), 0)    AS dibatalkan,
                    COALESCE(SUM(CASE WHEN status_pesanan IN ('selesai','berlangsung','dikonfirmasi')
                                      THEN total_harga ELSE 0 END), 0) AS total_bayar,
                    COALESCE(SUM(CASE WHEN status_pesanan IN ('selesai','berlangsung','dikonfirmasi')
                                      THEN durasi_jam ELSE 0 END), 0)  AS total_jam
               FROM bookings WHERE user_id = ?",
            [$userId]
        ) ?? [];
    }
}

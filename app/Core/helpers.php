<?php
/**
 * helpers.php — Kumpulan fungsi global yang dipakai di controller & view.
 * Semua keluaran teks yang berasal dari input pengguna WAJIB melewati e()
 * agar terlindung dari Cross-Site Scripting (XSS).
 */

declare(strict_types=1);

if (!function_exists('env')) {
    /** Ambil nilai dari environment / .env dengan fallback. */
    function env(string $key, ?string $default = null): ?string
    {
        $value = getenv($key);
        if ($value === false) {
            return $_ENV[$key] ?? $default;
        }
        return $value;
    }
}

if (!function_exists('config')) {
    /**
     * Baca konfigurasi dengan notasi titik, contoh: config('database.host').
     * File konfigurasi hanya dimuat sekali (static cache).
     */
    function config(string $key, $default = null)
    {
        static $config = null;
        if ($config === null) {
            $config = require APP_ROOT . '/app/config/config.php';
        }
        $value = $config;
        foreach (explode('.', $key) as $seg) {
            if (!is_array($value) || !array_key_exists($seg, $value)) {
                return $default;
            }
            $value = $value[$seg];
        }
        return $value;
    }
}

if (!function_exists('request')) {
    /** Instance request saat ini (method, uri, input, file upload). */
    function request(): App\Core\Request
    {
        return App\Core\Request::instance();
    }
}

if (!function_exists('e')) {
    /** Escape HTML. Kembalikan '' untuk null agar view bersih. */
    function e(mixed $value): string
    {
        return htmlspecialchars((string) ($value ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}

if (!function_exists('url')) {
    /** Bangun URL absolut dari path aplikasi, contoh: url('admin/unit'). */
    function url(string $path = ''): string
    {
        $base = rtrim((string) config('app.url', ''), '/');
        $path = ltrim($path, '/');
        return $path === '' ? $base . '/' : $base . '/' . $path;
    }
}

if (!function_exists('route')) {
    /**
     * URL berdasarkan nama rute: route('admin.pesanan', ['id' => 5])
     * Bila nama tidak terdaftar, path dianggap URL biasa (tidak fatal).
     */
    function route(string $name, array $params = []): string
    {
        return url(App\Core\Router::instance()->path($name, $params));
    }
}

if (!function_exists('asset')) {    /** URL aset statis di folder public/assets. */
    function asset(string $path): string
    {
        return url('assets/' . ltrim($path, '/'));
    }
}

if (!function_exists('upload_url')) {
    /** URL file hasil upload (foto unit, bukti transfer). */
    function upload_url(?string $path): string
    {
        if ($path === null || trim($path) === '') {
            return asset('img/placeholder-unit.svg');
        }
        if (str_starts_with($path, 'http')) {
            return $path;
        }
        return url('uploads/' . ltrim($path, '/'));
    }
}

if (!function_exists('redirect')) {
    /** Arahkan dan hentikan eksekusi. */
    function redirect(string $path): never
    {
        header('Location: ' . (str_starts_with($path, 'http') ? $path : url($path)));
        exit;
    }
}

if (!function_exists('back')) {
    /** Kembali ke halaman sebelumnya (dipakai setelah form gagal). */
    function back(): never
    {
        $refer = $_SERVER['HTTP_REFERER'] ?? url('/');
        redirect(str_starts_with($refer, 'http') ? $refer : url($refer));
    }
}

if (!function_exists('old')) {
    /** Nilai input sebelumnya setelah validasi gagal (feature "keep old input"). */
    function old(string $key, $default = ''): string
    {
        return e($_SESSION['_old'][$key] ?? $default);
    }
}

if (!function_exists('csrf_token')) {
    function csrf_token(): string
    {
        return App\Core\Csrf::token();
    }
}

if (!function_exists('csrf_field')) {
    /** Input hidden CSRF untuk semua form POST. */
    function csrf_field(): string
    {
        return '<input type="hidden" name="_token" value="' . e(csrf_token()) . '">';
    }
}

if (!function_exists('flash')) {
    /** Tulis / baca pesan sementara satu kali tampil. */
    function flash(string $type, ?string $message = null)
    {
        if ($message === null) {
            $msg = $_SESSION['_flash'][$type] ?? null;
            unset($_SESSION['_flash'][$type]);
            return $msg;
        }
        $_SESSION['_flash'][$type] = $message;
        return null;
    }
}

if (!function_exists('rupiah')) {
    /** Format angka menjadi Rupiah, contoh: Rp20.000 */
    function rupiah(float|int|string|null $angka, bool $prefix = true): string
    {
        $hasil = number_format((float) $angka, 0, ',', '.');
        return $prefix ? 'Rp' . $hasil : $hasil;
    }
}

if (!function_exists('jam')) {
    /** "19:00" -> "19.00" gaya baca Indonesia. */
    function jam(?string $time): string
    {
        if (!$time) {
            return '-';
        }
        return substr($time, 0, 5);
    }
}

if (!function_exists('tgl_indo')) {
    /** Tanggal YYYY-MM-DD -> "Senin, 16 November 2026". */
    function tgl_indo(?string $tanggal): string
    {
        if (!$tanggal) {
            return '-';
        }
        $hari = ['Minggu', 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu'];
        $bulan = [1 => 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni',
            'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];
        $ts = strtotime($tanggal);
        if ($ts === false) {
            return e($tanggal);
        }
        return $hari[(int) date('w', $ts)] . ', ' . date('j', $ts) . ' '
            . $bulan[(int) date('n', $ts)] . ' ' . date('Y', $ts);
    }
}

if (!function_exists('tgl_waktu')) {
    /** "16 Nov 2026 · 19.00" — ringkas untuk tabel. */
    function tgl_waktu(?string $datetime): string
    {
        if (!$datetime) {
            return '-';
        }
        $ts = strtotime($datetime);
        if ($ts === false) {
            return e($datetime);
        }
        return date('d M Y', $ts) . ' · ' . date('H.i', $ts);
    }
}

if (!function_exists('status_label')) {
    /** Terjemahan status ENUM -> label Bahasa Indonesia. */
    function status_label(?string $status): string
    {
        $map = [
            'tersedia' => 'Tersedia', 'dipakai' => 'Dipakai', 'dipesan' => 'Dipesan',
            'maintenance' => 'Maintenance',
            'menunggu' => 'Menunggu', 'dikonfirmasi' => 'Dikonfirmasi',
            'berlangsung' => 'Berlangsung', 'selesai' => 'Selesai', 'dibatalkan' => 'Dibatalkan',
            'belum' => 'Belum Bayar', 'menunggu_verifikasi' => 'Menunggu Verifikasi',
            'lunas' => 'Lunas', 'ditolak' => 'Ditolak',
            'aktif' => 'Aktif', 'nonaktif' => 'Nonaktif',
            'transfer' => 'Transfer Bank', 'bayar_di_tempat' => 'Bayar di Tempat',
            'pelanggan' => 'Pelanggan', 'operator' => 'Operator', 'admin' => 'Admin',
            'persen' => 'Persen', 'nominal' => 'Nominal',
            'reguler' => 'Reguler', 'vip' => 'VIP', 'ruangan' => 'Ruangan',
        ];
        return $map[$status] ?? ucfirst((string) $status);
    }
}

if (!function_exists('status_class')) {
    /** Peta status -> kelas badge tema (lihat app.css). */
    function status_class(?string $status): string
    {
        $map = [
            'tersedia' => 'badge-success', 'dipakai' => 'badge-info',
            'dipesan' => 'badge-violet', 'maintenance' => 'badge-warning',
            'menunggu' => 'badge-warning', 'menunggu_verifikasi' => 'badge-warning',
            'dikonfirmasi' => 'badge-cyan', 'berlangsung' => 'badge-info',
            'selesai' => 'badge-success', 'lunas' => 'badge-success',
            'dibatalkan' => 'badge-danger', 'ditolak' => 'badge-danger',
            'belum' => 'badge-neutral', 'aktif' => 'badge-success',
            'nonaktif' => 'badge-neutral', 'belum bayar' => 'badge-neutral',
        ];
        return $map[$status] ?? 'badge-neutral';
    }
}

if (!function_exists('str_slug')) {
    function str_slug(string $text): string
    {
        $text = strtolower(trim($text));
        $text = preg_replace('/[^a-z0-9]+/u', '-', $text) ?? '';
        return trim($text, '-');
    }
}

if (!function_exists('array_pluck')) {
    /** Versi ringan array_column untuk data yang key-nya tidak berurut. */
    function array_pluck(array $rows, string $key): array
    {
        return array_map(static fn ($row) => $row[$key] ?? null, $rows);
    }
}

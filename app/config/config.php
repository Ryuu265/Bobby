<?php
/**
 * Konfigurasi aplikasi. Nilai dapat ditimpa lewat file .env di root proyek.
 */

return [
    'app' => [
        'name'          => env('APP_NAME', 'Qengamestation'),
        'env'           => env('APP_ENV', 'local'),
        'debug'         => filter_var(env('APP_DEBUG', 'true'), FILTER_VALIDATE_BOOLEAN),
        'url'           => rtrim(env('APP_URL', 'http://localhost/qengamestation/public'), '/'),
        'timezone'      => env('APP_TIMEZONE', 'Asia/Jakarta'),
        'session_name'  => env('SESSION_NAME', 'qs_session'),
        'secure_cookie' => filter_var(env('SESSION_SECURE', 'false'), FILTER_VALIDATE_BOOLEAN),
    ],

    'database' => [
        'host'      => env('DB_HOST', '127.0.0.1'),
        'port'      => (int) env('DB_PORT', '3306'),
        'name'      => env('DB_NAME', 'qengamestation'),
        'user'      => env('DB_USER', 'root'),
        'pass'      => env('DB_PASS', ''),
        'charset'   => 'utf8mb4',
        'collation' => 'utf8mb4_unicode_ci',
    ],

    'upload' => [
        'max_size_kb'   => 2048, // maks 2 MB per file
        'image_types'   => ['jpg', 'jpeg', 'png', 'webp'],
        'proof_types'   => ['jpg', 'jpeg', 'png', 'webp', 'pdf'],
    ],

    'booking' => [
        // Jam operasional tampilan (realnya dibaca dari tabel settings)
        'open_hour'  => 9,
        'close_hour' => 24,
        'step_hour'  => 1,           // granularity slot jadwal (jam)
        'min_durasi' => 1,
        'max_durasi' => 8,
        'advance_days' => 14,        // maksimal pemesanan H-14
    ],
];

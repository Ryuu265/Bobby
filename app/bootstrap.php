<?php
/**
 * bootstrap.php — Jantung aplikasi.
 * Dimuat oleh public/index.php pada setiap request.
 * Tugas: autoloader, konfigurasi, session, error handler.
 */

declare(strict_types=1);

define('APP_ROOT', dirname(__DIR__));
define('APP_START', microtime(true));

/* ---------------------------------------------------------------------
 * 1. AUTOLOADER SEDERHANA (PSR-4)
 * App\Core\Database  -> app/Core/Database.php
 * App\Models\Booking -> app/Models/Booking.php
 * ------------------------------------------------------------------- */
spl_autoload_register(function (string $class): void {
    $prefix = 'App\\';
    if (strncmp($class, $prefix, strlen($prefix)) !== 0) {
        return; // kelas di luar namespace App ditangani PHP/internal
    }
    $relative = substr($class, strlen($prefix));
    $file = APP_ROOT . '/app/' . str_replace('\\', '/', $relative) . '.php';
    if (is_file($file)) {
        require_once $file;
    }
});

/* ---------------------------------------------------------------------
 * 2. MEMBACA FILE .env (tanpa dependensi dotenv pihak ketiga)
 * ------------------------------------------------------------------- */
$envFile = APP_ROOT . '/.env';
if (is_file($envFile)) {
    foreach (file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#')) {
            continue;
        }
        [$key, $value] = array_pad(explode('=', $line, 2), 2, '');
        $key = trim($key);
        $value = trim(trim($value), "\"'");
        if ($key !== '' && getenv($key) === false) {
            putenv("{$key}={$value}");
            $_ENV[$key] = $value;
        }
    }
}

/* ---------------------------------------------------------------------
 * 3. HELPER GLOBAL (fungsi config() memuat file konfigurasi saat dibutuhkan)
 * ------------------------------------------------------------------- */
require_once APP_ROOT . '/app/Core/helpers.php';

/* ---------------------------------------------------------------------
 * 4. ZONA WAKTU & ERROR HANDLING
 * ------------------------------------------------------------------- */
date_default_timezone_set(config('app.timezone', 'Asia/Jakarta'));
mb_internal_encoding('UTF-8');

if (config('app.debug')) {
    error_reporting(E_ALL);
    ini_set('display_errors', '1');
} else {
    error_reporting(0);
    ini_set('display_errors', '0');
    ini_set('log_errors', '1');
    ini_set('error_log', APP_ROOT . '/storage/logs/php-error.log');
}

/* ---------------------------------------------------------------------
 * 5. SESSION (aman: HttpOnly, SameSite=Lax)
 * ------------------------------------------------------------------- */
if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'domain'   => '',
        'secure'   => (bool) config('app.secure_cookie', false),
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_name(config('app.session_name', 'qs_session'));
    session_start();
}

// Peningkatan id sesi saat baru login, mencegah session fixation
if (isset($_SESSION['_regenerate']) && $_SESSION['_regenerate'] === true) {
    session_regenerate_id(true);
    unset($_SESSION['_regenerate']);
}

/* ---------------------------------------------------------------------
 * 6. PASTIKAN FOLDER UPLOAD & STORAGE ADA
 * ------------------------------------------------------------------- */
foreach ([
    APP_ROOT . '/storage/logs',
    APP_ROOT . '/public/uploads/units',
    APP_ROOT . '/public/uploads/games',
    APP_ROOT . '/public/uploads/payments',
] as $dir) {
    if (!is_dir($dir)) {
        @mkdir($dir, 0775, true);
    }
}

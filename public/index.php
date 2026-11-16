<?php
/**
 * public/index.php — FRONT CONTROLLER
 * Hanya file ini yang boleh diakses web server (document root = folder public).
 * Semua request masuk ke sini, lalu diteruskan oleh Router.
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/app/bootstrap.php';

use App\Core\Error;
use App\Core\Request;
use App\Core\Router;
use App\Core\View;

/* ---------------------------------------------------------------------
 * 1. TANGGAP ERROR GLOBAL -> halaman 500 (stack trace hanya saat debug)
 * ------------------------------------------------------------------- */
set_exception_handler(static function (Throwable $e): void {
    Error::exception($e);
});

set_error_handler(static function (int $severity, string $message, string $file, int $line): bool {
    // Ubah warning PHP menjadi exception agar tidak senyap
    if (!(error_reporting() & $severity)) {
        return false;
    }
    throw new ErrorException($message, 0, $severity, $file, $line);
});

/* ---------------------------------------------------------------------
 * 2. PATH TIDAK BOLEH MENGANDUNG NULL BYTE (proteksi dasar)
 * ------------------------------------------------------------------- */
if (str_contains((string) ($_SERVER['REQUEST_URI'] ?? ''), "\0")) {
    Error::abort(400, 'Permintaan tidak valid.');
}

/* ---------------------------------------------------------------------
 * 3. LOADING & DISPATCH
 * ------------------------------------------------------------------- */
$router = new Router();
require APP_ROOT . '/routes/web.php';   // mendaftarkan rute ke variabel $router
Router::setCurrent($router);

$router->dispatch(Request::instance());

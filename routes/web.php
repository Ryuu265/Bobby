<?php
/**
 * routes/web.php — Peta seluruh URL aplikasi.
 * Group:
 *   ''            halaman publik
 *   'auth'        tamu saja (login/register)
 *   'akun'        wajib login (pelanggan)
 *   'admin'       wajib login + role admin/operator
 */

declare(strict_types=1);

use App\Core\Router;

/** @var Router $router */

/* ============================ PUBLIK ============================ */
$router->get('/', 'HomeController@index', 'beranda');
$router->get('/katalog', 'CatalogController@units', 'katalog');
$router->get('/katalog/game', 'CatalogController@games', 'katalog.game');
$router->get('/katalog/unit/{kode}', 'CatalogController@unitDetail', 'katalog.unit');
$router->get('/katalog/game/{id}', 'CatalogController@gameDetail', 'katalog.game.detail');

// Cek ketersediaan jadwal (dipakai halaman katalog & form booking, juga via AJAX)
$router->get('/jadwal/{unit_id}', 'BookingController@jadwal', 'jadwal');
$router->get('/harga', 'BookingController@kalkulasi', 'kalkulasi');

/* ============================ AUTENTIKASI ============================ */
$router->group(['middleware' => ['tamu']], static function (Router $r): void {
    $r->get('/daftar', 'AuthController@registerForm', 'register.form');
    $r->post('/daftar', 'AuthController@register');
    $r->get('/masuk', 'AuthController@loginForm', 'login.form');
    $r->post('/masuk', 'AuthController@login');
});

$router->post('/keluar', 'AuthController@logout', 'logout'); // middleware csrf global

/* ============================ AREA PELANGGAN ============================ */
$router->group(['middleware' => ['auth']], static function (Router $r): void {
    $r->get('/booking', 'BookingController@form', 'booking.form');
    $r->post('/booking', 'BookingController@simpan', 'booking.simpan');
    $r->get('/booking/{id}', 'HistoryController@detail', 'booking.detail');

    $r->post('/booking/{id}/bayar', 'PaymentController@unggah', 'payment.unggah');
    $r->post('/booking/{id}/batal', 'HistoryController@batalkan', 'booking.batal');

    $r->get('/riwayat', 'HistoryController@index', 'riwayat');

    $r->get('/profil', 'AccountController@edit', 'profil');
    $r->post('/profil', 'AccountController@perbarui');
    $r->post('/profil/password', 'AccountController@gantiPassword');
});

/* ============================ AREA ADMIN ============================ */
$router->group(['prefix' => '/admin', 'middleware' => ['admin']], static function (Router $r): void {
    $r->get('/', 'Admin\\DashboardController@index');
    $r->get('/dashboard', 'Admin\\DashboardController@index', 'admin.dashboard');

    /* ---- Kelola unit PlayStation ---- */
    $r->get('/unit', 'Admin\\UnitController@index', 'admin.unit');
    $r->get('/unit/baru', 'Admin\\UnitController@create', 'admin.unit.baru');
    $r->post('/unit', 'Admin\\UnitController@store');
    $r->get('/unit/{id}/ubah', 'Admin\\UnitController@edit', 'admin.unit.ubah');
    $r->post('/unit/{id}', 'Admin\\UnitController@update');
    $r->post('/unit/{id}/status', 'Admin\\UnitController@ubahStatus');
    $r->post('/unit/{id}/hapus', 'Admin\\UnitController@destroy');

    /* ---- Kelola game ---- */
    $r->get('/game', 'Admin\\GameController@index', 'admin.game');
    $r->get('/game/baru', 'Admin\\GameController@create', 'admin.game.baru');
    $r->post('/game', 'Admin\\GameController@store');
    $r->get('/game/{id}/ubah', 'Admin\\GameController@edit', 'admin.game.ubah');
    $r->post('/game/{id}', 'Admin\\GameController@update');
    $r->post('/game/{id}/unit', 'Admin\\GameController@sinkronUnit');
    $r->post('/game/{id}/hapus', 'Admin\\GameController@destroy');

    /* ---- Kelola paket / promo ---- */
    $r->get('/paket', 'Admin\\PackageController@index', 'admin.paket');
    $r->get('/paket/baru', 'Admin\\PackageController@create', 'admin.paket.baru');
    $r->post('/paket', 'Admin\\PackageController@store');
    $r->get('/paket/{id}/ubah', 'Admin\\PackageController@edit', 'admin.paket.ubah');
    $r->post('/paket/{id}', 'Admin\\PackageController@update');
    $r->post('/paket/{id}/hapus', 'Admin\\PackageController@destroy');

    /* ---- Manajemen pesanan ---- */
    $r->get('/pesanan', 'Admin\\BookingController@index', 'admin.pesanan');
    $r->get('/pesanan/{id}', 'Admin\\BookingController@detail', 'admin.pesanan.detail');
    $r->post('/pesanan/{id}/status', 'Admin\\BookingController@ubahStatus');
    $r->post('/pesanan/{id}/catatan', 'Admin\\BookingController@catatan');

    /* ---- Verifikasi pembayaran ---- */
    $r->get('/pembayaran', 'Admin\\PaymentController@index', 'admin.pembayaran');
    $r->post('/pembayaran/{id}', 'Admin\\PaymentController@verifikasi');

    /* ---- Manajemen pelanggan ---- */
    $r->get('/pelanggan', 'Admin\\CustomerController@index', 'admin.pelanggan');
    $r->get('/pelanggan/{id}', 'Admin\\CustomerController@detail', 'admin.pelanggan.detail');
    $r->post('/pelanggan/{id}/status', 'Admin\\CustomerController@ubahStatus');
    $r->post('/pelanggan/{id}/password', 'Admin\\CustomerController@resetPassword');

    /* ---- Monitoring & timer ---- */
    $r->get('/monitoring', 'Admin\\MonitorController@index', 'admin.monitoring');
    $r->get('/monitoring/data', 'Admin\\MonitorController@data', 'admin.monitoring.data');

    /* ---- Laporan & ekspor ---- */
    $r->get('/laporan', 'Admin\\ReportController@index', 'admin.laporan');
    $r->get('/laporan/harian', 'Admin\\ReportController@harian', 'admin.laporan.harian');
    $r->get('/laporan/bulanan', 'Admin\\ReportController@bulanan', 'admin.laporan.bulanan');
    $r->get('/laporan/unit', 'Admin\\ReportController@unit', 'admin.laporan.unit');
    $r->get('/laporan/aktivitas', 'Admin\\ReportController@aktivitas', 'admin.laporan.aktivitas');
    $r->get('/laporan/ekspor', 'Admin\\ReportController@ekspor', 'admin.laporan.ekspor');
    $r->get('/laporan/cetak', 'Admin\\ReportController@cetak', 'admin.laporan.cetak');

    /* ---- Pengaturan operasional ---- */
    $r->get('/pengaturan', 'Admin\\SettingController@index', 'admin.pengaturan');
    $r->post('/pengaturan', 'Admin\\SettingController@perbarui');
});

/* ============================ FALLBACK ============================ */
$router->get('/404', 'HomeController@notFound');

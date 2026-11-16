<?php
/**
 * HomeController — Landing page & halaman statis publik.
 */

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Schedule;
use App\Models\Package;
use App\Models\Setting;
use App\Models\Unit;

class HomeController extends Controller
{
    /** Beranda: hero, katalog singkat, paket, harga, jam operasional, lokasi. */
    public function index(): void
    {
        $units = Unit::katalog();

        // Soroti unit yang paling menarik (PS5 lebih dulu) maksimal 6 kartu
        $unggulan = array_values(array_filter($units, static fn (array $u): bool => $u['status'] !== 'maintenance'));
        $unggulan = array_slice($unggulan, 0, 6);

        $paket = Package::denganEstimasi();

        // Rekap ketersediaan hari ini untuk panel "Status Live"
        $statusHariIni = array_map(static function (array $u): array {
            return [
                'unit'   => $u,
                'status' => $u['status_realtime'],
                'slot'   => count(array_filter(
                    Schedule::slots((int) $u['id'], date('Y-m-d'), 1),
                    static fn (array $s): bool => $s['tersedia']
                )),
            ];
        }, array_slice($units, 0, 8));

        $tersedia = count(array_filter($units, static fn (array $u): bool => $u['status_realtime'] === 'tersedia'));

        $this->view('pages/landing', [
            'units'        => $units,
            'unggulan'     => $unggulan,
            'paket'        => $paket,
            'statusHari'   => $statusHariIni,
            'jumlahTersedia' => $tersedia,
            'tarifController' => \App\Core\Pricing::tarifController(),
            'judulSitus'   => Setting::get('nama_rental', config('app.name')),
        ]);
    }

    /** Fallback rute tidak dikenal (biasanya ditangani Router). */
    public function notFound(): void
    {
        \App\Core\Error::abort(404, 'Halaman yang Anda cari tidak ditemukan.');
    }
}

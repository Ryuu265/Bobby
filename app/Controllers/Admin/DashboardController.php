<?php
/**
 * DashboardController — Ringkasan operasional admin & operator.
 * Membaca statistik dari model, tidak menulis apa pun selain
 * penutupan sesi otomatis (pengganti cron).
 */

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Controller;
use App\Models\ActivityLog;
use App\Models\Booking;
use App\Models\Payment;
use App\Models\Unit;

class DashboardController extends Controller
{
    /** Jumlah unit yang ditampilkan pada panel "sedang dipakai". */
    private const PANEL_UNIT = 6;

    public function index(): void
    {
        // Tanpa cron: setiap dashboard dibuka, sesi yang lewat jam ditutup
        // dan pesanan yang tidak diambil siapa-siapa dibatalkan.
        Booking::selesaikanOtomatis();
        Booking::kedaluwarsaOtomatis();

        $ringkasan = Booking::ringkasanDashboard();
        $unit      = Unit::statistik();
        $pembayaran = Payment::statistik();
        $grafik    = Booking::grafikPendapatan(14);
        $antrean   = Booking::admin('menunggu', '', '', '', 1, 8);
        $sesi      = array_slice(Booking::dipantau(), 0, self::PANEL_UNIT);

        $this->adminView('admin/dashboard/index', [
            'ringkasan'     => $ringkasan,
            'unit'          => $unit,
            'pembayaran'    => $pembayaran,
            'grafik'        => $grafik,
            'antrean'       => $antrean['rows'],
            'antreanTotal'  => (int) $antrean['total'],
            'sesi'          => $sesi,
            'aktivitas'     => ActivityLog::terbaru(8),
            'kartu'         => $this->kartuStat($ringkasan, $unit, $pembayaran, count($sesi)),
        ]);
    }

    /**
     * Empat kartu paling atas: omzet hari, pesanan hari, unit dipakai,
     * antrean verifikasi pembayaran.
     */
    private function kartuStat(array $r, array $unit, array $bayar, int $sesiAktif): array
    {
        return [
            [
                'label'  => 'Omzet Hari Ini',
                'nilai'  => rupiah($r['omzet_hari']),
                'hint'   => 'Sebulan berjalan ' . rupiah($r['omzet_bulan']),
                'tautan' => route('admin.laporan.harian'),
                'aksi'   => 'Lihat laporan harian',
            ],
            [
                'label'  => 'Pesanan Hari Ini',
                'nilai'  => number_format((float) $r['pesanan_hari']),
                'hint'   => $r['menunggu_konfi'] . ' menunggu konfirmasi · '
                    . $r['berlangsung'] . ' berlangsung',
                'tautan' => route('admin.pesanan'),
                'aksi'   => 'Kelola pesanan',
            ],
            [
                'label'  => 'Unit Dipakai Sekarang',
                'nilai'  => $sesiAktif . ' / ' . (int) $unit['total'],
                'hint'   => ($unit['total'] - $unit['maintenance']) . ' unit siap pakai · '
                    . $unit['maintenance'] . ' maintenance',
                'tautan' => route('admin.monitoring'),
                'aksi'   => 'Buka monitoring',
            ],
            [
                'label'  => 'Antrean Verifikasi',
                'nilai'  => number_format((float) $bayar['menunggu']),
                'hint'   => 'Lunas hari ini ' . rupiah($bayar['lunas_hari_ini']) . ' · '
                    . (int) $bayar['ditolak'] . ' bukti ditolak',
                'tautan' => route('admin.pembayaran'),
                'aksi'   => 'Verifikasi pembayaran',
            ],
        ];
    }
}

<?php
/**
 * MonitorController — Papan timer sesi sewa yang sedang berjalan.
 * index() merender kartu (sisi server), data() dipakai AJAX refresh
 * dan sengaja hanya membaca: tidak mengubah data lewat URL GET.
 */

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Controller;
use App\Models\Booking;

class MonitorController extends Controller
{
    public function index(): void
    {
        // Penutupan sesi otomatis, supaya timer tidak menampilkan sesi mati
        Booking::selesaikanOtomatis();
        Booking::kedaluwarsaOtomatis();

        $this->adminView('admin/monitoring/index', [
            'sesi'      => Booking::dipantau(),
            'endpoint'  => route('admin.monitoring.data'),
            'tautanPesanan' => route('admin.pesanan.detail', ['id' => '__ID__']),
        ]);
    }

    /**
     * JSON: {generated_at, sesi:[{id, invoice, kode_unit, nama_pelanggan,
     * sisa_detik, total_detik, status}]}
     */
    public function data(): void
    {
        $sesi = [];
        foreach (Booking::dipantau() as $b) {
            $sisa = (int) ($b['sisa_detik'] ?? 0);
            // Sesi yang jamnya habis tetap dikirim dengan sisa 0, sama seperti
            // render PHP di halaman ini, agar kartunya tidak hilang mendadak.
            // Status permanen ditutup saat halaman dimuat ulang oleh
            // Booking::selesaikanOtomatis().
            $sesi[] = [
                'id'             => (int) $b['id'],
                'invoice'        => (string) $b['invoice_number'],
                'kode_unit'      => (string) $b['kode_unit'],
                'nama_pelanggan' => (string) $b['nama_pelanggan'],
                'sisa_detik'     => max(0, $sisa),
                'total_detik'    => self::totalDetik($b),
                'status'         => (string) $b['status_pesanan'],
            ];
        }

        $this->json([
            'generated_at' => date('c'),
            'sesi'         => $sesi,
        ]);
    }

    /** Durasi penuh sesi dalam detik (dipakai progress bar). */
    public static function totalDetik(array $booking): int
    {
        return max(60, (int) $booking['durasi_jam'] * 3600);
    }
}

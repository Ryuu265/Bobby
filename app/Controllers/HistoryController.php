<?php
/**
 * HistoryController — riwayat pesanan milik pelanggan yang sedang login,
 * detail satu pesanan, dan pembatalan mandiri.
 */

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Error;
use App\Models\Booking;
use App\Models\Payment;
use RuntimeException;

class HistoryController extends Controller
{
    private const STATUS = ['menunggu', 'dikonfirmasi', 'berlangsung', 'selesai', 'dibatalkan'];
    private const PER_HALAMAN = 8;

    /** GET /riwayat */
    public function index(): void
    {
        $user = $this->user();
        if ($user === null) {
            redirect('masuk');
        }

        $status = (string) $this->request->input('status', '');
        if (!in_array($status, self::STATUS, true)) {
            $status = '';
        }

        $halaman = max(1, $this->request->int('halaman', 1));
        $data = Booking::riwayat((int) $user['id'], $status, '', '', $halaman, self::PER_HALAMAN);

        $this->view('pages/riwayat/index', [
            'pesanan'  => $data['rows'],
            'total'    => $data['total'],
            'halaman'  => $data['halaman'],
            'terakhir' => $data['terakhir'],
            'status'   => $status,
            'daftarStatus' => self::STATUS,
            'rekap'    => Booking::rekapPelanggan((int) $user['id']),
        ]);
    }

    /** GET /booking/{id} */
    public function detail(string $id): void
    {
        $b = Booking::denganDetail((int) $id);
        if ($b === null) {
            Error::abort(404, 'Pesanan tidak ditemukan.');
        }

        $this->authorizeOwner((int) $b['user_id'], 'Pesanan ini bukan milik Anda.');

        [$bolehBatal, $alasanBatal] = Booking::bisaDibatalkan($b);

        $this->view('pages/riwayat/detail', [
            'pesanan'     => $b,
            'pembayaran'  => Payment::semuaUntuk((int) $b['id']),
            'bolehBatal'  => $bolehBatal,
            'alasanBatal' => $alasanBatal,
        ]);
    }

    /** POST /booking/{id}/batal */
    public function batalkan(string $id): void
    {
        $b = Booking::denganDetail((int) $id);
        if ($b === null) {
            Error::abort(404, 'Pesanan tidak ditemukan.');
        }

        $this->authorizeOwner((int) $b['user_id'], 'Pesanan ini bukan milik Anda.');
        $kembali = route('booking.detail', ['id' => (int) $b['id']]);

        $data = $this->validate([
            'alasan' => 'max:255',
        ], ['alasan' => 'Alasan pembatalan']);

        [$boleh, $pesan] = Booking::bisaDibatalkan($b);
        if (!$boleh) {
            flash('warning', $pesan);
            redirect($kembali);
        }

        $alasan = trim((string) ($data['alasan'] ?? ''));

        try {
            Booking::ubahStatus((int) $b['id'], 'dibatalkan', [
                'alasan_batal' => $alasan !== ''
                    ? 'Pelanggan: ' . $alasan
                    : 'Dibatalkan sendiri oleh pelanggan.',
            ]);
        } catch (RuntimeException $e) {
            flash('danger', $e->getMessage());
            redirect($kembali);
        }

        flash('success', 'Pesanan ' . $b['invoice_number'] . ' berhasil dibatalkan. Slot jam sudah dibuka kembali.');
        redirect($kembali);
    }
}

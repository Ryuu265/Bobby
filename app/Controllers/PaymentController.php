<?php
/**
 * PaymentController — pelanggan mencatat pembayaran + unggah bukti transfer.
 * Pembayaran tidak pernah langsung "lunas"; selalu menunggu verifikasi admin.
 */

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Error;
use App\Core\Uploader;
use App\Models\Booking;
use App\Models\Payment;
use RuntimeException;

class PaymentController extends Controller
{
    /** POST /booking/{id}/bayar */
    public function unggah(string $id): void
    {
        $b = Booking::denganDetail((int) $id);
        if ($b === null) {
            Error::abort(404, 'Pesanan tidak ditemukan.');
        }

        $this->authorizeOwner((int) $b['user_id'], 'Pesanan ini bukan milik Anda.');
        $kembali = route('booking.detail', ['id' => (int) $b['id']]);

        if ($b['status_pesanan'] === 'dibatalkan') {
            flash('warning', 'Pesanan yang sudah dibatalkan tidak dapat dibayar.');
            redirect($kembali);
        }
        if ($b['status_bayar'] === 'lunas') {
            flash('info', 'Pembayaran pesanan ini sudah lunas, tidak perlu mengirim bukti lagi.');
            redirect($kembali);
        }

        $data = $this->validate([
            'metode'          => 'required|in:transfer,bayar_di_tempat',
            'nomor_referensi' => 'max:60',
            'jumlah'          => 'minval:1',
        ], [
            'metode'          => 'Metode pembayaran',
            'nomor_referensi' => 'Nomor referensi',
            'jumlah'          => 'Nominal pembayaran',
        ]);

        // Nominal default = total harga pesanan; pemisah ribuan gaya Indonesia diabaikan
        $kotor = str_replace([' ', 'Rp', 'rp'], '', (string) $this->request->input('jumlah', ''));
        if (str_contains($kotor, ',')) {
            $kotor = str_replace(',', '.', str_replace('.', '', $kotor));
        } else {
            $kotor = str_replace('.', '', $kotor);
        }
        $jumlah = $kotor === '' ? (float) $b['total_harga'] : (float) $kotor;
        if ($jumlah <= 0) {
            $jumlah = (float) $b['total_harga'];
        }

        try {
            $bukti = Uploader::store(
                $this->request->file('bukti'),
                'payments',
                config('upload.proof_types')
            );
        } catch (RuntimeException $e) {
            flash('danger', $e->getMessage());
            back();
        }

        // Metode dibatasi pada nilai yang dikenal kolom ENUM; nilai lain jatuh ke
        // metode saat memesan (jaga-jaga bila form dikirim sebagian).
        $metode = in_array((string) ($data['metode'] ?? ''), ['transfer', 'bayar_di_tempat'], true)
            ? (string) $data['metode']
            : (string) $b['metode_bayar'];

        try {
            $p = Payment::catat([
                'booking_id'      => (int) $b['id'],
                'metode'          => $metode,
                'jumlah'          => $jumlah,
                'nomor_referensi' => trim((string) ($data['nomor_referensi'] ?? '')) !== ''
                    ? (string) $data['nomor_referensi'] : null,
                'bukti_path'      => $bukti,
            ]);
        } catch (RuntimeException $e) {
            flash('danger', $e->getMessage());
            redirect($kembali);
        }

        flash('success', 'Pembayaran ' . rupiah($p['jumlah'] ?? $jumlah)
            . ' tercatat. Status: menunggu verifikasi admin.');
        redirect($kembali);
    }
}

<?php
/**
 * PaymentController — antrean verifikasi bukti pembayaran.
 * Keputusan (terima/tolak) ditulis ke payments sekaligus menyinkronkan
 * status_bayar pesanan melalui Payment::verifikasi().
 */

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Controller;
use App\Core\Error;
use App\Models\Payment;
use RuntimeException;

class PaymentController extends Controller
{
    /** Status yang boleh disaring pada riwayat. */
    private const STATUS = ['menunggu_verifikasi', 'lunas', 'ditolak'];

    public function index(): void
    {
        $status = (string) $this->request->input('status', '');
        if (!in_array($status, self::STATUS, true)) {
            $status = '';
        }

        $dari   = (string) $this->request->input('dari', '');
        $sampai = (string) $this->request->input('sampai', '');

        $this->adminView('admin/pembayaran/index', [
            'antrean'   => Payment::menungguVerifikasi(50),
            'statistik' => Payment::statistik(),
            'riwayat'   => Payment::daftar($status, $this->tanggal($dari), $this->tanggal($sampai)),
            'status'    => $status,
            'dari'      => $dari,
            'sampai'    => $sampai,
            'statusList'=> self::STATUS,
        ]);
    }

    /** Terima atau tolak satu bukti pembayaran. */
    public function verifikasi(string $id): void
    {
        $data = $this->validate([
            'keputusan' => 'required|in:terima,tolak',
            'catatan'   => 'max:255',
        ], [
            'keputusan' => 'Keputusan',
            'catatan'   => 'Catatan admin',
        ]);

        $paymentId = (int) $id;
        $terima    = ($data['keputusan'] ?? '') === 'terima';
        $catatan   = (string) ($data['catatan'] ?? '');

        try {
            Payment::verifikasi($paymentId, $terima, $catatan);
            flash(
                'success',
                ($terima ? 'Pembayaran #' . $paymentId . ' diterima — pesanan menjadi lunas.'
                         : 'Pembayaran #' . $paymentId . ' ditolak. Pelanggan dapat mengunggah ulang bukti.')
            );
        } catch (RuntimeException $e) {
            flash('danger', $e->getMessage());
        } catch (\Throwable $e) {
            Error::abort(500, 'Gagal memproses verifikasi pembayaran.');
        }

        $this->redirect('admin/pembayaran');
    }

    /** Filter tanggal opsional: format salah dianggap kosong (tanpa filter). */
    private function tanggal(string $nilai): string
    {
        if ($nilai === '') {
            return '';
        }
        $ts = strtotime($nilai);
        return ($ts !== false && date('Y-m-d', $ts) === $nilai) ? $nilai : '';
    }
}

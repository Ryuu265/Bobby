<?php
/**
 * Admin\BookingController — manajemen pesanan sisi admin.
 *
 * Status unit TIDAK diubah di sini: status unit dihitung real-time oleh
 * Schedule::statusUnit() berdasarkan pesanan nyata, sehingga tidak perlu
 * dipaksa saat pesanan dikonfirmasi/dimulai.
 */

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\Error;
use App\Core\Logger;
use App\Core\Schedule;
use App\Models\Booking;
use App\Models\Payment;
use App\Models\Unit;
use RuntimeException;

class BookingController extends Controller
{
    private const STATUS = ['menunggu', 'dikonfirmasi', 'berlangsung', 'selesai', 'dibatalkan'];

    /** Tujuan transisi yang boleh dipicu admin (kembali ke 'menunggu' tidak diizinkan). */
    private const TUJUAN = ['dikonfirmasi', 'berlangsung', 'selesai', 'dibatalkan'];

    /** Daftar pesanan + tab status + filter. */
    public function index(): void
    {
        // Pastikan daftar selalu akurat sebelum ditampilkan
        Booking::selesaikanOtomatis();
        Booking::kedaluwarsaOtomatis();

        $status = $this->whitelist((string) $this->request->input('status', ''), self::STATUS);
        $tipe = $this->whitelist((string) $this->request->input('tipe', ''), ['PS3', 'PS4', 'PS5']);
        $tanggal = $this->tanggal((string) $this->request->input('tanggal', ''));
        $cari = mb_substr(trim((string) $this->request->input('cari', '')), 0, 60);
        $halaman = max(1, $this->request->int('halaman', 1));

        $pesanan = Booking::admin($status, $tanggal, $cari, $tipe, $halaman, 15);

        $hitungan = [];
        foreach (self::STATUS as $s) {
            $hitungan[$s] = (new Booking())->countWhere(['status_pesanan' => $s]);
        }

        $this->adminView('admin/pesanan/index', [
            'pesanan'      => $pesanan,
            'hitungan'     => $hitungan,
            'totalSemua'   => (new Booking())->countWhere(),
            'status'       => $status,
            'tipe'         => $tipe,
            'tanggal'      => $tanggal,
            'cari'         => $cari,
            'daftarStatus' => self::STATUS,
            'daftarTipe'   => ['PS5', 'PS4', 'PS3'],
            'filter'       => ['tanggal' => $tanggal, 'tipe' => $tipe, 'cari' => $cari],
            'hariIni'      => date('Y-m-d'),
        ]);
    }

    /** Detail satu pesanan + pembayaran, jadwal unit, dan timeline status. */
    public function detail(string $id): void
    {
        $pesanan = Booking::denganDetail((int) $id);
        if ($pesanan === null) {
            Error::abort(404, 'Pesanan tidak ditemukan.');
        }

        $unitId = (int) $pesanan['unit_id'];
        $tanggal = (string) $pesanan['booking_date'];

        $this->adminView('admin/pesanan/detail', [
            'pesanan'    => $pesanan,
            'pembayaran' => Payment::semuaUntuk((int) $pesanan['id']),
            'bayarTerakhir' => Payment::terakhir((int) $pesanan['id']),
            'slots'      => Schedule::slots($unitId, $tanggal, 1),
            'kepadatan'  => Schedule::kepadatan($unitId, $tanggal),
            'operasional' => Schedule::jamOperasional($tanggal),
            'games'      => Unit::games($unitId),
            'hariIni'    => date('Y-m-d'),
        ]);
    }

    /** Pindahkan status pesanan; transisi ilegal dilempar model sebagai RuntimeException. */
    public function ubahStatus(string $id): void
    {
        $pesanan = (new Booking())->findOrFail((int) $id);
        $status = (string) $this->request->input('status', '');

        if (!in_array($status, self::TUJUAN, true)) {
            flash('danger', 'Status tujuan tidak dikenali atau tidak boleh dipilih admin.');
            back();
        }

        $alasan = mb_substr(trim((string) $this->request->input('alasan_batal', '')), 0, 255);
        $extra = $status === 'dibatalkan'
            ? ['alasan_batal' => $alasan !== '' ? $alasan : 'Dibatalkan oleh admin.']
            : [];

        try {
            $baru = Booking::ubahStatus((int) $pesanan['id'], $status, $extra, Auth::id());
        } catch (RuntimeException $e) {
            flash('danger', $e->getMessage());
            back();
        }

        $pesan = match ($status) {
            'dikonfirmasi' => 'Pesanan ' . $pesanan['invoice_number'] . ' dikonfirmasi. Jadwal kini terkunci untuk pelanggan.',
            'berlangsung'  => 'Sewa ' . $pesanan['invoice_number'] . ' dimulai.',
            'selesai'      => 'Pesanan ' . $pesanan['invoice_number'] . ' selesai. Total ' . rupiah((float) $pesanan['total_harga']) . '.',
            default        => 'Pesanan ' . $pesanan['invoice_number'] . ' dibatalkan.',
        };
        flash('success', $pesan);

        // Kembali ke halaman asal (indeks dengan filter aktif / detail), fallback ke detail
        if (isset($_SERVER['HTTP_REFERER'])) {
            back();
        }
        redirect('admin/pesanan/' . (int) $baru['id']);
    }

    /** Simpan catatan internal admin (tidak ditampilkan ke pelanggan). */
    public function catatan(string $id): void
    {
        $pesanan = (new Booking())->findOrFail((int) $id);
        $data = $this->validate(
            ['catatan_admin' => 'max:255'],
            ['catatan_admin' => 'Catatan admin']
        );

        $isi = trim((string) ($data['catatan_admin'] ?? ''));
        (new Booking())->updateRaw((int) $pesanan['id'], ['catatan_admin' => $isi === '' ? null : $isi]);

        Logger::write(Auth::id(), 'booking', 'catatan', 'bookings', (int) $pesanan['id'],
            'Catatan admin untuk ' . $pesanan['invoice_number'] . ' diperbarui.');

        flash('success', 'Catatan admin tersimpan.');
        redirect('admin/pesanan/' . (int) $pesanan['id']);
    }

    /* =====================================================================
     * UTILITAS INTERNAL
     * ===================================================================== */

    private function whitelist(string $nilai, array $boleh): string
    {
        return in_array($nilai, $boleh, true) ? $nilai : '';
    }

    /** Filter tanggal divalidasi agar query tetap terparameterisasi & rapi. */
    private function tanggal(string $nilai): string
    {
        if ($nilai === '') {
            return '';
        }
        $ts = strtotime($nilai);
        return $ts === false ? '' : date('Y-m-d', $ts);
    }
}

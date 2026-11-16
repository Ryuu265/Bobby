<?php
/**
 * CustomerController — manajemen akun pelanggan.
 * Hanya akun ber-role "pelanggan" yang ditampilkan; perubahan hak akses
 * (role admin/operator) tetap dilakukan admin lewat master data user lain.
 */

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\Error;
use App\Models\Booking;
use App\Models\Payment;
use App\Models\User;

class CustomerController extends Controller
{
    /** Status akun yang boleh dipilih pada filter daftar. */
    private const STATUS = ['aktif', 'nonaktif'];

    /** Status pesanan yang boleh disaring pada halaman detail. */
    private const STATUS_PESANAN = ['menunggu', 'dikonfirmasi', 'berlangsung', 'selesai', 'dibatalkan'];

    public function index(): void
    {
        $cari   = mb_substr((string) $this->request->input('cari', ''), 0, 60);
        $status = (string) $this->request->input('status', '');
        if (!in_array($status, self::STATUS, true)) {
            $status = '';
        }

        $halaman = max(1, $this->request->int('halaman', 1));
        $data    = User::denganStatistik($halaman, 12, $cari, $status);

        // Halaman diminta di luar jangkauan: kembalikan ke halaman terakhir
        $halaman = max(1, min($halaman, (int) $data['terakhir']));

        $this->adminView('admin/pelanggan/index', [
            'pelanggan' => $data,
            'halaman'   => $halaman,
            'cari'      => $cari,
            'status'    => $status,
            'terakhir'  => (int) $data['terakhir'],
            'jumlah'    => [
                'total'     => User::hitung('pelanggan'),
                'terfilter' => (int) $data['total'],
                'operator'  => User::hitung('operator'),
                'admin'     => User::hitung('admin'),
            ],
        ]);
    }

    /** Profil satu pelanggan + riwayat pesanan + pembayaran terakhir. */
    public function detail(string $id): void
    {
        $userId = (int) $id;
        $profil = User::profilPelanggan($userId);

        if (($profil['user'] ?? []) === []) {
            Error::abort(404, 'Pelanggan tidak ditemukan.');
        }

        $halaman = max(1, $this->request->int('halaman', 1));

        // Status pesanan disaring lewat whitelist agar nilai sembarang tidak masuk query
        $status = (string) $this->request->input('status', '');
        if (!in_array($status, self::STATUS_PESANAN, true)) {
            $status = '';
        }

        $riwayat = Booking::riwayat($userId, $status, '', '', $halaman, 8);

        // Pembayaran terakhir dari 8 pesanan teratas (untuk panel ringkasan)
        $pembayaran = [];
        foreach (array_slice($riwayat['rows'], 0, 8) as $b) {
            $p = Payment::terakhir((int) $b['id']);
            if ($p !== null) {
                $pembayaran[(int) $b['id']] = $p;
            }
        }

        $this->adminView('admin/pelanggan/detail', [
            'profil'     => $profil,
            'pelanggan'  => $profil['user'],
            'statistik'  => $profil['statistik'],
            'unitFavorit'=> $profil['unit_favorit'],
            'riwayat'    => $riwayat,
            'pembayaran' => $pembayaran,
            'halaman'    => max(1, min($halaman, (int) $riwayat['terakhir'])),
            'terakhir'   => (int) $riwayat['terakhir'],
            'status'     => $status,
            'statusList' => self::STATUS_PESANAN,
        ]);
    }

    /**
     * Aktifkan/nonaktifkan akun. Sengaja tidak menyentuh kolom role di sini
     * agar satu endpoint tidak bisa dipakai menaikkan hak akses.
     */
    public function ubahStatus(string $id): void
    {
        $data = $this->validate([
            'status' => 'required|in:aktif,nonaktif',
        ], [
            'status' => 'Status akun',
        ]);

        $userId  = (int) $id;
        $status  = (string) $data['status'];
        $target  = (new User())->find($userId);

        if ($target === null) {
            Error::abort(404, 'Akun tidak ditemukan.');
        }
        if ($userId === Auth::id()) {
            flash('danger', 'Anda tidak dapat menonaktifkan akun yang sedang dipakai.');
            $this->redirect('admin/pelanggan');
        }
        if ($target['role'] !== 'pelanggan') {
            flash('danger', 'Halaman ini hanya untuk akun pelanggan.');
            $this->redirect('admin/pelanggan');
        }

        User::ubahAkses($userId, 'status', $status);
        flash('success', 'Status akun ' . $target['nama'] . ' diubah menjadi ' . status_label($status) . '.');

        $this->redirect('admin/pelanggan/' . $userId);
    }

    /** Atur ulang password pelanggan (admin tidak melihat password lama). */
    public function resetPassword(string $id): void
    {
        $data = $this->validate([
            'password_baru' => 'required|min:8|max:60',
        ], [
            'password_baru' => 'Password baru',
        ]);

        $userId = (int) $id;
        $target = (new User())->find($userId);
        if ($target === null) {
            Error::abort(404, 'Akun tidak ditemukan.');
        }
        // Tanpa batas ini, seorang operator dapat memasang password baru pada
        // akun admin lalu masuk sebagai admin (eskalasi hak akses).
        if ($target['role'] !== 'pelanggan') {
            flash('danger', 'Halaman ini hanya untuk akun pelanggan.');
            $this->redirect('admin/pelanggan');
        }

        User::updatePassword($userId, (string) $data['password_baru']);
        flash(
            'success',
            'Password ' . $target['nama'] . ' berhasil diatur ulang. Sampaikan password baru ini secara pribadi.'
        );

        $this->redirect('admin/pelanggan/' . $userId);
    }
}

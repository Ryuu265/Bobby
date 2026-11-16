<?php
/**
 * AccountController — profil pelanggan (identitas & password).
 */

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Models\Booking;
use App\Models\User;

class AccountController extends Controller
{
    /** GET /profil */
    public function edit(): void
    {
        $user = $this->user();
        if ($user === null) {
            redirect('masuk');
        }

        $this->view('pages/profil/edit', [
            'rekap'    => Booking::rekapPelanggan((int) $user['id']),
            'terakhir' => Booking::riwayat((int) $user['id'], '', '', '', 1, 3)['rows'],
        ]);
    }

    /** POST /profil */
    public function perbarui(): void
    {
        $user = $this->user();
        if ($user === null) {
            redirect('masuk');
        }

        $data = $this->validate([
            'nama'    => 'required|min:3|max:100',
            'email'   => 'required|email|max:150|unique:users,email',
            'telepon' => 'phone',
            'alamat'  => 'max:255',
        ], [
            'nama'    => 'Nama lengkap',
            'email'   => 'Email',
            'telepon' => 'Nomor telepon',
            'alamat'  => 'Alamat',
        ]);

        (new User())->update((int) $user['id'], [
            'nama'    => (string) $data['nama'],
            'email'   => strtolower(trim((string) $data['email'])),
            'telepon' => trim((string) ($data['telepon'] ?? '')) !== '' ? (string) $data['telepon'] : null,
            'alamat'  => trim((string) ($data['alamat'] ?? '')) !== '' ? (string) $data['alamat'] : null,
        ]);

        flash('success', 'Profil berhasil diperbarui.');
        redirect('profil');
    }

    /** POST /profil/password */
    public function gantiPassword(): void
    {
        $user = $this->user();
        if ($user === null) {
            redirect('masuk');
        }

        $data = $this->validate([
            'password_lama' => 'required',
            'password'      => 'required|min:8|max:60|confirmed',
        ], [
            'password_lama' => 'Password saat ini',
            'password'      => 'Password baru',
        ]);

        if (!password_verify((string) $data['password_lama'], (string) Auth::hashPenggunaAktif())) {
            flash('danger', 'Password saat ini tidak cocok. Silakan coba lagi.');
            redirect('profil');
        }

        if ((string) $data['password_lama'] === (string) $data['password']) {
            flash('danger', 'Password baru tidak boleh sama dengan password saat ini.');
            redirect('profil');
        }

        User::updatePassword((int) $user['id'], (string) $data['password']);
        flash('success', 'Password berhasil diganti. Gunakan password baru saat login berikutnya.');
        redirect('profil');
    }
}

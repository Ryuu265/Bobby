<?php
/**
 * AuthController — registrasi, login, dan logout.
 * Proteksi: validasi input server-side, hash bcrypt, jeda waktu saat gagal,
 * dan regenerasi id sesi ketika login berhasil.
 */

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\Logger;
use App\Core\View;
use App\Models\User;

class AuthController extends Controller
{
    /** Jeda singkat saat percobaan login gagal untuk memperlambat tebak-tebakan. */
    private const JEDA_GAGAL_US = 600000; // 0,6 detik

    public function loginForm(): void
    {
        View::set('title', 'Masuk — ' . config('app.name'));
        View::set('page_tujuan', 'Masuk ke akun');
        $this->view('pages/auth/login');
    }

    public function login(): void
    {
        $data = $this->validate([
            'email'    => 'required|email|max:150',
            'password' => 'required|min:6',
        ], [
            'email'    => 'Email',
            'password' => 'Password',
        ]);

        if (Auth::attempt((string) $data['email'], (string) $data['password'])) {
            flash('success', 'Selamat datang kembali, ' . (Auth::user()['nama'] ?? '') . '!');

            // Kembalikan ke halaman yang sebelumnya diminta, bila ada
            $tujuan = $_SESSION['_after_login'] ?? null;
            unset($_SESSION['_after_login']);

            if ($tujuan) {
                redirect($tujuan);
            }
            redirect(Auth::isAdmin() ? 'admin/dashboard' : '');
        }

        usleep(self::JEDA_GAGAL_US);
        $_SESSION['_old'] = ['email' => $data['email']];
        redirect('masuk');
    }

    public function registerForm(): void
    {
        View::set('title', 'Daftar Akun — ' . config('app.name'));
        $this->view('pages/auth/register');
    }

    public function register(): void
    {
        $data = $this->validate([
            'nama'     => 'required|min:3|max:100',
            'email'    => 'required|email|max:150|unique:users,email',
            'telepon'  => 'phone',
            'alamat'   => 'max:255',
            'password' => 'required|min:8|max:60|confirmed',
        ], [
            'nama'     => 'Nama lengkap',
            'email'    => 'Email',
            'telepon'  => 'Nomor telepon',
            'alamat'   => 'Alamat',
            'password' => 'Password',
        ]);

        // Simpan ulang old input agar form terisi kembali setelah redirect
        $_SESSION['_old'] = [
            'nama' => $data['nama'], 'email' => $data['email'],
            'telepon' => $data['telepon'] ?? '', 'alamat' => $data['alamat'] ?? '',
        ];

        $id = User::daftar(
            (string) $data['nama'],
            (string) $data['email'],
            (string) $data['password'],
            (string) ($data['telepon'] ?? '') !== '' ? (string) $data['telepon'] : null,
            (string) ($data['alamat'] ?? '') !== '' ? (string) $data['alamat'] : null
        );

        $user = (new User())->find($id);
        if ($user === null) {
            flash('danger', 'Akun gagal dibuat. Silakan coba lagi.');
            redirect('daftar');
        }

        Auth::login($user);
        Logger::write($id, 'auth', 'daftar', 'users', $id, 'Pendaftaran akun pelanggan baru.');
        flash('success', 'Akun berhasil dibuat. Selamat datang di Qengamestation!');
        redirect('');
    }

    public function logout(): void
    {
        Auth::logout();
        // Sesi baru diperlukan agar flash "Anda telah keluar" dapat ditampilkan
        session_start();
        flash('info', 'Anda telah keluar. Sampai jumpa lagi!');
        redirect('');
    }
}

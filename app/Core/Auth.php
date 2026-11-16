<?php
/**
 * Auth — autentikasi berbasis sesi.
 * Password diverifikasi dengan password_verify() terhadap hash bcrypt.
 * Saat login berhasil hash lama otomatis di-upgrade bila biaya bcrypt diubah.
 */

namespace App\Core;

use App\Models\User;

class Auth
{
    private static ?array $user = null;

    /** Hash bcrypt buang-buang waktu: membuat waktu respon login tetap sama
     *  saat email tidak ditemukan (mengurangi celah user enumeration). */
    private const DUMMY_HASH = '$2y$10$N9qo8uLOickgx2ZMRZoMyeIjZAgcfl7p92ldGxad68LJZdL17lhWy';

    /** Coba login. Mengembalikan true/false, pesan error lewat flash. */
    public static function attempt(string $email, string $password): bool
    {
        $user = User::findByEmail($email);
        $hash = is_array($user) ? (string) $user['password'] : self::DUMMY_HASH;

        $ok = password_verify($password, $hash);

        if (!$ok || $user === null || $user['status'] !== 'aktif') {
            Logger::attempt(
                is_array($user) ? (int) $user['id'] : null,
                'auth', 'gagal_login', 'users',
                is_array($user) ? (int) $user['id'] : 0,
                'Percobaan login gagal untuk email ' . $email
            );
            flash('danger', $ok ? 'Akun Anda dinonaktifkan. Hubungi admin Qengamestation.'
                                : 'Email atau password salah.');
            return false;
        }

        if (password_needs_rehash($hash, PASSWORD_BCRYPT, ['cost' => 10])) {
            User::updatePassword((int) $user['id'], $password);
        }

        self::login($user);
        Logger::attempt((int) $user['id'], 'auth', 'login', 'users', (int) $user['id'],
            $user['nama'] . ' masuk ke sistem.');
        return true;
    }

    /** Simpan id pengguna ke sesi (bukan seluruh data, agar selalu segar). */
    public static function login(array $user): void
    {
        $_SESSION['user_id'] = (int) $user['id'];
        $_SESSION['user_role'] = (string) $user['role'];
        $_SESSION['_regenerate'] = true; // ganti id sesi => cegah session fixation
        self::$user = null;
        User::touchLogin((int) $user['id']);
    }

    public static function logout(): void
    {
        if (self::check()) {
            Logger::attempt(self::id(), 'auth', 'logout', 'users', self::id(), 'Pengguna keluar dari sistem.');
        }
        self::$user = null;
        unset($_SESSION['user_id'], $_SESSION['user_role']);
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $p = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
        }
        session_destroy();
    }

    public static function check(): bool
    {
        return isset($_SESSION['user_id']);
    }

    public static function id(): ?int
    {
        return isset($_SESSION['user_id']) ? (int) $_SESSION['user_id'] : null;
    }

    /**
     * Data pengguna aktif (di-cache per request, selalu dari database).
     * Hash password dibuang dari hasil: nilai itu tidak dibutuhkan tampilan
     * mana pun, dan sekali tercetak ke log/JSON ia menjadi rahasia bocor.
     */
    public static function user(): ?array
    {
        if (!self::check()) {
            return null;
        }
        if (self::$user === null) {
            self::$user = (new User())->find((int) $_SESSION['user_id']) ?? [];
            if (self::$user === []) {
                self::logout();
                return null;
            }
            unset(self::$user['password']);
        }
        return self::$user;
    }

    /** Hash bcrypt pengguna aktif — hanya untuk verifikasi password lama. */
    public static function hashPenggunaAktif(): ?string
    {
        $row = (new User())->find((int) ($_SESSION['user_id'] ?? 0));
        return is_array($row) ? (string) $row['password'] : null;
    }

    public static function role(): ?string
    {
        return isset($_SESSION['user_role']) ? (string) $_SESSION['user_role'] : null;
    }

    /** Admin dan operator boleh masuk area dashboard. */
    public static function isAdmin(): bool
    {
        return in_array(self::role(), ['admin', 'operator'], true);
    }

    public static function isOnlyAdmin(): bool
    {
        return self::role() === 'admin';
    }
}

<?php
/**
 * Csrf — token per sesi yang wajib ada pada setiap form POST/PUT/DELETE.
 * Tanpa token yang valid, request ditolak (419).
 */

namespace App\Core;

class Csrf
{
    public static function token(): string
    {
        if (empty($_SESSION['_csrf'])) {
            $_SESSION['_csrf'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['_csrf'];
    }

    /** Bandingkan token dengan perbandingan konstan waktu (anti timing attack). */
    public static function verify(mixed $token): void
    {
        if (!is_string($token) || $token === '' || !hash_equals(self::token(), $token)) {
            Error::abort(
                419,
                'Sesi halaman sudah kedaluwarsa. Muat ulang halaman lalu ulangi tindakan Anda.'
            );
        }
    }
}

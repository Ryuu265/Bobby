<?php
/**
 * User — akun pelanggan, operator, dan admin.
 * Password selalu di-hash dengan bcrypt (cost 10) lewat self::hash().
 */

namespace App\Models;

use App\Core\Auth;
use App\Core\Database;
use App\Core\Logger;
use App\Core\Model;

class User extends Model
{
    protected string $table = 'users';
    protected array $fillable = ['nama', 'email', 'telepon', 'password', 'role', 'status', 'alamat'];

    /** Cari berdasarkan email (kolom unik). */
    public static function findByEmail(string $email): ?array
    {
        return Database::instance()->one('SELECT * FROM users WHERE email = ? LIMIT 1', [strtolower(trim($email))]);
    }

    /** Registrasi pelanggan baru. */
    public static function daftar(string $nama, string $email, string $password, ?string $telepon = null, ?string $alamat = null): int
    {
        $id = Database::instance()->insert('users', [
            'nama'     => $nama,
            'email'    => strtolower(trim($email)),
            'telepon'  => $telepon,
            'password' => self::hash($password),
            'role'     => 'pelanggan',
            'status'   => 'aktif',
            'alamat'   => $alamat,
        ]);
        Logger::write($id, 'user', 'buat', 'users', $id, "Akun pelanggan baru terdaftar: {$email}");
        return $id;
    }

    public static function hash(string $password): string
    {
        return password_hash($password, PASSWORD_BCRYPT, ['cost' => 10]);
    }

    /** Simpan hash baru (ganti password / rehash otomatis saat login). */
    public static function updatePassword(int $id, string $password): void
    {
        Database::instance()->update('users', $id, ['password' => self::hash($password)]);
    }

    public static function touchLogin(int $id): void
    {
        Database::instance()->run('UPDATE users SET last_login_at = NOW() WHERE id = ?', [$id]);
    }

    /**
     * Daftar pelanggan + ringkasan transaksi (halaman manajemen pelanggan).
     * Penyaringan keyword/status dilakukan di PHP agar tetap konsisten dengan
     * hasil agregasi GROUP BY.
     */
    public static function denganStatistik(int $halaman = 1, int $perHalaman = 10, string $cari = '', string $status = ''): array
    {
        $rows = Database::instance()->all(
            "SELECT u.*,
                    COUNT(b.id)                     AS jumlah_pesanan,
                    COALESCE(SUM(b.total_harga), 0) AS total_bayar,
                    MAX(b.booking_date)             AS terakhir_sewa
               FROM users u
               LEFT JOIN bookings b
                 ON b.user_id = u.id
                AND b.status_pesanan IN ('dikonfirmasi','berlangsung','selesai')
              WHERE u.role = 'pelanggan'
           GROUP BY u.id
           ORDER BY jumlah_pesanan DESC, u.nama ASC"
        );

        if ($cari !== '' || $status !== '') {
            $jarum = mb_strtolower($cari);
            $rows = array_values(array_filter($rows, static function (array $r) use ($jarum, $cari, $status): bool {
                if ($status !== '' && $r['status'] !== $status) {
                    return false;
                }
                if ($cari === '') {
                    return true;
                }
                foreach (['nama', 'email', 'telepon'] as $kolom) {
                    if (str_contains(mb_strtolower((string) $r[$kolom]), $jarum)) {
                        return true;
                    }
                }
                return false;
            }));
        }

        $total = count($rows);
        $offset = max(0, ($halaman - 1) * $perHalaman);

        return [
            'rows'        => array_slice($rows, $offset, $perHalaman),
            'total'       => $total,
            'halaman'     => $halaman,
            'per_halaman' => $perHalaman,
            'terakhir'    => max(1, (int) ceil($total / $perHalaman)),
        ];
    }

    /** Ringkasan satu pelanggan untuk halaman detail. */
    public static function profilPelanggan(int $id): array
    {
        $user = Database::instance()->one('SELECT * FROM users WHERE id = ? LIMIT 1', [$id]) ?? [];

        $statistik = Database::instance()->one(
            "SELECT COUNT(*) AS jumlah,
                    COALESCE(SUM(total_harga), 0) AS omzet,
                    COALESCE(SUM(durasi_jam), 0)  AS jam
               FROM bookings
              WHERE user_id = ? AND status_pesanan IN ('dikonfirmasi','berlangsung','selesai')",
            [$id]
        ) ?? ['jumlah' => 0, 'omzet' => 0, 'jam' => 0];

        $unitFavorit = Database::instance()->one(
            "SELECT u.kode_unit, u.nama, COUNT(*) AS sering
               FROM bookings b JOIN units u ON u.id = b.unit_id
              WHERE b.user_id = ? AND b.status_pesanan <> 'dibatalkan'
              GROUP BY u.id ORDER BY sering DESC LIMIT 1",
            [$id]
        );

        return ['user' => $user, 'statistik' => $statistik, 'unit_favorit' => $unitFavorit];
    }

    public static function hitung(?string $role = null): int
    {
        $sql = 'SELECT COUNT(*) FROM users';
        $params = [];
        if ($role !== null) {
            $sql .= ' WHERE role = ?';
            $params[] = $role;
        }
        return (int) Database::instance()->scalar($sql, $params);
    }

    /** Ganti role / status oleh admin (whitelist kolom agar tidak sembarang). */
    public static function ubahAkses(int $id, string $field, string $value): void
    {
        if (!in_array($field, ['role', 'status'], true)) {
            throw new \InvalidArgumentException('Kolom akses tidak valid.');
        }
        Database::instance()->update('users', $id, [$field => $value]);
        Logger::write(Auth::id(), 'user', 'ubah', 'users', $id, "Ubah {$field} user #{$id} menjadi {$value}.");
    }

    /** Semua pelanggan aktif untuk dropdown pilihan admin. */
    public static function semuaPelanggan(): array
    {
        return Database::instance()->all(
            "SELECT id, nama, email, telepon FROM users
              WHERE role = 'pelanggan' AND status = 'aktif' ORDER BY nama"
        );
    }
}

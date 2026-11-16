<?php
/**
 * Logger — jejak audit (tabel activity_logs).
 * Dipanggil setiap aksi penting: login, CRUD, konfirmasi, verifikasi bayar.
 * Kegagalan menulis log TIDAK boleh menggagalkan aksi utama pengguna.
 */

namespace App\Core;

class Logger
{
    /**
     * @param int|null $userId  null = sistem / belum login
     * @param string   $modul   unit, game, booking, payment, paket, user, auth
     * @param string   $aksi    buat, ubah, hapus, konfirmasi, mulai, selesai, login
     */
    public static function write(
        ?int $userId,
        string $modul,
        string $aksi,
        string $entitas,
        ?int $entitasId,
        string $deskripsi,
        ?array $old = null,
        ?array $new = null
    ): void {
        try {
            Database::instance()->insert('activity_logs', [
                'user_id'    => $userId,
                'modul'      => $modul,
                'aksi'       => $aksi,
                'entitas'    => $entitas,
                'entitas_id' => $entitasId,
                'deskripsi'  => mb_substr($deskripsi, 0, 255),
                'nilai_lama' => $old === null ? null : json_encode(self::ringkas($old), JSON_UNESCAPED_UNICODE),
                'nilai_baru' => $new === null ? null : json_encode(self::ringkas($new), JSON_UNESCAPED_UNICODE),
                'ip_address' => self::ip(),
            ]);
        } catch (\Throwable $e) {
            error_log('[audit-gagal] ' . $e->getMessage());
        }
    }

    /** Versi singkat untuk peristiwa autentikasi. */
    public static function attempt(?int $userId, string $modul, string $aksi, string $entitas, int $entitasId, string $deskripsi): void
    {
        self::write($userId, $modul, $aksi, $entitas, $entitasId, $deskripsi);
    }

    /** Buang kolom sensitif sebelum disimpan ke log. */
    private static function ringkas(array $data): array
    {
        $peka = ['password', 'remember_token', '_token', 'bukti_path'];
        return array_diff_key($data, array_flip($peka));
    }

    /** IP klien, aman dipanggil sebelum Request dibuat. */
    private static function ip(): ?string
    {
        return isset($_SERVER['REMOTE_ADDR']) ? (string) $_SERVER['REMOTE_ADDR'] : null;
    }
}

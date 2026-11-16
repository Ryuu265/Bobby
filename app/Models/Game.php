<?php
/**
 * Game — katalog permainan yang tersedia di rental.
 */

namespace App\Models;

use App\Core\Database;
use App\Core\Model;

class Game extends Model
{
    protected string $table = 'games';
    protected array $fillable = ['judul', 'platform', 'genre', 'cover_path', 'rating', 'deskripsi', 'status'];

    /** Katalog untuk pelanggan (hanya yang aktif). */
    public static function katalog(string $platform = '', string $genre = '', string $cari = ''): array
    {
        $sql = 'SELECT g.*,
                       (SELECT COUNT(*) FROM unit_games ug WHERE ug.game_id = g.id) AS jumlah_unit
                  FROM games g
                 WHERE g.status = "aktif"';
        $params = [];

        if ($platform !== '') {
            $sql .= ' AND (g.platform = ? OR g.platform = "Multi")';
            $params[] = $platform;
        }
        if ($genre !== '') {
            $sql .= ' AND g.genre = ?';
            $params[] = $genre;
        }
        if ($cari !== '') {
            $sql .= ' AND g.judul LIKE ?';
            $params[] = '%' . $cari . '%';
        }
        $sql .= ' ORDER BY g.judul';

        return Database::instance()->all($sql, $params);
    }

    /** Semua game termasuk nonaktif (kelola admin). */
    public static function daftar(string $cari = ''): array
    {
        $sql = 'SELECT g.*,
                       (SELECT COUNT(*) FROM unit_games ug WHERE ug.game_id = g.id) AS jumlah_unit
                  FROM games g';
        $params = [];
        if ($cari !== '') {
            $sql .= ' WHERE g.judul LIKE ? OR g.genre LIKE ?';
            $params = ['%' . $cari . '%', '%' . $cari . '%'];
        }
        $sql .= ' ORDER BY g.status DESC, g.judul';
        return Database::instance()->all($sql, $params);
    }

    public static function daftarGenre(): array
    {
        return array_pluck(
            Database::instance()->all('SELECT DISTINCT genre FROM games ORDER BY genre'),
            'genre'
        );
    }

    /** Unit yang memuat game tertentu (halaman detail game). */
    public static function unitTerpasang(int $gameId): array
    {
        return Database::instance()->all(
            'SELECT u.* FROM units u
              JOIN unit_games ug ON ug.unit_id = u.id
             WHERE ug.game_id = ?
             ORDER BY u.kode_unit',
            [$gameId]
        );
    }

    public static function tersimpan(int $gameId): array
    {
        return Database::instance()->all(
            'SELECT ug.unit_id, ug.game_id FROM unit_games ug WHERE ug.game_id = ?',
            [$gameId]
        );
    }
}

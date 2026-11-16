<?php
/**
 * UnitGame — tabel relasi many-to-many antara unit dan game.
 */

namespace App\Models;

use App\Core\Database;
use App\Core\Model;

class UnitGame extends Model
{
    protected string $table = 'unit_games';
    protected array $fillable = ['unit_id', 'game_id'];

    public static function gameUntuk(int $unitId): array
    {
        return array_pluck(
            Database::instance()->all('SELECT game_id FROM unit_games WHERE unit_id = ?', [$unitId]),
            'game_id'
        );
    }

    public static function unitUntuk(int $gameId): array
    {
        return array_pluck(
            Database::instance()->all('SELECT unit_id FROM unit_games WHERE game_id = ?', [$gameId]),
            'unit_id'
        );
    }

    /** Tempel game ke beberapa unit sekaligus (bulk admin). */
    public static function pasang(int $gameId, array $unitIds): int
    {
        $db = Database::instance();
        $jumlah = 0;
        foreach (array_unique(array_map('intval', $unitIds)) as $unitId) {
            if ($unitId > 0) {
                $jumlah += $db->run(
                    'INSERT IGNORE INTO unit_games (unit_id, game_id) VALUES (?, ?)',
                    [$unitId, $gameId]
                )->rowCount();
            }
        }
        return $jumlah;
    }

    public static function lepas(int $gameId, int $unitId): void
    {
        Database::instance()->run('DELETE FROM unit_games WHERE game_id = ? AND unit_id = ?', [$gameId, $unitId]);
    }
}

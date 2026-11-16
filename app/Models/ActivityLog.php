<?php
/**
 * ActivityLog — jejak audit seluruh aksi penting.
 */

namespace App\Models;

use App\Core\Database;
use App\Core\Model;

class ActivityLog extends Model
{
    protected string $table = 'activity_logs';
    protected array $fillable = [
        'user_id', 'modul', 'aksi', 'entitas', 'entitas_id',
        'deskripsi', 'nilai_lama', 'nilai_baru', 'ip_address',
    ];

    /** Log terbaru untuk dashboard. */
    public static function terbaru(int $batas = 12): array
    {
        return Database::instance()->all(
            'SELECT l.*, u.nama, u.role FROM activity_logs l
              LEFT JOIN users u ON u.id = l.user_id
             ORDER BY l.created_at DESC LIMIT ' . max(1, min($batas, 100))
        );
    }

    /** Log dengan filter untuk halaman aktivitas. */
    public static function terfilter(string $modul = '', int $halaman = 1, int $perHalaman = 25): array
    {
        $offset = max(0, ($halaman - 1) * $perHalaman);
        $where = '';
        $params = [];
        if ($modul !== '') {
            $where = 'WHERE l.modul = ?';
            $params[] = $modul;
        }

        $rows = Database::instance()->all(
            "SELECT l.*, u.nama FROM activity_logs l
               LEFT JOIN users u ON u.id = l.user_id
               {$where}
              ORDER BY l.created_at DESC
              LIMIT {$perHalaman} OFFSET {$offset}",
            $params
        );

        return [
            'rows'  => $rows,
            'total' => (int) (Database::instance()->scalar(
                'SELECT COUNT(*) FROM activity_logs l ' . $where,
                $params
            ) ?? 0),
        ];
    }

    /** Bersihkan log lama (dipakai perintah pembersihan). */
    public static function hapusLebihTua(int $hari): int
    {
        return Database::instance()->run(
            'DELETE FROM activity_logs WHERE created_at < (NOW() - INTERVAL ? DAY)',
            [max(1, $hari)]
        )->rowCount();
    }
}

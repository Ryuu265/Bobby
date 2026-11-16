<?php
/**
 * Unit — konsol/ruang bermain yang bisa disewa.
 */

namespace App\Models;

use App\Core\Database;
use App\Core\Schedule;
use App\Core\Logger;
use App\Core\Model;

class Unit extends Model
{
    protected string $table = 'units';
    protected array $fillable = [
        'kode_unit', 'nama', 'tipe', 'kategori', 'harga_per_jam',
        'kapasitas', 'status', 'deskripsi', 'foto_path',
    ];

    /** Semua unit + jumlah game terpasang + status real-time. */
    public static function katalog(string $tipe = '', string $status = ''): array
    {
        $sql = 'SELECT u.*,
                       (SELECT COUNT(*) FROM unit_games ug WHERE ug.unit_id = u.id) AS jumlah_game
                  FROM units u';
        $kondisi = [];
        $params = [];
        if ($tipe !== '') {
            $kondisi[] = 'u.tipe = ?';
            $params[] = $tipe;
        }
        if ($kondisi !== []) {
            $sql .= ' WHERE ' . implode(' AND ', $kondisi);
        }
        $sql .= ' ORDER BY FIELD(u.tipe, "PS5", "PS4", "PS3"), u.kode_unit';

        $rows = Database::instance()->all($sql, $params);

        // Status dihitung ulang dari pesanan nyata (bukan cache kolom). Filter
        // status ikut dilakukan di PHP supaya hasil dan badge tidak berbeda.
        $hasil = [];
        foreach ($rows as $row) {
            $row['status_realtime'] = Schedule::statusUnit($row);
            if ($status !== '' && $row['status_realtime'] !== $status) {
                continue;
            }
            $hasil[] = $row;
        }

        return $hasil;
    }

    public static function semua(): array
    {
        return Database::instance()->all('SELECT * FROM units ORDER BY FIELD(tipe, "PS5", "PS4", "PS3"), kode_unit');
    }

    /** Unit yang boleh dipesan pelanggan (tidak dalam maintenance). */
    public static function bisaDipesan(): array
    {
        return Database::instance()->all(
            "SELECT * FROM units WHERE status <> 'maintenance'
              ORDER BY FIELD(tipe, 'PS5', 'PS4', 'PS3'), kode_unit"
        );
    }

    public static function findByKode(string $kode): ?array
    {
        return Database::instance()->one('SELECT * FROM units WHERE kode_unit = ? LIMIT 1', [$kode]);
    }

    /** Daftar game yang terpasang di satu unit. */
    public static function games(int $unitId): array
    {
        return Database::instance()->all(
            'SELECT g.* FROM games g
              JOIN unit_games ug ON ug.game_id = g.id
             WHERE ug.unit_id = ? AND g.status = "aktif"
             ORDER BY g.judul',
            [$unitId]
        );
    }

    /** Ganti daftar game sebuah unit (sinkron dari form centang admin). */
    public static function sinkronGames(int $unitId, array $gameIds): void
    {
        $db = Database::instance();
        $db->run('DELETE FROM unit_games WHERE unit_id = ?', [$unitId]);
        foreach (array_unique(array_map('intval', $gameIds)) as $gameId) {
            if ($gameId > 0) {
                $db->run('INSERT IGNORE INTO unit_games (unit_id, game_id) VALUES (?, ?)', [$unitId, $gameId]);
            }
        }
    }

    /** Kode unit berikutnya, contoh: PS5-04. */
    public static function kodeBerikutnya(string $tipe): string
    {
        $terakhir = Database::instance()->scalar(
            'SELECT MAX(CAST(SUBSTRING_INDEX(kode_unit, "-", -1) AS UNSIGNED))
               FROM units WHERE tipe = ?',
            [$tipe]
        );
        return sprintf('%s-%02d', $tipe, (int) $terakhir + 1);
    }

    public static function ubahStatus(int $id, string $status, ?int $oleh = null): void
    {
        Database::instance()->update('units', $id, ['status' => $status]);
        $unit = (new self())->find($id);
        Logger::write($oleh, 'unit', 'ubah', 'units', $id,
            'Status unit ' . ($unit['kode_unit'] ?? $id) . ' diubah menjadi ' . $status . '.');
    }

    /**
     * Ringkasan untuk kartu statistik dashboard & halaman kelola unit.
     * Status dihitung ulang dari pesanan nyata (bukan kolom cache `units.status`)
     * agar angkanya sama dengan badge yang tampil di tabel. Cukup 2 query:
     * satu untuk daftar unit, satu untuk agregat pesanan hari ini.
     */
    public static function statistik(): array
    {
        $hasil = ['total' => 0, 'tersedia' => 0, 'dipakai' => 0, 'dipesan' => 0, 'maintenance' => 0];

        $units = Database::instance()->all('SELECT id, status FROM units');
        $hariIni = date('Y-m-d');
        $sekarang = Schedule::keJam(Schedule::toMenit(date('H:i')));

        // Peta unit_id => true untuk dua kondisi: sedang dipakai, dan punya
        // pesanan tersisa hari ini. Aturan irisan jamnya sama dengan
        // Schedule::statusUnit() supaya angka dan badge tidak berbeda.
        $dipakai = [];
        $berpesan = [];
        $rows = Database::instance()->all(
            "SELECT unit_id,
                    MAX(CASE WHEN status_pesanan IN ('dikonfirmasi','berlangsung')
                              AND start_time <= ? AND end_time > ? THEN 1 ELSE 0 END) AS sedang_dipakai
               FROM bookings
              WHERE booking_date = ? AND end_time > ?
                AND status_pesanan IN ('menunggu','dikonfirmasi','berlangsung')
              GROUP BY unit_id",
            [$sekarang, $sekarang, $hariIni, $sekarang]
        );
        foreach ($rows as $row) {
            $berpesan[(int) $row['unit_id']] = true;
            if ((int) $row['sedang_dipakai'] === 1) {
                $dipakai[(int) $row['unit_id']] = true;
            }
        }

        foreach ($units as $unit) {
            $id = (int) $unit['id'];
            if ($unit['status'] === 'maintenance') {
                $status = 'maintenance';
            } elseif (isset($dipakai[$id])) {
                $status = 'dipakai';
            } elseif (isset($berpesan[$id])) {
                $status = 'dipesan';
            } else {
                $status = 'tersedia';
            }

            $hasil['total']++;
            $hasil[$status]++;
        }

        return $hasil;
    }

    /** Kinerja per unit (dipakai laporan & halaman kelola unit). */
    public static function kinerja(string $dari, string $sampai): array
    {
        return Database::instance()->all(
            "SELECT u.id, u.kode_unit, u.nama, u.tipe, u.status, u.harga_per_jam,
                    COUNT(b.id)                       AS jumlah_pesanan,
                    COALESCE(SUM(b.durasi_jam), 0)    AS total_jam,
                    COALESCE(SUM(b.total_harga), 0)   AS omzet
               FROM units u
               LEFT JOIN bookings b
                 ON b.unit_id = u.id
                AND b.booking_date BETWEEN ? AND ?
                AND b.status_pesanan IN ('dikonfirmasi','berlangsung','selesai')
              GROUP BY u.id
              ORDER BY omzet DESC",
            [$dari, $sampai]
        );
    }
}

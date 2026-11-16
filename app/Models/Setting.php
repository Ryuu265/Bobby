<?php
/**
 * Setting — pembacaan tabel settings (key-value) dengan cache per request.
 * Dipakai landing page (jam buka, alamat) dan perhitungan biaya (tarif controller).
 */

namespace App\Models;

use App\Core\Database;
use App\Core\Model;

class Setting extends Model
{
    protected string $table = 'settings';
    protected string $primaryKey = 'key_name';
    protected array $fillable = ['key_name', 'value_text', 'tipe', 'label'];

    private static ?array $cache = null;

    /** Ambil semua settings sebagai key => value (di-cache sekali query). */
    public static function allCached(): array
    {
        if (self::$cache === null) {
            $rows = Database::instance()->all('SELECT key_name, value_text FROM settings');
            self::$cache = array_column($rows, 'value_text', 'key_name');
        }
        return self::$cache;
    }

    /** Nilai satu kunci dengan fallback. */
    public static function get(string $key, mixed $default = null): mixed
    {
        $all = self::allCached();
        $value = $all[$key] ?? null;
        return ($value === null || $value === '') ? $default : $value;
    }

    /** Simpan/perbarui nilai kunci (dipakai halaman pengaturan admin). */
    public static function set(string $key, mixed $value): void
    {
        Database::instance()->run(
            'INSERT INTO settings (key_name, value_text) VALUES (?, ?)
             ON DUPLICATE KEY UPDATE value_text = VALUES(value_text)',
            [$key, (string) $value]
        );
        self::$cache = null; // buang cache agar nilai baru langsung terpakai
    }

    public static function setBanyak(array $data): void
    {
        foreach ($data as $key => $value) {
            self::set((string) $key, $value);
        }
    }

    /** Semua baris mentah untuk tabel kelola pengaturan. */
    public static function daftar(): array
    {
        return Database::instance()->all('SELECT * FROM settings ORDER BY label');
    }
}

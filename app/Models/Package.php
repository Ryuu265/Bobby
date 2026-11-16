<?php
/**
 * Package — paket & promo harga sewa.
 */

namespace App\Models;

use App\Core\Database;
use App\Core\Model;

class Package extends Model
{
    protected string $table = 'packages';
    protected array $fillable = [
        'nama', 'deskripsi', 'durasi_jam', 'tipe_diskon', 'diskon_persen',
        'diskon_nilai', 'harga', 'berlaku_mulai', 'berlaku_akhir', 'status',
    ];

    public static function semuaAktif(): array
    {
        return Database::instance()->all(
            "SELECT * FROM packages WHERE status = 'aktif'
              ORDER BY diskon_nilai DESC, durasi_jam"
        );
    }

    public static function daftar(): array
    {
        return Database::instance()->all('SELECT * FROM packages ORDER BY status DESC, berlaku_akhir DESC');
    }

    /** Paket yang berlaku pada tanggal tertentu untuk ditampilkan di form booking. */
    public static function berlakuPada(?string $tanggal = null): array
    {
        $tanggal = $tanggal ?? date('Y-m-d');
        return Database::instance()->all(
            "SELECT * FROM packages
              WHERE status = 'aktif'
                AND (berlaku_mulai IS NULL OR berlaku_mulai <= ?)
                AND (berlaku_akhir IS NULL OR berlaku_akhir >= ?)
              ORDER BY durasi_jam",
            [$tanggal, $tanggal]
        );
    }

    /** Estimasi penghematan untuk kartu promo di landing page. */
    public static function denganEstimasi(): array
    {
        $tarifTerendah = (float) (Database::instance()->scalar(
            'SELECT MIN(harga_per_jam) FROM units WHERE status <> "maintenance"'
        ) ?? 0);

        return array_map(static function (array $p) use ($tarifTerendah): array {
            $dasar = $tarifTerendah * (int) $p['durasi_jam'];
            if ((float) $p['harga'] > 0) {
                $hemat = max(0, $dasar - (float) $p['harga']);
            } elseif ($p['tipe_diskon'] === 'persen') {
                $hemat = $dasar * ((float) $p['diskon_persen'] / 100);
            } else {
                $hemat = (float) $p['diskon_nilai'];
            }
            $p['estimasi_hemat'] = round($hemat);
            $p['estimasi_harga'] = round(max(0, $dasar - $hemat));
            return $p;
        }, self::semuaAktif());
    }
}

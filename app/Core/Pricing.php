<?php
/**
 * Pricing — perhitungan total harga sewa.
 *
 * RUMUS:
 *   subtotal = (durasi_jam x harga_per_jam) + (controller_tambahan x tarif_controller x durasi_jam)
 *   diskon   = sesuai paket (persen / nominal), dibatasi tidak melebihi subtotal
 *   total    = subtotal - diskon
 *
 * Semua nilai dibulatkan ke rupiah penuh (tanpa sen) agar cocok dengan
 * harga yang tertera di papan rental.
 */

namespace App\Core;

use App\Models\Package;
use App\Models\Setting;

class Pricing
{
    /** Tarif controller tambahan per jam. */
    public static function tarifController(): float
    {
        return round((float) Setting::get('tarif_controller', 3000));
    }

    /**
     * Hitung penawaran lengkap.
     *
     * @param array $unit     baris tabel units
     * @param string $tanggal Y-m-d
     * @param int    $durasi  jumlah jam
     * @param int    $controller  jumlah controller tambahan
     * @param int|null $packageId
     * @return array{
     *   durasi:int, harga_per_jam:float, biaya_dasar:float,
     *   tarif_controller:float, controller_tambahan:int, biaya_controller:float,
     *   subtotal:float, diskon:float, total:float,
     *   paket:?array, rincian:array<int, array{label:string, nilai:float}>
     * }
     */
    public static function hitung(array $unit, string $tanggal, int $durasi, int $controller = 0, ?int $packageId = null): array
    {
        $durasi = max(1, min($durasi, (int) config('booking.max_durasi', 8)));
        $controller = max(0, min($controller, 6));

        $tarifUnit = round((float) $unit['harga_per_jam']);
        $tarifCtrl = self::tarifController();

        $biayaDasar = $durasi * $tarifUnit;
        $biayaCtrl = $controller * $tarifCtrl * $durasi;
        $subtotal = $biayaDasar + $biayaCtrl;

        $paket = $packageId !== null ? (new Package())->find($packageId) : null;
        $diskon = 0.0;
        $catatanPaket = null;

        if ($paket !== null && self::paketBerlaku($paket, $tanggal)) {
            if ((float) $paket['harga'] > 0) {
                // Paket berharga tetap: selisih terhadap subtotal dihitung sebagai diskon
                $diskon = max(0, $subtotal - round((float) $paket['harga']));
                $catatanPaket = 'Harga paket tetap ' . rupiah((float) $paket['harga']);
            } elseif ($paket['tipe_diskon'] === 'persen') {
                $diskon = round($subtotal * ((float) $paket['diskon_persen'] / 100));
                $catatanPaket = 'Diskon ' . rtrim(rtrim(number_format((float) $paket['diskon_persen'], 2, ',', ''), '0'), '.') . '%';
            } else {
                $diskon = round((float) $paket['diskon_nilai']);
                $catatanPaket = 'Potongan nominal';
            }
        } elseif ($paket !== null) {
            $paket = null; // paket dipilih tetapi sedang tidak berlaku
        }

        $diskon = min($diskon, $subtotal);
        $total = max(0, $subtotal - $diskon);

        $rincian = [
            ['label' => "Sewa {$durasi} jam x " . rupiah($tarifUnit), 'nilai' => $biayaDasar],
        ];
        if ($controller > 0) {
            $rincian[] = [
                'label' => "Controller tambahan x{$controller} x {$durasi} jam",
                'nilai' => $biayaCtrl,
            ];
        }
        if ($diskon > 0) {
            $rincian[] = ['label' => 'Diskon ' . ($catatanPaket ? "({$catatanPaket})" : ''), 'nilai' => -$diskon];
        }

        return [
            'unit_id'           => (int) $unit['id'],
            'kode_unit'         => $unit['kode_unit'],
            'tipe'              => $unit['tipe'],
            'tanggal'           => $tanggal,
            'durasi'            => $durasi,
            'harga_per_jam'     => $tarifUnit,
            'biaya_dasar'       => $biayaDasar,
            'tarif_controller'  => $tarifCtrl,
            'controller_tambahan' => $controller,
            'biaya_controller'  => $biayaCtrl,
            'subtotal'          => $subtotal,
            'diskon'            => $diskon,
            'total'             => $total,
            'paket'             => $paket,
            'rincian'           => $rincian,
        ];
    }

    /** Paket berlaku pada tanggal tertentu? */
    public static function paketBerlaku(array $paket, string $tanggal): bool
    {
        if ($paket['status'] !== 'aktif') {
            return false;
        }
        if (!empty($paket['berlaku_mulai']) && $tanggal < $paket['berlaku_mulai']) {
            return false;
        }
        if (!empty($paket['berlaku_akhir']) && $tanggal > $paket['berlaku_akhir']) {
            return false;
        }
        return true;
    }

    /** Daftarkan paket yang sedang berlaku untuk pilihan di form booking. */
    public static function paketAktif(?string $tanggal = null): array
    {
        $tanggal ??= date('Y-m-d');
        return array_values(array_filter(
            Package::semuaAktif(),
            static fn (array $p): bool => self::paketBerlaku($p, $tanggal)
        ));
    }
}

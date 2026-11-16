<?php
/**
 * Export — pengeluaran laporan ke Excel (CSV) dan PDF (lewat halaman cetak).
 *
 * Strategi tanpa pustaka eksternal:
 *  - Excel : berkas .csv ber-UTF-8 BOM + pemisah koma. Excel (Windows/ID)
 *            membukanya langsung dan tetap bisa diolah lebih lanjut.
 *  - PDF   : view `admin/laporan/cetak` dirender khusus lalu tombol "Cetak PDF"
 *            memakai dialog cetak browser (Save as PDF). Hasilnya vector,
 *            bisa dicetak, dan tanpa dependensi Composer.
 */

namespace App\Core;

class Exporter
{
    /** Kirim balasan unduhan CSV ke browser lalu hentikan eksekusi. */
    public static function csv(string $namaFile, array $header, array $rows): never
    {
        $namaFile = preg_replace('/[^A-Za-z0-9_\-]+/', '_', $namaFile) ?: 'laporan';
        if (!str_ends_with(strtolower($namaFile), '.csv')) {
            $namaFile .= '.csv';
        }

        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename="' . $namaFile . '"');
        header('Cache-Control: no-store, no-cache, must-revalidate');
        header('Pragma: no-cache');

        $keluar = fopen('php://output', 'w');

        // BOM UTF-8 supaya Excel membaca karakter Indonesia dengan benar
        fwrite($keluar, "\xEF\xBB\xBF");

        fputcsv($keluar, $header, ',', '"', '');
        foreach ($rows as $row) {
            $baris = array_map(static function ($v): string {
                if ($v === null) {
                    return '';
                }
                $v = (string) $v;
                // Cegah formula injection (=, +, -, @) saat dibuka di Excel
                if ($v !== '' && in_array($v[0], ['=', '+', '-', '@'], true)) {
                    $v = "'" . $v;
                }
                return $v;
            }, array_values((array) $row));
            fputcsv($keluar, $baris, ',', '"', '');
        }

        fclose($keluar);
        exit;
    }

    /** Ringkasan yang selalu diletakkan di atas berkas laporan. */
    public static function metadata(array $info): array
    {
        $baris = [];
        foreach ($info as $key => $value) {
            $baris[] = [self::judul($key), $value];
        }
        $baris[] = ['', ''];
        return $baris;
    }

    private static function judul(string $key): string
    {
        return ucwords(str_replace('_', ' ', $key));
    }
}

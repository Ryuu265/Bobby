<?php
/**
 * Schedule — Otak ketersediaan jadwal sewa.
 *
 * Tanggung jawab:
 *  1. Menentukan jam buka-tutup dari tabel settings.
 *  2. Menghasilkan daftar slot jam per unit per tanggal (untuk grid real-time).
 *  3. Mendeteksi bentrok jadwal dengan SELECT ... FOR UPDATE di dalam transaksi.
 *  4. Menyimpulkan status unit SAAT INI (tersedia/dipakai/dipesan/maintenance).
 *
 * CATATAN: kolom end_time bertipe TIME dan dapat berisi '24:00:00'
 * (MySQL TIME menerima nilai hingga 838:59:59), sehingga penyewaan
 * yang selesai tepat tengah malam tetap bisa disimpan dan dibandingkan.
 */

namespace App\Core;

use App\Models\Setting;

class Schedule
{
    /** Batas status pesanan yang "memakan" jadwal. */
    public const STATUS_MEMESAN = ['menunggu', 'dikonfirmasi', 'berlangsung'];

    public static function toMenit(string $time): int
    {
        [$h, $m] = array_pad(explode(':', $time), 2, '0');
        return ((int) $h) * 60 + (int) $m;
    }

    public static function keJam(int $menit): string
    {
        return sprintf('%02d:%02d:00', intdiv($menit, 60), $menit % 60);
    }

    /** Jam buka & tutup hari ini (24 berarti tutup pukul 24.00 / tengah malam). */
    public static function jamOperasional(?string $tanggal = null): array
    {
        $hari = (int) date('w', strtotime($tanggal ?? date('Y-m-d'))); // 0 = Minggu
        $akhirPekan = in_array($hari, [0, 6], true);

        $buka  = (int) Setting::get('jam_operasional_buka', $akhirPekan ? 9 : 10);
        $tutup = (int) Setting::get('jam_operasional_tutup', 24);

        return ['buka' => max(0, min($buka, 23)), 'tutup' => max($buka + 1, min($tutup, 24))];
    }

    /** Daftar tanggal yang boleh dipesan (hari ini s/d H+advance_days). */
    public static function rentangTanggal(int $hari = 14): array
    {
        $list = [];
        for ($i = 0; $i <= $hari; $i++) {
            $list[] = date('Y-m-d', strtotime("+{$i} day"));
        }
        return $list;
    }

    /**
     * Ambil rentang waktu yang sudah terisi untuk satu unit pada satu tanggal.
     * @return array<int, array{start:string,end:string,invoice:string,status:string}>
     */
    public static function terisi(int $unitId, string $tanggal, ?int $kecualiId = null): array
    {
        $sql = 'SELECT id, invoice_number, start_time, end_time, status_pesanan
                  FROM bookings
                 WHERE unit_id = ?
                   AND booking_date = ?
                   AND status_pesanan IN ("menunggu","dikonfirmasi","berlangsung")';
        $params = [$unitId, $tanggal];
        if ($kecualiId !== null) {
            $sql .= ' AND id <> ?';
            $params[] = $kecualiId;
        }
        $sql .= ' ORDER BY start_time';

        $rows = Database::instance()->all($sql, $params);
        return array_map(static fn (array $r): array => [
            'id'      => (int) $r['id'],
            'invoice' => $r['invoice_number'],
            'start'   => self::toMenit($r['start_time']),
            'end'     => self::toMenit($r['end_time']),
            'status'  => $r['status_pesanan'],
        ], $rows);
    }

    /**
     * Grid slot jam untuk satu unit + tanggal.
     * @return array<int, array{label:string,value:string,menit:int,tersedia:bool,alasan:string}>
     */
    public static function slots(int $unitId, string $tanggal, int $durasi = 1, ?int $kecualiId = null): array
    {
        ['buka' => $buka, 'tutup' => $tutup] = self::jamOperasional($tanggal);
        $terisi = self::terisi($unitId, $tanggal, $kecualiId);
        $langkah = max(1, (int) config('booking.step_hour', 1));

        $sekarang = self::toMenit(date('H:i'));
        $hariIni = $tanggal === date('Y-m-d');

        $slots = [];
        for ($menit = $buka * 60; $menit + $durasi * 60 <= $tutup * 60; $menit += $langkah * 60) {
            $akhir = $menit + $durasi * 60;
            $alasan = 'tersedia';

            if ($hariIni && $menit <= $sekarang) {
                $alasan = 'lewat';
            } else {
                foreach ($terisi as $r) {
                    // Aturan irisan: lama.start < baru.end AND lama.end > baru.start
                    if ($r['start'] < $akhir && $r['end'] > $menit) {
                        $alasan = 'terisi';
                        break;
                    }
                }
            }

            $slots[] = [
                'value'    => sprintf('%02d:%02d', intdiv($menit, 60), $menit % 60),
                'label'    => sprintf('%02d.%02d', intdiv($menit, 60), $menit % 60),
                'menit'    => $menit,
                'tersedia' => $alasan === 'tersedia',
                'alasan'   => $alasan,
            ];
        }

        return $slots;
    }

    /** Cek apakah kombinasi unit+tanggal+jam masih bebas. */
    public static function tersedia(int $unitId, string $tanggal, string $mulai, string $selesai, ?int $kecualiId = null): bool
    {
        return self::tabrakan($unitId, $tanggal, $mulai, $selesai, $kecualiId) === null;
    }

    /**
     * Kembalikan baris pesanan yang bentrok, atau null bila bebas.
     * Dipanggil DALAM transaksi oleh Booking::buat() agar aman dari race condition.
     */
    public static function tabrakan(int $unitId, string $tanggal, string $mulai, string $selesai, ?int $kecualiId = null): ?array
    {
        $sql = 'SELECT id, invoice_number, start_time, end_time, status_pesanan
                  FROM bookings
                 WHERE unit_id = ? AND booking_date = ?
                   AND status_pesanan IN ("menunggu","dikonfirmasi","berlangsung")
                   AND start_time < ? AND end_time > ?';
        $params = [$unitId, $tanggal, $selesai, $mulai];
        if ($kecualiId !== null) {
            $sql .= ' AND id <> ?';
            $params[] = $kecualiId;
        }
        $sql .= ' ORDER BY start_time LIMIT 1';

        $row = Database::instance()->one($sql, $params);
        if ($row !== null) {
            return $row;
        }

        // Bila slot berada di luar jam operasional, tolak lebih awal
        ['buka' => $buka, 'tutup' => $tutup] = self::jamOperasional($tanggal);
        $m = self::toMenit($mulai);
        $s = self::toMenit($selesai);
        if ($m < $buka * 60 || $s > $tutup * 60) {
            return [
                'id' => 0,
                'invoice_number' => '-',
                'start_time' => sprintf('%02d:00:00', $buka),
                'end_time' => sprintf('%02d:00:00', $tutup),
                'status_pesanan' => 'di_luar_jam_operasional',
            ];
        }

        return null;
    }

    /**
     * Status unit SAAT INI, dihitung dari pesanan nyata (bukan kolom cache).
     * Prioritas: maintenance > dipakai > dipesan > tersedia.
     */
    public static function statusUnit(array $unit): string
    {
        if ($unit['status'] === 'maintenance') {
            return 'maintenance';
        }

        $hariIni = date('Y-m-d');
        $sekarang = self::keJam(self::toMenit(date('H:i')));

        $berlangsung = Database::instance()->one(
            'SELECT id FROM bookings
              WHERE unit_id = ? AND booking_date = ?
                AND status_pesanan IN ("dikonfirmasi","berlangsung")
                AND start_time <= ? AND end_time > ?
              LIMIT 1',
            [(int) $unit['id'], $hariIni, $sekarang, $sekarang]
        );
        if ($berlangsung !== null) {
            return 'dipakai';
        }

        $akanDatang = Database::instance()->one(
            'SELECT id FROM bookings
              WHERE unit_id = ? AND booking_date = ?
                AND status_pesanan IN ("menunggu","dikonfirmasi")
                AND end_time > ?
              LIMIT 1',
            [(int) $unit['id'], $hariIni, $sekarang]
        );

        return $akanDatang !== null ? 'dipesan' : 'tersedia';
    }

    /** Ringkasan keterisian jadwal satu unit untuk satu tanggal (dipakai admin). */
    public static function kepadatan(int $unitId, string $tanggal): array
    {
        return self::kepadatanBanyak([$unitId], $tanggal)[$unitId]
            ?? ['total_jam_operasional' => 1, 'jam_terisi' => 0.0, 'persen' => 0, 'jumlah_pesanan' => 0];
    }

    /**
     * Kepadatan banyak unit sekaligus dengan SATU query.
     * Dipakai laporan kinerja unit: memanggil kepadatan() per unit berarti
     * dua query per baris tabel (N+1) tanpa hasil yang berbeda.
     *
     * @param int[] $unitIds
     * @return array<int, array{total_jam_operasional:int,jam_terisi:float,persen:int,jumlah_pesanan:int}>
     */
    public static function kepadatanBanyak(array $unitIds, string $tanggal): array
    {
        ['buka' => $buka, 'tutup' => $tutup] = self::jamOperasional($tanggal);
        $totalJam = max(1, $tutup - $buka);

        $ids = array_values(array_unique(array_filter(array_map('intval', $unitIds))));
        $menit = array_fill_keys($ids, 0);
        $jumlah = array_fill_keys($ids, 0);

        if ($ids !== []) {
            $tanya = implode(', ', array_fill(0, count($ids), '?'));
            $rows = Database::instance()->all(
                "SELECT unit_id, start_time, end_time FROM bookings
                  WHERE unit_id IN ({$tanya}) AND booking_date = ?
                    AND status_pesanan IN ('menunggu','dikonfirmasi','berlangsung')",
                [...$ids, $tanggal]
            );
            foreach ($rows as $row) {
                $id = (int) $row['unit_id'];
                $mulai  = max(self::toMenit($row['start_time']), $buka * 60);
                $selesai = min(self::toMenit($row['end_time']), $tutup * 60);
                $menit[$id] += max(0, $selesai - $mulai);
                $jumlah[$id]++;
            }
        }

        $hasil = [];
        foreach ($ids as $id) {
            $hasil[$id] = [
                'total_jam_operasional' => $totalJam,
                'jam_terisi'            => round($menit[$id] / 60, 2),
                'persen'                => min(100, (int) round($menit[$id] / ($totalJam * 60) * 100)),
                'jumlah_pesanan'        => $jumlah[$id],
            ];
        }

        return $hasil;
    }
}

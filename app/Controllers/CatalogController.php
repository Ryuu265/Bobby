<?php
/**
 * CatalogController — halaman publik: katalog unit PlayStation & daftar game.
 * Boleh diakses tamu; data ketersediaan selalu dihitung ulang dari pesanan nyata.
 */

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Error;
use App\Core\Schedule;
use App\Models\Game;
use App\Models\Unit;

class CatalogController extends Controller
{
    private const TIPE = ['PS5', 'PS4', 'PS3'];
    private const STATUS = ['tersedia', 'dipakai', 'dipesan', 'maintenance'];
    private const PLATFORM = ['PS5', 'PS4', 'PS3', 'Multi'];

    /** Katalog unit + grid slot ketersediaan hari ini per unit. */
    public function units(): void
    {
        $tipe = (string) $this->request->input('tipe', '');
        if (!in_array($tipe, self::TIPE, true)) {
            $tipe = '';
        }

        $status = (string) $this->request->input('status', '');
        if (!in_array($status, self::STATUS, true)) {
            $status = '';
        }

        $units = Unit::katalog($tipe, $status);
        $hariIni = date('Y-m-d');

        // Grid slot durasi 1 jam untuk tiap unit, dipakai kartu ketersediaan
        $slot = [];
        foreach ($units as $u) {
            $slot[(int) $u['id']] = Schedule::slots((int) $u['id'], $hariIni, 1);
        }

        $this->view('pages/katalog/index', [
            'units'      => $units,
            'slot'       => $slot,
            'hariIni'    => $hariIni,
            'tipe'       => $tipe,
            'status'     => $status,
            'daftarTipe' => self::TIPE,
            'daftarStatus' => self::STATUS,
        ]);
    }

    /** Katalog game: pencarian + filter platform/genre + unit tempat tersedia. */
    public function games(): void
    {
        $platform = (string) $this->request->input('platform', '');
        if (!in_array($platform, self::PLATFORM, true)) {
            $platform = '';
        }

        $cari = trim((string) $this->request->input('cari', ''));
        if (mb_strlen($cari) > 60) {
            $cari = mb_substr($cari, 0, 60);
        }

        $daftarGenre = array_values(array_filter(
            Game::daftarGenre(),
            static fn (?string $g): bool => $g !== null && $g !== ''
        ));

        $genre = (string) $this->request->input('genre', '');
        if (!in_array($genre, $daftarGenre, true)) {
            $genre = '';
        }

        $games = Game::katalog($platform, $genre, $cari);

        // Kode unit yang memuat tiap game (dipakai baris "Tersedia di")
        $terpasang = [];
        foreach ($games as $g) {
            $terpasang[(int) $g['id']] = array_pluck(Game::unitTerpasang((int) $g['id']), 'kode_unit');
        }

        $this->view('pages/katalog/game-index', [
            'games'        => $games,
            'terpasang'    => $terpasang,
            'daftarGenre'  => $daftarGenre,
            'daftarPlatform' => ['PS5', 'PS4', 'PS3'],
            'platform'     => $platform,
            'genre'        => $genre,
            'cari'         => $cari,
        ]);
    }

    /** Detail satu unit berdasarkan kode_unit (contoh: PS5-01). */
    public function unitDetail(string $kode): void
    {
        $unit = Unit::findByKode($kode);
        if ($unit === null) {
            Error::abort(404, 'Unit dengan kode "' . $kode . '" tidak ditemukan.');
        }

        $unitId = (int) $unit['id'];
        $hariIni = date('Y-m-d');

        $terisi = array_map(
            static fn (array $r): array => $r + ['rentang' => self::rentangText($r['start'], $r['end'])],
            Schedule::terisi($unitId, $hariIni)
        );

        $this->view('pages/katalog/unit-detail', [
            'unit'       => $unit,
            'statusNow'  => Schedule::statusUnit($unit),
            'games'      => Unit::games($unitId),
            'slots'      => Schedule::slots($unitId, $hariIni, 1),
            'terisi'     => $terisi,
            'operasional' => Schedule::jamOperasional($hariIni),
            'hariIni'    => $hariIni,
            'kepadatan'  => Schedule::kepadatan($unitId, $hariIni),
        ]);
    }

    /** Detail satu game + daftar unit tempat game itu terpasang. */
    public function gameDetail(string $id): void
    {
        $game = (new Game())->find((int) $id);
        if ($game === null || $game['status'] !== 'aktif') {
            Error::abort(404, 'Game tidak ditemukan atau sudah tidak tersedia.');
        }

        $gameId = (int) $game['id'];
        $hariIni = date('Y-m-d');

        $units = array_map(static function (array $u) use ($hariIni): array {
            $slots = Schedule::slots((int) $u['id'], $hariIni, 1);
            $u['status_realtime'] = Schedule::statusUnit($u);
            $u['slot_kosong'] = count(array_filter($slots, static fn (array $s): bool => $s['tersedia']));
            return $u;
        }, Game::unitTerpasang($gameId));

        // Game lain dengan genre sama sebagai saran lanjutan
        $serupa = array_values(array_filter(
            Game::katalog('', (string) $game['genre'], ''),
            static fn (array $g): bool => (int) $g['id'] !== $gameId
        ));

        $this->view('pages/katalog/game-detail', [
            'game'    => $game,
            'units'   => $units,
            'serupa'  => array_slice($serupa, 0, 4),
            'hariIni' => $hariIni,
        ]);
    }

    /** "19.00 - 22.00" dari hitungan menit sejak tengah hari. */
    private static function rentangText(int $mulai, int $selesai): string
    {
        return sprintf('%02d.%02d', intdiv($mulai, 60), $mulai % 60)
            . ' - ' . sprintf('%02d.%02d', intdiv($selesai, 60), $selesai % 60);
    }
}

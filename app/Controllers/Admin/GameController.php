<?php
/**
 * Admin\GameController — CRUD master data game + penempatan game di unit.
 *
 * Relasi unit <-> game disimpan di tabel unit_games dan ditulis lewat
 * UnitGame::pasang() di dalam satu transaksi (bukan lewat Game::tersimpan()).
 */

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\Database;
use App\Core\Logger;
use App\Core\Uploader;
use App\Models\Game;
use App\Models\Unit;
use App\Models\UnitGame;
use PDOException;
use RuntimeException;

class GameController extends Controller
{
    private const PLATFORM = ['PS3', 'PS4', 'PS5', 'Multi'];
    private const STATUS = ['aktif', 'nonaktif'];

    /** Rating ENUM tidak boleh ditulis lewat rule `in:` karena helper `|` pemisah aturan. */
    private const RATING = ['3+', '7+', '12+', '16+', '18+'];

    /** Daftar game + jumlah unit tempat terpasang. */
    public function index(): void
    {
        $platform = $this->whitelist((string) $this->request->input('platform', ''), self::PLATFORM);
        $status = $this->whitelist((string) $this->request->input('status', ''), self::STATUS);
        $cari = mb_substr(trim((string) $this->request->input('cari', '')), 0, 60);

        $games = array_filter(
            Game::daftar($cari),
            static fn (array $g): bool => ($platform === '' || $g['platform'] === $platform)
                && ($status === '' || $g['status'] === $status)
        );

        $this->adminView('admin/game/index', [
            'games'          => array_values($games),
            'genre'          => $this->daftarGenre(),
            'platform'       => $platform,
            'cari'           => $cari,
            'daftarPlatform' => self::PLATFORM,
            'status'         => $status,
            'daftarStatus'   => self::STATUS,
            'totalSemua'     => (new Game())->countWhere(),
            'aktif'          => (new Game())->countWhere(['status' => 'aktif']),
        ]);
    }

    public function create(): void
    {
        $this->adminView('admin/game/form', [
            'mode'           => 'baru',
            'game'           => [],
            'units'          => Unit::semua(),
            'terpasang'      => [],
            'genre'          => $this->daftarGenre(),
            'daftarPlatform' => self::PLATFORM,
            'daftarRating'   => self::RATING,
            'daftarStatus'   => self::STATUS,
        ]);
    }

    public function store(): void
    {
        $data = $this->validasi();
        $payload = $this->payload($data);

        $cover = $this->coverBaru();
        if ($cover !== null) {
            $payload['cover_path'] = $cover;
        }

        try {
            $id = (new Game())->create($payload);
        } catch (PDOException $e) {
            flash('danger', 'Game dengan judul dan platform itu sudah terdaftar.');
            back();
        }

        $jumlah = $this->sinkron($id, array_map('intval', $this->request->array('unit_id')));
        Logger::write(Auth::id(), 'game', 'buat', 'games', $id,
            'Game ' . $payload['judul'] . ' (' . $payload['platform'] . ') ditambahkan, terpasang di ' . $jumlah . ' unit.');

        flash('success', 'Game ' . $payload['judul'] . ' berhasil ditambahkan.');
        redirect('admin/game');
    }

    public function edit(string $id): void
    {
        $game = (new Game())->findOrFail((int) $id);

        $this->adminView('admin/game/form', [
            'mode'           => 'ubah',
            'game'           => $game,
            'units'          => Unit::semua(),
            'terpasang'      => array_map('intval', UnitGame::unitUntuk((int) $game['id'])),
            'genre'          => $this->daftarGenre(),
            'daftarPlatform' => self::PLATFORM,
            'daftarRating'   => self::RATING,
            'daftarStatus'   => self::STATUS,
        ]);
    }

    public function update(string $id): void
    {
        $game = (new Game())->findOrFail((int) $id);
        $data = $this->validasi();
        $payload = $this->payload($data, $game);

        $cover = $this->coverBaru();
        if ($cover !== null) {
            Uploader::delete($game['cover_path'] ?? null);
            $payload['cover_path'] = $cover;
        }

        try {
            (new Game())->update((int) $game['id'], $payload);
        } catch (PDOException $e) {
            flash('danger', 'Judul + platform tersebut sudah dipakai game lain.');
            back();
        }

        // Checkbox unit ikut tersimpan bila form mengirimnya
        if ($this->request->has('unit_id')) {
            $this->sinkron((int) $game['id'], array_map('intval', $this->request->array('unit_id')));
        }

        Logger::write(Auth::id(), 'game', 'ubah', 'games', (int) $game['id'],
            'Game ' . $game['judul'] . ' diperbarui.');

        flash('success', 'Game ' . $payload['judul'] . ' berhasil diperbarui.');
        redirect('admin/game');
    }

    /** Tulis ulang daftar unit tempat game ini dipasang. */
    public function sinkronUnit(string $id): void
    {
        $game = (new Game())->findOrFail((int) $id);
        $unitIds = array_map('intval', $this->request->array('unit_id'));
        $jumlah = $this->sinkron((int) $game['id'], $unitIds);

        Logger::write(Auth::id(), 'game', 'ubah', 'unit_games', (int) $game['id'],
            'Game ' . $game['judul'] . ' dipasang pada ' . $jumlah . ' unit.');

        flash('success', 'Penempatan game ' . $game['judul'] . ' diperbarui (' . $jumlah . ' unit).');
        back();
    }

    /** Unit games ikut terhapus lewat FK CASCADE, jadi penghapusan aman. */
    public function destroy(string $id): void
    {
        $game = (new Game())->findOrFail((int) $id);
        $judul = (string) $game['judul'];

        Uploader::delete($game['cover_path'] ?? null);
        (new Game())->delete((int) $game['id']);
        Logger::write(Auth::id(), 'game', 'hapus', 'games', (int) $game['id'],
            'Game ' . $judul . ' dihapus dari master data.');

        flash('success', 'Game ' . $judul . ' telah dihapus.');
        redirect('admin/game');
    }

    /* =====================================================================
     * UTILITAS INTERNAL
     * ===================================================================== */

    /** Gabung aturan validator + pemeriksaan rating manual (nilai mengandung tanda "+"). */
    private function validasi(): array
    {
        $data = $this->validate($this->aturan(), $this->label(), ['cover' => 'image|max:' . (int) config('upload.max_size_kb', 2048)]);

        $rating = (string) ($data['rating'] ?? '7+');
        if (!in_array($rating, self::RATING, true)) {
            $_SESSION['_errors'] = ['rating' => ['Rating harus salah satu dari: ' . implode(', ', self::RATING) . '.']];
            $_SESSION['_old'] = $this->request->all();
            flash('danger', 'Periksa kembali isian Anda: Rating tidak dikenali.');
            back();
        }

        return $data;
    }

    private function aturan(): array
    {
        return [
            'judul'     => 'required|min:2|max:150',
            'platform'  => 'in:PS3,PS4,PS5,Multi',
            'genre'     => 'required|max:60',
            'rating'    => 'max:5',
            'status'    => 'in:aktif,nonaktif',
            'deskripsi' => 'max:1000',
        ];
    }

    private function label(): array
    {
        return [
            'judul'     => 'Judul game',
            'platform'  => 'Platform',
            'genre'     => 'Genre',
            'rating'    => 'Rating usia',
            'status'    => 'Status',
            'deskripsi' => 'Deskripsi',
            'cover'     => 'Cover game',
        ];
    }

    private function payload(array $d, array $lama = []): array
    {
        $pilihan = static function (mixed $nilai, string $key, array $boleh, array $lama): string {
            if (is_string($nilai) && in_array($nilai, $boleh, true)) {
                return $nilai;
            }
            return (string) ($lama[$key] ?? $boleh[0]);
        };

        $deskripsi = trim((string) ($d['deskripsi'] ?? ''));
        $genre = trim((string) ($d['genre'] ?? ''));

        return [
            'judul'     => trim((string) $d['judul']),
            'platform'  => $pilihan($d['platform'] ?? null, 'platform', self::PLATFORM, $lama),
            'genre'     => $genre === '' ? (string) ($lama['genre'] ?? 'Umum') : $genre,
            'rating'    => in_array((string) ($d['rating'] ?? ''), self::RATING, true)
                ? (string) $d['rating']
                : (string) ($lama['rating'] ?? '7+'),
            'status'    => $pilihan($d['status'] ?? null, 'status', self::STATUS, $lama),
            'deskripsi' => $deskripsi === '' ? null : $deskripsi,
        ];
    }

    /**
     * Ganti seluruh relasi unit_games milik satu game.
     * @return int jumlah baris hasil pemasangan
     */
    private function sinkron(int $gameId, array $unitIds): int
    {
        $unitIds = array_values(array_filter($unitIds, static fn (int $v): bool => $v > 0));

        return Database::instance()->transaction(
            static function () use ($gameId, $unitIds): int {
                Database::instance()->run('DELETE FROM unit_games WHERE game_id = ?', [$gameId]);
                return $unitIds === [] ? 0 : UnitGame::pasang($gameId, $unitIds);
            }
        );
    }

    /** Genre yang sudah pernah dipakai, untuk saran isian di form. */
    private function daftarGenre(): array
    {
        return array_values(array_filter(
            Game::daftarGenre(),
            static fn (?string $g): bool => $g !== null && $g !== ''
        ));
    }

    private function whitelist(string $nilai, array $boleh): string
    {
        return in_array($nilai, $boleh, true) ? $nilai : '';
    }

    /** Simpan cover yang diunggah; pesan gagal Uploader diteruskan sebagai flash. */
    private function coverBaru(): ?string
    {
        try {
            return Uploader::store($this->request->file('cover'), 'games');
        } catch (RuntimeException $e) {
            flash('danger', $e->getMessage());
            back();
        }
    }
}

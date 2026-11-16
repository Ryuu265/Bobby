<?php
/**
 * admin/game/index.php — Tabel master data game.
 * Kolom "Unit" memakai hitungan jumlah_unit dari Game::daftar().
 */

use App\Core\View;

View::set('title', 'Kelola Game — ' . config('app.name'));
View::set('page_title', 'Kelola Game');
View::set('page_subtitle', 'Judul, platform, rating usia, dan penempatan game di unit.');


$games = $games ?? [];
$totalTerpasang = array_sum(array_map(static fn (array $g): int => (int) $g['jumlah_unit'], $games));
?>
<!-- ============ RINGKASAN ============ -->
<div class="grid gap-3 grid-cols-2 lg:grid-cols-4" style="margin-bottom: var(--sp-5);">
    <div class="stat">
        <p class="stat__label">Total Judul</p>
        <p class="stat__value"><?= (int) ($totalSemua ?? 0) ?></p>
    </div>
    <div class="stat">
        <p class="stat__label">Status Aktif</p>
        <p class="stat__value"><?= (int) ($aktif ?? 0) ?></p>
        <p class="stat__hint">Muncul di katalog pelanggan</p>
    </div>
    <div class="stat">
        <p class="stat__label">Hasil Tampil</p>
        <p class="stat__value"><?= count($games) ?></p>
        <p class="stat__hint">Sesuai filter di bawah</p>
    </div>
    <div class="stat">
        <p class="stat__label">Total Terpasang</p>
        <p class="stat__value"><?= (int) $totalTerpasang ?></p>
        <p class="stat__hint">Relasi game ke unit</p>
    </div>
</div>

<!-- ============ TABEL GAME ============ -->
<div class="qs-panel">
    <div class="qs-panel__head" style="flex-wrap: wrap; row-gap: var(--sp-3);">
        <div>
            <h2 class="display" style="margin: 0; font-size: var(--text-lg);">Daftar Game</h2>
            <p class="text-muted" style="margin: 2px 0 0; font-size: var(--text-xs);">
                <?= $cari !== '' ? 'Kata kunci "' . e($cari) . '" · ' : '' ?><?= $platform !== '' ? e($platform) . ' · ' : '' ?><?= $status !== '' ? e(status_label($status)) : 'semua status' ?>
            </p>
        </div>

        <div class="flex flex-wrap items-center gap-2">
            <form method="get" action="<?= url('admin/game') ?>" class="flex flex-wrap items-end gap-2">
                <div>
                    <label class="label" for="f-cari" style="margin: 0 0 2px; font-size: var(--text-xs);">Cari</label>
                    <input class="input" id="f-cari" name="cari" maxlength="60" value="<?= e($cari) ?>"
                           placeholder="Judul atau genre" style="padding: 0.45rem 0.7rem; font-size: var(--text-xs); width: 170px;">
                </div>
                <div>
                    <label class="label" for="f-platform" style="margin: 0 0 2px; font-size: var(--text-xs);">Platform</label>
                    <select class="select" id="f-platform" name="platform"
                            style="width: auto; padding: 0.45rem 2.1rem 0.45rem 0.7rem; font-size: var(--text-xs);">
                        <option value="">Semua</option>
                        <?php foreach ($daftarPlatform as $p): ?>
                            <option value="<?= e($p) ?>" <?= $platform === $p ? 'selected' : '' ?>><?= e($p) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="label" for="f-status" style="margin: 0 0 2px; font-size: var(--text-xs);">Status</label>
                    <select class="select" id="f-status" name="status"
                            style="width: auto; padding: 0.45rem 2.1rem 0.45rem 0.7rem; font-size: var(--text-xs);">
                        <option value="">Semua</option>
                        <?php foreach ($daftarStatus as $s): ?>
                            <option value="<?= e($s) ?>" <?= $status === $s ? 'selected' : '' ?>><?= e(status_label($s)) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <button type="submit" class="btn btn-outline btn-sm">Terapkan</button>
                <a href="<?= url('admin/game') ?>" class="btn btn-ghost btn-sm">Reset</a>
            </form>
            <a href="<?= url('admin/game/baru') ?>" class="btn btn-primary btn-sm">+ Tambah Game</a>
        </div>
    </div>

    <div class="qs-panel__body">
        <?php if (!empty($genre)): ?>
            <!-- Pintasan genre: mengirim kata kunci yang sama ke filter GET -->
            <div class="flex flex-wrap items-center gap-2" style="margin-bottom: var(--sp-4);">
                <span class="text-muted" style="font-size: var(--text-xs);">Genre terbanyak dipakai:</span>
                <?php foreach (array_slice($genre, 0, 8) as $g): ?>
                    <a class="badge badge-neutral badge-no-dot" style="text-decoration: none;"
                       href="<?= e(url('admin/game') . '?' . http_build_query(array_filter([
                           'cari' => (string) $g, 'platform' => $platform, 'status' => $status,
                       ], static fn ($v): bool => (string) $v !== ''))) ?>"><?= e((string) $g) ?></a>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <label class="sr-only" for="saring-game">Saring baris tabel</label>
        <input id="saring-game" class="input" type="search" placeholder="Saring cepat baris di halaman ini…"
               data-table-filter="#tabel-game" style="max-width: 320px; margin-bottom: var(--sp-4);">

        <?php if ($games === []): ?>
            <div class="empty-state">
                <p class="display" style="font-size: var(--text-xl); margin: 0 0 var(--sp-2);">Belum ada game</p>
                <p class="text-secondary" style="margin: 0 0 var(--sp-4);">
                    Tambahkan judul game lalu centang unit tempat game itu dipasang.
                </p>
                <a href="<?= url('admin/game/baru') ?>" class="btn btn-primary btn-sm">Tambah Game</a>
            </div>
        <?php else: ?>
            <div class="table-wrap">
                <table class="table" id="tabel-game">
                    <thead>
                        <tr>
                            <th scope="col">Cover</th>
                            <th scope="col">Judul</th>
                            <th scope="col">Platform</th>
                            <th scope="col">Genre</th>
                            <th scope="col">Rating</th>
                            <th scope="col">Status</th>
                            <th scope="col" class="num">Unit</th>
                            <th scope="col">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($games as $g): ?>
                            <?php $id = (int) $g['id']; ?>
                            <tr>
                                <td>
                                    <div class="unit-card__media" style="width: 56px; height: 56px; aspect-ratio: 1; margin: 0; border-radius: var(--radius-sm);">
                                        <img src="<?= e(upload_url($g['cover_path'] ?? null)) ?>" alt="Cover <?= e($g['judul']) ?>" loading="lazy">
                                    </div>
                                </td>
                                <td>
                                    <span style="font-weight: 600;"><?= e($g['judul']) ?></span>
                                    <?php if (!empty($g['deskripsi'])): ?>
                                        <p class="text-muted" style="margin: 2px 0 0; font-size: var(--text-xs);">
                                            <?= e(mb_strimwidth(strip_tags((string) $g['deskripsi']), 0, 64, '…')) ?>
                                        </p>
                                    <?php endif; ?>
                                </td>
                                <td><span class="badge <?= $g['platform'] === 'Multi' ? 'badge-violet' : 'badge-neutral' ?> badge-no-dot"><?= e($g['platform']) ?></span></td>
                                <td class="text-secondary"><?= e($g['genre']) ?></td>
                                <td><span class="mono"><?= e($g['rating']) ?></span></td>
                                <td><span class="badge <?= e(status_class((string) $g['status'])) ?>"><?= e(status_label((string) $g['status'])) ?></span></td>
                                <td class="num">
                                    <span class="badge <?= (int) $g['jumlah_unit'] > 0 ? 'badge-cyan' : 'badge-neutral' ?> badge-no-dot">
                                        <?= (int) $g['jumlah_unit'] ?> unit
                                    </span>
                                </td>
                                <td>
                                    <div class="flex flex-wrap items-center gap-2">
                                        <a href="<?= route('admin.game.ubah', ['id' => $id]) ?>" class="btn btn-outline btn-sm">Ubah</a>
                                        <form method="post" action="<?= url('admin/game/' . $id . '/hapus') ?>"
                                              data-confirm="Hapus game <?= e($g['judul']) ?>? Penempatannya di unit ikut terhapus.">
                                            <?= csrf_field() ?>
                                            <button type="submit" class="btn btn-danger btn-sm">Hapus</button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <p class="text-muted" style="margin: var(--sp-4) 0 0; font-size: var(--text-xs);">
                Game nonaktif hilang dari katalog pelanggan tetapi riwayatnya tetap tersimpan.
            </p>
        <?php endif; ?>
    </div>
</div>

<?php
/**
 * pages/katalog/game-index.php — Katalog game: pencarian, filter, dan kartu game.
 *
 * @var array $games
 * @var array $terpasang   game_id => daftar kode_unit
 * @var array $daftarGenre
 * @var array $daftarPlatform
 * @var string $platform
 * @var string $genre
 * @var string $cari
 */

use App\Core\View;
use App\Models\Setting;

View::set('title', 'Daftar Game — ' . Setting::get('nama_rental', config('app.name')));
View::set('meta_description', 'Cari game PS5, PS4, dan PS3 yang tersedia di Qengamestation beserta unit tempatnya terpasang.');

$adaFilter = $platform !== '' || $genre !== '' || $cari !== '';
?>
<section class="section" style="padding-bottom: var(--sp-6);">
    <div class="container-page">
        <span class="badge badge-violet" style="margin-bottom: var(--sp-3);">Daftar Game</span>
        <h1 class="display" style="font-size: var(--text-3xl); margin-bottom: var(--sp-2);"><?= count($games) ?> Judul Siap Dimainkan</h1>
        <p class="text-secondary" style="margin:0; max-width: 68ch;">
            Setiap game terpasang di beberapa unit. Buka halaman game untuk melihat konsol mana saja
            yang memilikinya, lalu langsung pesan slotnya.
        </p>
    </div>
</section>

<section style="padding-bottom: var(--sp-8);">
    <div class="container-page">
        <form method="get" action="<?= url('katalog/game') ?>" class="qs-panel" style="padding: var(--sp-4) var(--sp-5);">
            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4" style="align-items: end;">
                <div class="field lg:col-span-2" style="margin-bottom: 0;">
                    <label class="label" for="cari">Cari judul</label>
                    <input class="input" type="search" id="cari" name="cari" maxlength="60"
                           value="<?= e($cari) ?>" placeholder="Contoh: EA FC, Mortal Kombat">
                </div>

                <div class="field" style="margin-bottom: 0;">
                    <label class="label" for="platform">Platform</label>
                    <select class="select" id="platform" name="platform">
                        <option value="">Semua platform</option>
                        <?php foreach ($daftarPlatform as $p): ?>
                            <option value="<?= e($p) ?>" <?= $platform === $p ? 'selected' : '' ?>><?= e($p) ?></option>
                        <?php endforeach; ?>
                        <option value="Multi" <?= $platform === 'Multi' ? 'selected' : '' ?>>Multi platform</option>
                    </select>
                </div>

                <div class="field" style="margin-bottom: 0;">
                    <label class="label" for="genre">Genre</label>
                    <select class="select" id="genre" name="genre">
                        <option value="">Semua genre</option>
                        <?php foreach ($daftarGenre as $g): ?>
                            <option value="<?= e($g) ?>" <?= $genre === $g ? 'selected' : '' ?>><?= e($g) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <div class="flex flex-wrap gap-2" style="margin-top: var(--sp-4);">
                <button type="submit" class="btn btn-primary">Terapkan</button>
                <?php if ($adaFilter): ?>
                    <a href="<?= url('katalog/game') ?>" class="btn btn-ghost">Hapus Filter</a>
                <?php endif; ?>
                <a href="<?= url('katalog') ?>" class="btn btn-outline">Katalog Unit</a>
            </div>
        </form>
    </div>
</section>

<section style="padding-bottom: var(--sp-12);">
    <div class="container-page">

        <?php if ($games === []): ?>
            <div class="qs-card empty-state">
                <h2 class="display" style="font-size: var(--text-lg);">Game tidak ditemukan</h2>
                <p class="text-secondary" style="max-width: 46ch; margin-inline: auto;">
                    Judul yang Anda cari belum tersedia. Coba kata kunci lain, atau minta operator
                    menambahkan game melalui katalog unit pilihan Anda.
                </p>
                <a href="<?= url('katalog/game') ?>" class="btn btn-outline btn-sm" style="margin-top: var(--sp-4);">
                    Tampilkan Semua Game
                </a>
            </div>
        <?php else: ?>
            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
                <?php foreach ($games as $g): ?>
                    <?php $kode = array_values(array_filter($terpasang[(int) $g['id']] ?? [])); ?>
                    <a href="<?= url('katalog/game/' . (int) $g['id']) ?>" class="qs-card qs-card--hoverable unit-card block">
                        <div class="unit-card__media" style="aspect-ratio: 3 / 4;">
                            <?php if (!empty($g['cover_path'])): ?>
                                <img src="<?= e(upload_url($g['cover_path'])) ?>" alt="Sampul <?= e($g['judul']) ?>" loading="lazy">
                            <?php else: ?>
                                <span class="unit-card__code"><?= e(mb_strtoupper(mb_substr((string) $g['platform'], 0, 4))) ?></span>
                            <?php endif; ?>
                        </div>

                        <p style="margin:0; font-weight:600; font-size: var(--text-sm); line-height:1.4;"><?= e($g['judul']) ?></p>
                        <p class="text-muted" style="margin:4px 0 0; font-size: var(--text-xs);">
                            <?= e($g['platform']) ?> · <?= e($g['genre']) ?> · usia <?= e($g['rating']) ?>
                        </p>

                        <div class="flex flex-wrap gap-1" style="margin-top: var(--sp-3);">
                            <?php if ($kode === []): ?>
                                <span class="badge badge-neutral badge-no-dot">Belum terpasang</span>
                            <?php else: ?>
                                <?php foreach (array_slice($kode, 0, 3) as $k): ?>
                                    <span class="badge badge-cyan badge-no-dot mono" style="font-size: 10px;"><?= e($k) ?></span>
                                <?php endforeach; ?>
                                <?php if (count($kode) > 3): ?>
                                    <span class="badge badge-neutral badge-no-dot" style="font-size: 10px;">
                                        +<?= count($kode) - 3 ?> unit
                                    </span>
                                <?php endif; ?>
                            <?php endif; ?>
                        </div>
                    </a>
                <?php endforeach; ?>
            </div>

            <p class="text-muted" style="font-size: var(--text-xs); margin-top: var(--sp-5);">
                Kode pada setiap kartu adalah unit tempat game tersebut terpasang.
                <?php if ($cari !== ''): ?>
                    Hasil pencarian untuk &ldquo;<span class="mono"><?= e($cari) ?></span>&rdquo;.
                <?php endif; ?>
            </p>
        <?php endif; ?>

    </div>
</section>

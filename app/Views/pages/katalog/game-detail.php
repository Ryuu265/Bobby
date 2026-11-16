<?php
/**
 * pages/katalog/game-detail.php — Satu game + daftar unit tempatnya tersedia.
 *
 * @var array $game
 * @var array $units   unit terpasang + status_realtime + slot_kosong
 * @var array $serupa
 * @var string $hariIni
 * @var array|null $authUser
 */

use App\Core\View;
use App\Models\Setting;

View::set('title', $game['judul'] . ' — Daftar Game | ' . Setting::get('nama_rental', config('app.name')));
View::set('meta_description', mb_substr(strip_tags((string) $game['deskripsi']), 0, 150));

$tautanPesan = static fn (int $id): string => $authUser
    ? url('booking?unit=' . $id)
    : route('login.form');
?>
<section class="section" style="padding-bottom: var(--sp-6);">
    <div class="container-page">
        <p class="text-muted" style="margin:0 0 var(--sp-3); font-size: var(--text-sm);">
            <a href="<?= url('katalog/game') ?>">Daftar Game</a> &rsaquo; <?= e($game['judul']) ?>
        </p>
        <h1 class="display" style="font-size: var(--text-3xl); margin-bottom: var(--sp-2);"><?= e($game['judul']) ?></h1>
        <div class="flex flex-wrap gap-2">
            <span class="badge badge-cyan"><?= e($game['platform']) ?></span>
            <span class="badge badge-violet"><?= e($game['genre']) ?></span>
            <span class="badge badge-neutral">Usia <?= e($game['rating']) ?></span>
        </div>
    </div>
</section>

<section style="padding-bottom: var(--sp-12);">
    <div class="container-page grid gap-4 lg:grid-cols-3">

        <div class="lg:col-span-2 grid gap-4">
            <div class="qs-card">
                <div class="grid gap-4 sm:grid-cols-3">
                    <div class="sm:col-span-1">
                        <div class="unit-card__media" style="aspect-ratio: 3 / 4; margin-bottom: 0;">
                            <?php if (!empty($game['cover_path'])): ?>
                                <img src="<?= e(upload_url($game['cover_path'])) ?>" alt="Sampul <?= e($game['judul']) ?>">
                            <?php else: ?>
                                <span class="unit-card__code"><?= e($game['platform']) ?></span>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="sm:col-span-2">
                        <h2 class="display" style="font-size: var(--text-sm); color: var(--text-secondary); letter-spacing: .1em;">
                            Sinopsis
                        </h2>
                        <p class="text-secondary" style="margin:0 0 var(--sp-4); max-width: 68ch;">
                            <?= nl2br(e((string) $game['deskripsi'])) ?>
                        </p>
                        <a href="<?= url('booking') ?>" class="btn btn-primary btn-sm">Pesan Slot Sekarang</a>
                    </div>
                </div>
            </div>

            <div class="qs-panel">
                <div class="qs-panel__head">
                    <h2 class="display" style="font-size: var(--text-base); margin:0;">Tersedia di Unit Ini</h2>
                    <span class="text-muted" style="font-size: var(--text-xs);"><?= count($units) ?> unit</span>
                </div>

                <?php if ($units === []): ?>
                    <div class="qs-panel__body">
                        <div class="empty-state" style="padding: var(--sp-8) 0;">
                            <p style="margin:0;">
                                Game ini belum terpasang di unit mana pun. Hubungi operator bila ingin memintanya.
                            </p>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="table-wrap" style="border:0; border-radius:0;">
                        <table class="table" style="min-width: 520px;">
                            <thead>
                                <tr>
                                    <th>Unit</th>
                                    <th>Kategori</th>
                                    <th>Slot kosong</th>
                                    <th>Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($units as $u): ?>
                                    <tr>
                                        <td>
                                            <a href="<?= url('katalog/unit/' . $u['kode_unit']) ?>" class="mono"
                                               style="color: var(--text-primary); font-weight:600;"><?= e($u['kode_unit']) ?></a>
                                            <p class="text-muted" style="margin:2px 0 0; font-size: var(--text-xs);"><?= e($u['nama']) ?></p>
                                        </td>
                                        <td>
                                            <span class="badge <?= status_class((string) $u['status_realtime']) ?>">
                                                <?= e(status_label((string) $u['status_realtime'])) ?>
                                            </span>
                                        </td>
                                        <td class="num">
                                            <span class="<?= (int) $u['slot_kosong'] > 0 ? 'text-success' : 'text-danger' ?>"
                                                  style="font-weight:600;"><?= (int) $u['slot_kosong'] ?></span>
                                            <span class="text-muted"> / hari ini</span>
                                        </td>
                                        <td>
                                            <?php if ($u['status_realtime'] === 'maintenance'): ?>
                                                <button type="button" class="btn btn-sm" disabled>Perawatan</button>
                                            <?php else: ?>
                                                <a href="<?= e($tautanPesan((int) $u['id'])) ?>" class="btn btn-outline btn-sm">Pesan</a>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <div class="grid gap-4" style="align-content: start;">
            <div class="qs-card">
                <h2 class="display" style="font-size: var(--text-sm); color: var(--text-secondary); letter-spacing: .1em;">
                    Informasi Game
                </h2>
                <ul style="list-style:none; margin:0; padding:0;">
                    <?php foreach ([
                        'Platform' => (string) $game['platform'],
                        'Genre'    => (string) $game['genre'],
                        'Batas usia' => (string) $game['rating'],
                        'Jumlah unit' => count($units) . ' unit',
                    ] as $label => $nilai): ?>
                        <li class="flex items-center justify-between gap-3 py-2" style="border-bottom: 1px solid var(--border-soft);">
                            <span class="text-secondary" style="font-size: var(--text-sm);"><?= e($label) ?></span>
                            <span class="mono" style="font-size: var(--text-sm);"><?= e($nilai) ?></span>
                        </li>
                    <?php endforeach; ?>
                </ul>
                <p class="text-muted" style="margin: var(--sp-3) 0 0; font-size: var(--text-xs);">
                    Ketersediaan dihitung per <?= e(tgl_indo($hariIni)) ?>.
                </p>
            </div>

            <?php if ($serupa !== []): ?>
                <div class="qs-panel">
                    <div class="qs-panel__head">
                        <h2 class="display" style="font-size: var(--text-sm); margin:0;">Genre Serupa</h2>
                    </div>
                    <div style="padding: var(--sp-3);">
                        <?php foreach ($serupa as $s): ?>
                            <a href="<?= url('katalog/game/' . (int) $s['id']) ?>" class="side-link">
                                <span class="min-w-0">
                                    <span style="display:block; font-size: var(--text-sm); color: var(--text-primary);"><?= e($s['judul']) ?></span>
                                    <span class="text-muted" style="font-size: var(--text-xs);"><?= e($s['platform']) ?> · <?= e($s['rating']) ?></span>
                                </span>
                            </a>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>
        </div>

    </div>
</section>

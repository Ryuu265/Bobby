<?php
/**
 * pages/katalog/unit-detail.php — Satu unit: foto, spesifikasi, game, jadwal hari ini.
 *
 * @var array $unit
 * @var string $statusNow
 * @var array $games
 * @var array $slots
 * @var array $terisi
 * @var array $operasional
 * @var string $hariIni
 * @var array $kepadatan
 * @var array|null $authUser
 */

use App\Core\View;
use App\Models\Setting;

View::set('title', $unit['kode_unit'] . ' — ' . $unit['nama'] . ' | ' . Setting::get('nama_rental', config('app.name')));
View::set('meta_description', mb_substr((string) $unit['deskripsi'], 0, 150));

$tautanPesan = $authUser
    ? url('booking?unit=' . (int) $unit['id'])
    : route('login.form');

$kelasSlot = static fn (array $s): string => $s['tersedia'] ? '' : ($s['alasan'] === 'lewat' ? ' is-past' : ' is-taken');

$kosong = count(array_filter($slots, static fn (array $s): bool => $s['tersedia']));
$tutupLabel = $operasional['tutup'] === 24 ? '24.00' : sprintf('%02d.00', $operasional['tutup']);

$spesifikasi = [
    'Jenis konsol' => (string) $unit['tipe'],
    'Kategori'     => status_label((string) $unit['kategori']),
    'Kapasitas'    => (int) $unit['kapasitas'] . ' pemain',
    'Tarif per jam' => rupiah($unit['harga_per_jam']),
    'Game terpasang' => count($games) . ' judul',
    'Jam operasional hari ini' => sprintf('%02d.00', $operasional['buka']) . ' - ' . $tutupLabel,
];
?>
<section class="section" style="padding-bottom: var(--sp-6);">
    <div class="container-page">
        <p class="text-muted" style="margin:0 0 var(--sp-3); font-size: var(--text-sm);">
            <a href="<?= url('katalog') ?>">Katalog Unit</a> &rsaquo; <span class="mono"><?= e($unit['kode_unit']) ?></span>
        </p>
        <div class="flex flex-wrap items-start justify-between gap-4">
            <div>
                <h1 class="display" style="font-size: var(--text-3xl); margin-bottom: 4px;"><?= e($unit['nama']) ?></h1>
                <p class="text-secondary" style="margin:0; font-size: var(--text-sm);">
                    <?= e($unit['kode_unit']) ?> · <?= e($unit['tipe']) ?> ·
                    <?= (int) $unit['kapasitas'] ?> pemain · <?= count($games) ?> game
                </p>
            </div>
            <span class="badge <?= status_class($statusNow) ?>" style="font-size: var(--text-sm);">
                <?= e(status_label($statusNow)) ?>
            </span>
        </div>
    </div>
</section>

<section style="padding-bottom: var(--sp-12);">
    <div class="container-page grid gap-4 lg:grid-cols-3">

        <!-- Kolom kiri: foto + spesifikasi + deskripsi -->
        <div class="lg:col-span-2 grid gap-4">
            <div class="qs-card">
                <div class="unit-card__media" style="aspect-ratio: 16 / 9;">
                    <?php if (!empty($unit['foto_path'])): ?>
                        <img src="<?= e(upload_url($unit['foto_path'])) ?>" alt="Foto <?= e($unit['nama']) ?>">
                    <?php else: ?>
                        <span class="unit-card__code"><?= e($unit['kode_unit']) ?></span>
                    <?php endif; ?>
                </div>

                <p class="text-secondary" style="margin: var(--sp-4) 0 0; max-width: 68ch;">
                    <?= e((string) $unit['deskripsi']) ?>
                </p>

                <hr class="divider">

                <h2 class="display" style="font-size: var(--text-sm); color: var(--text-secondary); letter-spacing: .1em;">
                    Spesifikasi
                </h2>
                <dl class="grid gap-4 sm:grid-cols-2" style="margin:0;">
                    <?php foreach ($spesifikasi as $label => $nilai): ?>
                        <div>
                            <dt class="text-muted" style="font-size: var(--text-xs); text-transform: uppercase; letter-spacing: .06em;">
                                <?= e($label) ?>
                            </dt>
                            <dd class="mono" style="margin:4px 0 0; font-size: var(--text-sm); color: var(--text-primary);">
                                <?= e($nilai) ?>
                            </dd>
                        </div>
                    <?php endforeach; ?>
                </dl>
            </div>

            <div class="qs-panel">
                <div class="qs-panel__head">
                    <h2 class="display" style="font-size: var(--text-base); margin:0;">Game Terpasang</h2>
                    <a href="<?= url('katalog/game') ?>" class="btn btn-ghost btn-sm">Semua Game</a>
                </div>
                <div class="qs-panel__body">
                    <?php if ($games === []): ?>
                        <p class="text-secondary" style="margin:0;">
                            Belum ada game terdaftar untuk unit ini. Tanya operator saat datang untuk permintaan khusus.
                        </p>
                    <?php else: ?>
                        <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                            <?php foreach ($games as $g): ?>
                                <a href="<?= url('katalog/game/' . (int) $g['id']) ?>"
                                   class="qs-card qs-card--flat qs-card--hoverable" style="padding: var(--sp-4); display:block;">
                                    <p style="margin:0; font-weight:600; font-size: var(--text-sm);"><?= e($g['judul']) ?></p>
                                    <p class="text-muted" style="margin:4px 0 0; font-size: var(--text-xs);">
                                        <?= e($g['genre']) ?> · <?= e($g['rating']) ?>
                                    </p>
                                </a>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Kolom kanan: ketersediaan + CTA -->
        <div class="grid gap-4">
            <div class="qs-card">
                <h2 class="display" style="font-size: var(--text-base); margin-bottom: var(--sp-2);">Jadwal Hari Ini</h2>
                <p class="text-muted" style="margin:0 0 var(--sp-4); font-size: var(--text-xs);"><?= e(tgl_indo($hariIni)) ?></p>

                <?php if ($slots === []): ?>
                    <div class="empty-state" style="padding: var(--sp-8) 0;">
                        <p style="margin:0;">Belum ada slot untuk tanggal ini.</p>
                    </div>
                <?php else: ?>
                    <div class="flex items-center justify-between gap-2" style="margin-bottom: var(--sp-3);">
                        <span class="text-muted" style="font-size: var(--text-xs);">Durasi 1 jam</span>
                        <span class="text-<?= $kosong > 0 ? 'success' : 'danger' ?>" style="font-size: var(--text-xs); font-weight:600;">
                            <?= $kosong ?> dari <?= count($slots) ?> kosong
                        </span>
                    </div>

                    <div class="slot-grid">
                        <?php foreach ($slots as $s): ?>
                            <span class="slot<?= $kelasSlot($s) ?>" title="<?= $s['tersedia'] ? 'Masih kosong' : 'Tidak tersedia' ?>">
                                <?= e($s['label']) ?>
                            </span>
                        <?php endforeach; ?>
                    </div>

                    <div class="flex flex-wrap gap-3" style="margin-top: var(--sp-4); font-size: var(--text-xs); color: var(--text-muted);">
                        <span class="flex items-center gap-2"><span class="slot" style="padding:.2rem .5rem; font-size:10px;">&nbsp;</span> Kosong</span>
                        <span class="flex items-center gap-2"><span class="slot is-taken" style="padding:.2rem .5rem; font-size:10px;">&nbsp;</span> Terisi</span>
                        <span class="flex items-center gap-2"><span class="slot is-past" style="padding:.2rem .5rem; font-size:10px;">&nbsp;</span> Terlewat</span>
                    </div>

                    <div style="margin-top: var(--sp-4);">
                        <div class="flex items-center justify-between gap-2" style="margin-bottom: var(--sp-2);">
                            <span class="text-muted" style="font-size: var(--text-xs);">Keterisian</span>
                            <span class="mono" style="font-size: var(--text-xs);"><?= (int) $kepadatan['persen'] ?>%</span>
                        </div>
                        <div class="bar">
                            <div class="bar__fill<?= $kepadatan['persen'] >= 80 ? ' bar__fill--danger' : ($kepadatan['persen'] >= 50 ? ' bar__fill--warning' : '') ?>"
                                 style="width: <?= (int) $kepadatan['persen'] ?>%;"></div>
                        </div>
                        <p class="text-muted" style="margin: var(--sp-2) 0 0; font-size: var(--text-xs);">
                            <?= (int) $kepadatan['jumlah_pesanan'] ?> pesanan ·
                            <?= e((string) $kepadatan['jam_terisi']) ?> jam terisi dari
                            <?= (int) $kepadatan['total_jam_operasional'] ?> jam operasional
                        </p>
                    </div>
                <?php endif; ?>

                <?php if ($terisi !== []): ?>
                    <hr class="divider">
                    <h3 class="display" style="font-size: var(--text-sm); color: var(--text-secondary); letter-spacing: .1em;">
                        Sedang Dipesan
                    </h3>
                    <ul style="list-style:none; margin:0; padding:0;">
                        <?php foreach ($terisi as $t): ?>
                            <li class="flex items-center justify-between gap-3 py-2"
                                style="border-bottom: 1px solid var(--border-soft); font-size: var(--text-sm);">
                                <span class="mono"><?= e($t['rentang']) ?></span>
                                <span class="badge <?= status_class($t['status']) ?>"><?= e(status_label($t['status'])) ?></span>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>

                <?php if ($statusNow === 'maintenance'): ?>
                    <button type="button" class="btn btn-block btn-lg" disabled style="margin-top: var(--sp-5);">
                        Unit Sedang Perawatan
                    </button>
                    <p class="text-muted" style="margin: var(--sp-2) 0 0; font-size: var(--text-xs); text-align:center;">
                        Unit ini dikunci sementara oleh operator.
                    </p>
                <?php else: ?>
                    <a href="<?= e($tautanPesan) ?>" class="btn btn-primary btn-block btn-lg" style="margin-top: var(--sp-5);">
                        Pesan <?= e($unit['kode_unit']) ?>
                    </a>
                    <?php if (!$authUser): ?>
                        <p class="text-muted" style="margin: var(--sp-2) 0 0; font-size: var(--text-xs); text-align:center;">
                            Anda perlu masuk dulu untuk membuat pesanan.
                        </p>
                    <?php endif; ?>
                <?php endif; ?>
            </div>

            <div class="qs-card">
                <h2 class="display" style="font-size: var(--text-sm); color: var(--text-secondary); letter-spacing: .1em;">
                    Rincian Tarif
                </h2>
                <ul style="list-style:none; margin:0; padding:0;">
                    <li class="flex items-center justify-between gap-3 py-2" style="border-bottom: 1px solid var(--border-soft);">
                        <span class="text-secondary" style="font-size: var(--text-sm);">Tarif dasar</span>
                        <span class="mono" style="font-size: var(--text-sm);"><?= rupiah($unit['harga_per_jam']) ?>/jam</span>
                    </li>
                    <li class="flex items-center justify-between gap-3 py-2" style="border-bottom: 1px solid var(--border-soft);">
                        <span class="text-secondary" style="font-size: var(--text-sm);">3 jam</span>
                        <span class="mono" style="font-size: var(--text-sm);"><?= rupiah((float) $unit['harga_per_jam'] * 3) ?></span>
                    </li>
                    <li class="flex items-center justify-between gap-3 py-2">
                        <span class="text-secondary" style="font-size: var(--text-sm);">8 jam</span>
                        <span class="mono" style="font-size: var(--text-sm);"><?= rupiah((float) $unit['harga_per_jam'] * 8) ?></span>
                    </li>
                </ul>
                <p class="text-muted" style="margin: var(--sp-3) 0 0; font-size: var(--text-xs);">
                    Paket &amp; promo dihitung otomatis di form pemesanan. Lokasi:
                    <?= e(Setting::get('alamat', '-')) ?>
                </p>
            </div>
        </div>

    </div>
</section>

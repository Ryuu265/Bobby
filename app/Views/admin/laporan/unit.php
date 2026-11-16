<?php
/**
 * admin/laporan/unit.php — Kinerja per unit + kepadatan jadwal hari ini.
 */

use App\Core\View;

View::set('title', 'Laporan Unit — ' . config('app.name'));
View::set('page_title', 'Laporan Kinerja Unit');
View::set('page_subtitle', tgl_indo($dari) . ' — ' . tgl_indo($sampai));

$tanya = static function (string $path, array $params): string {
    $qs = http_build_query($params);
    return $path . ($qs !== '' ? '?' . $qs : '');
};

View::set('scripts', '<script src="' . asset('js/dashboard.js') . '" defer></script>');
$configJs = ['grafik' => [['el' => 'grafik-unit', 'jenis' => 'bar', 'warna' => 'cyan', 'tinggi' => 180, 'data' => $grafik]]];
?>
<div class="grid gap-5">

    <section class="qs-panel">
        <div class="qs-panel__head flex-wrap gap-3">
            <form method="get" action="<?= e(route('admin.laporan.unit')) ?>" class="flex flex-wrap items-end gap-3">
                <div class="field" style="margin: 0;">
                    <label class="label" for="dari">Dari</label>
                    <input class="input" type="date" id="dari" name="dari" value="<?= e($dari) ?>" style="width: 175px;">
                </div>
                <div class="field" style="margin: 0;">
                    <label class="label" for="sampai">Sampai</label>
                    <input class="input" type="date" id="sampai" name="sampai" value="<?= e($sampai) ?>" style="width: 175px;">
                </div>
                <button type="submit" class="btn btn-outline btn-sm">Terapkan</button>
            </form>
            <div class="flex flex-wrap gap-2" style="margin-left: auto;">
                <a class="btn btn-ghost btn-sm" href="<?= e(route('admin.laporan')) ?>">Semua laporan</a>
                <a class="btn btn-outline btn-sm"
                   href="<?= e($tanya(route('admin.laporan.ekspor'), ['jenis' => 'unit', 'dari' => $dari, 'sampai' => $sampai])) ?>">Unduh CSV</a>
                <a class="btn btn-primary btn-sm" target="_blank" rel="noopener"
                   href="<?= e($tanya(route('admin.laporan.cetak'), ['jenis' => 'unit', 'dari' => $dari, 'sampai' => $sampai])) ?>">Cetak / PDF</a>
            </div>
        </div>
        <div class="qs-panel__body">
            <div class="flex flex-wrap items-center justify-between gap-3" style="margin-bottom: var(--sp-4);">
                <p class="text-secondary" style="margin: 0; font-size: var(--text-sm);">
                    Jam operasional <?= e($hari) ?>:
                    <strong><?= e(sprintf('%02d.00', (int) $jam['buka'])) ?> —
                        <?= $jam['tutup'] === 24 ? '24.00' : e(sprintf('%02d.00', (int) $jam['tutup'])) ?> WIB</strong>
                    · rata-rata keterisian hari ini <?= e(number_format((float) $total['rataRata'], 1)) ?>%
                </p>
                <a class="btn btn-outline btn-sm" href="<?= e(route('admin.unit')) ?>">Kelola Unit</a>
            </div>
            <div id="grafik-unit" class="flex gap-1 items-stretch"></div>
        </div>
    </section>

    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <?php foreach ([
            ['Unit terdaftar', number_format((float) $total['unit']), 'Termasuk unit maintenance'],
            ['Pesanan periode', number_format((float) $total['pesanan']), number_format((float) $total['jam']) . ' jam sewa'],
            ['Omzet unit', rupiah($total['omzet']), 'Rata-rata ' . rupiah($total['unit'] > 0 ? $total['omzet'] / $total['unit'] : 0) . '/unit'],
            ['Keterisian hari ini', number_format((float) $total['rataRata'], 1) . '%', 'Rata-rata semua unit'],
        ] as [$label, $nilai, $hint]): ?>
            <div class="stat">
                <p class="stat__label"><?= e($label) ?></p>
                <p class="stat__value"><?= e($nilai) ?></p>
                <p class="stat__hint"><?= e($hint) ?></p>
            </div>
        <?php endforeach; ?>
    </div>

    <section class="qs-panel">
        <div class="qs-panel__head">
            <h2 class="display" style="font-size: var(--text-lg); margin: 0;">Rincian per Unit</h2>
            <div class="flex items-center gap-2">
                <label class="text-muted" for="cari-unit" style="font-size: var(--text-xs);">Cari unit</label>
                <input class="input" type="search" id="cari-unit" data-table-filter="#tabel-unit"
                       placeholder="PS5-01" style="width: 160px;">
            </div>
        </div>
        <div class="qs-panel__body" style="padding: 0;">
            <?php if ($baris === []): ?>
                <div class="empty-state">
                    <p class="display" style="font-size: var(--text-lg); margin-bottom: 4px;">Belum ada unit</p>
                    <p class="text-secondary" style="margin: 0 auto; font-size: var(--text-sm); max-width: 54ch;">
                        Tambahkan unit PlayStation lewat menu Kelola Unit agar laporan kinerja terisi.
                    </p>
                </div>
            <?php else: ?>
                <div class="table-wrap" style="border: 0; border-radius: 0;">
                    <table class="table" id="tabel-unit">
                        <thead>
                            <tr>
                                <th>Unit</th>
                                <th>Status</th>
                                <th class="num">Harga/Jam</th>
                                <th class="num">Pesanan</th>
                                <th class="num">Jam</th>
                                <th class="num">Omzet</th>
                                <th>Kepadatan hari ini</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($baris as $u): ?>
                                <?php $padat = (int) ($u['hari_ini']['persen'] ?? 0); ?>
                                <tr>
                                    <td>
                                        <p style="margin: 0; font-weight: 600;"><?= e($u['kode_unit']) ?> · <?= e($u['tipe']) ?></p>
                                        <p class="text-muted" style="margin: 2px 0 0; font-size: var(--text-xs);"><?= e($u['nama']) ?></p>
                                    </td>
                                    <td><span class="badge <?= status_class($u['status']) ?>"><?= e(status_label($u['status'])) ?></span></td>
                                    <td class="num"><?= e(rupiah($u['harga_per_jam'])) ?></td>
                                    <td class="num"><?= e(number_format((float) $u['jumlah_pesanan'])) ?></td>
                                    <td class="num"><?= e(number_format((float) $u['total_jam'])) ?></td>
                                    <td class="num text-cyan"><?= e(rupiah($u['omzet'])) ?></td>
                                    <td style="min-width: 180px;">
                                        <div class="flex items-center gap-2">
                                            <span class="mono" style="font-size: var(--text-xs); width: 38px;"><?= $padat ?>%</span>
                                            <div class="bar" style="flex: 1;">
                                                <div class="bar__fill<?= $padat >= 80 ? ' bar__fill--warning' : '' ?>" style="width: <?= $padat ?>%;"></div>
                                            </div>
                                        </div>
                                        <p class="text-muted" style="margin: 4px 0 0; font-size: var(--text-xs);">
                                            <?= e($u['hari_ini']['jam_terisi'] ?? 0) ?> jam terisi dari
                                            <?= e($u['hari_ini']['total_jam_operasional'] ?? 0) ?> jam
                                        </p>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </section>

    <p class="text-secondary" style="margin: 0; font-size: var(--text-sm); max-width: 78ch;">
        Kepadatan dihitung dari slot jadwal yang sudah terisi pada hari ini, bukan dari riwayat.
        Unit dengan omzet tinggi tetapi keterisian rendah cocok dijadikan paket promo pada jam sepi.
    </p>
</div>

<script id="qs-admin-data" type="application/json"><?= json_encode($configJs, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?></script>

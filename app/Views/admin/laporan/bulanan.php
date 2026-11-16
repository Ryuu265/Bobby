<?php
/**
 * admin/laporan/bulanan.php — Rekap per bulan + pertumbuhan antar bulan.
 */

use App\Core\View;

View::set('title', 'Laporan Bulanan — ' . config('app.name'));
View::set('page_title', 'Laporan Bulanan');
View::set('page_subtitle', 'Perbandingan 6 bulan terakhir · ' . tgl_indo($dari) . ' — ' . tgl_indo($sampai));

$tanya = static function (string $path, array $params): string {
    $qs = http_build_query($params);
    return $path . ($qs !== '' ? '?' . $qs : '');
};

$namaBulan = static function (string $bulan): string {
    $parts = explode('-', $bulan);
    if (count($parts) < 2) {
        return $bulan;
    }
    [$t, $m] = $parts;
    $daftar = [1 => 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni',
        'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];
    return $daftar[(int) $m] . ' ' . $t;
};

// Label pendek untuk grafik: "Nov 26"
$labelGrafik = static function (string $bulan): string {
    $parts = explode('-', $bulan);
    if (count($parts) < 2) {
        return $bulan;
    }
    return date('M y', (int) strtotime($parts[0] . '-' . $parts[1] . '-01'));
};

$omzetTertinggi = $baris === [] ? 0.0 : max(array_map(static fn (array $b): float => (float) $b['omzet'], $baris));
$bulanTerbaik   = '-';
foreach ($baris as $b) {
    if ((float) $b['omzet'] === $omzetTertinggi) {
        $bulanTerbaik = $namaBulan((string) $b['bulan']);
        break;
    }
}

$grafikJs = array_map(
    static fn (array $g): array => ['label' => $labelGrafik((string) $g['label']), 'nilai' => (float) $g['nilai']],
    $grafik
);

View::set('scripts', '<script src="' . asset('js/dashboard.js') . '" defer></script>');
$configJs = ['grafik' => [['el' => 'grafik-bulanan', 'jenis' => 'bar', 'warna' => 'violet', 'tinggi' => 190, 'data' => $grafikJs]]];
?>
<div class="grid gap-5">

    <section class="qs-panel">
        <div class="qs-panel__head flex-wrap gap-3">
            <form method="get" action="<?= e(route('admin.laporan.bulanan')) ?>" class="flex flex-wrap items-end gap-3">
                <div class="field" style="margin: 0;">
                    <label class="label" for="dari">Bulan dari</label>
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
                   href="<?= e($tanya(route('admin.laporan.ekspor'), ['jenis' => 'bulanan', 'dari' => $dari, 'sampai' => $sampai])) ?>">Unduh CSV</a>
                <a class="btn btn-primary btn-sm" target="_blank" rel="noopener"
                   href="<?= e($tanya(route('admin.laporan.cetak'), ['jenis' => 'bulanan', 'dari' => $dari, 'sampai' => $sampai])) ?>">Cetak / PDF</a>
            </div>
        </div>
        <div class="qs-panel__body">
            <?php if ($baris === []): ?>
                <div class="empty-state">
                    <p class="text-secondary" style="margin: 0; font-size: var(--text-sm);">
                        Belum ada pesanan pada rentang bulan ini.
                    </p>
                </div>
            <?php else: ?>
                <div id="grafik-bulanan" class="flex gap-1 items-stretch"></div>
            <?php endif; ?>
        </div>
    </section>

    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <?php foreach ([
            ['Omzet keseluruhan', rupiah($total['omzet']), count($baris) . ' bulan terhitung'],
            ['Total pesanan', number_format((float) $total['pesanan']), $total['selesai'] . ' pesanan selesai'],
            ['Jam sewa', number_format((float) $total['jam']) . ' jam', 'Rata-rata ' . number_format((float) ($total['pesanan'] > 0 ? $total['jam'] / $total['pesanan'] : 0), 1) . ' jam/pesanan'],
            ['Bulan terbaik', $bulanTerbaik, 'Omzet ' . rupiah($omzetTertinggi)],
        ] as [$label, $nilai, $hint]): ?>
            <div class="stat">
                <p class="stat__label"><?= e($label) ?></p>
                <p class="stat__value" style="font-size: <?= $label === 'Bulan terbaik' ? 'var(--text-xl)' : '2rem' ?>;"><?= e($nilai) ?></p>
                <p class="stat__hint"><?= e($hint) ?></p>
            </div>
        <?php endforeach; ?>
    </div>

    <section class="qs-panel">
        <div class="qs-panel__head">
            <h2 class="display" style="font-size: var(--text-lg); margin: 0;">Rekap Bulanan</h2>
            <span class="text-muted" style="font-size: var(--text-xs);">Pertumbuhan dihitung dari omzet bulan sebelumnya</span>
        </div>
        <div class="qs-panel__body" style="padding: 0;">
            <?php if ($baris === []): ?>
                <div class="empty-state">
                    <p class="display" style="font-size: var(--text-lg); margin-bottom: 4px;">Tidak ada data</p>
                    <p class="text-secondary" style="margin: 0 auto; font-size: var(--text-sm); max-width: 54ch;">
                        Perluas rentang bulan di atas untuk melihat perbandingan pendapatan.
                    </p>
                </div>
            <?php else: ?>
                <div class="table-wrap" style="border: 0; border-radius: 0;">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Bulan</th>
                                <th class="num">Pesanan</th>
                                <th class="num">Jam</th>
                                <th class="num">Omzet</th>
                                <th class="num">Diskon</th>
                                <th class="num">Selesai</th>
                                <th>Pertumbuhan</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($baris as $b): ?>
                                <?php
                                $omzet  = (float) $b['omzet'];
                                $persen = $omzetTertinggi > 0 ? (int) round($omzet / $omzetTertinggi * 100) : 0;
                                $tumbuh = $b['pertumbuhan'] ?? null;
                                ?>
                                <tr>
                                    <td><strong><?= e($namaBulan((string) $b['bulan'])) ?></strong></td>
                                    <td class="num"><?= e(number_format((float) $b['jumlah_pesanan'])) ?></td>
                                    <td class="num"><?= e(number_format((float) $b['total_jam'])) ?></td>
                                    <td class="num text-cyan"><?= e(rupiah($omzet)) ?></td>
                                    <td class="num text-secondary"><?= e(rupiah($b['total_diskon'])) ?></td>
                                    <td class="num"><?= e(number_format((float) $b['jumlah_selesai'])) ?></td>
                                    <td style="min-width: 190px;">
                                        <div class="flex items-center gap-2">
                                            <?php if ($tumbuh === null): ?>
                                                <span class="badge badge-neutral">Pembanding awal</span>
                                            <?php else: ?>
                                                <span class="badge <?= $tumbuh >= 0 ? 'badge-success' : 'badge-danger' ?>">
                                                    <?= $tumbuh >= 0 ? '+' : '' ?><?= e(number_format((float) $tumbuh, 1)) ?>%
                                                </span>
                                            <?php endif; ?>
                                            <div class="bar" style="flex: 1;">
                                                <div class="bar__fill<?= $tumbuh !== null && $tumbuh < 0 ? ' bar__fill--warning' : '' ?>"
                                                     style="width: <?= $persen ?>%;"></div>
                                            </div>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                        <tfoot>
                            <tr>
                                <th>Total</th>
                                <th class="num"><?= e(number_format((float) $total['pesanan'])) ?></th>
                                <th class="num"><?= e(number_format((float) $total['jam'])) ?></th>
                                <th class="num"><?= e(rupiah($total['omzet'])) ?></th>
                                <th colspan="3"></th>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </section>
</div>

<script id="qs-admin-data" type="application/json"><?= json_encode($configJs, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?></script>

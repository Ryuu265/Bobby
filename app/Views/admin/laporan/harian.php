<?php
/**
 * admin/laporan/harian.php — Omzet & pesanan per tanggal.
 */

use App\Core\View;

View::set('title', 'Laporan Harian — ' . config('app.name'));
View::set('page_title', 'Laporan Harian');
View::set('page_subtitle', tgl_indo($dari) . ' — ' . tgl_indo($sampai));

$tanya = static function (string $path, array $params): string {
    $qs = http_build_query($params);
    return $path . ($qs !== '' ? '?' . $qs : '');
};

$dasar = ['dari' => $dari, 'sampai' => $sampai];
View::set('scripts', '<script src="' . asset('js/dashboard.js') . '" defer></script>');
$configJs = ['grafik' => [['el' => 'grafik-harian', 'jenis' => 'bar', 'warna' => 'cyan', 'tinggi' => 170, 'data' => $grafik]]];
?>
<div class="grid gap-5">

    <!-- ============ PERIODE + AKSI ============ -->
    <section class="qs-panel">
        <div class="qs-panel__head flex-wrap gap-3">
            <form method="get" action="<?= e(route('admin.laporan.harian')) ?>" class="flex flex-wrap items-end gap-3">
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
                <a class="btn btn-outline btn-sm" href="<?= e($tanya(route('admin.laporan.ekspor'), array_merge(['jenis' => 'harian'], $dasar))) ?>">Unduh CSV</a>
                <a class="btn btn-primary btn-sm" target="_blank" rel="noopener"
                   href="<?= e($tanya(route('admin.laporan.cetak'), array_merge(['jenis' => 'harian'], $dasar))) ?>">Cetak / PDF</a>
            </div>
        </div>
        <div class="qs-panel__body">
            <div id="grafik-harian" class="flex gap-1 items-stretch"></div>
        </div>
    </section>

    <!-- ============ RINGKASAN ============ -->
    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <?php foreach ([
            ['Omzet periode', rupiah($total['omzet']), 'Diskon diberikan ' . rupiah($total['diskon'])],
            ['Total pesanan', number_format((float) $total['pesanan']), count($baris) . ' hari aktif'],
            ['Jam sewa', number_format((float) $total['jam']) . ' jam', 'Rata-rata ' . rupiah($total['pesanan'] > 0 ? $total['omzet'] / $total['pesanan'] : 0) . ' per pesanan'],
            ['Pesanan lunas', number_format((float) $total['lunas']), 'Omzet controller ' . rupiah($total['controller'])],
        ] as [$label, $nilai, $hint]): ?>
            <div class="stat">
                <p class="stat__label"><?= e($label) ?></p>
                <p class="stat__value"><?= e($nilai) ?></p>
                <p class="stat__hint"><?= e($hint) ?></p>
            </div>
        <?php endforeach; ?>
    </div>

    <!-- ============ TABEL ============ -->
    <section class="qs-panel">
        <div class="qs-panel__head">
            <h2 class="display" style="font-size: var(--text-lg); margin: 0;">Rincian Harian</h2>
            <div class="flex items-center gap-2">
                <label class="text-muted" for="cari-harian" style="font-size: var(--text-xs);">Cari tanggal</label>
                <input class="input" type="search" id="cari-harian" data-table-filter="#tabel-harian"
                       placeholder="contoh: 2026-11" style="width: 170px;">
            </div>
        </div>
        <div class="qs-panel__body" style="padding: 0;">
            <?php if ($baris === []): ?>
                <div class="empty-state">
                    <p class="display" style="font-size: var(--text-lg); margin-bottom: 4px;">Belum ada transaksi</p>
                    <p class="text-secondary" style="margin: 0 auto; font-size: var(--text-sm); max-width: 54ch;">
                        Tidak ditemukan pesanan pada periode ini. Coba perluas rentang tanggal
                        atau periksa apakah pesanan masih berstatus dibatalkan.
                    </p>
                </div>
            <?php else: ?>
                <div class="table-wrap" style="border: 0; border-radius: 0;">
                    <table class="table" id="tabel-harian">
                        <thead>
                            <tr>
                                <th>Tanggal</th>
                                <th class="num">Pesanan</th>
                                <th class="num">Batal</th>
                                <th class="num">Jam</th>
                                <th class="num">Omzet</th>
                                <th class="num">Diskon</th>
                                <th class="num">Transfer</th>
                                <th class="num">Di tempat</th>
                                <th class="num">Lunas</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($baris as $b): ?>
                                <tr>
                                    <td>
                                        <a href="<?= e($tanya(route('admin.laporan.harian'), ['dari' => $b['tanggal'], 'sampai' => $b['tanggal']])) ?>">
                                            <?= e(tgl_indo($b['tanggal'])) ?>
                                        </a>
                                    </td>
                                    <td class="num"><?= e(number_format((float) $b['jumlah_pesanan'])) ?></td>
                                    <td class="num text-secondary"><?= e(number_format((float) $b['jumlah_batal'])) ?></td>
                                    <td class="num"><?= e(number_format((float) $b['total_jam'])) ?></td>
                                    <td class="num text-cyan"><?= e(rupiah($b['omzet'])) ?></td>
                                    <td class="num text-secondary"><?= e(rupiah($b['total_diskon'])) ?></td>
                                    <td class="num"><?= e(number_format((float) $b['bayar_transfer'])) ?></td>
                                    <td class="num"><?= e(number_format((float) $b['bayar_tempat'])) ?></td>
                                    <td class="num"><?= e(number_format((float) $b['sudah_lunas'])) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                        <tfoot>
                            <tr>
                                <th>Total</th>
                                <th class="num"><?= e(number_format((float) $total['pesanan'])) ?></th>
                                <th></th>
                                <th class="num"><?= e(number_format((float) $total['jam'])) ?></th>
                                <th class="num"><?= e(rupiah($total['omzet'])) ?></th>
                                <th class="num"><?= e(rupiah($total['diskon'])) ?></th>
                                <th colspan="3"></th>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </section>

    <p class="text-secondary" style="margin: 0; font-size: var(--text-sm); max-width: 78ch;">
        Angka mengabaikan pesanan berstatus dibatalkan. Kolom "Lunas" menghitung pesanan yang
        pembayarannya sudah diverifikasi, sehingga selisihnya adalah piutang yang perlu ditagih.
    </p>
</div>

<script id="qs-admin-data" type="application/json"><?= json_encode($configJs, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?></script>

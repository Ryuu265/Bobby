<?php
/**
 * admin/laporan/index.php — Pusat laporan: pilih periode + tautan sub-laporan.
 */

use App\Core\View;

View::set('title', 'Laporan & Ekspor — ' . config('app.name'));
View::set('page_title', 'Laporan & Ekspor');
View::set('page_subtitle', 'Pilih periode, tinjau angka, lalu unduh CSV atau cetak PDF');

/** Tautan dengan query string (route() hanya mengisi placeholder {id}). */
$tanya = static function (string $path, array $params): string {
    $qs = http_build_query($params);
    return $path . ($qs !== '' ? '?' . $qs : '');
};

$preset = [
    ['label' => '7 hari terakhir', 'dari' => date('Y-m-d', strtotime('-6 day')), 'sampai' => date('Y-m-d')],
    ['label' => '30 hari terakhir', 'dari' => date('Y-m-d', strtotime('-29 day')), 'sampai' => date('Y-m-d')],
    ['label' => 'Bulan ini', 'dari' => date('Y-m-01'), 'sampai' => date('Y-m-d')],
    ['label' => '3 bulan terakhir', 'dari' => date('Y-m-d', strtotime('-3 month')), 'sampai' => date('Y-m-d')],
];

$subLaporan = [
    ['harian', 'Laporan Harian', 'Omzet, jam sewa, diskon, dan metode bayar per tanggal.', $tanya($tautan['harian'], ['dari' => $dari, 'sampai' => $sampai]), 'harian'],
    ['bulanan', 'Laporan Bulanan', 'Perbandingan pendapatan antar bulan beserta pertumbuhannya.', $tanya($tautan['bulanan'], ['dari' => date('Y-m-01', strtotime('-5 month')), 'sampai' => $sampai]), 'bulanan'],
    ['unit', 'Kinerja Unit', 'Kontribusi tiap konsol plus kepadatan jadwal hari ini.', $tanya($tautan['unit'], ['dari' => $dari, 'sampai' => $sampai]), 'unit'],
    ['aktivitas', 'Jejak Aktivitas', 'Audit trail login, pesanan, pembayaran, dan perubahan data.', $tautan['aktivitas'], null],
];

$eksporTautan = static fn (string $jenis): string => $tanya($tautan['ekspor'], ['jenis' => $jenis, 'dari' => $dari, 'sampai' => $sampai]);
?>
<div class="grid gap-5">

    <!-- ============ FILTER PERIODE ============ -->
    <section class="qs-panel">
        <div class="qs-panel__head">
            <h2 class="display" style="font-size: var(--text-lg); margin: 0;">Filter Periode</h2>
            <span class="text-muted" style="font-size: var(--text-xs);">Bawaan: 30 hari terakhir</span>
        </div>
        <div class="qs-panel__body">
            <!-- Form ini dipakai bersama: tombol submit memilih sub-laporan tujuan -->
            <form method="get" action="<?= e($tautan['harian']) ?>" class="grid gap-4 md:grid-cols-3 md:items-end">
                <div class="field" style="margin: 0;">
                    <label class="label" for="dari">Tanggal dari</label>
                    <input class="input" type="date" id="dari" name="dari" value="<?= e($dari) ?>">
                </div>
                <div class="field" style="margin: 0;">
                    <label class="label" for="sampai">Tanggal sampai</label>
                    <input class="input" type="date" id="sampai" name="sampai" value="<?= e($sampai) ?>">
                </div>
                <div class="flex flex-wrap gap-2 md:justify-end">
                    <button type="submit" class="btn btn-primary btn-sm" formaction="<?= e($tautan['harian']) ?>">Laporan Harian</button>
                    <button type="submit" class="btn btn-outline btn-sm" formaction="<?= e($tautan['bulanan']) ?>">Laporan Bulanan</button>
                    <button type="submit" class="btn btn-outline btn-sm" formaction="<?= e($tautan['unit']) ?>">Kinerja Unit</button>
                </div>
            </form>

            <div class="flex flex-wrap gap-2" style="margin-top: var(--sp-4);">
                <?php foreach ($preset as $p): ?>
                    <a class="btn btn-ghost btn-sm" href="<?= e($tanya($tautan['harian'], ['dari' => $p['dari'], 'sampai' => $p['sampai']])) ?>">
                        <?= e($p['label']) ?>
                    </a>
                <?php endforeach; ?>
            </div>
        </div>
    </section>

    <!-- ============ RINGKASAN CEPAT ============ -->
    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <div class="stat">
            <p class="stat__label">Omzet periode</p>
            <p class="stat__value"><?= e(rupiah($ringkas['omzet'])) ?></p>
            <p class="stat__hint"><?= e(tgl_indo($dari)) ?> — <?= e(tgl_indo($sampai)) ?></p>
        </div>
        <div class="stat">
            <p class="stat__label">Pesanan aktif</p>
            <p class="stat__value"><?= e(number_format((float) $ringkas['pesanan'])) ?></p>
            <p class="stat__hint">Tidak termasuk pesanan dibatalkan</p>
        </div>
        <div class="stat">
            <p class="stat__label">Jam sewa</p>
            <p class="stat__value"><?= e(number_format((float) $ringkas['jam'])) ?></p>
            <p class="stat__hint">Rata-rata <?= e(number_format((float) ($ringkas['pesanan'] > 0 ? $ringkas['jam'] / $ringkas['pesanan'] : 0), 1)) ?> jam/pesanan</p>
        </div>
        <div class="stat">
            <p class="stat__label">Diskon diberikan</p>
            <p class="stat__value"><?= e(rupiah($ringkas['diskon'])) ?></p>
            <p class="stat__hint">
                <?php if ($bulanIni): ?>
                    Bulan <?= e($bulanIni['bulan']) ?>: <?= e(rupiah($bulanIni['omzet'])) ?>
                <?php else: ?>
                    Belum ada data bulan ini
                <?php endif; ?>
            </p>
        </div>
    </div>

    <!-- ============ SUB LAPORAN ============ -->
    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <?php foreach ($subLaporan as [$jenis, $judul, $isi, $link, $unduh]): ?>
            <article class="qs-card qs-card--hoverable">
                <h3 style="margin: 0; font-size: var(--text-base);"><?= e($judul) ?></h3>
                <p class="text-secondary" style="margin: var(--sp-2) 0 var(--sp-4); font-size: var(--text-sm);"><?= e($isi) ?></p>
                <div class="flex flex-wrap gap-2">
                    <a class="btn btn-primary btn-sm" href="<?= e($link) ?>">Buka</a>
                    <?php if ($unduh !== null): ?>
                        <a class="btn btn-outline btn-sm" href="<?= e($eksporTautan($unduh)) ?>">CSV</a>
                        <a class="btn btn-ghost btn-sm" target="_blank" rel="noopener"
                           href="<?= e($unduh === 'bulanan'
                                ? $tanya($tautan['cetak'], ['jenis' => 'bulanan', 'dari' => date('Y-m-01', strtotime('-5 month')), 'sampai' => $sampai])
                                : $tanya($tautan['cetak'], ['jenis' => $unduh, 'dari' => $dari, 'sampai' => $sampai])) ?>">Cetak</a>
                    <?php else: ?>
                        <a class="btn btn-outline btn-sm" href="<?= e($eksporTautan('pesanan')) ?>">CSV pesanan</a>
                    <?php endif; ?>
                </div>
            </article>
        <?php endforeach; ?>
    </div>

    <!-- ============ UNIT TERATAS ============ -->
    <section class="qs-panel">
        <div class="qs-panel__head">
            <h2 class="display" style="font-size: var(--text-lg); margin: 0;">Unit Kontribusi Terbesar</h2>
            <a href="<?= e($tanya($tautan['unit'], ['dari' => $dari, 'sampai' => $sampai])) ?>" class="btn btn-ghost btn-sm">Laporan unit</a>
        </div>
        <div class="qs-panel__body" style="padding: 0;">
            <?php if ($unitTop === []): ?>
                <div class="empty-state">
                    <p class="text-secondary" style="margin: 0; font-size: var(--text-sm);">Belum ada unit terdaftar.</p>
                </div>
            <?php else: ?>
                <div class="table-wrap" style="border: 0; border-radius: 0;">
                    <table class="table">
                        <thead>
                            <tr><th>Unit</th><th>Tipe</th><th class="num">Pesanan</th><th class="num">Jam</th><th class="num">Omzet</th></tr>
                        </thead>
                        <tbody>
                            <?php foreach ($unitTop as $u): ?>
                                <tr>
                                    <td><strong><?= e($u['kode_unit']) ?></strong> <span class="text-muted">· <?= e($u['nama']) ?></span></td>
                                    <td class="text-secondary"><?= e($u['tipe']) ?></td>
                                    <td class="num"><?= e(number_format((float) $u['jumlah_pesanan'])) ?></td>
                                    <td class="num"><?= e(number_format((float) $u['total_jam'])) ?></td>
                                    <td class="num text-cyan"><?= e(rupiah($u['omzet'])) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </section>

    <div class="qs-card flex flex-wrap items-center justify-between gap-3">
        <div>
            <h3 class="display" style="font-size: var(--text-lg); margin: 0;">Butuh versi cetak?</h3>
            <p class="text-secondary" style="margin: 4px 0 0; font-size: var(--text-sm); max-width: 62ch;">
                Halaman cetak memakai tata letak hemat tinta dan bisa disimpan sebagai PDF
                melalui dialog cetak browser.
            </p>
        </div>
        <div class="flex flex-wrap gap-2">
            <a class="btn btn-outline btn-sm" href="<?= e($tanya($tautan['cetak'], ['jenis' => 'harian', 'dari' => $dari, 'sampai' => $sampai])) ?>" target="_blank" rel="noopener">
                Cetak harian
            </a>
            <a class="btn btn-outline btn-sm" href="<?= e($tanya($tautan['cetak'], ['jenis' => 'unit', 'dari' => $dari, 'sampai' => $sampai])) ?>" target="_blank" rel="noopener">
                Cetak unit
            </a>
            <a class="btn btn-primary btn-sm" href="<?= e($eksporTautan('pesanan')) ?>">Unduh pesanan (CSV)</a>
        </div>
    </div>
</div>

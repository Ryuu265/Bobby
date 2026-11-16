<?php
/**
 * admin/dashboard/index.php — Halaman utama panel admin.
 * Kartu statistik, grafik 14 hari, antrian pesanan, sesi berjalan, aktivitas.
 */

use App\Core\View;

View::set('title', 'Dashboard — ' . config('app.name'));
View::set('page_title', 'Dashboard ' . ($isAdmin ? 'Admin' : 'Operator'));
View::set('page_subtitle', tgl_indo(date('Y-m-d')) . ' · ' . jam(date('H:i')) . ' WIB');

// Konfigurasi grafik & refresh monitoring dibaca public/assets/js/dashboard.js
$configJs = [
    'grafik' => [
        [
            'el'    => 'grafik-omzet',
            'jenis' => 'bar',
            'warna' => 'cyan',
            'tinggi'=> 190,
            'data'  => array_map(
                static fn (array $g): array => ['label' => $g['label'], 'nilai' => (float) $g['omzet']],
                $grafik
            ),
        ],
        [
            'el'    => 'grafik-tren',
            'jenis' => 'line',
            'tinggi'=> 150,
            'data'  => array_map(
                static fn (array $g): array => ['label' => $g['label'], 'nilai' => (float) $g['pesanan']],
                $grafik
            ),
        ],
    ],
    'monitor' => [
        'url'        => route('admin.monitoring.data'),
        'daftar'     => 'daftar-sesi-dashboard',
        'penghitung' => 'jumlah-sesi-dashboard',
        'sinkron'    => 'sinkron-sesi',
        'pesanan'    => route('admin.pesanan.detail', ['id' => '__ID__']),
        'batas'      => 6,
    ],
];
?>
<div class="grid gap-5">

    <!-- ============ KARTU STATISTIK ============ -->
    <?= View::include('admin/dashboard/_stat', ['kartu' => $kartu]) ?>

    <!-- ============ GRAFIK ============ -->
    <div class="grid gap-4 lg:grid-cols-3">
        <section class="qs-panel lg:col-span-2">
            <div class="qs-panel__head">
                <h2 class="display" style="font-size: var(--text-lg); margin: 0;">Pendapatan 14 Hari</h2>
                <a href="<?= route('admin.laporan.harian') ?>" class="btn btn-ghost btn-sm">Laporan harian</a>
            </div>
            <div class="qs-panel__body">
                <div id="grafik-omzet" class="flex gap-1 items-stretch"></div>
                <p class="text-muted" style="margin: var(--sp-4) 0 0; font-size: var(--text-xs);">
                    Total 14 hari terakhir <?= e(rupiah(array_sum(array_map(static fn (array $g): float => (float) $g['omzet'], $grafik)))) ?>
                    — meliputi pesanan terkonfirmasi, berlangsung, dan selesai.
                </p>
            </div>
        </section>

        <section class="qs-panel">
            <div class="qs-panel__head">
                <h2 class="display" style="font-size: var(--text-lg); margin: 0;">Tren Pesanan</h2>
                <span class="text-muted" style="font-size: var(--text-xs);">14 hari</span>
            </div>
            <div class="qs-panel__body">
                <div id="grafik-tren"></div>
                <dl class="grid grid-cols-2 gap-3" style="margin: var(--sp-4) 0 0;">
                    <div>
                        <dt class="stat__label">Rata-rata / pesanan</dt>
                        <dd class="stat__value" style="font-size: var(--text-xl); margin-top: 2px;"><?= e(rupiah($ringkasan['rata_rata'])) ?></dd>
                    </div>
                    <div>
                        <dt class="stat__label">Pesanan bulan ini</dt>
                        <dd class="stat__value" style="font-size: var(--text-xl); margin-top: 2px;"><?= e(number_format((float) $ringkasan['pesanan_bulan'])) ?></dd>
                    </div>
                </dl>
            </div>
        </section>
    </div>

    <!-- ============ ANTRIAN + SESI ============ -->
    <div class="grid gap-4 lg:grid-cols-2">

        <section class="qs-panel">
            <div class="qs-panel__head">
                <div>
                    <h2 class="display" style="font-size: var(--text-lg); margin: 0;">Perlu Tindakan</h2>
                    <p class="text-muted" style="margin: 2px 0 0; font-size: var(--text-xs);">
                        Pesanan menunggu konfirmasi admin (<?= (int) $antreanTotal ?> total)
                    </p>
                </div>
                <a href="<?= route('admin.pesanan') ?>" class="btn btn-outline btn-sm">Kelola Pesanan</a>
            </div>
            <div class="qs-panel__body" style="padding: 0;">
                <?php if ($antrean === []): ?>
                    <div class="empty-state">
                        <p class="display" style="font-size: var(--text-lg); margin-bottom: 4px;">Antrian bersih</p>
                        <p class="text-secondary" style="margin: 0; font-size: var(--text-sm);">
                            Tidak ada pesanan yang menunggu konfirmasi saat ini.
                        </p>
                    </div>
                <?php else: ?>
                    <div class="table-wrap" style="border: 0; border-radius: 0;">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>Faktur</th>
                                    <th>Pelanggan &amp; Unit</th>
                                    <th>Jadwal</th>
                                    <th>Total</th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($antrean as $b): ?>
                                    <tr>
                                        <td class="mono" style="font-size: var(--text-xs);"><?= e($b['invoice_number']) ?></td>
                                        <td>
                                            <p style="margin: 0; font-weight: 600;"><?= e($b['nama_pelanggan']) ?></p>
                                            <p class="text-muted" style="margin: 2px 0 0; font-size: var(--text-xs);">
                                                <?= e($b['kode_unit']) ?> · <?= e($b['tipe']) ?>
                                            </p>
                                        </td>
                                        <td class="mono" style="font-size: var(--text-xs); white-space: nowrap;">
                                            <?= e(tgl_waktu($b['booking_date'] . ' ' . $b['start_time'])) ?>
                                        </td>
                                        <td class="num"><?= e(rupiah($b['total_harga'])) ?></td>
                                        <td style="text-align: right;">
                                            <a href="<?= route('admin.pesanan.detail', ['id' => $b['id']]) ?>" class="btn btn-outline btn-sm">
                                                Proses
                                            </a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </section>

        <section class="qs-panel">
            <div class="qs-panel__head">
                <div>
                    <h2 class="display" style="font-size: var(--text-lg); margin: 0;">Sedang Dipakai</h2>
                    <p class="text-muted" style="margin: 2px 0 0; font-size: var(--text-xs);">
                        <span id="jumlah-sesi-dashboard"><?= count($sesi) ?></span> sesi berjalan ·
                        <span id="sinkron-sesi">menyiapkan timer</span>
                    </p>
                </div>
                <a href="<?= route('admin.monitoring') ?>" class="btn btn-outline btn-sm">Monitoring</a>
            </div>
            <div class="qs-panel__body">
                <div id="daftar-sesi-dashboard" class="grid gap-3 sm:grid-cols-2">
                    <?php if ($sesi === []): ?>
                        <div class="empty-state" style="grid-column: 1 / -1; padding: var(--sp-8) var(--sp-4);">
                            <p class="text-secondary" style="margin: 0; font-size: var(--text-sm);">
                                Tidak ada sesi berjalan. Panel terisi otomatis begitu pesanan dimulai.
                            </p>
                        </div>
                    <?php else: ?>
                        <?php foreach ($sesi as $b):
                            $sisa  = max(0, (int) $b['sisa_detik']);
                            $total = max(60, (int) $b['durasi_jam'] * 3600);
                            $persen = min(100, round(($total - $sisa) / $total * 100));
                            $segera = $sisa > 0 && $sisa < 600;
                        ?>
                            <article class="qs-card qs-card--hoverable" data-monitor-card
                                     data-sesi-id="<?= (int) $b['id'] ?>"
                                     data-countdown="<?= $sisa ?>" data-total-detik="<?= $total ?>"
                                     style="padding: var(--sp-4);">
                                <div class="flex items-center justify-between gap-2">
                                    <span class="mono" style="font-weight: 600;"><?= e($b['kode_unit']) ?></span>
                                    <span class="badge <?= $segera ? 'badge-warning' : 'badge-info' ?>" data-timer-badge>
                                        <?= $segera ? 'Segera selesai' : e(status_label($b['status_pesanan'])) ?>
                                    </span>
                                </div>
                                <p class="text-secondary" style="margin: 6px 0 0; font-size: var(--text-xs);">
                                    <?= e($b['nama_pelanggan']) ?>
                                </p>
                                <p class="timer" style="margin: var(--sp-2) 0 6px;">
                                    <span data-timer-text><?= e(gmdate('H:i:s', $sisa)) ?></span>
                                </p>
                                <div class="bar">
                                    <div class="bar__fill<?= $persen >= 90 ? ' bar__fill--warning' : '' ?>"
                                         data-timer-bar style="width: <?= $persen ?>%;"></div>
                                </div>
                            </article>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </section>
    </div>

    <!-- ============ AKTIVITAS + PINTASAN ============ -->
    <div class="grid gap-4 lg:grid-cols-3">
        <section class="qs-panel lg:col-span-2">
            <div class="qs-panel__head">
                <h2 class="display" style="font-size: var(--text-lg); margin: 0;">Aktivitas Terbaru</h2>
                <a href="<?= route('admin.laporan.aktivitas') ?>" class="btn btn-ghost btn-sm">Semua aktivitas</a>
            </div>
            <div class="qs-panel__body" style="padding: 0;">
                <?php if ($aktivitas === []): ?>
                    <div class="empty-state">
                        <p class="text-secondary" style="margin: 0; font-size: var(--text-sm);">Belum ada aktivitas tercatat.</p>
                    </div>
                <?php else: ?>
                    <ul style="list-style: none; margin: 0; padding: 0;">
                        <?php foreach ($aktivitas as $l): ?>
                            <li class="flex items-start justify-between gap-3 px-5 py-3"
                                style="border-bottom: 1px solid var(--border-soft);">
                                <div>
                                    <p style="margin: 0; font-size: var(--text-sm);"><?= e($l['deskripsi']) ?></p>
                                    <p class="text-muted" style="margin: 2px 0 0; font-size: var(--text-xs);">
                                        <?= e($l['nama'] ?? 'Sistem') ?> · <?= e($l['modul']) ?>/<?= e($l['aksi']) ?>
                                    </p>
                                </div>
                                <span class="text-muted mono" style="font-size: var(--text-xs); white-space: nowrap;">
                                    <?= e(tgl_waktu($l['created_at'])) ?>
                                </span>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </div>
        </section>

        <section class="qs-panel">
            <div class="qs-panel__head">
                <h2 class="display" style="font-size: var(--text-lg); margin: 0;">Pintasan</h2>
            </div>
            <div class="qs-panel__body grid gap-2">
                <a href="<?= route('admin.pesanan') ?>" class="side-link">Kelola Pesanan</a>
                <a href="<?= route('admin.pembayaran') ?>" class="side-link">Verifikasi Pembayaran</a>
                <a href="<?= route('admin.pelanggan') ?>" class="side-link">Manajemen Pelanggan</a>
                <a href="<?= route('admin.laporan') ?>" class="side-link">Laporan &amp; Ekspor</a>
                <a href="<?= route('admin.monitoring') ?>" class="side-link">Monitoring &amp; Timer</a>
                <?php if ($isAdmin && (($authUser['role'] ?? '') === 'admin')): ?>
                    <a href="<?= route('admin.pengaturan') ?>" class="side-link">Pengaturan</a>
                <?php endif; ?>
            </div>
        </section>
    </div>
</div>

<script id="qs-admin-data" type="application/json"><?= json_encode($configJs, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?></script>
<?php View::set('scripts', '<script src="' . asset('js/dashboard.js') . '" defer></script>'); ?>

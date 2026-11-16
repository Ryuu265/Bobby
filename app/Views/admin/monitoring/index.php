<?php
/**
 * admin/monitoring/index.php — Papan timer sesi sewa berjalan.
 * Kartu dirender PHP (agar tetap terbaca tanpa JavaScript), lalu disegarkan
 * tiap 30 detik oleh public/assets/js/dashboard.js lewat endpoint JSON.
 */

use App\Core\View;

View::set('title', 'Monitoring & Timer — ' . config('app.name'));
View::set('page_title', 'Monitoring & Timer');
View::set('page_subtitle', count($sesi) . ' sesi berjalan · diperbarui otomatis setiap 30 detik');

// Konfigurasi refresh monitoring (dibaca dashboard.js)
$configJs = [
    'monitor' => [
        'url'        => $endpoint,
        'daftar'     => 'daftar-sesi',
        'penghitung' => 'jumlah-sesi',
        'sinkron'    => 'sinkron-sesi',
        'pesanan'    => $tautanPesanan,
    ],
];

/** Format detik -> HH:MM:SS untuk nilai awal kartu. */
$pemisah = static fn (int $detik): string => gmdate('H:i:s', max(0, $detik));
?>
<div class="grid gap-5">

    <div class="qs-panel">
        <div class="qs-panel__head flex-wrap">
            <div>
                <h2 class="display" style="font-size: var(--text-lg); margin: 0;">Sesi Berjalan</h2>
                <p class="text-muted" style="margin: 2px 0 0; font-size: var(--text-xs);">
                    <strong id="jumlah-sesi"><?= count($sesi) ?></strong> unit aktif ·
                    <span id="sinkron-sesi">sinkron terakhir: halaman dimuat</span>
                </p>
            </div>
            <div class="flex items-center gap-2">
                <a href="<?= route('admin.pesanan') ?>" class="btn btn-outline btn-sm">Kelola Pesanan</a>
                <button type="button" class="btn btn-ghost btn-sm" data-refresh-monitor>Muat ulang sekarang</button>
            </div>
        </div>

        <div class="qs-panel__body">
            <div id="daftar-sesi" class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
                <?php if ($sesi === []): ?>
                    <div class="empty-state" style="grid-column: 1 / -1;">
                        <p class="display" style="font-size: var(--text-lg); margin-bottom: 4px;">
                            Tidak ada sesi berjalan
                        </p>
                        <p class="text-secondary" style="margin: 0; font-size: var(--text-sm); max-width: 56ch;">
                            Semua unit sedang kosong atau pesanannya belum dimulai. Timer muncul otomatis
                            begitu ada pesanan berstatus berlangsung.
                        </p>
                    </div>
                <?php else: ?>
                    <?php foreach ($sesi as $b):
                        $sisa   = (int) ($b['sisa_detik'] ?? 0);
                        $total  = max(60, (int) $b['durasi_jam'] * 3600);
                        $persen = $sisa <= 0 ? 100 : min(100, round(($total - $sisa) / $total * 100));
                        $segera = $sisa > 0 && $sisa < 600;
                    ?>
                        <article class="qs-card qs-card--hoverable" data-monitor-card
                                 data-sesi-id="<?= (int) $b['id'] ?>"
                                 data-countdown="<?= max(0, $sisa) ?>" data-total-detik="<?= $total ?>"
                                 style="padding: var(--sp-4); display: flex; flex-direction: column; gap: var(--sp-3);">

                            <header class="flex items-start justify-between gap-3">
                                <div>
                                    <p class="display" style="margin: 0; font-size: var(--text-lg); color: var(--text-primary);">
                                        <?= e($b['kode_unit']) ?>
                                    </p>
                                    <p class="text-muted" style="margin: 2px 0 0; font-size: var(--text-xs);">
                                        <?= e($b['nama_unit']) ?> · <?= e($b['tipe']) ?>
                                    </p>
                                </div>
                                <span class="badge <?= $segera ? 'badge-warning' : 'badge-info' ?>" data-timer-badge>
                                    <?= $segera ? 'Segera selesai' : e(status_label($b['status_pesanan'])) ?>
                                </span>
                            </header>

                            <div>
                                <p style="margin: 0; font-size: var(--text-sm); font-weight: 600;">
                                    <?= e($b['nama_pelanggan']) ?>
                                </p>
                                <p class="text-muted" style="margin: 2px 0 0; font-size: var(--text-xs);">
                                    <span class="mono"><?= e($b['telepon'] ?? '-') ?></span> ·
                                    <?= e($b['invoice_number']) ?>
                                </p>
                            </div>

                            <p class="timer" style="margin: 0; color: var(--cyan);">
                                <span data-timer-text><?= e($pemisah($sisa)) ?></span>
                            </p>

                            <div class="bar">
                                <div class="bar__fill<?= $persen >= 90 ? ' bar__fill--warning' : '' ?>"
                                     data-timer-bar style="width: <?= $persen ?>%;"></div>
                            </div>

                            <dl class="grid grid-cols-3 gap-2" style="margin: 0;">
                                <div>
                                    <dt class="stat__label">Mulai</dt>
                                    <dd class="mono" style="margin: 2px 0 0; font-size: var(--text-sm);">
                                        <?= e(jam($b['start_time'])) ?>
                                    </dd>
                                </div>
                                <div>
                                    <dt class="stat__label">Selesai</dt>
                                    <dd class="mono" style="margin: 2px 0 0; font-size: var(--text-sm);">
                                        <?= e(jam($b['end_time'])) ?>
                                    </dd>
                                </div>
                                <div>
                                    <dt class="stat__label">Tarif/Jam</dt>
                                    <dd class="mono" style="margin: 2px 0 0; font-size: var(--text-sm);">
                                        <?= e(rupiah($b['harga_per_jam'])) ?>
                                    </dd>
                                </div>
                            </dl>

                            <a href="<?= route('admin.pesanan.detail', ['id' => $b['id']]) ?>"
                               class="btn btn-outline btn-sm" style="margin-top: auto;">Buka detail pesanan</a>
                        </article>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <p class="text-secondary" style="margin: 0; font-size: var(--text-sm); max-width: 78ch;">
        Timer menghitung mundur dari jam selesai pesanan. Saat waktu habis, sesi ditandai
        <strong>Selesai</strong>; penutupan status permanen terjadi ketika halaman ini atau dashboard dimuat
        kembali. Panel tidak mengubah data apa pun saat penyegaran otomatis.
    </p>
</div>

<script id="qs-admin-data" type="application/json"><?= json_encode($configJs, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?></script>
<?php View::set('scripts', '<script src="' . asset('js/dashboard.js') . '" defer></script>'); ?>

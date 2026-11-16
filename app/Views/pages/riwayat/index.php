<?php
/**
 * pages/riwayat/index.php — Riwayat pesanan pelanggan + filter status + rekap.
 *
 * @var array $pesanan
 * @var int $total
 * @var int $halaman
 * @var int $terakhir
 * @var string $status
 * @var array $daftarStatus
 * @var array $rekap
 * @var array|null $authUser
 */

use App\Core\View;
use App\Models\Setting;

View::set('title', 'Riwayat Pesanan — ' . Setting::get('nama_rental', config('app.name')));

$kartuRekap = [
    ['label' => 'Total pesanan',   'nilai' => (int) ($rekap['total'] ?? 0),          'satuan' => 'pesanan'],
    ['label' => 'Sedang berjalan', 'nilai' => (int) ($rekap['menunggu'] ?? 0) + (int) ($rekap['dikonfirmasi'] ?? 0) + (int) ($rekap['berlangsung'] ?? 0), 'satuan' => 'pesanan'],
    ['label' => 'Selesai',         'nilai' => (int) ($rekap['selesai'] ?? 0),        'satuan' => 'pesanan'],
    ['label' => 'Total jam main',  'nilai' => (int) ($rekap['total_jam'] ?? 0),      'satuan' => 'jam'],
    ['label' => 'Total pembayaran', 'nilai' => rupiah($rekap['total_bayar'] ?? 0),   'satuan' => 'akumulasi pesanan aktif/selesai'],
];
?>
<section class="section" style="padding-bottom: var(--sp-6);">
    <div class="container-page">
        <span class="badge badge-cyan" style="margin-bottom: var(--sp-3);">Riwayat Saya</span>
        <h1 class="display" style="font-size: var(--text-3xl); margin-bottom: var(--sp-2);">Pesanan Anda</h1>
        <p class="text-secondary" style="margin:0; max-width: 68ch;">
            Pantau status konfirmasi, unggah bukti transfer, dan batalkan pesanan mandiri
            selama masih melewati batas waktu pembatalan.
        </p>
    </div>
</section>

<!-- Kartu rekap -->
<section style="padding-bottom: var(--sp-8);">
    <div class="container-page grid gap-4 sm:grid-cols-2 lg:grid-cols-5">
        <?php foreach ($kartuRekap as $k): ?>
            <div class="qs-card">
                <p class="text-muted" style="margin:0 0 4px; font-size: var(--text-xs); text-transform: uppercase; letter-spacing: .06em;">
                    <?= e($k['label']) ?>
                </p>
                <p class="display" style="margin:0; font-size: var(--text-2xl); color: var(--text-primary);">
                    <?= e((string) $k['nilai']) ?>
                </p>
                <p class="text-muted" style="margin:2px 0 0; font-size: var(--text-xs);"><?= e($k['satuan']) ?></p>
            </div>
        <?php endforeach; ?>
    </div>
</section>

<!-- Filter status -->
<section style="padding-bottom: var(--sp-6);">
    <div class="container-page">
        <div class="flex flex-wrap items-center gap-2">
            <a href="<?= url('riwayat') ?>" class="btn btn-sm <?= $status === '' ? 'btn-primary' : 'btn-outline' ?>">Semua</a>
            <?php foreach ($daftarStatus as $s): ?>
                <a href="<?= url('riwayat?status=' . $s) ?>"
                   class="btn btn-sm <?= $status === $s ? 'btn-primary' : 'btn-outline' ?>">
                    <?= e(status_label($s)) ?>
                </a>
            <?php endforeach; ?>
            <span class="text-muted" style="font-size: var(--text-xs); margin-left: auto;">
                <?= $total ?> pesanan<?= $status !== '' ? ' dengan status ' . e(status_label($status)) : '' ?>
            </span>
        </div>
    </div>
</section>

<!-- Daftar pesanan -->
<section style="padding-bottom: var(--sp-12);">
    <div class="container-page">

        <?php if ($pesanan === []): ?>
            <div class="qs-card empty-state">
                <h2 class="display" style="font-size: var(--text-lg);">
                    <?= $status !== '' ? 'Tidak ada pesanan berstatus ' . e(status_label($status)) : 'Belum ada pesanan' ?>
                </h2>
                <p class="text-secondary" style="max-width: 46ch; margin-inline: auto;">
                    <?php if ($status !== ''): ?>
                        Coba pilih status lain, atau lihat seluruh riwayat pesanan Anda.
                    <?php else: ?>
                        Begitu Anda memesan satu slot, semua rinciannya akan muncul di halaman ini.
                    <?php endif; ?>
                </p>
                <div class="flex flex-wrap gap-2 justify-center" style="margin-top: var(--sp-4);">
                    <?php if ($status !== ''): ?>
                        <a href="<?= url('riwayat') ?>" class="btn btn-outline btn-sm">Semua Pesanan</a>
                    <?php endif; ?>
                    <a href="<?= url('booking') ?>" class="btn btn-primary btn-sm">Buat Pesanan Baru</a>
                </div>
            </div>
        <?php else: ?>
            <div class="grid gap-4">
                <?php foreach ($pesanan as $b): ?>
                    <article class="qs-card qs-card--hoverable">
                        <div class="flex flex-wrap items-start justify-between gap-3">
                            <div class="min-w-0">
                                <p class="mono" style="margin:0; font-size: var(--text-sm); color: var(--text-primary); font-weight:600;">
                                    <?= e($b['invoice_number']) ?>
                                </p>
                                <p class="text-muted" style="margin:2px 0 0; font-size: var(--text-xs);">
                                    Dibuat <?= e(tgl_waktu($b['created_at'])) ?>
                                    <?php if (!empty($b['jumlah_bayar'])): ?>
                                        · <?= (int) $b['jumlah_bayar'] ?> catatan pembayaran
                                    <?php endif; ?>
                                </p>
                            </div>
                            <div class="flex flex-wrap gap-2">
                                <span class="badge <?= status_class((string) $b['status_pesanan']) ?>">
                                    <?= e(status_label((string) $b['status_pesanan'])) ?>
                                </span>
                                <span class="badge <?= status_class((string) $b['status_bayar']) ?>">
                                    <?= e(status_label((string) $b['status_bayar'])) ?>
                                </span>
                            </div>
                        </div>

                        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4" style="margin-top: var(--sp-4);">
                            <div>
                                <p class="text-muted" style="margin:0 0 2px; font-size: var(--text-xs); text-transform: uppercase; letter-spacing: .06em;">Unit</p>
                                <p style="margin:0; font-size: var(--text-sm); font-weight:600;">
                                    <?= e($b['kode_unit']) ?> <span class="text-muted"><?= e($b['tipe']) ?></span>
                                </p>
                            </div>
                            <div>
                                <p class="text-muted" style="margin:0 0 2px; font-size: var(--text-xs); text-transform: uppercase; letter-spacing: .06em;">Tanggal</p>
                                <p style="margin:0; font-size: var(--text-sm);"><?= e(tgl_indo($b['booking_date'])) ?></p>
                            </div>
                            <div>
                                <p class="text-muted" style="margin:0 0 2px; font-size: var(--text-xs); text-transform: uppercase; letter-spacing: .06em;">Jam</p>
                                <p class="mono" style="margin:0; font-size: var(--text-sm);">
                                    <?= e(jam($b['start_time'])) ?> - <?= e(jam($b['end_time'])) ?>
                                    <span class="text-muted">(<?= (int) $b['durasi_jam'] ?> jam)</span>
                                </p>
                            </div>
                            <div>
                                <p class="text-muted" style="margin:0 0 2px; font-size: var(--text-xs); text-transform: uppercase; letter-spacing: .06em;">Total</p>
                                <p class="display text-cyan" style="margin:0; font-size: var(--text-lg);"><?= e(rupiah($b['total_harga'])) ?></p>
                            </div>
                        </div>

                        <?php if ($b['status_pesanan'] === 'dibatalkan' && !empty($b['alasan_batal'])): ?>
                            <p class="text-muted" style="margin: var(--sp-3) 0 0; font-size: var(--text-xs);">
                                Alasan batal: <?= e($b['alasan_batal']) ?>
                            </p>
                        <?php elseif (!empty($b['catatan_pelanggan'])): ?>
                            <p class="text-muted" style="margin: var(--sp-3) 0 0; font-size: var(--text-xs);">
                                Catatan: <?= e($b['catatan_pelanggan']) ?>
                            </p>
                        <?php endif; ?>

                        <div class="flex flex-wrap gap-2" style="margin-top: var(--sp-4);">
                            <a href="<?= route('booking.detail', ['id' => (int) $b['id']]) ?>" class="btn btn-outline btn-sm">
                                Lihat Detail
                            </a>
                            <?php if ($b['status_bayar'] !== 'lunas' && $b['status_pesanan'] !== 'dibatalkan'): ?>
                                <a href="<?= route('booking.detail', ['id' => (int) $b['id']]) ?>#bayar" class="btn btn-primary btn-sm">
                                    Bayar Sekarang
                                </a>
                            <?php endif; ?>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>

            <?= View::include('partials/pagination', [
                'halaman' => $halaman,
                'terakhir' => $terakhir,
                'total'   => $total,
                'label'   => 'pesanan',
                'base'    => url('riwayat'),
                'query'   => $status !== '' ? ['status' => $status] : [],
            ]) ?>
        <?php endif; ?>

    </div>
</section>

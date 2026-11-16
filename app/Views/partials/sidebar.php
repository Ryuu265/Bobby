<?php
/**
 * partials/sidebar.php — Menu navigasi area admin/operator.
 */

use App\Core\Auth;
use App\Models\Booking;
use App\Models\Payment;

$uri = App\Core\Request::instance()->uri();
// Closure harus menangkap $uri secara eksplisit; tanpa `use`, variabel dari
// atas file ini tidak terlihat di dalam body closure.
$aktif = static function (string $path) use ($uri): string {
    return $path === '/admin' ? ($uri === '/admin' ? 'is-active' : '') : (str_starts_with($uri, $path) ? 'is-active' : '');
};

// Angka badge pada menu yang perlu perhatian segera.
// Sengaja memakai COUNT langsung: partial ini dirender di setiap halaman admin.
$menungguPesanan = Booking::hitungStatus('menunggu');
$menungguBayar = Payment::hitungAntrian();
?>
<nav class="p-3">
    <p class="side-group-title">Utama</p>
    <a href="<?= url('admin/dashboard') ?>" class="side-link <?= $aktif('/admin/dashboard') ?: ($uri === '/admin' ? 'is-active' : '') ?>">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="7" height="9" rx="1.5"/><rect x="14" y="3" width="7" height="5" rx="1.5"/><rect x="14" y="12" width="7" height="9" rx="1.5"/><rect x="3" y="16" width="7" height="5" rx="1.5"/></svg>
        Dashboard
    </a>
    <a href="<?= url('admin/monitoring') ?>" class="side-link <?= $aktif('/admin/monitoring') ?>">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/></svg>
        Monitoring &amp; Timer
    </a>

    <p class="side-group-title">Transaksi</p>
    <a href="<?= url('admin/pesanan') ?>" class="side-link <?= $aktif('/admin/pesanan') ?>">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 6h16M4 12h16M4 18h10"/></svg>
        Kelola Pesanan
        <?php if ($menungguPesanan > 0): ?>
            <span class="badge badge-warning badge-no-dot ml-auto"><?= (int) $menungguPesanan ?></span>
        <?php endif; ?>
    </a>
    <a href="<?= url('admin/pembayaran') ?>" class="side-link <?= $aktif('/admin/pembayaran') ?>">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="2.5" y="5.5" width="19" height="13" rx="2.5"/><path d="M2.5 10h19"/></svg>
        Verifikasi Pembayaran
        <?php if ($menungguBayar > 0): ?>
            <span class="badge badge-warning badge-no-dot ml-auto"><?= (int) $menungguBayar ?></span>
        <?php endif; ?>
    </a>

    <p class="side-group-title">Master Data</p>
    <a href="<?= url('admin/unit') ?>" class="side-link <?= $aktif('/admin/unit') ?>">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="7" width="18" height="11" rx="3"/><path d="M8 12h2M9 11v2M15 13h.01M17 12h.01"/></svg>
        Kelola Unit PlayStation
    </a>
    <a href="<?= url('admin/game') ?>" class="side-link <?= $aktif('/admin/game') ?>">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="8" cy="12" r="3.2"/><path d="M15 10h5M17.5 8v4"/></svg>
        Kelola Game
    </a>
    <a href="<?= url('admin/paket') ?>" class="side-link <?= $aktif('/admin/paket') ?>">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 12 12 4H5v7l8 8z"/><circle cx="8.5" cy="8.5" r="1.4"/></svg>
        Paket &amp; Promo
    </a>
    <a href="<?= url('admin/pelanggan') ?>" class="side-link <?= $aktif('/admin/pelanggan') ?>">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="9" cy="8" r="3.4"/><path d="M3 20c0-3.3 2.7-6 6-6s6 2.7 6 6"/><path d="M17 11h4"/></svg>
        Manajemen Pelanggan
    </a>

    <p class="side-group-title">Analisis</p>
    <a href="<?= url('admin/laporan') ?>" class="side-link <?= $aktif('/admin/laporan') ?>">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 20V10M10 20V4M16 20v-7M22 20H2"/></svg>
        Laporan &amp; Ekspor
    </a>
    <?php if (Auth::isOnlyAdmin()): ?>
        <a href="<?= url('admin/pengaturan') ?>" class="side-link <?= $aktif('/admin/pengaturan') ?>">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="3"/><path d="M12 2v3M12 19v3M2 12h3M19 12h3M5 5l2 2M17 17l2 2M19 5l-2 2M7 17l-2 2"/></svg>
            Pengaturan
        </a>
    <?php endif; ?>
</nav>

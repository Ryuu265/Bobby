<?php
/**
 * partials/navbar.php — Navigasi atas (halaman publik & pelanggan).
 * Mobile-first: menu collapse memakai <details> tanpa JavaScript wajib.
 */

use App\Core\Auth;
use App\Core\View;

$uri = App\Core\Request::instance()->uri();
$aktif = static fn (string $path): string => $uri === $path || ($path !== '/' && str_starts_with($uri, $path)) ? 'is-active' : '';
$user = Auth::user();
?>
<header class="qs-nav">
    <div class="container-page flex items-center justify-between gap-4" style="min-height: var(--header-h);">

        <a href="<?= url('') ?>" class="brand" aria-label="<?= e(config('app.name')) ?> beranda">
            <span class="brand__dot" aria-hidden="true"></span>
            Qengamestation
        </a>

        <!-- Menu desktop -->
        <nav class="hidden md:flex items-center gap-1" aria-label="Navigasi utama">
            <a href="<?= url('') ?>" class="qs-nav__link <?= $aktif('/') ?>">Beranda</a>
            <a href="<?= url('katalog') ?>" class="qs-nav__link <?= $aktif('/katalog') ?>">Katalog Unit</a>
            <a href="<?= url('katalog/game') ?>" class="qs-nav__link <?= str_starts_with($uri, '/katalog/game') ? 'is-active' : '' ?>">Daftar Game</a>
            <a href="<?= url('booking') ?>" class="qs-nav__link <?= $aktif('/booking') ?>">Pesan Sekarang</a>
            <?php if ($user): ?>
                <a href="<?= url('riwayat') ?>" class="qs-nav__link <?= $aktif('/riwayat') ?>">Riwayat Saya</a>
            <?php endif; ?>
        </nav>

        <div class="hidden md:flex items-center gap-2">
            <?php if ($user): ?>
                <?php if (Auth::isAdmin()): ?>
                    <a href="<?= url('admin/dashboard') ?>" class="btn btn-violet btn-sm">Dashboard Admin</a>
                <?php endif; ?>
                <a href="<?= url('profil') ?>" class="qs-nav__link" title="Profil saya">
                    <span class="inline-flex items-center gap-2">
                        <span aria-hidden="true" class="inline-grid place-items-center w-7 h-7 rounded-full text-xs font-semibold"
                              style="background: rgba(var(--cyan-rgb)/0.14); color: var(--cyan);">
                            <?= e(mb_strtoupper(mb_substr($user['nama'], 0, 1))) ?>
                        </span>
                        <?= e(explode(' ', $user['nama'])[0]) ?>
                    </span>
                </a>
                <form method="post" action="<?= url('keluar') ?>" class="inline">
                    <?= csrf_field() ?>
                    <button type="submit" class="btn btn-ghost btn-sm">Keluar</button>
                </form>
            <?php else: ?>
                <a href="<?= url('masuk') ?>" class="btn btn-ghost btn-sm">Masuk</a>
                <a href="<?= url('daftar') ?>" class="btn btn-primary btn-sm">Daftar Akun</a>
            <?php endif; ?>
        </div>

        <!-- Menu mobile -->
        <details class="md:hidden relative">
            <summary class="btn btn-outline btn-sm list-none cursor-pointer" style="list-style:none;" aria-label="Buka menu navigasi">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true">
                    <line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="18" x2="21" y2="18"/>
                </svg>
            </summary>
            <nav class="absolute right-0 z-[60] mt-2 w-60 p-2 rounded-lg"
                 style="background: var(--ink-700); border: 1px solid var(--border-medium); box-shadow: var(--shadow-pop);"
                 aria-label="Navigasi mobile">
                <a href="<?= url('') ?>" class="side-link <?= $aktif('/') ?>">Beranda</a>
                <a href="<?= url('katalog') ?>" class="side-link <?= $aktif('/katalog') ?>">Katalog Unit</a>
                <a href="<?= url('katalog/game') ?>" class="side-link">Daftar Game</a>
                <a href="<?= url('booking') ?>" class="side-link <?= $aktif('/booking') ?>">Pesan Sekarang</a>
                <a href="<?= url('riwayat') ?>" class="side-link <?= $aktif('/riwayat') ?>">Riwayat Saya</a>
                <hr class="divider" style="margin: var(--sp-2) 0;">
                <?php if ($user): ?>
                    <?php if (Auth::isAdmin()): ?>
                        <a href="<?= url('admin/dashboard') ?>" class="side-link">Dashboard Admin</a>
                    <?php endif; ?>
                    <a href="<?= url('profil') ?>" class="side-link">Profil Saya</a>
                    <form method="post" action="<?= url('keluar') ?>">
                        <?= csrf_field() ?>
                        <button type="submit" class="side-link w-full text-left btn-ghost">Keluar</button>
                    </form>
                <?php else: ?>
                    <a href="<?= url('masuk') ?>" class="side-link">Masuk</a>
                    <a href="<?= url('daftar') ?>" class="side-link">Daftar Akun</a>
                <?php endif; ?>
            </nav>
        </details>
    </div>
</header>

<?= View::include('partials/flash') ?>

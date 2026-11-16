<?php
/**
 * layouts/admin.php — Cangkang halaman admin/operator.
 * Sidebar tetap di desktop, drawer di mobile (dikelola app.js).
 */

use App\Core\Auth;
use App\Core\View;
?>
<!DOCTYPE html>
<html lang="id" class="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#0F1117">
    <meta name="robots" content="noindex, nofollow">
    <title><?= e(View::section('title', 'Dashboard — ' . config('app.name'))) ?></title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&family=Rajdhani:wght@500;600;700&display=swap" rel="stylesheet">

    <link rel="stylesheet" href="<?= asset('css/tokens.css') ?>">
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="<?= asset('js/tailwind.config.js') ?>"></script>
    <link rel="stylesheet" href="<?= asset('css/app.css') ?>">
    <link rel="icon" href="<?= asset('img/favicon.svg') ?>" type="image/svg+xml">
</head>
<body>
<a class="skip-link" href="#konten">Lompat ke konten utama</a>

<div class="flex min-h-screen">
    <!-- Sidebar desktop -->
    <aside class="hidden lg:block flex-none sticky top-0 h-screen overflow-y-auto"
           style="width: var(--sidebar-w); background: var(--ink-800); border-right: 1px solid var(--border-soft);"
           aria-label="Menu admin">
        <div class="p-5" style="border-bottom: 1px solid var(--border-soft);">
            <a href="<?= url('admin/dashboard') ?>" class="brand">
                <span class="brand__dot" aria-hidden="true"></span>Qengamestation
            </a>
            <p class="text-muted" style="font-size: var(--text-xs); margin: 6px 0 0;">Panel Admin &amp; Operator</p>
        </div>
        <?= View::include('partials/sidebar') ?>
    </aside>

    <div class="flex-1 min-w-0">
        <!-- Header atas -->
        <header class="qs-nav lg:hidden">
            <div class="container-page flex items-center justify-between" style="min-height: var(--header-h);">
                <button type="button" class="btn btn-outline btn-sm" data-toggle-sidebar aria-expanded="false" aria-controls="sidebarMobile">
                    ☰ Menu
                </button>
                <a href="<?= url('admin/dashboard') ?>" class="brand" style="font-size: 1rem;">Qengamestation</a>
                <form method="post" action="<?= url('keluar') ?>">
                    <?= csrf_field() ?>
                    <button type="submit" class="btn btn-ghost btn-sm">Keluar</button>
                </form>
            </div>
        </header>

        <header class="hidden lg:flex items-center justify-between gap-4 px-6 py-4"
                style="border-bottom: 1px solid var(--border-soft); background: rgba(21,24,35,0.6);">
            <div>
                <h1 class="display" style="font-size: 1.35rem; margin: 0;"><?= e(View::section('page_title', 'Dashboard')) ?></h1>
                <p class="text-muted" style="margin: 2px 0 0; font-size: var(--text-sm);"><?= e(View::section('page_subtitle', '')) ?></p>
            </div>
            <div class="flex items-center gap-2">
                <a href="<?= url('') ?>" class="btn btn-outline btn-sm" target="_blank" rel="noopener">Lihat Situs</a>
                <div class="text-right">
                    <p style="margin: 0; font-size: var(--text-sm); font-weight: 600;"><?= e(Auth::user()['nama'] ?? '') ?></p>
                    <p class="text-muted" style="margin: 0; font-size: var(--text-xs);"><?= e(status_label(Auth::role())) ?></p>
                </div>
                <form method="post" action="<?= url('keluar') ?>">
                    <?= csrf_field() ?>
                    <button type="submit" class="btn btn-ghost btn-sm">Keluar</button>
                </form>
            </div>
        </header>

        <main id="konten" class="px-4 py-5 lg:px-6 lg:py-6" style="max-width: 1500px;">
            <?= View::include('partials/flash') ?>
            <?= View::section('content') ?>
        </main>
    </div>
</div>

<!-- Sidebar mobile (drawer) -->
<div id="sidebarMobile" class="modal lg:hidden" data-sidebar-drawer>
    <div class="modal__backdrop" data-dismiss-modal></div>
    <div class="modal__panel" style="margin: 0; width: min(320px, 90vw); height: 100vh; max-height: 100vh; border-radius: 0;">
        <div class="flex items-center justify-between p-4" style="border-bottom: 1px solid var(--border-soft);">
            <span class="brand" style="font-size: 1rem;">Qengamestation</span>
            <button type="button" class="btn btn-ghost btn-sm" data-dismiss-modal aria-label="Tutup menu">&times;</button>
        </div>
        <?= View::include('partials/sidebar') ?>
    </div>
</div>

<script src="<?= asset('js/app.js') ?>"></script>
<?= View::section('scripts') ?>
</body>
</html>

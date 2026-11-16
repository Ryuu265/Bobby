<?php
/**
 * layouts/app.php — Cangkang halaman publik & pelanggan.
 * Konten halaman masuk lewat section "content" (lihat App\Core\View).
 */

use App\Core\Auth;
use App\Core\View;

$judulHalaman = View::section('title', config('app.name') . ' — Sewa PlayStation');
?>
<!DOCTYPE html>
<html lang="id" class="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#0F1117">
    <meta name="description" content="<?= e(View::section('meta_description', 'Sewa PS5, PS4, dan PS3 di Qengamestation. Cek ketersediaan jadwal, pesan online, bayar mudah.')) ?>">
    <title><?= e($judulHalaman) ?></title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&family=Rajdhani:wght@500;600;700&display=swap" rel="stylesheet">

    <!-- Design tokens lebih dulu, lalu Tailwind CDN + konfigurasinya, lalu komponen -->
    <link rel="stylesheet" href="<?= asset('css/tokens.css') ?>">
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="<?= asset('js/tailwind.config.js') ?>"></script>
    <link rel="stylesheet" href="<?= asset('css/app.css') ?>">
    <link rel="icon" href="<?= asset('img/favicon.svg') ?>" type="image/svg+xml">
</head>
<body>
<a class="skip-link" href="#konten">Lompat ke konten utama</a>

<?= View::include('partials/navbar', ['authUser' => $authUser ?? null]) ?>

<main id="konten" class="min-h-screen">
    <?= View::section('content') ?>
</main>

<?= View::include('partials/footer') ?>

<script src="<?= asset('js/app.js') ?>"></script>
<?= View::section('scripts') ?>
</body>
</html>

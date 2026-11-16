<?php
/**
 * pages/error.php — Halaman galat 404 / 403 / 419 / 500.
 * Dijampilkan lewat App\Core\Error::abort().
 */

use App\Core\View;

View::set('title', ($judul ?? 'Galat') . ' — ' . config('app.name'));
$kode = (int) ($kode ?? 500);
$ikon = [
    403 => 'Akses ditolak',
    404 => 'Halaman tidak ditemukan',
    419 => 'Form kedaluwarsa',
    500 => 'Kesalahan sistem',
];
?>
<section class="section">
    <div class="container-narrow text-center" style="max-width: 560px;">
        <div class="qs-card" style="padding: var(--sp-12) var(--sp-6);">
            <p class="display" style="font-size: 4rem; margin:0; color: var(--cyan); line-height:1;"><?php
                // Angka besar dengan garis tepi neon tipis, tanpa glow berlebihan
                echo $kode;
            ?></p>
            <h1 class="display" style="font-size: var(--text-xl); margin-top: var(--sp-4);">
                <?= e($ikon[$kode] ?? ($judul ?? 'Terjadi kesalahan')) ?>
            </h1>
            <p class="text-secondary" style="margin-top: var(--sp-3);"><?= e($pesan ?? 'Maaf, terjadi kesalahan.') ?></p>

            <?php if (!empty($detail) && config('app.debug')): ?>
                <pre class="mono text-danger" style="text-align:left; font-size: 11px; white-space: pre-wrap;
                     background: var(--ink-850); border: 1px solid var(--border-soft);
                     border-radius: var(--radius-sm); padding: var(--sp-4); margin-top: var(--sp-5); overflow:auto;
                     max-height: 260px;"><?= e($detail) ?></pre>
            <?php endif; ?>

            <div class="flex flex-wrap gap-2 justify-center" style="margin-top: var(--sp-6);">
                <a href="<?= url('') ?>" class="btn btn-primary">Kembali ke Beranda</a>
                <a href="<?= url('katalog') ?>" class="btn btn-outline">Lihat Katalog</a>
                <a href="<?= url('booking') ?>" class="btn btn-ghost">Buat Pesanan</a>
            </div>
        </div>
    </div>
</section>

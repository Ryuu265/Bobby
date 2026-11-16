<?php
/**
 * partials/pagination.php — Navigasi halaman.
 * Cara pakai:
 *   View::include('partials/pagination', [
 *       'halaman' => 2, 'terakhir' => 7, 'query' => ['status' => 'selesai'],
 *       'base' => url('riwayat'), 'label' => 'pesanan',
 *   ])
 */

$halaman  = max(1, (int) ($halaman ?? 1));
$terakhir = max(1, (int) ($terakhir ?? 1));
$base     = $base ?? App\Core\Request::instance()->uri();
$query    = $query ?? [];

/** Bangun URL halaman ke-$n tanpa menghilangkan filter aktif. */
$tautan = static function (int $n) use ($base, $query): string {
    $params = array_merge($query, $n > 1 ? ['halaman' => $n] : []);
    $qs = http_build_query($params);
    return $base . ($qs !== '' ? '?' . $qs : '');
};

// Tampilkan maksimal 5 nomor halaman di sekitar halaman aktif
$dari = max(1, $halaman - 2);
$sampai = min($terakhir, $dari + 4);
$dari = max(1, $sampai - 4);
?>
<?php if ($terakhir > 1): ?>
<nav class="flex flex-wrap items-center justify-between gap-3 pt-4" aria-label="Navigasi halaman">
    <p class="text-muted" style="margin:0; font-size: var(--text-sm);">
        Halaman <?= $halaman ?> dari <?= $terakhir ?>
        <?= !empty($total) ? ' · ' . (int) $total . ' ' . e($label ?? 'data') : '' ?>
    </p>
    <div class="flex items-center gap-1">
        <?php if ($halaman > 1): ?>
            <a class="btn btn-outline btn-sm" href="<?= e($tautan($halaman - 1)) ?>" rel="prev">Sebelumnya</a>
        <?php endif; ?>

        <?php for ($i = $dari; $i <= $sampai; $i++): ?>
            <?php if ($i === $halaman): ?>
                <span class="btn btn-sm btn-primary" aria-current="page" style="min-width: 38px;"><?= $i ?></span>
            <?php else: ?>
                <a class="btn btn-sm btn-ghost" style="min-width: 38px;" href="<?= e($tautan($i)) ?>"><?= $i ?></a>
            <?php endif; ?>
        <?php endfor; ?>

        <?php if ($halaman < $terakhir): ?>
            <a class="btn btn-outline btn-sm" href="<?= e($tautan($halaman + 1)) ?>" rel="next">Berikutnya</a>
        <?php endif; ?>
    </div>
</nav>
<?php endif; ?>

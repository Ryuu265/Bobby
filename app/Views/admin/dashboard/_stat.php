<?php
/**
 * admin/dashboard/_stat.php — Baris kartu statistik dashboard.
 * Dipakai dengan: View::include('admin/dashboard/_stat', ['kartu' => [...]])
 * tiap kartu = ['label', 'nilai', 'hint', 'tautan', 'aksi']
 */
?>
<div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
    <?php foreach (($kartu ?? []) as $k): ?>
        <div class="stat">
            <p class="stat__label"><?= e($k['label']) ?></p>
            <p class="stat__value"><?= e($k['nilai']) ?></p>
            <p class="stat__hint"><?= e($k['hint'] ?? '') ?></p>
            <?php if (!empty($k['tautan'])): ?>
                <a href="<?= e($k['tautan']) ?>" class="btn btn-outline btn-sm" style="margin-top: var(--sp-3);">
                    <?= e($k['aksi'] ?? 'Lihat') ?>
                </a>
            <?php endif; ?>
        </div>
    <?php endforeach; ?>
</div>

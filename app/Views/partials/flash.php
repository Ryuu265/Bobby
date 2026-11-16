<?php
/**
 * partials/flash.php — Pesan notifikasi satu kali tampil.
 * Nilai dikirim controller melalui helper flash('success', '...').
 */
$pesan = [
    'success' => flash('success'),
    'warning' => flash('warning'),
    'danger'  => flash('danger'),
    'info'    => flash('info'),
];
$adaPesan = array_filter($pesan);
if ($adaPesan === []) {
    return;
}
$ikon = [
    'success' => '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 17l-5-5"/></svg>',
    'warning' => '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 9v4"/><path d="M12 17h.01"/><path d="M10.3 3.9 1.8 18a2 2 0 0 0 1.7 3h17a2 2 0 0 0 1.7-3L13.7 3.9a2 2 0 0 0-3.4 0z"/></svg>',
    'danger'  => '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><path d="M15 9l-6 6M9 9l6 6"/></svg>',
    'info'    => '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><path d="M12 16v-4M12 8h.01"/></svg>',
];
?>
<div class="container-page" style="padding-top: var(--sp-4);" data-flash role="status" aria-live="polite">
    <?php foreach ($pesan as $tipe => $isi): if (!$isi) { continue; } ?>
        <div class="alert alert-<?= e($tipe) ?>" data-autoclose="<?= $tipe === 'success' || $tipe === 'info' ? '6000' : '0' ?>">
            <span aria-hidden="true" style="flex:none;margin-top:1px;"><?= $ikon[$tipe] ?? '' ?></span>
            <div class="flex-1"><?= e($isi) ?></div>
            <button type="button" class="btn btn-ghost btn-sm" data-dismiss aria-label="Tutup pesan">&times;</button>
        </div>
    <?php endforeach; ?>
</div>

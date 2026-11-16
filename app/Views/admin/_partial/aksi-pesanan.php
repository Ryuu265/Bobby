<?php
/**
 * admin/_partial/aksi-pesanan.php — Kumpulan tombol perpindahan status pesanan.
 * Dipakai ulang oleh admin/pesanan/index.php dan admin/pesanan/detail.php.
 *
 * Data masuk:
 *   $b     baris pesanan (id, invoice_number, status_pesanan)
 *   $rapat true = tombol ringkas tanpa kolom alasan (dipakai di tabel indeks)
 */

use App\Models\Booking;

$idPesanan   = (int) $b['id'];
$statusAwal  = (string) $b['status_pesanan'];
$rapat       = $rapat ?? false;
$nomorInvoice = (string) $b['invoice_number'];

$gaya = [
    'dikonfirmasi' => ['btn-primary', 'Konfirmasi'],
    'berlangsung'  => ['btn-violet', 'Mulai'],
    'selesai'      => ['btn-outline', 'Selesai'],
    'dibatalkan'   => ['btn-danger', 'Batalkan'],
];

// Hanya transisi yang legal menurut Booking::TRANSISI
$tujuan = array_values(array_filter(
    array_keys($gaya),
    static fn (string $s): bool => Booking::bolehTransisi($statusAwal, $s)
));
?>
<?php if ($tujuan === []): ?>
    <span class="text-muted" style="font-size: var(--text-xs);">
        Pesanan sudah <?= e(status_label($statusAwal)) ?> &mdash; tidak ada aksi lanjutan
    </span>
<?php else: ?>
    <div class="flex flex-wrap items-center gap-2">
        <?php foreach ($tujuan as $status): ?>
            <?php [$kelas, $label] = $gaya[$status]; ?>

            <?php if ($status === 'dibatalkan' && !$rapat): ?>
                <form method="post" action="<?= url('admin/pesanan/' . $idPesanan . '/status') ?>"
                      class="flex flex-wrap items-center gap-2" style="flex: 1 1 240px;"
                      data-confirm="Batalkan pesanan <?= e($nomorInvoice) ?>? Slot jadwal akan terbuka lagi untuk pelanggan lain.">
                    <?= csrf_field() ?>
                    <input type="hidden" name="status" value="dibatalkan">
                    <label class="sr-only" for="alasan-<?= $idPesanan ?>">Alasan pembatalan</label>
                    <input class="input" id="alasan-<?= $idPesanan ?>" type="text" name="alasan_batal" maxlength="255"
                           placeholder="Alasan pembatalan (opsional)"
                           style="flex: 1 1 180px; padding: 0.45rem 0.7rem; font-size: var(--text-xs);">
                    <button type="submit" class="btn btn-danger btn-sm"><?= e($label) ?></button>
                </form>
            <?php else: ?>
                <form method="post" action="<?= url('admin/pesanan/' . $idPesanan . '/status') ?>"
                      <?= $status === 'dibatalkan'
                          ? 'data-confirm="Batalkan pesanan ' . e($nomorInvoice) . '? Slot jadwal akan terbuka lagi."'
                          : '' ?>>
                    <?= csrf_field() ?>
                    <input type="hidden" name="status" value="<?= e($status) ?>">
                    <button type="submit" class="btn <?= e($kelas) ?> btn-sm"><?= e($label) ?></button>
                </form>
            <?php endif; ?>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

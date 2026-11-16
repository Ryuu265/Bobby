<?php
/**
 * pages/riwayat/detail.php — Detail satu pesanan: linimasa, rincian biaya,
 * pembayaran, form unggah bukti, dan form pembatalan mandiri.
 *
 * @var array $pesanan
 * @var array $pembayaran
 * @var bool $bolehBatal
 * @var string $alasanBatal
 * @var array $errors
 * @var array|null $authUser
 */

use App\Core\View;
use App\Models\Setting;

$b = $pesanan;
View::set('title', 'Pesanan ' . $b['invoice_number'] . ' — ' . Setting::get('nama_rental', config('app.name')));

$err = static fn (string $key): string => isset($errors[$key]) ? '<span class="field-error">' . e($errors[$key][0]) . '</span>' : '';

$bisaBayar = $b['status_bayar'] !== 'lunas' && $b['status_pesanan'] !== 'dibatalkan';
$metode = (string) old('metode', (string) $b['metode_bayar']);
if (!in_array($metode, ['transfer', 'bayar_di_tempat'], true)) {
    $metode = (string) $b['metode_bayar'];
}

/* Linimasa status pesanan */
$tahap = [
    ['Dibuat', $b['created_at'], true],
    ['Menunggu konfirmasi admin', null, $b['status_pesanan'] === 'menunggu'],
    ['Dikonfirmasi', $b['dikonfirmasi_pada'], $b['dikonfirmasi_pada'] !== null],
    ['Sewa berlangsung', $b['dimulai_pada'], $b['dimulai_pada'] !== null],
    ['Selesai', $b['selesai_pada'], $b['selesai_pada'] !== null],
];
if ($b['status_pesanan'] === 'dibatalkan') {
    $tahap[] = ['Dibatalkan', $b['dibatalkan_pada'], true];
}

$rincian = [
    'Tarif per jam'      => rupiah($b['harga_per_jam']) . ' / jam',
    'Durasi sewa'        => (int) $b['durasi_jam'] . ' jam',
    'Biaya sewa dasar'   => rupiah((float) $b['harga_per_jam'] * (int) $b['durasi_jam']),
];
if ((int) $b['controller_tambahan'] > 0) {
    $rincian['Controller tambahan'] = (int) $b['controller_tambahan'] . ' x ' . rupiah($b['tarif_controller'])
        . ' x ' . (int) $b['durasi_jam'] . ' jam = ' . rupiah($b['biaya_controller']);
}
$rincian['Subtotal'] = rupiah($b['subtotal']);
$rincian['Diskon'] = $b['nama_paket']
    ? rupiah($b['diskon']) . ' (' . $b['nama_paket'] . ')'
    : rupiah($b['diskon']);
?>
<section class="section" style="padding-bottom: var(--sp-6);">
    <div class="container-page">
        <p class="text-muted" style="margin:0 0 var(--sp-3); font-size: var(--text-sm);">
            <a href="<?= url('riwayat') ?>">Riwayat Pesanan</a> &rsaquo; <span class="mono"><?= e($b['invoice_number']) ?></span>
        </p>
        <div class="flex flex-wrap items-start justify-between gap-4">
            <div>
                <h1 class="display" style="font-size: var(--text-2xl); margin-bottom: 4px;">
                    <?= e($b['kode_unit']) ?> · <?= e($b['nama_unit']) ?>
                </h1>
                <p class="text-secondary" style="margin:0; font-size: var(--text-sm);">
                    <?= e(tgl_indo($b['booking_date'])) ?> ·
                    <span class="mono"><?= e(jam($b['start_time'])) ?> - <?= e(jam($b['end_time'])) ?></span>
                    (<?= (int) $b['durasi_jam'] ?> jam) · <?= e($b['tipe']) ?>
                </p>
            </div>
            <div class="flex flex-wrap gap-2">
                <span class="badge <?= status_class((string) $b['status_pesanan']) ?>" style="font-size: var(--text-sm);">
                    <?= e(status_label((string) $b['status_pesanan'])) ?>
                </span>
                <span class="badge <?= status_class((string) $b['status_bayar']) ?>" style="font-size: var(--text-sm);">
                    <?= e(status_label((string) $b['status_bayar'])) ?>
                </span>
            </div>
        </div>
    </div>
</section>

<?php if ($b['status_pesanan'] === 'dibatalkan'): ?>
    <section style="padding-bottom: var(--sp-6);">
        <div class="container-page">
            <div class="alert alert-danger">
                <div class="flex-1">
                    Pesanan ini dibatalkan pada <?= e(tgl_waktu($b['dibatalkan_pada'])) ?>.
                    Alasan: <?= e((string) $b['alasan_batal']) ?>
                </div>
            </div>
        </div>
    </section>
<?php elseif ($b['status_pesanan'] === 'menunggu'): ?>
    <section style="padding-bottom: var(--sp-6);">
        <div class="container-page">
            <div class="alert alert-info">
                <div class="flex-1">
                    Jadwal Anda sudah dikunci sistem, tetapi masih menunggu konfirmasi operator.
                    Slot tidak akan dilepas ke pelanggan lain sebelum status berubah.
                </div>
            </div>
        </div>
    </section>
<?php endif; ?>

<section style="padding-bottom: var(--sp-12);">
    <div class="container-page grid gap-4 lg:grid-cols-3">

        <!-- Kolom utama -->
        <div class="lg:col-span-2 grid gap-4" style="align-content: start;">

            <div class="qs-panel">
                <div class="qs-panel__head">
                    <h2 class="display" style="font-size: var(--text-base); margin:0;">Rincian Sewa</h2>
                    <span class="text-muted" style="font-size: var(--text-xs);"><?= e($b['kode_unit']) ?></span>
                </div>
                <div class="qs-panel__body grid gap-4 sm:grid-cols-2">
                    <?php foreach ($rincian as $label => $nilai): ?>
                        <div class="flex items-start justify-between gap-3">
                            <span class="text-secondary" style="font-size: var(--text-sm);"><?= e($label) ?></span>
                            <span class="mono" style="font-size: var(--text-sm); text-align:right;"><?= e($nilai) ?></span>
                        </div>
                    <?php endforeach; ?>

                    <div class="divider" style="grid-column: 1 / -1; margin: 0;"></div>

                    <div class="flex items-end justify-between gap-3" style="grid-column: 1 / -1;">
                        <span class="display" style="font-size: var(--text-sm); color: var(--text-secondary);">Total tagihan</span>
                        <span class="display text-cyan" style="font-size: var(--text-2xl);"><?= e(rupiah($b['total_harga'])) ?></span>
                    </div>

                    <?php if (!empty($b['catatan_pelanggan'])): ?>
                        <div style="grid-column: 1 / -1;">
                            <p class="text-muted" style="margin:0 0 4px; font-size: var(--text-xs); text-transform: uppercase; letter-spacing: .06em;">
                                Catatan Anda
                            </p>
                            <p class="text-secondary" style="margin:0; font-size: var(--text-sm);"><?= e($b['catatan_pelanggan']) ?></p>
                        </div>
                    <?php endif; ?>

                    <?php if (!empty($b['catatan_admin'])): ?>
                        <div style="grid-column: 1 / -1;">
                            <p class="text-muted" style="margin:0 0 4px; font-size: var(--text-xs); text-transform: uppercase; letter-spacing: .06em;">
                                Catatan operator
                            </p>
                            <p class="text-secondary" style="margin:0; font-size: var(--text-sm);"><?= e($b['catatan_admin']) ?></p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Linimasa -->
            <div class="qs-panel">
                <div class="qs-panel__head">
                    <h2 class="display" style="font-size: var(--text-base); margin:0;">Linimasa Status</h2>
                </div>
                <div class="qs-panel__body">
                    <ol style="list-style:none; margin:0; padding:0;">
                        <?php foreach ($tahap as $i => [$judul, $waktu, $lewat]): ?>
                            <li class="flex gap-3" style="<?= $i === 0 ? '' : 'margin-top: var(--sp-4);' ?>">
                                <span style="flex:none; width: 12px; margin-top: 5px;">
                                    <span style="display:block; width: 12px; height: 12px; border-radius: 50%;
                                                 background: <?= $lewat ? 'var(--cyan)' : 'var(--ink-600)' ?>;
                                                 border: 1px solid <?= $lewat ? 'rgba(var(--cyan-rgb) / 0.5)' : 'var(--border-medium)' ?>;"></span>
                                    <?php if ($i !== count($tahap) - 1): ?>
                                        <span style="display:block; width: 1px; height: 26px; margin: 4px auto 0;
                                                     background: var(--border-medium);"></span>
                                    <?php endif; ?>
                                </span>
                                <span class="flex-1">
                                    <span style="display:block; font-size: var(--text-sm); font-weight:600;
                                                 color: <?= $lewat ? 'var(--text-primary)' : 'var(--text-muted)' ?>;">
                                        <?= e($judul) ?>
                                    </span>
                                    <span class="text-muted mono" style="font-size: var(--text-xs);">
                                        <?= $waktu ? e(tgl_waktu($waktu)) : 'Belum terjadi' ?>
                                    </span>
                                </span>
                            </li>
                        <?php endforeach; ?>
                    </ol>
                </div>
            </div>

            <!-- Riwayat pembayaran -->
            <div class="qs-panel">
                <div class="qs-panel__head">
                    <h2 class="display" style="font-size: var(--text-base); margin:0;">Pembayaran</h2>
                    <span class="text-muted" style="font-size: var(--text-xs);"><?= count($pembayaran) ?> catatan</span>
                </div>
                <div class="qs-panel__body">
                    <?php if ($pembayaran === []): ?>
                        <div class="empty-state" style="padding: var(--sp-6) 0;">
                            <p style="margin:0;">
                                Belum ada pembayaran dicatat untuk pesanan ini. Gunakan formulir di samping.
                            </p>
                        </div>
                    <?php else: ?>
                        <ul style="list-style:none; margin:0; padding:0;">
                            <?php foreach ($pembayaran as $p): ?>
                                <li style="padding: var(--sp-3) 0; border-bottom: 1px solid var(--border-soft);">
                                    <div class="flex flex-wrap items-center justify-between gap-3">
                                        <span class="mono" style="font-size: var(--text-sm);"><?= e(rupiah($p['jumlah'])) ?></span>
                                        <span class="badge <?= status_class((string) $p['status']) ?>">
                                            <?= e(status_label((string) $p['status'])) ?>
                                        </span>
                                    </div>
                                    <p class="text-muted" style="margin:4px 0 0; font-size: var(--text-xs);">
                                        <?= e(status_label((string) $p['metode'])) ?> · dikirim <?= e(tgl_waktu($p['created_at'])) ?>
                                        <?php if (!empty($p['nomor_referensi'])): ?>
                                            · ref <span class="mono"><?= e($p['nomor_referensi']) ?></span>
                                        <?php endif; ?>
                                    </p>
                                    <?php if (!empty($p['bukti_path'])): ?>
                                        <a href="<?= e(upload_url($p['bukti_path'])) ?>" target="_blank" rel="noopener"
                                           class="btn btn-outline btn-sm" style="margin-top: var(--sp-2);">
                                            Lihat Bukti
                                        </a>
                                    <?php endif; ?>
                                    <?php if (!empty($p['catatan_admin'])): ?>
                                        <p class="text-secondary" style="margin: var(--sp-2) 0 0; font-size: var(--text-xs);">
                                            Operator: <?= e($p['catatan_admin']) ?>
                                            <?php if (!empty($p['nama_verifikator'])): ?>
                                                (<?= e($p['nama_verifikator']) ?>)
                                            <?php endif; ?>
                                        </p>
                                    <?php endif; ?>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Kolom samping: aksi -->
        <div class="grid gap-4" style="align-content: start;">

            <?php if ($bisaBayar): ?>
                <div class="qs-card" id="bayar">
                    <h2 class="display" style="font-size: var(--text-base); margin-bottom: var(--sp-2);">Catat Pembayaran</h2>
                    <p class="text-secondary" style="margin:0 0 var(--sp-4); font-size: var(--text-sm);">
                        Metode dipilih saat memesan: <?= e(status_label((string) $b['metode_bayar'])) ?>.
                        Bukti Anda diperiksa admin sebelum status menjadi lunas.
                    </p>

                    <form method="post" action="<?= route('payment.unggah', ['id' => (int) $b['id']]) ?>"
                          enctype="multipart/form-data">
                        <?= csrf_field() ?>

                        <div class="field">
                            <span class="label">Metode</span>
                            <div class="grid gap-3">
                                <?php foreach (['transfer' => 'Transfer bank', 'bayar_di_tempat' => 'Bayar di tempat'] as $nilai => $label): ?>
                                    <label class="choice">
                                        <input type="radio" name="metode" value="<?= e($nilai) ?>" <?= $metode === $nilai ? 'checked' : '' ?>>
                                        <strong style="font-size: var(--text-sm); font-weight:600;"><?= e($label) ?></strong>
                                    </label>
                                <?php endforeach; ?>
                            </div>
                            <?= $err('metode') ?>
                        </div>

                        <?php if ($b['metode_bayar'] === 'transfer'): ?>
                            <div class="qs-card qs-card--flat" style="padding: var(--sp-4); margin-bottom: var(--sp-5);">
                                <p class="text-muted" style="margin:0 0 4px; font-size: var(--text-xs); text-transform: uppercase; letter-spacing: .06em;">
                                    Rekening tujuan
                                </p>
                                <p class="mono" style="margin:0; font-size: var(--text-sm); color: var(--cyan);">
                                    <?= e(Setting::get('rekening_bank', '-')) ?>
                                </p>
                            </div>
                        <?php endif; ?>

                        <div class="field">
                            <label class="label" for="jumlah">Nominal</label>
                            <input class="input <?= isset($errors['jumlah']) ? 'is-invalid' : '' ?>"
                                   type="text" id="jumlah" name="jumlah" inputmode="numeric"
                                   value="<?= old('jumlah', (string) (int) round((float) $b['total_harga'])) ?>"
                                   placeholder="<?= e(rupiah($b['total_harga'], false)) ?>">
                            <?= $err('jumlah') ?>
                            <small class="text-muted">Angka saja (contoh: 54000). Boleh kurang dari total untuk bayar sebagian.</small>
                        </div>

                        <div class="field">
                            <label class="label" for="nomor_referensi">No. referensi transfer <small>(opsional)</small></label>
                            <input class="input <?= isset($errors['nomor_referensi']) ? 'is-invalid' : '' ?>"
                                   type="text" id="nomor_referensi" name="nomor_referensi" maxlength="60"
                                   value="<?= old('nomor_referensi') ?>" placeholder="Contoh: 882716450012">
                            <?= $err('nomor_referensi') ?>
                        </div>

                        <div class="field">
                            <label class="label" for="bukti">Bukti pembayaran <small>(jpg/png/webp/pdf, maks 2 MB)</small></label>
                            <input class="input" type="file" id="bukti" name="bukti"
                                   accept=".jpg,.jpeg,.png,.webp,.pdf">
                            <?= $err('bukti') ?>
                            <small class="text-muted">Kosongkan bila Anda membayar langsung di tempat.</small>
                        </div>

                        <button type="submit" class="btn btn-primary btn-block btn-lg">Kirim Pembayaran</button>
                    </form>
                </div>
            <?php else: ?>
                <div class="qs-card">
                    <h2 class="display" style="font-size: var(--text-base); margin-bottom: var(--sp-2);">Pembayaran</h2>
                    <p class="text-secondary" style="margin:0; font-size: var(--text-sm);">
                        <?php if ($b['status_bayar'] === 'lunas'): ?>
                            Terima kasih, pesanan ini sudah <strong>lunas</strong>.
                            <?php if (!empty($b['selesai_pada'])): ?>
                                Dibayar penuh sebelum sewa selesai.
                            <?php endif; ?>
                        <?php else: ?>
                            Pesanan dibatalkan, jadi tidak ada pembayaran yang perlu dicatat.
                        <?php endif; ?>
                    </p>
                </div>
            <?php endif; ?>

            <div class="qs-card">
                <h2 class="display" style="font-size: var(--text-base); margin-bottom: var(--sp-2);">Batalkan Pesanan</h2>
                <p class="text-secondary" style="margin:0 0 var(--sp-3); font-size: var(--text-sm);">
                    <?= e($alasanBatal) ?>
                </p>
                <?php if ($bolehBatal): ?>
                    <form method="post" action="<?= route('booking.batal', ['id' => (int) $b['id']]) ?>"
                          data-confirm="Batalkan pesanan <?= e($b['invoice_number']) ?>? Jadwal akan dilepas untuk pelanggan lain.">
                        <?= csrf_field() ?>
                        <div class="field">
                            <label class="label" for="alasan">Alasan <small>(opsional)</small></label>
                            <textarea class="textarea <?= isset($errors['alasan']) ? 'is-invalid' : '' ?>"
                                      id="alasan" name="alasan" maxlength="255" rows="3"
                                      placeholder="Contoh: berhalangan datang sore ini."><?= old('alasan') ?></textarea>
                            <?= $err('alasan') ?>
                        </div>
                        <button type="submit" class="btn btn-danger btn-block">Batalkan Pesanan</button>
                    </form>
                <?php else: ?>
                    <button type="button" class="btn btn-danger btn-block" disabled>Pembatalan Ditutup</button>
                    <p class="text-muted" style="margin: var(--sp-2) 0 0; font-size: var(--text-xs); text-align:center;">
                        Hubungi <?= e(Setting::get('telepon', 'operator')) ?> untuk bantuan pembatalan.
                    </p>
                <?php endif; ?>
            </div>

            <div class="qs-card">
                <h2 class="display" style="font-size: var(--text-sm); color: var(--text-secondary); letter-spacing: .1em;">
                    Unit Disewa
                </h2>
                <a href="<?= url('katalog/unit/' . $b['kode_unit']) ?>" class="unit-card__media block" style="margin-bottom: var(--sp-3);">
                    <?php if (!empty($b['foto_path'])): ?>
                        <img src="<?= e(upload_url($b['foto_path'])) ?>" alt="Foto <?= e($b['nama_unit']) ?>" loading="lazy">
                    <?php else: ?>
                        <span class="unit-card__code"><?= e($b['kode_unit']) ?></span>
                    <?php endif; ?>
                </a>
                <p style="margin:0; font-size: var(--text-sm); font-weight:600;"><?= e($b['nama_unit']) ?></p>
                <p class="text-muted" style="margin:2px 0 0; font-size: var(--text-xs);">
                    <?= e($b['tipe']) ?> · kapasitas <?= (int) $b['kapasitas'] ?> pemain
                </p>
                <a href="<?= url('booking?unit=' . (int) $b['unit_id']) ?>" class="btn btn-outline btn-sm btn-block" style="margin-top: var(--sp-3);">
                    Pesan Unit Ini Lagi
                </a>
            </div>

        </div>
    </div>
</section>

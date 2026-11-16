<?php
/**
 * admin/pesanan/detail.php — Satu pesanan lengkap: pelanggan, unit, jadwal,
 * rincian biaya, ketersediaan jam, riwayat pembayaran, timeline, dan form catatan.
 */

use App\Core\View;

$pesanan = is_array($pesanan ?? null) ? $pesanan : [];

$id        = (int) $pesanan['id'];
$invoice   = (string) $pesanan['invoice_number'];
$statusPesanan = (string) $pesanan['status_pesanan'];
$statusBayar   = (string) $pesanan['status_bayar'];
$tanggalSewa   = (string) $pesanan['booking_date'];
$mulaiMenit    = (int) substr((string) $pesanan['start_time'], 0, 2) * 60 + (int) substr((string) $pesanan['start_time'], 3, 2);
$selesaiMenit  = (int) substr((string) $pesanan['end_time'], 0, 2) * 60 + (int) substr((string) $pesanan['end_time'], 3, 2);
$hariIni   = (string) ($hariIni ?? date('Y-m-d'));
$slots     = $slots ?? [];
$kepadatan = $kepadatan ?? ['total_jam_operasional' => 0, 'jam_terisi' => 0, 'persen' => 0, 'jumlah_pesanan' => 0];
$operasional = $operasional ?? ['buka' => 0, 'tutup' => 0];
$pembayaran  = $pembayaran ?? [];
$games       = $games ?? [];

View::set('title', 'Pesanan ' . $invoice . ' — ' . config('app.name'));
View::set('page_title', 'Pesanan ' . $invoice);
View::set('page_subtitle', tgl_indo($tanggalSewa) . ' · ' . jam((string) $pesanan['start_time']) . '–' . jam((string) $pesanan['end_time'])
    . ' · ' . (string) $pesanan['kode_unit'] . ' (' . (string) $pesanan['tipe'] . ')');


/** Kiri-kanan label/nilai untuk blok informasi. */
$info = static function (string $label, string $nilai, bool $mono = false): string {
    return '<div class="flex items-start justify-between gap-3" style="padding: 0.4rem 0; border-bottom: 1px solid var(--border-soft);">'
        . '<span class="text-muted" style="font-size: var(--text-xs);">' . e($label) . '</span>'
        . '<span' . ($mono ? ' class="mono"' : '') . ' style="font-size: var(--text-sm); text-align: right; font-weight: 600;">' . $nilai . '</span>'
        . '</div>';
};

// Timeline: kolom waktu status yang sudah terjadi
$tahap = [
    ['Pesanan dibuat', (string) $pesanan['created_at'], 'badge-neutral'],
    ['Dikonfirmasi admin', (string) ($pesanan['dikonfirmasi_pada'] ?? ''), 'badge-cyan'],
    ['Sewa dimulai', (string) ($pesanan['dimulai_pada'] ?? ''), 'badge-info'],
    ['Sewa selesai', (string) ($pesanan['selesai_pada'] ?? ''), 'badge-success'],
];
if ($statusPesanan === 'dibatalkan') {
    $tahap = [
        ['Pesanan dibuat', (string) $pesanan['created_at'], 'badge-neutral'],
        ['Dibatalkan', (string) ($pesanan['dibatalkan_pada'] ?? ''), 'badge-danger'],
    ];
}

$terpilihSlot = static function (array $s) use ($mulaiMenit, $selesaiMenit, $statusPesanan): string {
    if (!$s['tersedia'] && $s['menit'] >= $mulaiMenit && $s['menit'] < $selesaiMenit && $statusPesanan !== 'dibatalkan') {
        return ' is-selected';
    }
    if ($s['alasan'] === 'lewat') {
        return ' is-past';
    }
    return $s['alasan'] === 'terisi' ? ' is-taken' : '';
};
?>
<!-- ============ STATUS + AKSI CEPAT ============ -->
<div class="qs-panel" style="margin-bottom: var(--sp-5);">
    <div class="qs-panel__head" style="flex-wrap: wrap; row-gap: var(--sp-3);">
        <div class="flex flex-wrap items-center gap-2">
            <span class="badge <?= e(status_class($statusPesanan)) ?>"><?= e(status_label($statusPesanan)) ?></span>
            <span class="badge <?= e(status_class($statusBayar)) ?>"><?= e(status_label($statusBayar)) ?></span>
            <span class="badge badge-neutral badge-no-dot"><?= e(status_label((string) $pesanan['metode_bayar'])) ?></span>
            <span class="text-muted" style="font-size: var(--text-xs);">
                dibuat <?= e(tgl_waktu((string) $pesanan['created_at'])) ?>
            </span>
        </div>
        <a href="<?= url('admin/pesanan') ?>" class="btn btn-ghost btn-sm">&larr; Kembali ke daftar</a>
    </div>
    <div class="qs-panel__body">
        <?php if (!empty($pesanan['alasan_batal'])): ?>
            <div class="alert alert-danger" style="margin-bottom: var(--sp-4);">
                <strong>Alasan pembatalan:</strong> <?= e((string) $pesanan['alasan_batal']) ?>
            </div>
        <?php endif; ?>
        <?= View::include('admin/_partial/aksi-pesanan', ['b' => $pesanan, 'rapat' => false]) ?>
    </div>
</div>

<div class="grid gap-5 lg:grid-cols-2">
    <!-- ============ PELANGGAN ============ -->
    <div class="qs-panel">
        <div class="qs-panel__head"><h2 class="display" style="margin: 0; font-size: var(--text-lg);">Data Pelanggan</h2></div>
        <div class="qs-panel__body">
            <p class="display" style="margin: 0 0 2px; font-size: var(--text-xl);"><?= e((string) $pesanan['nama_pelanggan']) ?></p>
            <p class="text-muted" style="margin: 0 0 var(--sp-4); font-size: var(--text-xs);">
                ID pelanggan #<?= (int) $pesanan['user_id'] ?>
            </p>
            <?= $info('Nama', e((string) $pesanan['nama_pelanggan'])) ?>
            <?= $info('No. HP', '<a class="mono" href="tel:' . e((string) $pesanan['telepon']) . '">' . e((string) $pesanan['telepon']) . '</a>', true) ?>
            <?= $info('Email', '<a href="mailto:' . e((string) $pesanan['email']) . '">' . e((string) $pesanan['email']) . '</a>') ?>
            <?= $info('Alamat', e((string) ($pesanan['alamat'] ?? '')) ?: '<span class="text-muted">belum diisi</span>') ?>
            <div style="padding-top: 0.5rem;">
                <p class="text-muted" style="margin: 0 0 4px; font-size: var(--text-xs);">Catatan pelanggan</p>
                <p class="text-secondary" style="margin: 0; font-size: var(--text-sm);">
                    <?= !empty($pesanan['catatan_pelanggan'])
                        ? e((string) $pesanan['catatan_pelanggan'])
                        : '<span class="text-muted">tidak ada catatan</span>' ?>
                </p>
            </div>
        </div>
    </div>

    <!-- ============ UNIT ============ -->
    <div class="qs-panel">
        <div class="qs-panel__head"><h2 class="display" style="margin: 0; font-size: var(--text-lg);">Unit &amp; Permainan</h2></div>
        <div class="qs-panel__body">
            <div class="flex gap-4" style="align-items: flex-start;">
                <div class="unit-card__media" style="width: 132px; flex: none; margin-bottom: 0;">
                    <img src="<?= e(upload_url($pesanan['foto_path'] ?? null)) ?>" alt="Foto <?= e((string) $pesanan['kode_unit']) ?>">
                </div>
                <div style="flex: 1; min-width: 0;">
                    <p class="display" style="margin: 0 0 2px; font-size: var(--text-xl);"><?= e((string) $pesanan['nama_unit']) ?></p>
                    <p class="text-muted mono" style="margin: 0; font-size: var(--text-xs);">
                        <?= e((string) $pesanan['kode_unit']) ?> &middot; <?= e((string) $pesanan['tipe']) ?>
                        &middot; maks <?= (int) $pesanan['kapasitas'] ?> pemain
                    </p>
                </div>
            </div>

            <div style="margin-top: var(--sp-4);">
                <?= $info('Tarif saat memesan', rupiah((float) $pesanan['harga_per_jam']) . ' / jam', true) ?>
                <?= $info('Paket dipakai', !empty($pesanan['nama_paket']) ? e((string) $pesanan['nama_paket']) : '<span class="text-muted">reguler (tanpa paket)</span>') ?>
            </div>

            <p class="text-muted" style="margin: var(--sp-4) 0 6px; font-size: var(--text-xs);">
                Game terpasang di unit ini (<?= count($games) ?>)
            </p>
            <?php if ($games === []): ?>
                <p class="text-secondary" style="margin: 0; font-size: var(--text-xs);">
                    Belum ada game terdaftar pada unit ini.
                </p>
            <?php else: ?>
                <div class="flex flex-wrap gap-2">
                    <?php foreach (array_slice($games, 0, 12) as $g): ?>
                        <span class="badge badge-neutral badge-no-dot" title="<?= e((string) $g['genre']) ?> · <?= e((string) $g['rating']) ?>">
                            <?= e(mb_strimwidth((string) $g['judul'], 0, 26, '…')) ?>
                        </span>
                    <?php endforeach; ?>
                    <?php if (count($games) > 12): ?>
                        <span class="text-muted" style="font-size: var(--text-xs);">+<?= count($games) - 12 ?> lainnya</span>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- ============ JADWAL + BIAYA ============ -->
    <div class="qs-panel">
        <div class="qs-panel__head"><h2 class="display" style="margin: 0; font-size: var(--text-lg);">Jadwal &amp; Rincian Biaya</h2></div>
        <div class="qs-panel__body">
            <?= $info('Tanggal sewa', e(tgl_indo($tanggalSewa)) . ($tanggalSewa === $hariIni ? ' <span class="text-cyan">(hari ini)</span>' : '')) ?>
            <?= $info('Jam sewa', '<span class="mono">' . e(jam((string) $pesanan['start_time'])) . ' &ndash; ' . e(jam((string) $pesanan['end_time'])) . '</span> <span class="text-muted">(' . (int) $pesanan['durasi_jam'] . ' jam)</span>') ?>
            <?= $info('Jam operasional', '<span class="mono">' . sprintf('%02d.00', (int) $operasional['buka']) . ' &ndash; '
                . ($operasional['tutup'] >= 24 ? '24.00' : sprintf('%02d.00', (int) $operasional['tutup'])) . '</span>') ?>

            <hr class="divider">

            <div class="flex items-start justify-between gap-3" style="padding: 0.4rem 0;">
                <span class="text-secondary" style="font-size: var(--text-sm);">
                    Sewa <?= (int) $pesanan['durasi_jam'] ?> jam &times; <?= rupiah((float) $pesanan['harga_per_jam']) ?>
                </span>
                <span class="mono" style="font-size: var(--text-sm); font-weight: 600;">
                    <?= rupiah((float) $pesanan['harga_per_jam'] * (int) $pesanan['durasi_jam']) ?>
                </span>
            </div>
            <?php if ((int) $pesanan['controller_tambahan'] > 0): ?>
                <div class="flex items-start justify-between gap-3" style="padding: 0.4rem 0;">
                    <span class="text-secondary" style="font-size: var(--text-sm);">
                        Controller tambahan &times;<?= (int) $pesanan['controller_tambahan'] ?> (<?= rupiah((float) $pesanan['tarif_controller']) ?>/jam)
                    </span>
                    <span class="mono" style="font-size: var(--text-sm); font-weight: 600;"><?= rupiah((float) $pesanan['biaya_controller']) ?></span>
                </div>
            <?php endif; ?>
            <div class="flex items-start justify-between gap-3" style="padding: 0.4rem 0; border-top: 1px solid var(--border-soft);">
                <span class="text-secondary" style="font-size: var(--text-sm);">Subtotal</span>
                <span class="mono" style="font-size: var(--text-sm); font-weight: 600;"><?= rupiah((float) $pesanan['subtotal']) ?></span>
            </div>
            <?php if ((float) $pesanan['diskon'] > 0): ?>
                <div class="flex items-start justify-between gap-3" style="padding: 0.4rem 0;">
                    <span class="text-success" style="font-size: var(--text-sm);">Diskon paket</span>
                    <span class="mono text-success" style="font-size: var(--text-sm); font-weight: 600;">
                        &minus;<?= rupiah((float) $pesanan['diskon']) ?>
                    </span>
                </div>
            <?php endif; ?>
            <div class="flex items-center justify-between gap-3" style="padding: 0.6rem 0 0; border-top: 1px solid var(--border-medium);">
                <span class="display" style="font-size: var(--text-base);">Total tagihan</span>
                <span class="display text-cyan" style="font-size: var(--text-2xl); font-weight: 700;">
                    <?= rupiah((float) $pesanan['total_harga']) ?>
                </span>
            </div>
        </div>
    </div>

    <!-- ============ TIMELINE + SLOT ============ -->
    <div class="qs-panel">
        <div class="qs-panel__head"><h2 class="display" style="margin: 0; font-size: var(--text-lg);">Riwayat Status &amp; Ketersediaan</h2></div>
        <div class="qs-panel__body">
            <?php foreach ($tahap as [$labelTahap, $waktuTahap, $warnaTahap]): ?>
                <div class="flex items-center justify-between gap-3" style="padding: 0.45rem 0;">
                    <div class="flex items-center gap-2">
                        <span class="badge <?= $waktuTahap === '' ? 'badge-neutral' : e($warnaTahap) ?> badge-no-dot">
                            <?= $waktuTahap === '' ? 'Belum' : 'Sudah' ?>
                        </span>
                        <span style="font-size: var(--text-sm);"><?= e($labelTahap) ?></span>
                    </div>
                    <span class="mono text-muted" style="font-size: var(--text-xs);">
                        <?= $waktuTahap === '' ? 'belum terjadi' : e(tgl_waktu($waktuTahap)) ?>
                    </span>
                </div>
            <?php endforeach; ?>
            <?php if (!empty($pesanan['dikonfirmasi_oleh_nama'])): ?>
                <p class="text-muted" style="margin: 4px 0 0; font-size: var(--text-xs);">
                    Dikonfirmasi oleh <?= e((string) $pesanan['dikonfirmasi_oleh_nama']) ?>
                </p>
            <?php endif; ?>
            <?php if ((float) $kepadatan['persen'] > 0): ?>
                <div style="margin-top: var(--sp-4);">
                    <div class="flex items-center justify-between gap-2">
                        <p class="text-muted" style="margin: 0; font-size: var(--text-xs);">
                            Kepadatan unit pada tanggal ini
                        </p>
                        <p class="mono text-secondary" style="margin: 0; font-size: var(--text-xs);">
                            <?= e((string) $kepadatan['jam_terisi']) ?> / <?= (int) $kepadatan['total_jam_operasional'] ?> jam
                            &middot; <?= (int) $kepadatan['persen'] ?>%
                        </p>
                    </div>
                    <div class="bar" style="margin-top: 6px;" role="img"
                         aria-label="Unit terisi <?= (int) $kepadatan['persen'] ?> persen pada tanggal ini">
                        <div class="bar__fill<?= (int) $kepadatan['persen'] >= 80 ? ' bar__fill--warning' : '' ?>"
                             style="width: <?= max(2, min(100, (int) $kepadatan['persen'])) ?>%;"></div>
                    </div>
                </div>
            <?php endif; ?>

            <p class="text-muted" style="margin: var(--sp-5) 0 8px; font-size: var(--text-xs);">
                Jam pada <?= e(tgl_indo($tanggalSewa)) ?> — slot bertanda cyan adalah jam yang dipakai pesanan ini
            </p>
            <?php if ($slots === []): ?>
                <p class="text-secondary" style="margin: 0; font-size: var(--text-xs);">
                    Tidak ada slot jam yang bisa ditampilkan untuk tanggal ini.
                </p>
            <?php else: ?>
                <div class="slot-grid" role="list" aria-label="Ketersediaan jam unit pada tanggal pesanan">
                    <?php foreach ($slots as $s): ?>
                        <?php $keterangan = match ((string) $s['alasan']) {
                            'tersedia' => 'masih kosong',
                            'lewat'    => 'sudah terlewat',
                            default    => 'sudah terisi pesanan',
                        }; ?>
                        <span class="slot<?= $terpilihSlot($s) ?>" role="listitem"
                              title="<?= e($s['label']) ?> &mdash; <?= e($keterangan) ?>">
                            <?= e($s['label']) ?>
                        </span>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- ============ RIWAYAT PEMBAYARAN ============ -->
<div class="qs-panel" style="margin-top: var(--sp-5);">
    <div class="qs-panel__head" style="flex-wrap: wrap; row-gap: var(--sp-3);">
        <h2 class="display" style="margin: 0; font-size: var(--text-lg);">Riwayat Pembayaran</h2>
        <a href="<?= url('admin/pembayaran') ?>" class="btn btn-outline btn-sm">Halaman Verifikasi</a>
    </div>
    <div class="qs-panel__body">
        <?php if ($pembayaran === []): ?>
            <div class="empty-state">
                <p class="display" style="font-size: var(--text-lg); margin: 0 0 var(--sp-2);">Belum ada pembayaran</p>
                <p class="text-secondary" style="margin: 0; font-size: var(--text-sm);">
                    <?php if ((string) $pesanan['metode_bayar'] === 'bayar_di_tempat'): ?>
                        Pembayaran tunai di tempat akan tercatat setelah admin menandai pesanan selesai.
                    <?php else: ?>
                        Bukti transfer akan muncul di sini begitu pelanggan mengunggahnya.
                    <?php endif; ?>
                </p>
            </div>
        <?php else: ?>
            <div class="table-wrap">
                <table class="table">
                    <thead>
                        <tr>
                            <th scope="col">Tanggal</th>
                            <th scope="col">Metode</th>
                            <th scope="col" class="num">Jumlah</th>
                            <th scope="col">No. referensi</th>
                            <th scope="col">Status</th>
                            <th scope="col">Diverifikasi</th>
                            <th scope="col">Bukti</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($pembayaran as $p): ?>
                            <tr>
                                <td class="mono" style="font-size: var(--text-xs);"><?= e(tgl_waktu((string) $p['created_at'])) ?></td>
                                <td class="text-secondary"><?= e(status_label((string) $p['metode'])) ?></td>
                                <td class="num"><?= rupiah((float) $p['jumlah']) ?></td>
                                <td class="mono" style="font-size: var(--text-xs);">
                                    <?= !empty($p['nomor_referensi']) ? e((string) $p['nomor_referensi']) : '<span class="text-muted">—</span>' ?>
                                </td>
                                <td>
                                    <span class="badge <?= e(status_class((string) $p['status'])) ?>">
                                        <?= e(status_label((string) $p['status'])) ?>
                                    </span>
                                    <?php if (!empty($p['catatan_admin'])): ?>
                                        <p class="text-muted" style="margin: 4px 0 0; font-size: var(--text-xs);">
                                            <?= e(mb_strimwidth((string) $p['catatan_admin'], 0, 48, '…')) ?>
                                        </p>
                                    <?php endif; ?>
                                </td>
                                <td style="font-size: var(--text-xs);">
                                    <?php if (!empty($p['verifikasi_pada'])): ?>
                                        <span class="mono"><?= e(tgl_waktu((string) $p['verifikasi_pada'])) ?></span>
                                        <p class="text-muted" style="margin: 2px 0 0;">
                                            oleh <?= e((string) ($p['nama_verifikator'] ?? '—')) ?>
                                        </p>
                                    <?php else: ?>
                                        <span class="text-muted">belum diverifikasi</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if (!empty($p['bukti_path'])): ?>
                                        <a class="btn btn-ghost btn-sm" target="_blank" rel="noopener"
                                           href="<?= e(upload_url((string) $p['bukti_path'])) ?>">Lihat bukti</a>
                                    <?php else: ?>
                                        <span class="text-muted" style="font-size: var(--text-xs);">tanpa bukti</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- ============ CATATAN ADMIN ============ -->
<div class="qs-panel" style="margin-top: var(--sp-5);">
    <div class="qs-panel__head">
        <div>
            <h2 class="display" style="margin: 0; font-size: var(--text-lg);">Catatan Internal</h2>
            <p class="text-muted" style="margin: 2px 0 0; font-size: var(--text-xs);">
                Hanya terlihat oleh admin/operator, tidak ditampilkan ke pelanggan.
            </p>
        </div>
    </div>
    <div class="qs-panel__body">
        <form method="post" action="<?= url('admin/pesanan/' . $id . '/catatan') ?>">
            <?= csrf_field() ?>
            <div class="field" style="margin-bottom: var(--sp-4);">
                <label class="label" for="catatan_admin">Catatan admin <small>maks. 255 karakter</small></label>
                <textarea class="textarea <?= isset($errors['catatan_admin']) ? 'is-invalid' : '' ?>" id="catatan_admin"
                          name="catatan_admin" maxlength="255"
                          placeholder="Contoh: controller extra sudah dikembalikan, pelanggan langganan rutin tiap Sabtu."><?= e((string) ($old['catatan_admin'] ?? $pesanan['catatan_admin'] ?? '')) ?></textarea>
                <?= isset($errors['catatan_admin'])
                    ? '<span class="field-error">' . e((string) $errors['catatan_admin'][0]) . '</span>' : '' ?>
            </div>
            <button type="submit" class="btn btn-primary btn-sm">Simpan Catatan</button>
        </form>
    </div>
</div>

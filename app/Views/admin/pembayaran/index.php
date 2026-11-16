<?php
/**
 * admin/pembayaran/index.php — Antrean verifikasi bukti pembayaran + riwayat.
 * Data dari Admin\PaymentController::index().
 */

use App\Core\View;
use App\Models\Setting;

View::set('title', 'Verifikasi Pembayaran — ' . config('app.name'));
View::set('page_title', 'Verifikasi Pembayaran');
View::set('page_subtitle', 'Periksa bukti transfer, terima atau tolak, lalu sinkronkan status pesanan');


$rekening   = (string) Setting::get('rekening_bank', '-');
$statusList = $statusList ?? [];

/** Bukti bisa berupa gambar atau PDF — cara tampilnya berbeda. */
$preview = static function (?string $path): array {
    $ext = strtolower((string) pathinfo((string) $path, PATHINFO_EXTENSION));
    return [
        'url' => upload_url($path),
        'pdf' => $ext === 'pdf',
    ];
};
?>
<div class="grid gap-5">

    <!-- ============ RINGKASAN ============ -->
    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <div class="stat">
            <p class="stat__label">Menunggu Verifikasi</p>
            <p class="stat__value<?= $statistik['menunggu'] > 0 ? ' text-warning' : '' ?>"><?= (int) $statistik['menunggu'] ?></p>
            <p class="stat__hint">Bukti yang perlu diperiksa hari ini</p>
        </div>
        <div class="stat">
            <p class="stat__label">Lunas Hari Ini</p>
            <p class="stat__value text-success"><?= e(rupiah($statistik['lunas_hari_ini'])) ?></p>
            <p class="stat__hint">Total pembayaran yang disetujui hari ini</p>
        </div>
        <div class="stat">
            <p class="stat__label">Ditolak</p>
            <p class="stat__value"><?= (int) $statistik['ditolak'] ?></p>
            <p class="stat__hint">Pelanggan dapat mengunggah ulang bukti</p>
        </div>
        <div class="stat">
            <p class="stat__label">Antrean Ditampilkan</p>
            <p class="stat__value"><?= count($antrean) ?></p>
            <p class="stat__hint">Maksimal 50 bukti paling lama</p>
        </div>
    </div>

    <p class="text-secondary" style="margin: 0; font-size: var(--text-sm); max-width: 78ch;">
        Bandingkan nominal pada bukti transfer dengan rekening tujuan sebelum menekan Terima.
        Rekening resmi: <strong><?= e($rekening) ?></strong> atas nama <?= e(Setting::get('nama_rental', config('app.name'))) ?>.
    </p>

    <!-- ============ ANTREAN VERIFIKASI ============ -->
    <section class="qs-panel">
        <div class="qs-panel__head flex-wrap gap-2">
            <h2 class="display" style="font-size: var(--text-lg); margin: 0;">Antrean Bukti Pembayaran</h2>
            <span class="badge badge-warning"><?= (int) $statistik['menunggu'] ?> menunggu</span>
            <a class="btn btn-ghost btn-sm" style="margin-left: auto;" href="<?= e(route('admin.pesanan')) ?>">Kelola Pesanan</a>
        </div>
        <div class="qs-panel__body">
            <?php if ($antrean === []): ?>
                <div class="empty-state">
                    <p class="display" style="font-size: var(--text-lg); margin-bottom: 4px;">Antrean bersih</p>
                    <p class="text-secondary" style="margin: 0 auto; font-size: var(--text-sm); max-width: 54ch;">
                        Tidak ada bukti pembayaran yang menunggu verifikasi. Pesanan baru dengan metode
                        transfer akan muncul di sini otomatis setelah pelanggan mengunggah bukti.
                    </p>
                </div>
            <?php else: ?>
                <div class="grid gap-4 xl:grid-cols-2">
                    <?php foreach ($antrean as $p): ?>
                        <?php
                        $id       = (int) $p['id'];
                        $booking  = (int) $p['booking_id'];
                        $jumlah   = (float) $p['jumlah'];
                        $tagihan  = (float) $p['total_harga'];
                        $kurang   = abs($jumlah - $tagihan) > 1;
                        $bukti    = $preview($p['bukti_path'] ?? null);
                        $punya    = $p['bukti_path'] !== null && trim((string) $p['bukti_path']) !== '';
                        ?>
                        <article class="qs-card qs-card--flat" style="padding: var(--sp-4);">
                            <div class="flex flex-wrap items-center gap-2" style="margin-bottom: var(--sp-3);">
                                <a class="mono" style="font-weight: 600;"
                                   href="<?= e(route('admin.pesanan.detail', ['id' => $booking])) ?>"><?= e($p['invoice_number']) ?></a>
                                <span class="badge badge-warning">Menunggu Verifikasi</span>
                                <span class="text-muted" style="margin-left: auto; font-size: var(--text-xs);">#<?= $id ?></span>
                            </div>

                            <div class="grid gap-3 sm:grid-cols-2" style="font-size: var(--text-sm);">
                                <div>
                                    <p class="text-muted" style="margin: 0; font-size: var(--text-xs); text-transform: uppercase;">Pelanggan</p>
                                    <p style="margin: 2px 0 0; font-weight: 600;"><?= e($p['nama_pelanggan']) ?></p>
                                    <p class="text-muted" style="margin: 2px 0 0; font-size: var(--text-xs);">
                                        Unit <?= e($p['kode_unit']) ?> ·
                                        <?= e(tgl_indo($p['booking_date'])) ?> <?= e(jam($p['start_time'])) ?>
                                    </p>
                                </div>
                                <div class="sm:text-right">
                                    <p class="text-muted" style="margin: 0; font-size: var(--text-xs); text-transform: uppercase;">Nominal</p>
                                    <p class="mono" style="margin: 2px 0 0; font-weight: 700; font-size: var(--text-lg);"><?= e(rupiah($jumlah)) ?></p>
                                    <?php if ($kurang): ?>
                                        <p class="text-warning" style="margin: 2px 0 0; font-size: var(--text-xs);">
                                            Beda dengan tagihan <?= e(rupiah($tagihan)) ?>
                                        </p>
                                    <?php endif; ?>
                                    <p class="text-muted" style="margin: 2px 0 0; font-size: var(--text-xs);">
                                        <?= e(status_label($p['metode'])) ?>
                                        <?= $p['nomor_referensi'] ? ' · Ref ' . e($p['nomor_referensi']) : '' ?>
                                    </p>
                                </div>
                            </div>

                            <!-- Bukti transfer -->
                            <div class="flex items-center gap-3" style="margin-top: var(--sp-3);">
                                <?php if (!$punya): ?>
                                    <p class="text-warning" style="margin: 0; font-size: var(--text-sm);">
                                        Tidak ada lampiran bukti — biasanya pembayaran di tempat.
                                    </p>
                                <?php elseif ($bukti['pdf']): ?>
                                    <span class="badge badge-neutral badge-no-dot">PDF</span>
                                    <a class="btn btn-outline btn-sm" target="_blank" rel="noopener" href="<?= e($bukti['url']) ?>">Buka bukti</a>
                                <?php else: ?>
                                    <a href="<?= e($bukti['url']) ?>" target="_blank" rel="noopener"
                                       aria-label="Perbesar bukti pembayaran <?= e($p['invoice_number']) ?>">
                                        <img src="<?= e($bukti['url']) ?>" alt="Bukti transfer <?= e($p['invoice_number']) ?>"
                                             loading="lazy" style="width: 96px; height: 72px; object-fit: cover; border-radius: var(--radius-sm); border: 1px solid var(--border-medium);">
                                    </a>
                                    <a class="btn btn-outline btn-sm" target="_blank" rel="noopener" href="<?= e($bukti['url']) ?>">Buka tab baru</a>
                                <?php endif; ?>
                                <span class="text-muted" style="margin-left: auto; font-size: var(--text-xs);">
                                    Diunggah <?= e(tgl_waktu($p['created_at'])) ?>
                                </span>
                            </div>

                            <!-- Keputusan -->
                            <div class="flex flex-wrap items-end gap-2" style="margin-top: var(--sp-4);">
                                <form method="post" action="<?= e(url('admin/pembayaran/' . $id)) ?>" class="flex-none">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="keputusan" value="terima">
                                    <button type="submit" class="btn btn-primary btn-sm">Terima Pembayaran</button>
                                </form>

                                <form method="post" action="<?= e(url('admin/pembayaran/' . $id)) ?>"
                                     class="field" style="flex: 1 1 220px; margin: 0;"
                                     data-confirm="Tolak bukti pembayaran <?= e($p['invoice_number']) ?>? Pelanggan harus mengunggah bukti baru.">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="keputusan" value="tolak">
                                    <label class="label" for="catatan-<?= $id ?>" style="font-size: var(--text-xs);">Alasan penolakan</label>
                                    <input class="input" type="text" id="catatan-<?= $id ?>" name="catatan" maxlength="255"
                                           placeholder="Nominal tidak sesuai / bukti kurang jelas">
                                    <button type="submit" class="btn btn-danger btn-sm" style="margin-top: var(--sp-2); width: 100%;">Tolak</button>
                                </form>
                            </div>
                        </article>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </section>

    <!-- ============ RIWAYAT PEMBAYARAN ============ -->
    <section class="qs-panel">
        <div class="qs-panel__head flex-wrap gap-3">
            <h2 class="display" style="font-size: var(--text-lg); margin: 0;">Riwayat Pembayaran</h2>
            <form method="get" action="<?= e(url('admin/pembayaran')) ?>" class="flex flex-wrap items-end gap-3" style="margin-left: auto;">
                <div class="field" style="margin: 0;">
                    <label class="label" for="status">Status</label>
                    <select class="select" id="status" name="status" style="width: 190px;">
                        <option value="">Semua status</option>
                        <?php foreach ($statusList as $s): ?>
                            <option value="<?= e($s) ?>" <?= $status === $s ? 'selected' : '' ?>><?= e(status_label($s)) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="field" style="margin: 0;">
                    <label class="label" for="dari">Dari</label>
                    <input class="input" type="date" id="dari" name="dari" value="<?= e($dari) ?>" style="width: 165px;">
                </div>
                <div class="field" style="margin: 0;">
                    <label class="label" for="sampai">Sampai</label>
                    <input class="input" type="date" id="sampai" name="sampai" value="<?= e($sampai) ?>" style="width: 165px;">
                </div>
                <button type="submit" class="btn btn-outline btn-sm">Terapkan</button>
                <?php if ($status !== '' || $dari !== '' || $sampai !== ''): ?>
                    <a class="btn btn-ghost btn-sm" href="<?= e(url('admin/pembayaran')) ?>">Reset</a>
                <?php endif; ?>
            </form>
        </div>
        <div class="qs-panel__body" style="padding: 0;">
            <?php if ($riwayat === []): ?>
                <div class="empty-state">
                    <p class="display" style="font-size: var(--text-lg); margin-bottom: 4px;">Belum ada pembayaran</p>
                    <p class="text-secondary" style="margin: 0 auto; font-size: var(--text-sm); max-width: 54ch;">
                        Tidak ada pembayaran yang cocok dengan filter. Coba perluas rentang tanggal
                        atau pilih status "Semua status".
                    </p>
                </div>
            <?php else: ?>
                <div class="table-wrap" style="border: 0; border-radius: 0;">
                    <table class="table" id="tabel-pembayaran">
                        <thead>
                            <tr>
                                <th>Faktur</th>
                                <th>Pelanggan</th>
                                <th class="num">Jumlah</th>
                                <th>Metode</th>
                                <th>Status</th>
                                <th>Diverifikasi</th>
                                <th>Catatan</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($riwayat as $r): ?>
                                <tr>
                                    <td>
                                        <a class="mono" style="font-weight: 600;"
                                           href="<?= e(route('admin.pesanan.detail', ['id' => (int) $r['booking_id']])) ?>"><?= e($r['invoice_number']) ?></a>
                                        <p class="text-muted" style="margin: 2px 0 0; font-size: var(--text-xs);">
                                            <?= e($r['kode_unit']) ?> · <?= e(tgl_waktu($r['created_at'])) ?>
                                        </p>
                                    </td>
                                    <td>
                                        <p style="margin: 0; font-weight: 600;"><?= e($r['nama_pelanggan']) ?></p>
                                        <a class="text-muted" style="font-size: var(--text-xs);"
                                           href="<?= e(route('admin.pelanggan.detail', ['id' => (int) $r['user_id']])) ?>">Profil pelanggan</a>
                                    </td>
                                    <td class="num mono"><?= e(rupiah($r['jumlah'])) ?></td>
                                    <td>
                                        <p style="margin: 0; font-size: var(--text-sm);"><?= e(status_label($r['metode'])) ?></p>
                                        <?php if (!empty($r['nomor_referensi'])): ?>
                                            <p class="text-muted" style="margin: 2px 0 0; font-size: var(--text-xs);">Ref <?= e($r['nomor_referensi']) ?></p>
                                        <?php endif; ?>
                                    </td>
                                    <td><span class="badge <?= status_class($r['status']) ?>"><?= e(status_label($r['status'])) ?></span></td>
                                    <td>
                                        <p style="margin: 0; font-size: var(--text-sm);"><?= e($r['nama_verifikator'] ?? '-') ?></p>
                                        <p class="text-muted" style="margin: 2px 0 0; font-size: var(--text-xs);"><?= e(tgl_waktu($r['verifikasi_pada'])) ?></p>
                                    </td>
                                    <td style="max-width: 260px;">
                                        <p class="text-secondary" style="margin: 0; font-size: var(--text-xs);"><?= e($r['catatan_admin'] ?? '-') ?></p>
                                        <?php if (!empty($r['bukti_path'])): ?>
                                            <a class="text-cyan" style="font-size: var(--text-xs);" target="_blank" rel="noopener"
                                               href="<?= e(upload_url($r['bukti_path'])) ?>">Lihat bukti</a>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </section>

    <p class="text-secondary" style="margin: 0; font-size: var(--text-sm); max-width: 78ch;">
        Riwayat menampilkan maksimal 200 pembayaran terbaru sesuai filter. Menerima pembayaran otomatis
        mengubah status bayar pesanan menjadi lunas, sedangkan penolakan membuka kesempatan pelanggan
        mengunggah bukti ulang dari halaman riwayat mereka.
    </p>
</div>

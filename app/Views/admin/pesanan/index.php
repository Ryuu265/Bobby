<?php
/**
 * admin/pesanan/index.php — Manajemen pesanan: tab status, filter, tabel, aksi cepat.
 * Jumlah pada tiap tab dihitung model sebelum dua penutup otomatis dijalankan
 * (lihat BookingController::index), jadi angka selalu segar.
 */

use App\Core\View;

View::set('title', 'Manajemen Pesanan — ' . config('app.name'));
View::set('page_title', 'Manajemen Pesanan');
View::set('page_subtitle', 'Konfirmasi jadwal, mulai/selesaikan sewa, dan pantau pembayaran.');


$pesanan   = is_array($pesanan ?? null) ? $pesanan : [];
$baris     = $pesanan['rows'] ?? [];
$total     = (int) ($pesanan['total'] ?? 0);
$halaman   = max(1, (int) ($pesanan['halaman'] ?? 1));
$terakhir  = max(1, (int) ($pesanan['terakhir'] ?? 1));
$hitungan  = $hitungan ?? [];
$totalSemua = (int) ($totalSemua ?? 0);
$filter    = is_array($filter ?? null) ? $filter : [];
$status    = (string) ($status ?? '');
$tipe      = (string) ($tipe ?? '');
$tanggal   = (string) ($tanggal ?? '');
$cari      = (string) ($cari ?? '');
$daftarStatus = $daftarStatus ?? [];
$daftarTipe   = $daftarTipe ?? [];
$hariIni   = (string) ($hariIni ?? date('Y-m-d'));

/** URL daftar pesanan dengan filter aktif (tab & pagination memakai ini). */
$tautan = static function (array $tambah) use ($filter, $status): string {
    $params = array_filter($tambah + $filter + ['status' => $status], static fn ($v): bool => (string) $v !== '');
    $qs = http_build_query($params);
    return url('admin/pesanan') . ($qs !== '' ? '?' . $qs : '');
};

$tab = array_merge(
    [['', 'Semua', $totalSemua]],
    array_map(
        static fn (string $s): array => [$s, status_label($s), (int) ($hitungan[$s] ?? 0)],
        $daftarStatus
    )
);
?>
<!-- ============ TAB STATUS ============ -->
<div class="flex flex-wrap gap-2" style="margin-bottom: var(--sp-5);">
    <?php foreach ($tab as [$nilai, $label, $jumlah]): ?>
        <a href="<?= e($tautan(['status' => $nilai])) ?>"
           class="btn btn-sm <?= $status === $nilai ? 'btn-primary' : 'btn-outline' ?>">
            <?= e($label) ?>
            <span class="mono" style="opacity: .75; margin-left: 4px;"><?= $jumlah ?></span>
        </a>
    <?php endforeach; ?>
</div>

<!-- ============ TABEL PESANAN ============ -->
<div class="qs-panel">
    <div class="qs-panel__head" style="flex-wrap: wrap; row-gap: var(--sp-3);">
        <div>
            <h2 class="display" style="margin: 0; font-size: var(--text-lg);">Daftar Pesanan</h2>
            <p class="text-muted" style="margin: 2px 0 0; font-size: var(--text-xs);">
                <?= $total ?> pesanan<?= $status !== '' ? ' berstatus ' . e(status_label($status)) : '' ?>
                <?= $tanggal !== '' ? ' pada ' . e(tgl_indo($tanggal)) : '' ?>
                <?= $tipe !== '' ? ' tipe ' . e($tipe) : '' ?>
                <?= $cari !== '' ? ' kata kunci "' . e($cari) . '"' : '' ?>
            </p>
        </div>
        <a href="<?= url('admin/pembayaran') ?>" class="btn btn-outline btn-sm">Verifikasi Pembayaran</a>
    </div>

    <div class="qs-panel__body">
        <form method="get" action="<?= url('admin/pesanan') ?>"
              class="grid gap-3 grid-cols-1 sm:grid-cols-2 lg:grid-cols-5" style="align-items: end; margin-bottom: var(--sp-4);">
            <?php if ($status !== ''): ?>
                <input type="hidden" name="status" value="<?= e($status) ?>">
            <?php endif; ?>
            <div>
                <label class="label" for="f-cari" style="margin: 0 0 2px; font-size: var(--text-xs);">Cari</label>
                <input class="input" id="f-cari" type="search" name="cari" maxlength="60"
                       placeholder="Invoice, nama, HP, kode unit" value="<?= e($cari) ?>"
                       style="padding: 0.45rem 0.7rem; font-size: var(--text-xs);">
            </div>
            <div>
                <label class="label" for="f-tanggal" style="margin: 0 0 2px; font-size: var(--text-xs);">Tanggal sewa</label>
                <input class="input" id="f-tanggal" type="date" name="tanggal" value="<?= e($tanggal) ?>"
                       style="padding: 0.45rem 0.7rem; font-size: var(--text-xs);">
            </div>
            <div>
                <label class="label" for="f-tipe" style="margin: 0 0 2px; font-size: var(--text-xs);">Tipe unit</label>
                <select class="select" id="f-tipe" name="tipe"
                        style="padding: 0.45rem 2.1rem 0.45rem 0.7rem; font-size: var(--text-xs);">
                    <option value="">Semua tipe</option>
                    <?php foreach ($daftarTipe as $t): ?>
                        <option value="<?= e($t) ?>" <?= $tipe === $t ? 'selected' : '' ?>><?= e($t) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="flex flex-wrap items-center gap-2">
                <button type="submit" class="btn btn-primary btn-sm">Terapkan</button>
                <?php if ($status !== '' || $tanggal !== '' || $tipe !== '' || $cari !== ''): ?>
                    <a href="<?= url('admin/pesanan') ?>" class="btn btn-ghost btn-sm">Reset</a>
                <?php endif; ?>
            </div>
        </form>

        <label class="sr-only" for="saring-pesanan">Saring baris tabel</label>
        <input id="saring-pesanan" class="input" type="search" placeholder="Saring cepat baris di halaman ini…"
               data-table-filter="#tabel-pesanan" style="max-width: 320px; margin-bottom: var(--sp-4);">

        <?php if ($baris === []): ?>
            <div class="empty-state">
                <p class="display" style="font-size: var(--text-xl); margin: 0 0 var(--sp-2);">Belum ada pesanan</p>
                <p class="text-secondary" style="margin: 0;">
                    <?php if ($status !== '' || $tanggal !== '' || $tipe !== '' || $cari !== ''): ?>
                        Tidak ada pesanan yang cocok dengan filter ini. Coba reset filter untuk melihat semua.
                    <?php else: ?>
                        Belum ada satu pun booking masuk. Pesanan pelanggan akan tampil di sini secara otomatis.
                    <?php endif; ?>
                </p>
            </div>
        <?php else: ?>
            <div class="table-wrap">
                <table class="table" id="tabel-pesanan">
                    <thead>
                        <tr>
                            <th scope="col">Invoice</th>
                            <th scope="col">Pelanggan</th>
                            <th scope="col">Unit</th>
                            <th scope="col">Jadwal sewa</th>
                            <th scope="col" class="num">Total</th>
                            <th scope="col">Status pesanan</th>
                            <th scope="col">Status bayar</th>
                            <th scope="col">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($baris as $b): ?>
                            <?php
                            $id = (int) $b['id'];
                            $sudahLewat = (string) $b['booking_date'] < $hariIni;
                            ?>
                            <tr>
                                <td>
                                    <a class="mono" style="font-weight: 600;" href="<?= e(route('admin.pesanan.detail', ['id' => $id])) ?>">
                                        <?= e($b['invoice_number']) ?>
                                    </a>
                                    <?php if ((int) ($b['controller_tambahan'] ?? 0) > 0): ?>
                                        <p class="text-muted" style="margin: 2px 0 0; font-size: var(--text-xs);">
                                            +<?= (int) $b['controller_tambahan'] ?> controller tambahan
                                        </p>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span style="font-weight: 600;"><?= e($b['nama_pelanggan']) ?></span>
                                    <p class="text-muted mono" style="margin: 2px 0 0; font-size: var(--text-xs);">
                                        <?= e($b['telepon']) ?>
                                    </p>
                                </td>
                                <td>
                                    <span class="mono" style="font-weight: 600;"><?= e($b['kode_unit']) ?></span>
                                    <p class="text-secondary" style="margin: 2px 0 0; font-size: var(--text-xs);">
                                        <?= e($b['tipe']) ?> &middot; <?= e($b['nama_unit']) ?>
                                    </p>
                                </td>
                                <td>
                                    <span <?= $sudahLewat ? 'class="text-muted"' : '' ?>><?= e(tgl_indo((string) $b['booking_date'])) ?></span>
                                    <p class="mono" style="margin: 2px 0 0; font-size: var(--text-xs);">
                                        <?= e(jam((string) $b['start_time'])) ?> &ndash; <?= e(jam((string) $b['end_time'])) ?>
                                        <span class="text-muted"><?= (int) $b['durasi_jam'] ?> jam</span>
                                    </p>
                                </td>
                                <td class="num"><?= rupiah((float) $b['total_harga']) ?></td>
                                <td>
                                    <span class="badge <?= e(status_class((string) $b['status_pesanan'])) ?>">
                                        <?= e(status_label((string) $b['status_pesanan'])) ?>
                                    </span>
                                    <?php if (!empty($b['alasan_batal'])): ?>
                                        <p class="text-muted" style="margin: 4px 0 0; font-size: var(--text-xs);">
                                            <?= e(mb_strimwidth((string) $b['alasan_batal'], 0, 42, '…')) ?>
                                        </p>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="badge <?= e(status_class((string) $b['status_bayar'])) ?>">
                                        <?= e(status_label((string) $b['status_bayar'])) ?>
                                    </span>
                                    <p class="text-muted" style="margin: 4px 0 0; font-size: var(--text-xs);">
                                        <?= e(status_label((string) $b['metode_bayar'])) ?>
                                    </p>
                                    <?php if ((string) ($b['status_bukti'] ?? '') === 'menunggu_verifikasi' && !empty($b['payment_id'])): ?>
                                        <a href="<?= e(url('admin/pembayaran')) ?>" class="text-warning"
                                           style="font-size: var(--text-xs); font-weight: 600;">
                                            perlu verifikasi
                                        </a>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div class="flex flex-col gap-2">
                                        <a href="<?= e(route('admin.pesanan.detail', ['id' => $id])) ?>"
                                           class="btn btn-ghost btn-sm">Detail</a>
                                        <?= View::include('admin/_partial/aksi-pesanan', ['b' => $b, 'rapat' => true]) ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <?= View::include('partials/pagination', [
                'halaman'  => $halaman,
                'terakhir' => $terakhir,
                'total'    => $total,
                'label'    => 'pesanan',
                'base'     => url('admin/pesanan'),
                'query'    => array_filter(
                    $filter + ['status' => $status],
                    static fn ($v): bool => (string) $v !== ''
                ),
            ]); ?>
        <?php endif; ?>
    </div>
</div>

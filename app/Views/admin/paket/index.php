<?php
/**
 * admin/paket/index.php — Daftar paket & promo beserta keadaan berlakunya.
 */

use App\Core\View;

View::set('title', 'Paket & Promo — ' . config('app.name'));
View::set('page_title', 'Paket & Promo');
View::set('page_subtitle', 'Potongan harga otomatis yang bisa dipilih pelanggan saat memesan.');


$paket = $paket ?? [];

$keadaanLabel = [
    'berlaku'      => ['Berlaku', 'badge-success'],
    'belum_mulai'  => ['Belum Mulai', 'badge-info'],
    'kedaluwarsa'  => ['Kedaluwarsa', 'badge-warning'],
    'nonaktif'     => ['Nonaktif', 'badge-neutral'],
];

$pendek = static function (?string $tanggal): string {
    if (!$tanggal) {
        return 'tidak dibatasi';
    }
    $bulan = [1 => 'Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'];
    $ts = strtotime($tanggal);
    if ($ts === false) {
        return (string) $tanggal;
    }
    return date('j', $ts) . ' ' . $bulan[(int) date('n', $ts)] . ' ' . date('Y', $ts);
};
?>
<!-- ============ RINGKASAN ============ -->
<div class="grid gap-3 grid-cols-2 lg:grid-cols-4" style="margin-bottom: var(--sp-5);">
    <?php foreach ([
        ['Total Paket', (int) ($total ?? 0), 'termasuk yang nonaktif'],
        ['Sedang Berlaku', (int) ($berlaku ?? 0), 'aktif & dalam rentang tanggal'],
        ['Kedaluwarsa', (int) ($kedaluwarsa ?? 0), 'melewat berlaku_akhir'],
        ['Nonaktif', (int) ($nonaktif ?? 0), 'tidak muncul di form booking'],
    ] as [$label, $nilai, $hint]): ?>
        <div class="stat">
            <p class="stat__label"><?= e($label) ?></p>
            <p class="stat__value"><?= $nilai ?></p>
            <p class="stat__hint"><?= e($hint) ?></p>
        </div>
    <?php endforeach; ?>
</div>

<!-- ============ TABEL PAKET ============ -->
<div class="qs-panel">
    <div class="qs-panel__head">
        <div>
            <h2 class="display" style="margin: 0; font-size: var(--text-lg);">Daftar Paket</h2>
            <p class="text-muted" style="margin: 2px 0 0; font-size: var(--text-xs);">
                Estimasi hemat dihitung dari tarif unit termurah yang non-maintenance.
            </p>
        </div>
        <a href="<?= url('admin/paket/baru') ?>" class="btn btn-primary btn-sm">+ Tambah Paket</a>
    </div>

    <div class="qs-panel__body">
        <label class="sr-only" for="saring-paket">Saring baris tabel</label>
        <input id="saring-paket" class="input" type="search" placeholder="Saring cepat baris di halaman ini…"
               data-table-filter="#tabel-paket" style="max-width: 320px; margin-bottom: var(--sp-4);">

        <?php if ($paket === []): ?>
            <div class="empty-state">
                <p class="display" style="font-size: var(--text-xl); margin: 0 0 var(--sp-2);">Belum ada paket</p>
                <p class="text-secondary" style="margin: 0 0 var(--sp-4);">
                    Buat paket hemat 3 jam atau promo akhir pekan agar pelanggan punya alasan main lebih lama.
                </p>
                <a href="<?= url('admin/paket/baru') ?>" class="btn btn-primary btn-sm">Tambah Paket</a>
            </div>
        <?php else: ?>
            <div class="table-wrap">
                <table class="table" id="tabel-paket">
                    <thead>
                        <tr>
                            <th scope="col">Paket</th>
                            <th scope="col" class="num">Durasi</th>
                            <th scope="col">Skema harga</th>
                            <th scope="col" class="num">Harga paket</th>
                            <th scope="col">Periode berlaku</th>
                            <th scope="col">Keadaan</th>
                            <th scope="col" class="num">Estimasi hemat</th>
                            <th scope="col">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($paket as $p): ?>
                            <?php $id = (int) $p['id']; $keadaan = (string) ($p['keadaan'] ?? 'nonaktif'); [$label, $kelas] = $keadaanLabel[$keadaan] ?? ['Nonaktif', 'badge-neutral']; ?>
                            <tr>
                                <td>
                                    <span style="font-weight: 600;"><?= e($p['nama']) ?></span>
                                    <?php if (!empty($p['deskripsi'])): ?>
                                        <p class="text-muted" style="margin: 2px 0 0; font-size: var(--text-xs);">
                                            <?= e(mb_strimwidth((string) $p['deskripsi'], 0, 58, '…')) ?>
                                        </p>
                                    <?php endif; ?>
                                </td>
                                <td class="num"><span class="mono"><?= (int) $p['durasi_jam'] ?> jam</span></td>
                                <td>
                                    <span class="text-secondary" style="font-size: var(--text-xs);">
                                        <?= e(status_label((string) $p['tipe_diskon'])) ?>
                                    </span>
                                    <div style="font-weight: 600;">
                                        <?php if ((float) $p['harga'] > 0): ?>
                                            harga tetap
                                        <?php elseif ($p['tipe_diskon'] === 'persen'): ?>
                                            <?= e(rtrim(rtrim(number_format((float) $p['diskon_persen'], 2, '.', ''), '0'), '.')) ?>% off
                                        <?php else: ?>
                                            potong <?= rupiah((float) $p['diskon_nilai']) ?>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                <td class="num">
                                    <?= (float) $p['harga'] > 0 ? rupiah((float) $p['harga']) : '<span class="text-muted">hitung otomatis</span>' ?>
                                </td>
                                <td class="text-secondary" style="font-size: var(--text-xs);">
                                    <?= e($pendek($p['berlaku_mulai'])) ?>
                                    <span class="text-muted"> &rarr; </span>
                                    <?= e($pendek($p['berlaku_akhir'])) ?>
                                </td>
                                <td>
                                    <span class="badge <?= e($kelas) ?>"><?= e($label) ?></span>
                                    <?php if ($keadaan === 'kedaluwarsa' || $keadaan === 'belum_mulai'): ?>
                                        <p class="text-muted" style="margin: 4px 0 0; font-size: var(--text-xs);">
                                            status kolom: <?= e(status_label((string) $p['status'])) ?>
                                        </p>
                                    <?php endif; ?>
                                </td>
                                <td class="num">
                                    <?php if ($p['estimasi_hemat'] !== null && (float) $p['estimasi_hemat'] > 0): ?>
                                        <span class="text-success" style="font-weight: 600;">
                                            <?= rupiah((float) $p['estimasi_hemat']) ?>
                                        </span>
                                        <p class="text-muted" style="margin: 2px 0 0; font-size: var(--text-xs);">
                                            bayar <?= rupiah((float) $p['estimasi_harga']) ?>
                                        </p>
                                    <?php elseif ($p['estimasi_hemat'] !== null): ?>
                                        <span class="text-muted">tidak hemat</span>
                                    <?php else: ?>
                                        <span class="text-muted">&mdash;</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div class="flex flex-wrap items-center gap-2">
                                        <a href="<?= route('admin.paket.ubah', ['id' => $id]) ?>" class="btn btn-outline btn-sm">Ubah</a>
                                        <form method="post" action="<?= url('admin/paket/' . $id . '/hapus') ?>"
                                              data-confirm="Hapus paket <?= e($p['nama']) ?>? Pesanan lama tetap ada tetapi tidak lagi memakai paket ini.">
                                            <?= csrf_field() ?>
                                            <button type="submit" class="btn btn-danger btn-sm">Hapus</button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <p class="text-muted" style="margin: var(--sp-4) 0 0; font-size: var(--text-xs);">
                Paket dengan harga tetap menggantikan hitungan diskon; diskon hanya dipakai saat harga paket 0.
            </p>
        <?php endif; ?>
    </div>
</div>

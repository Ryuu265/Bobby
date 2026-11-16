<?php
/**
 * admin/laporan/aktivitas.php — Jejak aktivitas (audit trail).
 * Satu baris = satu kejadian: login, ubah data, verifikasi pembayaran, dll.
 */

use App\Core\View;

View::set('title', 'Jejak Aktivitas — ' . config('app.name'));
View::set('page_title', 'Jejak Aktivitas');
View::set('page_subtitle', $total . ' kejadian tercatat · 25 baris per halaman');

/** Tautan filter modul tanpa membuang halaman aktif. */
$tanya = static function (string $path, array $params): string {
    $qs = http_build_query(array_filter($params, static fn ($v): bool => $v !== ''));
    return $path . ($qs !== '' ? '?' . $qs : '');
};

$base = route('admin.laporan.aktivitas');

// Pengelompokan modul agar daftar filter tidak terlalu panjang
$grupModul = [
    'Sistem'   => ['auth'],
    'Operasional' => ['booking', 'payment', 'unit', 'game', 'paket'],
    'Akun'     => ['user'],
];
$labelModul = [
    'auth'    => 'Autentikasi',
    'booking' => 'Pesanan',
    'payment' => 'Pembayaran',
    'unit'    => 'Unit',
    'game'    => 'Game',
    'paket'   => 'Paket',
    'user'    => 'Akun',
];
$aksiClass = static function (string $aksi): string {
    return match (true) {
        str_contains($aksi, 'hapus')  => 'badge-danger',
        str_contains($aksi, 'tolak')  => 'badge-danger',
        str_contains($aksi, 'buat')   => 'badge-success',
        str_contains($aksi, 'verifikasi') => 'badge-success',
        str_contains($aksi, 'selesai')    => 'badge-success',
        str_contains($aksi, 'ubah')   => 'badge-info',
        str_contains($aksi, 'login')  => 'badge-violet',
        default                      => 'badge-neutral',
    };
};
?>
<div class="grid gap-5">

    <!-- ============ FILTER MODUL ============ -->
    <section class="qs-panel">
        <div class="qs-panel__head flex-wrap gap-3">
            <h2 class="display" style="font-size: var(--text-lg); margin: 0;">Filter</h2>
            <div class="flex items-center gap-2" style="margin-left: auto;">
                <label class="text-muted" for="cari-aktivitas" style="font-size: var(--text-xs);">Cari deskripsi</label>
                <input class="input" type="search" id="cari-aktivitas" data-table-filter="#tabel-aktivitas"
                       placeholder="PS5-01" style="width: 190px;">
            </div>
        </div>
        <div class="qs-panel__body">
            <div class="grid gap-4 md:grid-cols-3">
                <?php foreach ($grupModul as $judul => $anggota): ?>
                    <?php $anggota = array_values(array_intersect($anggota, $modulList)); ?>
                    <?php if ($anggota === []) {
                        continue;
                    } ?>
                    <div>
                        <p class="text-muted" style="margin: 0 0 var(--sp-2); font-size: var(--text-xs); text-transform: uppercase; letter-spacing: 0.06em;"><?= e($judul) ?></p>
                        <div class="flex flex-wrap gap-2">
                            <?php foreach ($anggota as $m): ?>
                                <?php $aktif = $modul === $m; ?>
                                <a class="btn btn-sm <?= $aktif ? 'btn-primary' : 'btn-outline' ?>"
                                   href="<?= e($aktif ? $base : $tanya($base, ['modul' => $m])) ?>"><?= e($labelModul[$m] ?? $m) ?></a>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

            <div class="flex flex-wrap items-center gap-2" style="margin-top: var(--sp-4);">
                <a class="btn btn-sm <?= $modul === '' ? 'btn-primary' : 'btn-ghost' ?>" href="<?= e($base) ?>">Semua modul</a>
                <span class="text-muted" style="font-size: var(--text-xs);">
                    Menampilkan <?= count($baris) ?> dari <?= $total ?> kejadian
                    <?= $modul !== '' ? '· filter: ' . e($labelModul[$modul] ?? $modul) : '' ?>
                </span>
                <a class="btn btn-outline btn-sm" style="margin-left: auto;" href="<?= e(route('admin.laporan')) ?>">Kembali ke laporan</a>
            </div>
        </div>
    </section>

    <!-- ============ TABEL KEJADIAN ============ -->
    <section class="qs-panel">
        <div class="qs-panel__head">
            <h2 class="display" style="font-size: var(--text-lg); margin: 0;">Log Kejadian</h2>
            <span class="text-muted" style="font-size: var(--text-xs);">Waktu server · WIB</span>
        </div>
        <div class="qs-panel__body" style="padding: 0;">
            <?php if ($baris === []): ?>
                <div class="empty-state">
                    <p class="display" style="font-size: var(--text-lg); margin-bottom: 4px;">Belum ada aktivitas</p>
                    <p class="text-secondary" style="margin: 0 auto; font-size: var(--text-sm); max-width: 56ch;">
                        Belum ada kejadian tercatat untuk filter ini. Semua aksi login, perubahan data,
                        dan verifikasi pembayaran akan muncul di sini secara otomatis.
                    </p>
                </div>
            <?php else: ?>
                <div class="table-wrap" style="border: 0; border-radius: 0;">
                    <table class="table" id="tabel-aktivitas">
                        <thead>
                            <tr>
                                <th>Waktu</th>
                                <th>Pelaku</th>
                                <th>Modul</th>
                                <th>Aksi</th>
                                <th>Deskripsi</th>
                                <th>IP</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($baris as $log): ?>
                                <?php
                                // nilai_lama / nilai_baru disimpan sebagai JSON
                                $lama = json_decode((string) ($log['nilai_lama'] ?? ''), true);
                                $baru = json_decode((string) ($log['nilai_baru'] ?? ''), true);
                                $ubah = is_array($lama) && is_array($baru);
                                ?>
                                <tr>
                                    <td style="white-space: nowrap;">
                                        <p class="mono" style="margin: 0; font-size: var(--text-sm);"><?= e(tgl_waktu($log['created_at'])) ?></p>
                                        <p class="text-muted" style="margin: 2px 0 0; font-size: var(--text-xs);">#<?= (int) $log['id'] ?></p>
                                    </td>
                                    <td>
                                        <p style="margin: 0; font-weight: 600;"><?= e($log['nama'] ?? 'Sistem') ?></p>
                                        <?php if (!empty($log['user_id'])): ?>
                                            <a class="text-muted" style="font-size: var(--text-xs);"
                                               href="<?= e(route('admin.pelanggan.detail', ['id' => (int) $log['user_id']])) ?>">Lihat akun</a>
                                        <?php endif; ?>
                                    </td>
                                    <td><span class="badge badge-neutral badge-no-dot"><?= e($labelModul[$log['modul']] ?? $log['modul']) ?></span></td>
                                    <td><span class="badge <?= $aksiClass((string) $log['aksi']) ?>"><?= e($log['aksi']) ?></span></td>
                                    <td style="min-width: 260px;">
                                        <p style="margin: 0;"><?= e($log['deskripsi']) ?></p>
                                        <?php if ($log['entitas'] !== '' && $log['entitas_id'] !== null): ?>
                                            <p class="text-muted" style="margin: 2px 0 0; font-size: var(--text-xs);">
                                                <?= e($log['entitas']) ?> #<?= (int) $log['entitas_id'] ?>
                                            </p>
                                        <?php endif; ?>
                                        <?php if ($ubah): ?>
                                            <details style="margin-top: 4px;">
                                                <summary class="text-muted" style="cursor: pointer; font-size: var(--text-xs);">Lihat perubahan</summary>
                                                <div class="table-wrap" style="margin-top: 6px;">
                                                    <table class="table">
                                                        <thead>
                                                            <tr><th>Kolom</th><th>Sebelum</th><th>Sesudah</th></tr>
                                                        </thead>
                                                        <tbody>
                                                            <?php foreach ($baru as $kolom => $nilai): ?>
                                                                <?php $lamaNilai = $lama[$kolom] ?? null; ?>
                                                                <?php if ((string) $lamaNilai === (string) $nilai) {
                                                                    continue;
                                                                } ?>
                                                                <tr>
                                                                    <td class="text-muted"><?= e($kolom) ?></td>
                                                                    <td><?= $lamaNilai === null ? '<span class="text-muted">-</span>' : e((string) $lamaNilai) ?></td>
                                                                    <td class="text-cyan"><?= e((string) $nilai) ?></td>
                                                                </tr>
                                                            <?php endforeach; ?>
                                                        </tbody>
                                                    </table>
                                                </div>
                                            </details>
                                        <?php endif; ?>
                                    </td>
                                    <td class="mono text-muted" style="font-size: var(--text-xs);"><?= e($log['ip_address'] ?? '-') ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </section>

    <?= View::include('partials/pagination', [
        'halaman'  => $halaman,
        'terakhir' => $terakhir,
        'total'    => $total,
        'label'    => 'kejadian',
        'base'     => $base,
        'query'    => $modul === '' ? [] : ['modul' => $modul],
    ]) ?>

    <p class="text-secondary" style="margin: 0; font-size: var(--text-sm); max-width: 78ch;">
        Log bersifat permanen dan hanya bisa dibaca — tidak ada tombol hapus di halaman ini.
        Gunakan tautan "Lihat akun" untuk memeriksa riwayat pelanggan yang melakukan aksi,
        dan filter modul pembayaran bila perlu menelusuri bukti transfer yang sudah diverifikasi.
    </p>
</div>

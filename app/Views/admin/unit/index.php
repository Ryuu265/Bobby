<?php
/**
 * admin/unit/index.php — Tabel master data unit PlayStation.
 * Kolom status menampilkan hasil hitung ulang real-time (Schedule::statusUnit),
 * sedangkan select di kolom Aksi menulis ulang kolom `units.status`.
 */

use App\Core\View;

View::set('title', 'Kelola Unit — ' . config('app.name'));
View::set('page_title', 'Kelola Unit PlayStation');
View::set('page_subtitle', 'Tarif per jam, kapasitas, dan status operasional tiap konsol.');


$units = $units ?? [];
$statistik = $statistik ?? ['total' => 0, 'tersedia' => 0, 'dipakai' => 0, 'dipesan' => 0, 'maintenance' => 0];
$kartu = [
    ['Total unit', $statistik['total'], 'badge-neutral'],
    ['Tersedia', $statistik['tersedia'], 'badge-success'],
    ['Dipakai', $statistik['dipakai'], 'badge-info'],
    ['Dipesan', $statistik['dipesan'], 'badge-violet'],
    ['Maintenance', $statistik['maintenance'], 'badge-warning'],
];
?>
<!-- ============ RINGKASAN ============ -->
<div class="grid gap-3 grid-cols-2 lg:grid-cols-5" style="margin-bottom: var(--sp-5);">
    <?php foreach ($kartu as [$label, $nilai, $warna]): ?>
        <div class="stat">
            <p class="stat__label"><?= e($label) ?></p>
            <p class="stat__value"><?= (int) $nilai ?></p>
            <span class="badge <?= e($warna) ?> badge-no-dot" style="margin-top: var(--sp-2);">unit</span>
        </div>
    <?php endforeach; ?>
</div>

<!-- ============ TABEL UNIT ============ -->
<div class="qs-panel">
    <div class="qs-panel__head" style="flex-wrap: wrap; row-gap: var(--sp-3);">
        <div>
            <h2 class="display" style="margin: 0; font-size: var(--text-lg);">Daftar Unit</h2>
            <p class="text-muted" style="margin: 2px 0 0; font-size: var(--text-xs);">
                <?= count($units) ?> unit<?= $tipe !== '' ? ' tipe ' . e($tipe) : '' ?><?= $status !== '' ? ' status ' . e(status_label($status)) : '' ?>
            </p>
        </div>

        <div class="flex flex-wrap items-center gap-2">
            <form method="get" action="<?= url('admin/unit') ?>" class="flex flex-wrap items-end gap-2">
                <div>
                    <label class="label" for="f-tipe" style="margin: 0 0 2px; font-size: var(--text-xs);">Tipe</label>
                    <select class="select" id="f-tipe" name="tipe" style="width: auto; padding: 0.45rem 2.1rem 0.45rem 0.7rem; font-size: var(--text-xs);">
                        <option value="">Semua tipe</option>
                        <?php foreach ($daftarTipe as $t): ?>
                            <option value="<?= e($t) ?>" <?= $tipe === $t ? 'selected' : '' ?>><?= e($t) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="label" for="f-status" style="margin: 0 0 2px; font-size: var(--text-xs);">Status</label>
                    <select class="select" id="f-status" name="status" style="width: auto; padding: 0.45rem 2.1rem 0.45rem 0.7rem; font-size: var(--text-xs);">
                        <option value="">Semua status</option>
                        <?php foreach ($daftarStatus as $s): ?>
                            <option value="<?= e($s) ?>" <?= $status === $s ? 'selected' : '' ?>><?= e(status_label($s)) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <button type="submit" class="btn btn-outline btn-sm">Terapkan</button>
            </form>
            <a href="<?= url('admin/unit/baru') ?>" class="btn btn-primary btn-sm">+ Tambah Unit</a>
        </div>
    </div>

    <div class="qs-panel__body">
        <label class="sr-only" for="saring-unit">Saring baris tabel</label>
        <input id="saring-unit" class="input" type="search" placeholder="Saring cepat baris di halaman ini…"
               data-table-filter="#tabel-unit" style="max-width: 320px; margin-bottom: var(--sp-4);">

        <?php if ($units === []): ?>
            <div class="empty-state">
                <p class="display" style="font-size: var(--text-xl); margin: 0 0 var(--sp-2);">Belum ada unit</p>
                <p class="text-secondary" style="margin: 0 0 var(--sp-4);">
                    Tambahkan konsol atau ruangan pertama agar pelanggan bisa mulai memesan.
                </p>
                <a href="<?= url('admin/unit/baru') ?>" class="btn btn-primary btn-sm">Tambah Unit</a>
            </div>
        <?php else: ?>
            <div class="table-wrap">
                <table class="table" id="tabel-unit">
                    <thead>
                        <tr>
                            <th scope="col">Kode</th>
                            <th scope="col">Nama unit</th>
                            <th scope="col">Tipe</th>
                            <th scope="col">Kategori</th>
                            <th scope="col" class="num">Harga / jam</th>
                            <th scope="col" class="num">Kapasitas</th>
                            <th scope="col" class="num">Game</th>
                            <th scope="col">Status saat ini</th>
                            <th scope="col">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($units as $u): ?>
                            <?php $id = (int) $u['id']; $realtime = (string) ($u['status_realtime'] ?? $u['status']); ?>
                            <tr>
                                <td><span class="mono" style="font-weight: 600;"><?= e($u['kode_unit']) ?></span></td>
                                <td>
                                    <span style="font-weight: 600;"><?= e($u['nama']) ?></span>
                                    <?php if (!empty($u['deskripsi'])): ?>
                                        <p class="text-muted" style="margin: 2px 0 0; font-size: var(--text-xs);">
                                            <?= e(mb_strimwidth((string) $u['deskripsi'], 0, 60, '…')) ?>
                                        </p>
                                    <?php endif; ?>
                                </td>
                                <td><span class="badge badge-neutral badge-no-dot"><?= e($u['tipe']) ?></span></td>
                                <td class="text-secondary"><?= e(status_label($u['kategori'])) ?></td>
                                <td class="num"><?= rupiah((float) $u['harga_per_jam']) ?></td>
                                <td class="num"><?= (int) $u['kapasitas'] ?> main</td>
                                <td class="num"><?= (int) $u['jumlah_game'] ?></td>
                                <td>
                                    <div class="flex flex-wrap items-center gap-2">
                                        <span class="badge <?= e(status_class($realtime)) ?>"><?= e(status_label($realtime)) ?></span>
                                        <?php if ($realtime !== (string) $u['status']): ?>
                                            <span class="text-muted" style="font-size: var(--text-xs);">
                                                (kolom: <?= e(status_label((string) $u['status'])) ?>)
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                    <form method="post" action="<?= url('admin/unit/' . $id . '/status') ?>"
                                          class="flex items-center gap-1" style="margin-top: var(--sp-2);">
                                        <?= csrf_field() ?>
                                        <label class="sr-only" for="st-<?= $id ?>">Ubah status <?= e($u['kode_unit']) ?></label>
                                        <select class="select" id="st-<?= $id ?>" name="status"
                                                style="width: auto; padding: 0.4rem 1.9rem 0.4rem 0.6rem; font-size: var(--text-xs);">
                                            <?php foreach ($daftarStatus as $s): ?>
                                                <option value="<?= e($s) ?>" <?= (string) $u['status'] === $s ? 'selected' : '' ?>>
                                                    <?= e(status_label($s)) ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                        <button type="submit" class="btn btn-ghost btn-sm">Ubah</button>
                                    </form>
                                </td>
                                <td>
                                    <div class="flex flex-wrap items-center gap-2">
                                        <a href="<?= route('admin.unit.ubah', ['id' => $id]) ?>" class="btn btn-outline btn-sm">Ubah</a>
                                        <form method="post" action="<?= url('admin/unit/' . $id . '/hapus') ?>"
                                              data-confirm="Hapus unit <?= e($u['kode_unit']) ?> secara permanen? Unit dengan riwayat pesanan tidak dapat dihapus.">
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
                Unit yang pernah dipakai pesanan tidak dapat dihapus — ubah statusnya menjadi
                <strong>Maintenance</strong> agar terkunci dari pemesanan.
            </p>
        <?php endif; ?>
    </div>
</div>

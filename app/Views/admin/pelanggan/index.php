<?php
/**
 * admin/pelanggan/index.php — Daftar akun pelanggan + ringkasan transaksi.
 * Data dari Admin\CustomerController::index().
 */

use App\Core\View;

View::set('title', 'Manajemen Pelanggan — ' . config('app.name'));
View::set('page_title', 'Manajemen Pelanggan');
View::set('page_subtitle', $jumlah['terfilter'] . ' akun cocok · ' . $jumlah['total'] . ' pelanggan terdaftar');


$rows  = $pelanggan['rows'];
$base  = url('admin/pelanggan');

// Query filter dipertahankan pada tautan pagination
$filter = ['cari' => $cari, 'status' => $status];
?>
<div class="grid gap-5">

    <!-- ============ RINGKASAN AKUN ============ -->
    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <div class="stat">
            <p class="stat__label">Total Pelanggan</p>
            <p class="stat__value"><?= (int) $jumlah['total'] ?></p>
            <p class="stat__hint">Akun ber-role pelanggan</p>
        </div>
        <div class="stat">
            <p class="stat__label">Sesuai Filter</p>
            <p class="stat__value"><?= (int) $jumlah['terfilter'] ?></p>
            <p class="stat__hint"><?= $cari !== '' ? 'Kata kunci: ' . e($cari) : 'Tanpa kata kunci' ?></p>
        </div>
        <div class="stat">
            <p class="stat__label">Operator</p>
            <p class="stat__value"><?= (int) $jumlah['operator'] ?></p>
            <p class="stat__hint">Bisa memverifikasi pembayaran</p>
        </div>
        <div class="stat">
            <p class="stat__label">Admin</p>
            <p class="stat__value"><?= (int) $jumlah['admin'] ?></p>
            <p class="stat__hint">Akses penuh termasuk pengaturan</p>
        </div>
    </div>

    <!-- ============ FILTER ============ -->
    <section class="qs-panel">
        <div class="qs-panel__head">
            <h2 class="display" style="font-size: var(--text-lg); margin: 0;">Cari Pelanggan</h2>
            <span class="text-muted" style="font-size: var(--text-xs);">Nama, email, atau nomor telepon</span>
        </div>
        <div class="qs-panel__body">
            <form method="get" action="<?= e($base) ?>" class="grid gap-4 md:grid-cols-[1fr_auto_auto] md:items-end">
                <div class="field" style="margin: 0;">
                    <label class="label" for="cari">Kata kunci</label>
                    <input class="input" type="search" id="cari" name="cari" maxlength="60"
                           value="<?= e($cari) ?>" placeholder="Budi / budi@mail.com / 0812">
                </div>
                <div class="field" style="margin: 0;">
                    <label class="label" for="status">Status akun</label>
                    <select class="select" id="status" name="status" style="width: 175px;">
                        <option value="">Semua status</option>
                        <option value="aktif" <?= $status === 'aktif' ? 'selected' : '' ?>>Aktif</option>
                        <option value="nonaktif" <?= $status === 'nonaktif' ? 'selected' : '' ?>>Nonaktif</option>
                    </select>
                </div>
                <div class="flex gap-2">
                    <button type="submit" class="btn btn-primary btn-sm">Cari</button>
                    <?php if ($cari !== '' || $status !== ''): ?>
                        <a class="btn btn-ghost btn-sm" href="<?= e($base) ?>">Reset</a>
                    <?php endif; ?>
                </div>
            </form>
        </div>
    </section>

    <!-- ============ DAFTAR ============ -->
    <section class="qs-panel">
        <div class="qs-panel__head">
            <h2 class="display" style="font-size: var(--text-lg); margin: 0;">Daftar Akun</h2>
            <span class="text-muted" style="font-size: var(--text-xs);">
                Halaman <?= (int) $pelanggan['halaman'] ?> dari <?= (int) $pelanggan['terakhir'] ?>
            </span>
        </div>
        <div class="qs-panel__body" style="padding: 0;">
            <?php if ($rows === []): ?>
                <div class="empty-state">
                    <p class="display" style="font-size: var(--text-lg); margin-bottom: 4px;">Pelanggan tidak ditemukan</p>
                    <p class="text-secondary" style="margin: 0 auto; font-size: var(--text-sm); max-width: 54ch;">
                        Tidak ada akun yang cocok dengan kata kunci atau status terpilih.
                        Kosongkan filter lalu coba lagi.
                    </p>
                </div>
            <?php else: ?>
                <div class="table-wrap" style="border: 0; border-radius: 0;">
                    <table class="table" id="tabel-pelanggan">
                        <thead>
                            <tr>
                                <th>Pelanggan</th>
                                <th>Kontak</th>
                                <th>Status</th>
                                <th class="num">Pesanan</th>
                                <th class="num">Total Belanja</th>
                                <th>Terakhir Sewa</th>
                                <th>Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($rows as $u): ?>
                                <?php
                                $id     = (int) $u['id'];
                                $nonaktif = $u['status'] === 'nonaktif';
                                ?>
                                <tr>
                                    <td style="min-width: 190px;">
                                        <p style="margin: 0; font-weight: 600;">
                                            <a href="<?= e(route('admin.pelanggan.detail', ['id' => $id])) ?>"><?= e($u['nama']) ?></a>
                                        </p>
                                        <p class="text-muted" style="margin: 2px 0 0; font-size: var(--text-xs);">
                                            #<?= $id ?> · bergabung <?= e(tgl_indo($u['created_at'])) ?>
                                        </p>
                                    </td>
                                    <td>
                                        <p style="margin: 0; font-size: var(--text-sm);"><?= e($u['email']) ?></p>
                                        <p class="text-muted" style="margin: 2px 0 0; font-size: var(--text-xs);">
                                            <?= e($u['telepon'] ?: 'Tanpa telepon') ?>
                                        </p>
                                    </td>
                                    <td>
                                        <span class="badge <?= status_class($u['status']) ?>"><?= e(status_label($u['status'])) ?></span>
                                        <?php if (empty($u['last_login_at'])): ?>
                                            <p class="text-muted" style="margin: 2px 0 0; font-size: var(--text-xs);">Belum pernah masuk</p>
                                        <?php else: ?>
                                            <p class="text-muted" style="margin: 2px 0 0; font-size: var(--text-xs);">
                                                Masuk <?= e(tgl_waktu($u['last_login_at'])) ?>
                                            </p>
                                        <?php endif; ?>
                                    </td>
                                    <td class="num mono"><?= (int) $u['jumlah_pesanan'] ?></td>
                                    <td class="num mono text-cyan"><?= e(rupiah($u['total_bayar'])) ?></td>
                                    <td style="white-space: nowrap;">
                                        <?= $u['terakhir_sewa'] ? e(tgl_indo($u['terakhir_sewa'])) : '<span class="text-muted">-</span>' ?>
                                    </td>
                                    <td>
                                        <div class="flex flex-wrap items-center gap-2">
                                            <a class="btn btn-outline btn-sm" href="<?= e(route('admin.pelanggan.detail', ['id' => $id])) ?>">Detail</a>
                                            <form method="post" action="<?= e(url('admin/pelanggan/' . $id . '/status')) ?>"
                                                  data-confirm="<?= $nonaktif
                                                      ? 'Aktifkan kembali akun ' . e($u['nama']) . '?'
                                                      : 'Nonaktifkan akun ' . e($u['nama']) . '? Pelanggan tidak bisa membuat pesanan baru.' ?>">
                                                <?= csrf_field() ?>
                                                <input type="hidden" name="status" value="<?= $nonaktif ? 'aktif' : 'nonaktif' ?>">
                                                <button type="submit" class="btn btn-sm <?= $nonaktif ? 'btn-primary' : 'btn-danger' ?>">
                                                    <?= $nonaktif ? 'Aktifkan' : 'Nonaktifkan' ?>
                                                </button>
                                            </form>
                                        </div>
                                    </td>
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
        'total'    => (int) $pelanggan['total'],
        'label'    => 'pelanggan',
        'base'     => $base,
        'query'    => $filter,
    ]) ?>

    <!-- ============ PANDUAN SINGKAT ============ -->
    <section class="qs-card qs-card--flat" style="padding: var(--sp-5);">
        <h2 class="display" style="font-size: var(--text-base); margin: 0 0 var(--sp-2);">Catatan Keamanan</h2>
        <div class="grid gap-2 text-secondary" style="font-size: var(--text-sm);">
            <p style="margin: 0;">
                Menonaktifkan akun hanya mencabut hak akses masuk dan memesan; seluruh riwayat pesanan
                serta pembayaran tetap tersimpan dan tetap muncul pada laporan.
            </p>
            <p style="margin: 0;">
                Pengaturan ulang password hanya bisa dilakukan pada halaman detail pelanggan, dan sistem
                tidak pernah menampilkan password lama. Sampaikan password baru lewat kanal pribadi.
            </p>
            <p style="margin: 0;">
                Role admin/operator tidak diubah dari halaman ini — gunakan master data akun internal
                agar tidak ada jalur cepat menaikkan hak akses.
            </p>
        </div>
    </section>
</div>

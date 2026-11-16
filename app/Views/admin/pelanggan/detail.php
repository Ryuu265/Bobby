<?php
/**
 * admin/pelanggan/detail.php — Profil satu pelanggan + riwayat pesanan.
 * Data dari Admin\CustomerController::detail().
 */

use App\Core\Auth;
use App\Core\View;

View::set('title', 'Profil Pelanggan — ' . config('app.name'));
View::set('page_title', 'Profil ' . ($pelanggan['nama'] ?? 'Pelanggan'));
View::set('page_subtitle', 'Akun #' . (int) $pelanggan['id'] . ' · ' . ($pelanggan['email'] ?? '-'));


$id        = (int) $pelanggan['id'];
$statusAkun= (string) ($pelanggan['status'] ?? 'aktif');
$nonaktif  = $statusAkun === 'nonaktif';
$diri      = (int) Auth::id() === $id;
$rows      = $riwayat['rows'];
$statusList= $statusList ?? [];
$base      = url('admin/pelanggan/' . $id);
$err       = static fn (string $key): string => isset($errors[$key])
    ? '<span class="field-error">' . e($errors[$key][0]) . '</span>'
    : '';
?>
<div class="grid gap-5">

    <!-- ============ IDENTITAS ============ -->
    <section class="qs-panel">
        <div class="qs-panel__head flex-wrap gap-3">
            <div class="flex flex-wrap items-center gap-3">
                <span class="qs-card qs-card--flat flex items-center justify-center"
                      style="width: 54px; height: 54px; border-radius: var(--radius-full); font-family: var(--font-display); font-size: var(--text-xl);">
                    <?= e(mb_strtoupper(mb_substr($pelanggan['nama'] ?? '?', 0, 1))) ?>
                </span>
                <div>
                    <h2 style="margin: 0; font-size: var(--text-lg);"><?= e($pelanggan['nama']) ?></h2>
                    <p class="text-muted" style="margin: 2px 0 0; font-size: var(--text-xs);">
                        Bergabung <?= e(tgl_indo($pelanggan['created_at'])) ?> ·
                        <?= empty($pelanggan['last_login_at'])
                            ? 'belum pernah masuk'
                            : 'masuk terakhir ' . e(tgl_waktu($pelanggan['last_login_at'])) ?>
                    </p>
                </div>
                <span class="badge <?= status_class($statusAkun) ?>"><?= e(status_label($statusAkun)) ?></span>
            </div>
            <a class="btn btn-ghost btn-sm" style="margin-left: auto;" href="<?= e(url('admin/pelanggan')) ?>">Kembali ke daftar</a>
        </div>
        <div class="qs-panel__body grid gap-4 md:grid-cols-[1fr_auto] md:items-start">
            <dl class="grid gap-4 sm:grid-cols-2" style="margin: 0;">
                <?php foreach ([
                    ['Email', $pelanggan['email'] ?? '-'],
                    ['Telepon', $pelanggan['telepon'] ?: '-'],
                    ['Alamat', $pelanggan['alamat'] ?: 'Belum diisi'],
                    ['Unit favorit', empty($unitFavorit)
                        ? 'Belum ada data'
                        : $unitFavorit['kode_unit'] . ' · ' . $unitFavorit['nama'] . ' (' . (int) $unitFavorit['sering'] . ' pesanan)'],
                ] as [$label, $isi]): ?>
                    <div>
                        <dt class="text-muted" style="font-size: var(--text-xs); text-transform: uppercase; letter-spacing: 0.06em;"><?= e($label) ?></dt>
                        <dd style="margin: 2px 0 0; font-size: var(--text-sm);"><?= e((string) $isi) ?></dd>
                    </div>
                <?php endforeach; ?>
            </dl>

            <!-- Kontrol status akun -->
            <div class="grid gap-2" style="min-width: 210px;">
                <?php if ($diri): ?>
                    <p class="text-warning" style="margin: 0; font-size: var(--text-xs);">
                        Ini akun Anda sendiri — status tidak dapat diubah dari halaman ini.
                    </p>
                <?php else: ?>
                    <form method="post" action="<?= e(url('admin/pelanggan/' . $id . '/status')) ?>"
                          data-confirm="<?= $nonaktif
                              ? 'Aktifkan kembali akun ' . e($pelanggan['nama']) . '?'
                              : 'Nonaktifkan akun ' . e($pelanggan['nama']) . '? Pelanggan tidak dapat memesan slot baru.' ?>">
                        <?= csrf_field() ?>
                        <input type="hidden" name="status" value="<?= $nonaktif ? 'aktif' : 'nonaktif' ?>">
                        <button type="submit" class="btn btn-sm btn-block <?= $nonaktif ? 'btn-primary' : 'btn-danger' ?>">
                            <?= $nonaktif ? 'Aktifkan akun' : 'Nonaktifkan akun' ?>
                        </button>
                    </form>
                    <p class="text-muted" style="margin: 0; font-size: var(--text-xs);">
                        Riwayat pesanan dan pembayaran tetap tersimpan.
                    </p>
                <?php endif; ?>
            </div>
        </div>
    </section>

    <!-- ============ STATISTIK ============ -->
    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <div class="stat">
            <p class="stat__label">Pesanan Sah</p>
            <p class="stat__value"><?= (int) $statistik['jumlah'] ?></p>
            <p class="stat__hint">Dikonfirmasi, berlangsung, atau selesai</p>
        </div>
        <div class="stat">
            <p class="stat__label">Total Belanja</p>
            <p class="stat__value text-cyan"><?= e(rupiah($statistik['omzet'])) ?></p>
            <p class="stat__hint">Rata-rata <?= $statistik['jumlah'] > 0 ? e(rupiah((float) $statistik['omzet'] / (int) $statistik['jumlah'])) : '-' ?></p>
        </div>
        <div class="stat">
            <p class="stat__label">Total Jam Sewa</p>
            <p class="stat__value"><?= (int) $statistik['jam'] ?> <span style="font-size: var(--text-sm);">jam</span></p>
            <p class="stat__hint">Akumulasi durasi pesanan sah</p>
        </div>
        <div class="stat">
            <p class="stat__label">Status Bayar Terakhir</p>
            <?php $bayarTerakhir = $rows === [] ? null : ($pembayaran[(int) $rows[0]['id']] ?? null); ?>
            <p class="stat__value" style="font-size: var(--text-xl);">
                <?= $bayarTerakhir === null ? '-' : e(status_label($bayarTerakhir['status'])) ?>
            </p>
            <p class="stat__hint"><?= $bayarTerakhir === null ? 'Belum ada pembayaran tercatat' : e(rupiah($bayarTerakhir['jumlah'])) ?></p>
        </div>
    </div>

    <!-- ============ RIWAYAT PESANAN ============ -->
    <section class="qs-panel">
        <div class="qs-panel__head flex-wrap gap-3">
            <h2 class="display" style="font-size: var(--text-lg); margin: 0;">Riwayat Pesanan</h2>
            <form method="get" action="<?= e($base) ?>" class="flex flex-wrap items-end gap-2" style="margin-left: auto;">
                <div class="field" style="margin: 0;">
                    <label class="label" for="status-pesanan">Status pesanan</label>
                    <select class="select" id="status-pesanan" name="status" style="width: 175px;">
                        <option value="">Semua status</option>
                        <?php foreach ($statusList as $s): ?>
                            <option value="<?= e($s) ?>" <?= $status === $s ? 'selected' : '' ?>><?= e(status_label($s)) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <button type="submit" class="btn btn-outline btn-sm">Terapkan</button>
                <?php if ($status !== ''): ?>
                    <a class="btn btn-ghost btn-sm" href="<?= e($base) ?>">Reset</a>
                <?php endif; ?>
            </form>
        </div>
        <div class="qs-panel__body" style="padding: 0;">
            <?php if ($rows === []): ?>
                <div class="empty-state">
                    <p class="display" style="font-size: var(--text-lg); margin-bottom: 4px;">Belum ada pesanan</p>
                    <p class="text-secondary" style="margin: 0 auto; font-size: var(--text-sm); max-width: 54ch;">
                        <?= $status !== ''
                            ? 'Tidak ada pesanan dengan status terpilih pada akun ini.'
                            : 'Pelanggan ini belum pernah memesan slot. Daftar kosong bila filter status terlalu sempit.' ?>
                    </p>
                </div>
            <?php else: ?>
                <div class="table-wrap" style="border: 0; border-radius: 0;">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Faktur</th>
                                <th>Jadwal</th>
                                <th>Unit</th>
                                <th class="num">Durasi</th>
                                <th class="num">Total</th>
                                <th>Status</th>
                                <th>Bayar</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($rows as $b): ?>
                                <?php $bayar = $pembayaran[(int) $b['id']] ?? null; ?>
                                <tr>
                                    <td>
                                        <a class="mono" style="font-weight: 600;"
                                           href="<?= e(route('admin.pesanan.detail', ['id' => (int) $b['id']])) ?>"><?= e($b['invoice_number']) ?></a>
                                        <?php if ((int) $b['jumlah_bayar'] > 1): ?>
                                            <p class="text-muted" style="margin: 2px 0 0; font-size: var(--text-xs);">
                                                <?= (int) $b['jumlah_bayar'] ?> percobaan bayar
                                            </p>
                                        <?php endif; ?>
                                    </td>
                                    <td style="white-space: nowrap;">
                                        <p style="margin: 0; font-size: var(--text-sm);"><?= e(tgl_indo($b['booking_date'])) ?></p>
                                        <p class="text-muted" style="margin: 2px 0 0; font-size: var(--text-xs);">
                                            <?= e(jam($b['start_time'])) ?> — <?= e(jam($b['end_time'])) ?>
                                        </p>
                                    </td>
                                    <td>
                                        <p style="margin: 0; font-weight: 600;"><?= e($b['kode_unit']) ?></p>
                                        <p class="text-muted" style="margin: 2px 0 0; font-size: var(--text-xs);"><?= e($b['nama_unit']) ?></p>
                                    </td>
                                    <td class="num mono"><?= (int) $b['durasi_jam'] ?> jam</td>
                                    <td class="num mono text-cyan"><?= e(rupiah($b['total_harga'])) ?></td>
                                    <td><span class="badge <?= status_class($b['status_pesanan']) ?>"><?= e(status_label($b['status_pesanan'])) ?></span></td>
                                    <td>
                                        <span class="badge <?= status_class($b['status_bayar']) ?>"><?= e(status_label($b['status_bayar'])) ?></span>
                                        <?php if ($bayar !== null): ?>
                                            <p class="text-muted" style="margin: 2px 0 0; font-size: var(--text-xs);">
                                                <?= e(status_label($bayar['metode'])) ?> · <?= e(rupiah($bayar['jumlah'])) ?>
                                            </p>
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

    <!-- ============ PEMBAYARAN TERKAIT ============ -->
    <?php if ($pembayaran !== []): ?>
        <section class="qs-panel">
            <div class="qs-panel__head">
                <h2 class="display" style="font-size: var(--text-lg); margin: 0;">Pembayaran Terbaru</h2>
                <a class="btn btn-outline btn-sm" style="margin-left: auto;" href="<?= e(url('admin/pembayaran')) ?>">Antrean verifikasi</a>
            </div>
            <div class="qs-panel__body" style="padding: 0;">
                <div class="table-wrap" style="border: 0; border-radius: 0;">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Bukti</th>
                                <th class="num">Jumlah</th>
                                <th>Metode</th>
                                <th>Status</th>
                                <th>Waktu</th>
                                <th>Catatan admin</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($pembayaran as $idBooking => $p): ?>
                                <tr>
                                    <td>
                                        <a class="mono" style="font-weight: 600;"
                                           href="<?= e(route('admin.pesanan.detail', ['id' => (int) $idBooking])) ?>">#<?= (int) $p['id'] ?></a>
                                    </td>
                                    <td class="num mono"><?= e(rupiah($p['jumlah'])) ?></td>
                                    <td style="font-size: var(--text-sm);"><?= e(status_label($p['metode'])) ?></td>
                                    <td><span class="badge <?= status_class($p['status']) ?>"><?= e(status_label($p['status'])) ?></span></td>
                                    <td class="text-muted" style="font-size: var(--text-xs); white-space: nowrap;"><?= e(tgl_waktu($p['created_at'])) ?></td>
                                    <td style="max-width: 240px;">
                                        <p class="text-secondary" style="margin: 0; font-size: var(--text-xs);"><?= e($p['catatan_admin'] ?? '-') ?></p>
                                        <?php if (!empty($p['bukti_path'])): ?>
                                            <a class="text-cyan" style="font-size: var(--text-xs);" target="_blank" rel="noopener"
                                               href="<?= e(upload_url($p['bukti_path'])) ?>">Lihat bukti</a>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </section>
    <?php endif; ?>

    <!-- ============ ZONA BERBAHAYA: RESET PASSWORD ============ -->
    <section class="qs-panel">
        <div class="qs-panel__head">
            <h2 class="display" style="font-size: var(--text-lg); margin: 0;">Atur Ulang Password</h2>
            <span class="badge badge-warning">Tindakan manual</span>
        </div>
        <div class="qs-panel__body grid gap-4 md:grid-cols-[1fr_320px] md:items-end">
            <div>
                <p class="text-secondary" style="margin: 0; font-size: var(--text-sm); max-width: 62ch;">
                    Gunakan fitur ini hanya ketika pelanggan mengajukan permintaan lewat kanal resmi.
                    Sistem mengganti password tanpa menampilkan yang lama, jadi catat password baru dan
                    sampaikan secara pribadi. Pelanggan disarankan menggantinya lagi setelah masuk.
                </p>
            </div>
            <form method="post" action="<?= e(url('admin/pelanggan/' . $id . '/password')) ?>" class="grid gap-2"
                  data-confirm="Ganti password <?= e($pelanggan['nama']) ?> sekarang? Pelanggan tidak dapat masuk dengan password lama.">
                <?= csrf_field() ?>
                <div class="field" style="margin: 0;">
                    <label class="label" for="password_baru">Password baru</label>
                    <input class="input <?= isset($errors['password_baru']) ? 'is-invalid' : '' ?>" type="text"
                           id="password_baru" name="password_baru" minlength="8" maxlength="60" required
                           autocomplete="off" placeholder="Minimal 8 karakter">
                    <?= $err('password_baru') ?>
                </div>
                <button type="submit" class="btn btn-danger btn-sm">Atur Ulang Password</button>
            </form>
        </div>
    </section>

    <div class="flex flex-wrap items-center gap-2">
        <a class="btn btn-outline btn-sm" href="<?= e(route('admin.pesanan') . '?cari=' . urlencode((string) ($pelanggan['email'] ?? ''))) ?>">
            Cari pesanan atas nama ini
        </a>
        <a class="btn btn-ghost btn-sm" href="<?= e(url('admin/pelanggan')) ?>">Semua pelanggan</a>
    </div>

    <?= View::include('partials/pagination', [
        'halaman'  => $halaman,
        'terakhir' => $terakhir,
        'total'    => (int) $riwayat['total'],
        'label'    => 'pesanan',
        'base'     => $base,
        'query'    => $status === '' ? [] : ['status' => $status],
    ]) ?>
</div>

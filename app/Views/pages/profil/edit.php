<?php
/**
 * pages/profil/edit.php — Data akun pelanggan + ganti password.
 *
 * @var array $rekap   ringkasan transaksi pelanggan
 * @var array $terakhir 3 pesanan terbaru
 * @var array $errors
 * @var array|null $authUser
 */

use App\Core\View;
use App\Models\Setting;

View::set('title', 'Profil Akun — ' . Setting::get('nama_rental', config('app.name')));

if ($authUser === null) {
    redirect('masuk');
}

$err = static fn (string $key): string => isset($errors[$key]) ? '<span class="field-error">' . e($errors[$key][0]) . '</span>' : '';
$inisial = e(mb_strtoupper(mb_substr((string) $authUser['nama'], 0, 1)));
?>
<section class="section" style="padding-bottom: var(--sp-6);">
    <div class="container-narrow">
        <span class="badge badge-cyan" style="margin-bottom: var(--sp-3);">Profil Saya</span>
        <h1 class="display" style="font-size: var(--text-3xl); margin-bottom: var(--sp-2);">Pengaturan Akun</h1>
        <p class="text-secondary" style="margin:0; max-width: 68ch;">
            Data ini dipakai operator untuk menghubungi Anda saat jadwal berubah.
        </p>
    </div>
</section>

<div class="container-narrow grid gap-4 pb-12">

    <!-- Identitas singkat -->
    <div class="qs-card">
        <div class="flex items-center gap-4">
            <span class="display inline-grid place-items-center" aria-hidden="true"
                  style="width:56px; height:56px; border-radius:50%; font-size: var(--text-xl);
                         background: rgba(var(--cyan-rgb) / 0.14); color: var(--cyan); flex:none;">
                <?= $inisial ?>
            </span>
            <div class="min-w-0">
                <p style="margin:0; font-size: var(--text-lg); font-weight:600;"><?= e($authUser['nama']) ?></p>
                <p class="text-muted mono" style="margin:2px 0 0; font-size: var(--text-xs);"><?= e($authUser['email']) ?></p>
            </div>
            <span class="badge <?= status_class((string) $authUser['role']) ?>" style="margin-left:auto;">
                <?= e(status_label((string) $authUser['role'])) ?>
            </span>
        </div>

        <hr class="divider">

        <div class="grid gap-4 sm:grid-cols-3">
            <?php foreach ([
                ['Pesanan aktif & selesai', (int) ($rekap['menunggu'] ?? 0) + (int) ($rekap['dikonfirmasi'] ?? 0) + (int) ($rekap['berlangsung'] ?? 0) + (int) ($rekap['selesai'] ?? 0)],
                ['Total jam main', (int) ($rekap['total_jam'] ?? 0)],
                ['Total pembayaran', rupiah($rekap['total_bayar'] ?? 0)],
            ] as [$label, $nilai]): ?>
                <div>
                    <p class="text-muted" style="margin:0 0 2px; font-size: var(--text-xs); text-transform: uppercase; letter-spacing: .06em;">
                        <?= e($label) ?>
                    </p>
                    <p class="display" style="margin:0; font-size: var(--text-xl); color: var(--text-primary);">
                        <?= e((string) $nilai) ?>
                    </p>
                </div>
            <?php endforeach; ?>
        </div>

        <p class="text-muted" style="margin: var(--sp-4) 0 0; font-size: var(--text-xs);">
            Terakhir masuk: <?= $authUser['last_login_at'] ? e(tgl_waktu($authUser['last_login_at'])) : 'baru pertama kali' ?>
            · Akun dibuat <?= e(tgl_indo((string) $authUser['created_at'])) ?>
        </p>
    </div>

    <!-- Form identitas -->
    <div class="qs-panel">
        <div class="qs-panel__head">
            <h2 class="display" style="font-size: var(--text-base); margin:0;">Data Pribadi</h2>
        </div>
        <div class="qs-panel__body">
            <form method="post" action="<?= url('profil') ?>" novalidate>
                <?= csrf_field() ?>
                <input type="hidden" name="_current_id" value="<?= (int) $authUser['id'] ?>">

                <div class="field">
                    <label class="label" for="nama">Nama lengkap</label>
                    <input class="input <?= isset($errors['nama']) ? 'is-invalid' : '' ?>"
                           id="nama" name="nama" required minlength="3" maxlength="100"
                           value="<?= old('nama', (string) $authUser['nama']) ?>">
                    <?= $err('nama') ?>
                </div>

                <div class="field">
                    <label class="label" for="email">Email</label>
                    <input class="input <?= isset($errors['email']) ? 'is-invalid' : '' ?>"
                           type="email" id="email" name="email" required autocomplete="email" maxlength="150"
                           value="<?= old('email', (string) $authUser['email']) ?>">
                    <?= $err('email') ?>
                    <small class="text-muted">Email dipakai untuk notifikasi status pesanan.</small>
                </div>

                <div class="grid gap-4 sm:grid-cols-2">
                    <div class="field">
                        <label class="label" for="telepon">No. WhatsApp</label>
                        <input class="input <?= isset($errors['telepon']) ? 'is-invalid' : '' ?>"
                               id="telepon" name="telepon" inputmode="tel" maxlength="20"
                               value="<?= old('telepon', (string) $authUser['telepon']) ?>" placeholder="08xxxxxxxxxx">
                        <?= $err('telepon') ?>
                    </div>

                    <div class="field">
                        <label class="label" for="alamat">Alamat</label>
                        <input class="input <?= isset($errors['alamat']) ? 'is-invalid' : '' ?>"
                               id="alamat" name="alamat" maxlength="255"
                               value="<?= old('alamat', (string) $authUser['alamat']) ?>" placeholder="Kota / alamat singkat">
                        <?= $err('alamat') ?>
                    </div>
                </div>

                <button type="submit" class="btn btn-primary">Simpan Perubahan</button>
                <a href="<?= url('riwayat') ?>" class="btn btn-ghost">Lihat Riwayat Pesanan</a>
            </form>
        </div>
    </div>

    <!-- Form password -->
    <div class="qs-panel">
        <div class="qs-panel__head">
            <h2 class="display" style="font-size: var(--text-base); margin:0;">Ganti Password</h2>
            <span class="text-muted" style="font-size: var(--text-xs);">minimal 8 karakter</span>
        </div>
        <div class="qs-panel__body">
            <form method="post" action="<?= url('profil/password') ?>" novalidate>
                <?= csrf_field() ?>

                <div class="field">
                    <label class="label" for="password_lama">Password saat ini</label>
                    <input class="input <?= isset($errors['password_lama']) ? 'is-invalid' : '' ?>"
                           type="password" id="password_lama" name="password_lama" required
                           autocomplete="current-password">
                    <?= $err('password_lama') ?>
                </div>

                <div class="grid gap-4 sm:grid-cols-2">
                    <div class="field">
                        <label class="label" for="password">Password baru</label>
                        <input class="input <?= isset($errors['password']) ? 'is-invalid' : '' ?>"
                               type="password" id="password" name="password" required minlength="8"
                               autocomplete="new-password">
                        <?= $err('password') ?>
                    </div>

                    <div class="field">
                        <label class="label" for="password_confirmation">Ulangi password baru</label>
                        <input class="input" type="password" id="password_confirmation"
                               name="password_confirmation" required autocomplete="new-password">
                    </div>
                </div>

                <button type="submit" class="btn btn-violet">Ganti Password</button>
            </form>
        </div>
    </div>

    <!-- Pesanan terbaru -->
    <div class="qs-panel">
        <div class="qs-panel__head">
            <h2 class="display" style="font-size: var(--text-base); margin:0;">Pesanan Terbaru</h2>
            <a href="<?= url('riwayat') ?>" class="btn btn-ghost btn-sm">Semua</a>
        </div>
        <div class="qs-panel__body">
            <?php if ($terakhir === []): ?>
                <p class="text-secondary" style="margin:0;">
                    Anda belum pernah memesan slot. Mulai dari
                    <a href="<?= url('katalog') ?>">katalog unit</a> untuk melihat jam yang masih kosong.
                </p>
            <?php else: ?>
                <ul style="list-style:none; margin:0; padding:0;">
                    <?php foreach ($terakhir as $b): ?>
                        <li class="flex flex-wrap items-center justify-between gap-3"
                            style="padding: var(--sp-3) 0; border-bottom: 1px solid var(--border-soft);">
                            <span class="min-w-0">
                                <a href="<?= route('booking.detail', ['id' => (int) $b['id']]) ?>" class="mono"
                                   style="font-size: var(--text-sm); font-weight:600;"><?= e($b['invoice_number']) ?></a>
                                <span class="text-muted" style="font-size: var(--text-xs);">
                                    · <?= e($b['kode_unit']) ?> · <?= e(tgl_indo($b['booking_date'])) ?>
                                    <?= e(jam($b['start_time'])) ?>
                                </span>
                            </span>
                            <span class="flex items-center gap-2">
                                <span class="badge <?= status_class((string) $b['status_pesanan']) ?>">
                                    <?= e(status_label((string) $b['status_pesanan'])) ?>
                                </span>
                                <span class="mono" style="font-size: var(--text-sm);"><?= e(rupiah($b['total_harga'])) ?></span>
                            </span>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </div>
    </div>

</div>

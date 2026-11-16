<?php
/**
 * pages/auth/register.php — Pendaftaran akun pelanggan baru.
 */
use App\Core\View;
use App\Models\Setting;

View::set('title', 'Daftar Akun — ' . Setting::get('nama_rental', config('app.name')));
$err = static fn (string $key): string => isset($errors[$key]) ? '<span class="field-error">' . e($errors[$key][0]) . '</span>' : '';
?>
<section class="section">
    <div class="container-narrow" style="max-width: 520px;">

        <div class="qs-card" style="padding: var(--sp-8) var(--sp-6);">
            <h1 class="display" style="font-size: var(--text-2xl); margin-bottom: 4px;">Buat akun baru</h1>
            <p class="text-secondary" style="font-size: var(--text-sm); margin-bottom: var(--sp-6);">
                Satu akun untuk memesan, melacak status, dan menyimpan bukti pembayaran.
            </p>

            <form method="post" action="<?= url('daftar') ?>" novalidate>
                <?= csrf_field() ?>

                <div class="field">
                    <label class="label" for="nama">Nama lengkap</label>
                    <input class="input <?= isset($errors['nama']) ? 'is-invalid' : '' ?>"
                           id="nama" name="nama" required minlength="3" maxlength="100"
                           value="<?= old('nama') ?>" placeholder="Contoh: Budi Santoso">
                    <?= $err('nama') ?>
                </div>

                <div class="field">
                    <label class="label" for="email">Email</label>
                    <input class="input <?= isset($errors['email']) ? 'is-invalid' : '' ?>"
                           type="email" id="email" name="email" required autocomplete="email"
                           value="<?= old('email') ?>" placeholder="nama@email.com">
                    <?= $err('email') ?>
                </div>

                <div class="grid gap-4 sm:grid-cols-2" style="gap: var(--sp-4);">
                    <div class="field">
                        <label class="label" for="telepon">No. WhatsApp <small>(opsional)</small></label>
                        <input class="input <?= isset($errors['telepon']) ? 'is-invalid' : '' ?>"
                               id="telepon" name="telepon" inputmode="tel"
                               value="<?= old('telepon') ?>" placeholder="08xxxxxxxxxx">
                        <?= $err('telepon') ?>
                    </div>

                    <div class="field">
                        <label class="label" for="alamat">Alamat <small>(opsional)</small></label>
                        <input class="input <?= isset($errors['alamat']) ? 'is-invalid' : '' ?>"
                               id="alamat" name="alamat" maxlength="255" value="<?= old('alamat') ?>"
                               placeholder="Kota / alamat singkat">
                        <?= $err('alamat') ?>
                    </div>
                </div>

                <div class="field">
                    <label class="label" for="password">Password</label>
                    <input class="input <?= isset($errors['password']) ? 'is-invalid' : '' ?>"
                           type="password" id="password" name="password" required
                           minlength="8" autocomplete="new-password" placeholder="Minimal 8 karakter">
                    <?= $err('password') ?>
                </div>

                <div class="field">
                    <label class="label" for="password_confirmation">Ulangi password</label>
                    <input class="input" type="password" id="password_confirmation"
                           name="password_confirmation" required autocomplete="new-password">
                </div>

                <button type="submit" class="btn btn-primary btn-block btn-lg">Daftar Sekarang</button>
            </form>

            <p class="text-secondary" style="margin: var(--sp-6) 0 0; font-size: var(--text-sm); text-align:center;">
                Sudah punya akun? <a href="<?= url('masuk') ?>">Masuk di sini</a>
            </p>
        </div>
    </div>
</section>

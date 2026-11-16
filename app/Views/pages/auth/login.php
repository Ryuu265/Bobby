<?php
/**
 * pages/auth/login.php — Form masuk pelanggan/admin.
 */
use App\Core\View;
use App\Models\Setting;

View::set('title', 'Masuk — ' . Setting::get('nama_rental', config('app.name')));
$err = static fn (string $key): string => isset($errors[$key]) ? '<span class="field-error">' . e($errors[$key][0]) . '</span>' : '';
?>
<section class="section">
    <div class="container-narrow" style="max-width: 460px;">

        <div class="qs-card" style="padding: var(--sp-8) var(--sp-6);">
            <h1 class="display" style="font-size: var(--text-2xl); margin-bottom: 4px;">Masuk ke akun</h1>
            <p class="text-secondary" style="font-size: var(--text-sm); margin-bottom: var(--sp-6);">
                Riwayat pesanan dan status pembayaran Anda ada di sini.
            </p>

            <form method="post" action="<?= url('masuk') ?>" novalidate>
                <?= csrf_field() ?>

                <div class="field">
                    <label class="label" for="email">Email</label>
                    <input class="input <?= isset($errors['email']) ? 'is-invalid' : '' ?>"
                           type="email" id="email" name="email" autocomplete="email" required
                           value="<?= old('email') ?>" placeholder="nama@email.com">
                    <?= $err('email') ?>
                </div>

                <div class="field">
                    <label class="label" for="password">Password</label>
                    <input class="input <?= isset($errors['password']) ? 'is-invalid' : '' ?>"
                           type="password" id="password" name="password" autocomplete="current-password" required
                           placeholder="Minimal 6 karakter">
                    <?= $err('password') ?>
                </div>

                <button type="submit" class="btn btn-primary btn-block btn-lg">Masuk</button>
            </form>

            <p class="text-secondary" style="margin: var(--sp-6) 0 0; font-size: var(--text-sm); text-align:center;">
                Belum punya akun? <a href="<?= url('daftar') ?>">Daftar gratis</a>
            </p>
        </div>

        <div class="qs-panel" style="margin-top: var(--sp-4); padding: var(--sp-4);">
            <p class="text-muted" style="margin:0; font-size: var(--text-xs); line-height: 1.7;">
                <strong style="color: var(--text-secondary);">Akun demo hasil seed</strong><br>
                Admin&nbsp;&nbsp;&nbsp;&nbsp;: admin@qengamestation.id<br>
                Operator: operator@qengamestation.id<br>
                Pelanggan: budi@example.com<br>
                Password semuanya: <code class="mono">password</code>
            </p>
        </div>
    </div>
</section>

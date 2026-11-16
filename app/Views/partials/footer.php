<?php
/**
 * partials/footer.php — Kaki halaman berisi info operasional singkat.
 */
use App\Models\Setting;
?>
<footer class="qs-footer" style="margin-top: var(--sp-16);">
    <div class="container-page py-12 grid gap-8 md:grid-cols-4">
        <div class="md:col-span-2">
            <div class="brand" style="margin-bottom: var(--sp-3);">
                <span class="brand__dot" aria-hidden="true"></span>Qengamestation
            </div>
            <p class="text-body-long" style="color: var(--text-secondary); max-width: 46ch;">
                <?= e(Setting::get('tagline', 'Sewa PlayStation nyaman, harga bersahabat, jadwal jelas.')) ?>
            </p>
        </div>

        <div>
            <h4 class="display" style="font-size: var(--text-sm); color: var(--text-secondary); letter-spacing: .1em;">Jam Operasional</h4>
            <p style="color: var(--text-primary); margin-bottom: var(--sp-1);">
                <?= e(Setting::get('jam_hari_biasa', 'Senin - Jumat: 10.00 - 23.00')) ?>
            </p>
            <p class="text-secondary" style="margin:0;">
                <?= e(Setting::get('jam_akhir_pekan', 'Sabtu - Minggu: 09.00 - 24.00')) ?>
            </p>
        </div>

        <div>
            <h4 class="display" style="font-size: var(--text-sm); color: var(--text-secondary); letter-spacing: .1em;">Kontak</h4>
            <p style="margin-bottom: var(--sp-1);">
                <a href="tel:<?= e(preg_replace('/[^0-9+]/', '', (string) Setting::get('telepon', ''))) ?>">
                    <?= e(Setting::get('telepon', '0812-0000-0000')) ?>
                </a>
            </p>
            <p style="margin-bottom: var(--sp-1);">
                <a href="mailto:<?= e(Setting::get('email', 'halo@example.com')) ?>"><?= e(Setting::get('email', 'halo@example.com')) ?></a>
            </p>
            <p class="text-secondary" style="margin:0;"><?= e(Setting::get('alamat', '-')) ?></p>
        </div>
    </div>

    <div style="border-top: 1px solid var(--border-soft);">
        <div class="container-page py-4 flex flex-col sm:flex-row items-center justify-between gap-2 text-sm">
            <span class="text-muted">&copy; <?= date('Y') ?> <?= e(Setting::get('nama_rental', config('app.name'))) ?>. Semua hak dilindungi.</span>
            <span class="text-muted">Sistem Informasi Penyewaan &amp; Pemesanan PlayStation</span>
        </div>
    </div>
</footer>

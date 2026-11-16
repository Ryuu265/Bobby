<?php
/**
 * admin/pengaturan/index.php — Pengaturan operasional (key-value).
 * Data dari Admin\SettingController::index(): daftar, jamOperasional, hanyaAdmin.
 * Hanya role "admin" yang boleh menyimpan; operator hanya dapat membaca.
 */

use App\Core\View;
use App\Models\Setting;

View::set('title', 'Pengaturan — ' . config('app.name'));
View::set('page_title', 'Pengaturan Operasional');
View::set('page_subtitle', 'Identitas rental, jam buka, tarif tambahan, dan rekening pembayaran');


// Batas nilai angka harus sama dengan SettingController::KUNCI_ANGKA
$batas = [
    'tarif_controller'      => ['min' => 0, 'max' => 1000000, 'step' => 500],
    'batas_menit_batal'     => ['min' => 0, 'max' => 1440, 'step' => 5],
    'jam_operasional_buka'  => ['min' => 0, 'max' => 23, 'step' => 1],
    'jam_operasional_tutup' => ['min' => 1, 'max' => 24, 'step' => 1],
];

$grup = [
    'Identitas & Kontak' => ['nama_rental', 'tagline', 'alamat', 'telepon', 'email', 'peta_url'],
    'Jam Operasional'    => ['jam_operasional_buka', 'jam_operasional_tutup', 'jam_hari_biasa', 'jam_akhir_pekan', 'batas_menit_batal'],
    'Harga & Pembayaran' => ['tarif_controller', 'rekening_bank'],
    'Fasilitas'          => ['deskripsi_tempat'],
];

$byKey = array_combine(array_column($daftar, 'key_name'), $daftar);

$err = static fn (string $key): string => isset($errors[$key])
    ? '<span class="field-error">' . e($errors[$key][0]) . '</span>'
    : '';

/** Satu input/textarea sesuai tipe kolom settings.tipe. */
$input = static function (array $s) use ($batas, $hanyaAdmin, $errors): string {
    $key   = (string) $s['key_name'];
    $nilai = (string) $s['value_text'];
    $lama  = old($key, $nilai);
    $salah = isset($errors[$key]) ? ' is-invalid' : '';
    $kunci = $hanyaAdmin ? '' : ' readonly';
    $id    = 'set-' . preg_replace('/[^a-z0-9_-]/i', '-', $key);

    if ($s['tipe'] === 'paragraf') {
        return '<textarea class="textarea' . $salah . '" id="' . e($id)
            . '" name="' . e($key) . '" rows="3" maxlength="255"' . $kunci . '>' . $lama . '</textarea>';
    }

    if ($s['tipe'] === 'angka' && isset($batas[$key])) {
        $b = $batas[$key];
        return '<input class="input' . $salah . '" type="number" id="' . e($id)
            . '" name="' . e($key) . '" value="' . $lama . '" min="' . $b['min'] . '" max="' . $b['max']
            . '" step="' . $b['step'] . '" inputmode="numeric"' . $kunci . '>';
    }

    return '<input class="input' . $salah . '" type="text" id="' . e($id) . '" name="' . e($key) . '" value="' . $lama
        . '" maxlength="150"' . $kunci . '>';
};

$tutup = (int) $jamOperasional['tutup'];
?>
<div class="grid gap-5">

    <?php if (!$hanyaAdmin): ?>
        <div class="alert alert-warning" role="alert">
            <div class="flex-1">
                <p style="margin: 0; font-weight: 600;">Hanya untuk dibaca</p>
                <p style="margin: 2px 0 0; font-size: var(--text-sm);">
                    Mengubah pengaturan operasional khusus akun ber-role admin. Nilai di bawah bersifat
                    aktif dan dipakai halaman publik maupun perhitungan harga, tetapi kolom isian dikunci.
                </p>
            </div>
        </div>
    <?php endif; ?>

    <form method="post" action="<?= e(url('admin/pengaturan')) ?>" class="grid gap-5">
        <?= csrf_field() ?>

        <?php foreach ($grup as $judul => $kunci): ?>
            <?php
            $anggota = array_values(array_filter(array_map(static fn (string $k): ?array => $byKey[$k] ?? null, $kunci)));
            if ($anggota === []) {
                continue;
            }
            ?>
            <section class="qs-panel">
                <div class="qs-panel__head">
                    <h2 class="display" style="font-size: var(--text-lg); margin: 0;"><?= e($judul) ?></h2>
                    <span class="text-muted" style="font-size: var(--text-xs);"><?= count($anggota) ?> kunci</span>
                </div>
                <div class="qs-panel__body grid gap-4 md:grid-cols-2">
                    <?php foreach ($anggota as $s): ?>
                        <?php $key = (string) $s['key_name']; ?>
                        <div class="field<?= in_array($key, ['tagline', 'alamat', 'deskripsi_tempat'], true) ? ' md:col-span-2' : '' ?>" style="margin: 0;">
                            <label class="label" for="set-<?= e($key) ?>"><?= e($s['label']) ?></label>
                            <?= $input($s) ?>
                            <?= $err($key) ?>
                            <?php if ($key === 'jam_operasional_tutup'): ?>
                                <p class="text-muted" style="margin: 4px 0 0; font-size: var(--text-xs);">
                                    24 berarti tutup tengah malam (slot terakhir berakhir 24.00).
                                </p>
                            <?php elseif ($key === 'tarif_controller'): ?>
                                <p class="text-muted" style="margin: 4px 0 0; font-size: var(--text-xs);">
                                    Ditabung per controller per jam. Nilai saat ini <?= e(rupiah((float) $s['value_text'])) ?>/jam.
                                </p>
                            <?php elseif ($key === 'batas_menit_batal'): ?>
                                <p class="text-muted" style="margin: 4px 0 0; font-size: var(--text-xs);">
                                    0 berarti pesanan dapat dibatalkan kapan saja sebelum mulai.
                                </p>
                            <?php elseif ($key === 'peta_url'): ?>
                                <p class="text-muted" style="margin: 4px 0 0; font-size: var(--text-xs);">
                                    Tautan lengkap, dipakai tombol arah pada halaman publik.
                                </p>
                            <?php elseif ($key === 'rekening_bank'): ?>
                                <p class="text-muted" style="margin: 4px 0 0; font-size: var(--text-xs);">
                                    Ditampilkan pada form pembayaran pelanggan dan halaman verifikasi admin.
                                </p>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            </section>
        <?php endforeach; ?>

        <div class="flex flex-wrap items-center gap-2">
            <?php if ($hanyaAdmin): ?>
                <button type="submit" class="btn btn-primary">Simpan Pengaturan</button>
            <?php else: ?>
                <button type="button" class="btn btn-outline" disabled>Simpan (khusus admin)</button>
            <?php endif; ?>
            <a class="btn btn-ghost" href="<?= e(url('admin/pengaturan')) ?>">Batalkan perubahan</a>
            <p class="text-muted" style="margin: 0 0 0 auto; font-size: var(--text-xs);">
                Nilai tersimpan langsung memengaruhi perhitungan harga pesanan baru — pesanan lama memakai snapshot.
            </p>
        </div>
    </form>

    <!-- ============ DAMPAK PENGATURAN ============ -->
    <section class="qs-panel">
        <div class="qs-panel__head">
            <h2 class="display" style="font-size: var(--text-lg); margin: 0;">Ringkasan Aktif</h2>
            <span class="text-muted" style="font-size: var(--text-xs);">Dihitung dari nilai tersimpan</span>
        </div>
        <div class="qs-panel__body grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
            <div class="stat">
                <p class="stat__label">Jam Buka</p>
                <p class="stat__value"><?= e(sprintf('%02d.00', (int) $jamOperasional['buka'])) ?></p>
                <p class="stat__hint">Batas awal slot dapat dipesan</p>
            </div>
            <div class="stat">
                <p class="stat__label">Jam Tutup</p>
                <p class="stat__value"><?= $tutup === 24 ? '24.00' : e(sprintf('%02d.00', $tutup)) ?></p>
                <p class="stat__hint">Batas akhir slot + penanda kepadatan</p>
            </div>
            <div class="stat">
                <p class="stat__label">Controller Tambahan</p>
                <p class="stat__value"><?= e(rupiah((float) Setting::get('tarif_controller', 0))) ?></p>
                <p class="stat__hint">Per controller, per jam</p>
            </div>
            <div class="stat">
                <p class="stat__label">Batal Minimal</p>
                <p class="stat__value"><?= (int) Setting::get('batas_menit_batal', 60) ?> <span style="font-size: var(--text-sm);">menit</span></p>
                <p class="stat__hint">Sebelum jadwal pesanan dimulai</p>
            </div>
        </div>
    </section>

    <p class="text-secondary" style="margin: 0; font-size: var(--text-sm); max-width: 78ch;">
        Perubahan jam buka/tutup langsung mengubah susunan slot pada halaman katalog dan cek ketersediaan.
        Menurunkan tarif controller tidak mengubah pesanan yang sudah tercatat karena harga disimpan sebagai
        snapshot pada kolom <code>harga_per_jam</code>, <code>tarif_controller</code>, dan <code>biaya_controller</code>.
    </p>
</div>

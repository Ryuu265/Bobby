<?php
/**
 * admin/unit/form.php — SATU form untuk tambah (mode=baru) & ubah (mode=ubah).
 * Nilai field: old input saat validasi gagal > data tersimpan > default.
 */

use App\Core\View;

View::set('title', (($mode ?? 'baru') === 'ubah' ? 'Ubah Unit' : 'Tambah Unit') . ' — ' . config('app.name'));
View::set('page_title', ($mode ?? 'baru') === 'ubah' ? 'Ubah Unit PlayStation' : 'Tambah Unit PlayStation');
View::set('page_subtitle', 'Lengkapi identitas konsol, tarif, dan foto unit bila tersedia.');


$modeUbah = ($mode ?? 'baru') === 'ubah';
$unit     = $unit ?? [];
$saran    = $saran ?? [];
$errors   = $errors ?? [];
$old      = $old ?? [];

$val = static function (string $key, string $default = '') use ($old): string {
    $nilai = $old[$key] ?? null;
    return ($nilai === null || $nilai === '') ? $default : (string) $nilai;
};
$err = static fn (string $key): string => isset($errors[$key])
    ? '<span class="field-error">' . e($errors[$key][0]) . '</span>'
    : '';

$aksi = $modeUbah ? url('admin/unit/' . (int) $unit['id']) : url('admin/unit');
$tipeAwal = $val('tipe', (string) ($unit['tipe'] ?? 'PS5'));
$kodeAwal = $val('kode_unit', (string) ($unit['kode_unit'] ?? ($saran[$tipeAwal] ?? '')));
?>
<div class="grid gap-5 lg:grid-cols-12" style="align-items: start;">
    <div class="lg:col-span-8">
        <div class="qs-card">
            <div class="flex flex-wrap items-center justify-between gap-2" style="margin-bottom: var(--sp-5);">
                <h2 class="display" style="margin: 0; font-size: var(--text-xl);">
                    <?= $modeUbah ? 'Data Unit' : 'Unit Baru' ?>
                </h2>
                <span class="badge <?= $modeUbah ? 'badge-cyan' : 'badge-violet' ?>">
                    <?= $modeUbah ? 'Mode ubah' : 'Mode tambah' ?>
                </span>
            </div>

            <form method="post" action="<?= e($aksi) ?>" enctype="multipart/form-data" novalidate>
                <?= csrf_field() ?>
                <?php if ($modeUbah): ?>
                    <input type="hidden" name="_current_id" value="<?= (int) $unit['id'] ?>">
                <?php endif; ?>

                <div class="grid gap-4 sm:grid-cols-2" style="gap: var(--sp-4);">
                    <div class="field">
                        <label class="label" for="kode_unit">Kode unit</label>
                        <input class="input <?= isset($errors['kode_unit']) ? 'is-invalid' : '' ?>"
                               id="kode_unit" name="kode_unit" required maxlength="20"
                               value="<?= e($kodeAwal) ?>" placeholder="PS5-01">
                        <?= $err('kode_unit') ?>
                        <p class="text-muted" style="margin: 6px 0 0; font-size: var(--text-xs);">
                            Unik, format bebas. Contoh: PS4-03, RUANG-VIP.
                        </p>
                    </div>

                    <div class="field">
                        <label class="label" for="nama">Nama unit</label>
                        <input class="input <?= isset($errors['nama']) ? 'is-invalid' : '' ?>"
                               id="nama" name="nama" required minlength="3" maxlength="100"
                               value="<?= e($val('nama', (string) ($unit['nama'] ?? ''))) ?>"
                               placeholder="Ruang VIP Lantai 2">
                        <?= $err('nama') ?>
                    </div>

                    <div class="field">
                        <label class="label" for="tipe">Tipe konsol</label>
                        <select class="select <?= isset($errors['tipe']) ? 'is-invalid' : '' ?>" id="tipe" name="tipe">
                            <?php foreach ($daftarTipe as $t): ?>
                                <option value="<?= e($t) ?>" <?= $tipeAwal === $t ? 'selected' : '' ?>><?= e($t) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <?= $err('tipe') ?>
                    </div>

                    <div class="field">
                        <label class="label" for="kategori">Kategori</label>
                        <select class="select <?= isset($errors['kategori']) ? 'is-invalid' : '' ?>" id="kategori" name="kategori">
                            <?php foreach ($daftarKategori as $k): ?>
                                <option value="<?= e($k) ?>" <?= $val('kategori', (string) ($unit['kategori'] ?? 'reguler')) === $k ? 'selected' : '' ?>>
                                    <?= e(status_label($k)) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <?= $err('kategori') ?>
                    </div>

                    <div class="field">
                        <label class="label" for="harga_per_jam">Harga per jam (Rp)</label>
                        <input class="input <?= isset($errors['harga_per_jam']) ? 'is-invalid' : '' ?>"
                               id="harga_per_jam" name="harga_per_jam" type="number" min="1000" step="500"
                               inputmode="numeric" required
                               value="<?= e($val('harga_per_jam', (string) (int) ($unit['harga_per_jam'] ?? 0))) ?>"
                               placeholder="20000">
                        <?= $err('harga_per_jam') ?>
                        <p class="text-muted" style="margin: 6px 0 0; font-size: var(--text-xs);">
                            Tanpa sen. Minimal Rp1.000 per jam.
                        </p>
                    </div>

                    <div class="field">
                        <label class="label" for="kapasitas">Kapasitas pemain</label>
                        <input class="input <?= isset($errors['kapasitas']) ? 'is-invalid' : '' ?>"
                               id="kapasitas" name="kapasitas" type="number" min="1" max="8" step="1"
                               value="<?= e($val('kapasitas', (string) (int) ($unit['kapasitas'] ?? 2))) ?>">
                        <?= $err('kapasitas') ?>
                    </div>

                    <div class="field">
                        <label class="label" for="status">Status operasional</label>
                        <select class="select <?= isset($errors['status']) ? 'is-invalid' : '' ?>" id="status" name="status">
                            <?php foreach ($daftarStatus as $s): ?>
                                <option value="<?= e($s) ?>" <?= $val('status', (string) ($unit['status'] ?? 'tersedia')) === $s ? 'selected' : '' ?>>
                                    <?= e(status_label($s)) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <?= $err('status') ?>
                        <p class="text-muted" style="margin: 6px 0 0; font-size: var(--text-xs);">
                            Status yang tampil untuk pelanggan dihitung ulang dari pesanan nyata.
                        </p>
                    </div>
                </div>

                <div class="field">
                    <label class="label" for="deskripsi">Deskripsi <small>(opsional)</small></label>
                    <textarea class="textarea <?= isset($errors['deskripsi']) ? 'is-invalid' : '' ?>"
                              id="deskripsi" name="deskripsi" maxlength="255" rows="3"
                              placeholder="DualSense baru, TV 55 inci, kursi gaming."><?= e($val('deskripsi', (string) ($unit['deskripsi'] ?? ''))) ?></textarea>
                    <?= $err('deskripsi') ?>
                </div>

                <div class="field">
                    <label class="label" for="foto">Foto unit <small>(opsional, maks 2 MB)</small></label>
                    <input class="input <?= isset($errors['foto']) ? 'is-invalid' : '' ?>"
                           type="file" id="foto" name="foto" accept="image/jpeg,image/png,image/webp">
                    <?= $err('foto') ?>
                    <?php if (!$modeUbah): ?>
                        <p class="text-muted" style="margin: 6px 0 0; font-size: var(--text-xs);">
                            Format jpg, png, atau webp. Berkas disimpan dengan nama acak.
                        </p>
                    <?php endif; ?>
                </div>

                <div class="divider"></div>

                <div class="flex flex-wrap gap-3">
                    <button type="submit" class="btn btn-primary btn-lg">
                        <?= $modeUbah ? 'Simpan Perubahan' : 'Simpan Unit' ?>
                    </button>
                    <a href="<?= url('admin/unit') ?>" class="btn btn-ghost btn-lg">Batal</a>
                </div>
            </form>
        </div>
    </div>

    <!-- ============ PANEL BANTU ============ -->
    <div class="lg:col-span-4">
        <div class="qs-card" style="padding: var(--sp-5);">
            <h3 style="margin: 0 0 var(--sp-2); font-size: var(--text-base);">Kode otomatis</h3>
            <p class="text-secondary" style="margin: 0 0 var(--sp-4); font-size: var(--text-sm);">
                Nomor berikutnya per tipe, dihitung dari kode terbesar yang sudah ada.
            </p>
            <div class="flex flex-wrap gap-2">
                <?php foreach ($saran as $t => $kode): ?>
                    <button type="button" class="btn btn-outline btn-sm" data-kode-saran="<?= e($kode) ?>">
                        <?= e($t) ?>: <span class="mono"><?= e($kode) ?></span>
                    </button>
                <?php endforeach; ?>
            </div>
            <p class="text-muted" style="margin: var(--sp-3) 0 0; font-size: var(--text-xs);">
                Ganti tipe konsol dan isian kode yang masih kosong akan terisi otomatis.
            </p>
        </div>

        <?php if ($modeUbah && !empty($unit['foto_path'])): ?>
            <div class="qs-card" style="margin-top: var(--sp-4); padding: var(--sp-5);">
                <h3 style="margin: 0 0 var(--sp-3); font-size: var(--text-base);">Foto saat ini</h3>
                <div class="unit-card__media" style="margin-bottom: 0;">
                    <img src="<?= e(upload_url($unit['foto_path'])) ?>" alt="Foto <?= e($unit['kode_unit']) ?>" loading="lazy">
                </div>
                <p class="text-muted" style="margin: var(--sp-3) 0 0; font-size: var(--text-xs);">
                    Unggah berkas baru untuk menggantikannya; berkas lama ikut terhapus.
                </p>
            </div>
        <?php elseif (!$modeUbah): ?>
            <div class="qs-card" style="margin-top: var(--sp-4); padding: var(--sp-5);">
                <h3 style="margin: 0 0 var(--sp-3); font-size: var(--text-base);">Sebelum menyimpan</h3>
                <ul class="text-secondary" style="margin: 0; padding-left: var(--sp-5); font-size: var(--text-sm);">
                    <li>Pastikan kode unit belum dipakai konsol lain.</li>
                    <li>Tarif VIP/ruangan biasanya lebih tinggi dari reguler.</li>
                    <li>Set status <em>Maintenance</em> bila unit sedang diperbaiki agar tidak bisa dipesan.</li>
                </ul>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php
$scripts = '<script>
(function () {
  var kode = document.getElementById("kode_unit");
  var tipe = document.getElementById("tipe");
  var saran = ' . json_encode($saran, JSON_UNESCAPED_SLASHES) . ';
  var semua = Object.keys(saran).map(function (k) { return saran[k]; });

  document.querySelectorAll("[data-kode-saran]").forEach(function (tombol) {
    tombol.addEventListener("click", function () {
      if (!kode) return;
      kode.value = tombol.getAttribute("data-kode-saran");
      kode.focus();
    });
  });

  if (tipe) {
    tipe.addEventListener("change", function () {
      if (!kode || !saran[tipe.value]) return;
      // Hanya menimpa bila pengguna belum mengubah kode secara manual
      if (kode.value === "" || semua.indexOf(kode.value) !== -1) {
        kode.value = saran[tipe.value];
      }
    });
  }
})();
</script>';
View::set('scripts', $scripts);

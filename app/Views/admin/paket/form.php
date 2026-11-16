<?php
/**
 * admin/paket/form.php — Form tambah/ubah paket atau promo.
 */

use App\Core\View;

View::set('title', (($mode ?? 'baru') === 'ubah' ? 'Ubah Paket' : 'Tambah Paket') . ' — ' . config('app.name'));
View::set('page_title', ($mode ?? 'baru') === 'ubah' ? 'Ubah Paket' : 'Tambah Paket');
View::set('page_subtitle', 'Atur durasi, skema potongan, dan periode berlakunya promo.');


$modeUbah = ($mode ?? 'baru') === 'ubah';
$paket    = $paket ?? [];
$errors   = $errors ?? [];
$old      = $old ?? [];

$val = static function (string $key, string $default = '') use ($old): string {
    $nilai = $old[$key] ?? null;
    return ($nilai === null || $nilai === '') ? $default : (string) $nilai;
};
$err = static fn (string $key): string => isset($errors[$key])
    ? '<span class="field-error">' . e($errors[$key][0]) . '</span>'
    : '';

$angka = static function (mixed $nilai): string {
    if ($nilai === null || $nilai === '') {
        return '';
    }
    return (string) round((float) $nilai, 2);
};

$aksi = $modeUbah ? url('admin/paket/' . (int) $paket['id']) : url('admin/paket');
$tipeDiskon = $val('tipe_diskon', (string) ($paket['tipe_diskon'] ?? 'nominal'));
?>
<div class="grid gap-5 lg:grid-cols-12" style="align-items: start;">
    <div class="lg:col-span-8">
        <div class="qs-card">
            <div class="flex flex-wrap items-center justify-between gap-2" style="margin-bottom: var(--sp-5);">
                <h2 class="display" style="margin: 0; font-size: var(--text-xl);">
                    <?= $modeUbah ? 'Data Paket' : 'Paket Baru' ?>
                </h2>
                <span class="badge <?= $modeUbah ? 'badge-cyan' : 'badge-violet' ?>">
                    <?= $modeUbah ? 'Mode ubah' : 'Mode tambah' ?>
                </span>
            </div>

            <form method="post" action="<?= e($aksi) ?>" novalidate>
                <?= csrf_field() ?>

                <div class="field">
                    <label class="label" for="nama">Nama paket</label>
                    <input class="input <?= isset($errors['nama']) ? 'is-invalid' : '' ?>"
                           id="nama" name="nama" required minlength="3" maxlength="120"
                           value="<?= e($val('nama', (string) ($paket['nama'] ?? ''))) ?>"
                           placeholder="Paket Santai 3 Jam">
                    <?= $err('nama') ?>
                </div>

                <div class="field">
                    <label class="label" for="deskripsi">Deskripsi <small>(opsional)</small></label>
                    <input class="input <?= isset($errors['deskripsi']) ? 'is-invalid' : '' ?>"
                           id="deskripsi" name="deskripsi" maxlength="255"
                           value="<?= e($val('deskripsi', (string) ($paket['deskripsi'] ?? ''))) ?>"
                           placeholder="Cocok untuk mabar sore, gratis minuman">
                    <?= $err('deskripsi') ?>
                </div>

                <div class="grid gap-4 sm:grid-cols-2" style="gap: var(--sp-4);">
                    <div class="field">
                        <label class="label" for="durasi_jam">Durasi (jam)</label>
                        <input class="input <?= isset($errors['durasi_jam']) ? 'is-invalid' : '' ?>"
                               id="durasi_jam" name="durasi_jam" type="number" min="1" max="24" step="1" required
                               value="<?= e($val('durasi_jam', (string) (int) ($paket['durasi_jam'] ?? 3))) ?>">
                        <?= $err('durasi_jam') ?>
                    </div>

                    <div class="field">
                        <label class="label" for="tipe_diskon">Skema potongan</label>
                        <select class="select" id="tipe_diskon" name="tipe_diskon" data-tipe-diskon>
                            <?php foreach ($daftarTipeDiskon as $td): ?>
                                <option value="<?= e($td) ?>" <?= $tipeDiskon === $td ? 'selected' : '' ?>>
                                    <?= e(status_label($td)) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="field" data-skema="persen">
                        <label class="label" for="diskon_persen">Diskon persen (0-90)</label>
                        <input class="input <?= isset($errors['diskon_persen']) ? 'is-invalid' : '' ?>"
                               id="diskon_persen" name="diskon_persen" type="number" min="0" max="90" step="0.5"
                               value="<?= e($val('diskon_persen', $angka($paket['diskon_persen'] ?? null))) ?>">
                        <?= $err('diskon_persen') ?>
                    </div>

                    <div class="field" data-skema="nominal">
                        <label class="label" for="diskon_nilai">Diskon nominal (Rp)</label>
                        <input class="input <?= isset($errors['diskon_nilai']) ? 'is-invalid' : '' ?>"
                               id="diskon_nilai" name="diskon_nilai" type="number" min="0" step="1000"
                               value="<?= e($val('diskon_nilai', $angka($paket['diskon_nilai'] ?? null))) ?>">
                        <?= $err('diskon_nilai') ?>
                    </div>

                    <div class="field">
                        <label class="label" for="harga">Harga paket tetap <small>(0 = hitung dari diskon)</small></label>
                        <input class="input <?= isset($errors['harga']) ? 'is-invalid' : '' ?>"
                               id="harga" name="harga" type="number" min="0" step="500"
                               value="<?= e($val('harga', $angka($paket['harga'] ?? null))) ?>">
                        <?= $err('harga') ?>
                    </div>

                    <div class="field">
                        <label class="label" for="status">Status</label>
                        <select class="select" id="status" name="status">
                            <?php foreach ($daftarStatus as $s): ?>
                                <option value="<?= e($s) ?>" <?= $val('status', (string) ($paket['status'] ?? 'aktif')) === $s ? 'selected' : '' ?>>
                                    <?= e(status_label($s)) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <div class="grid gap-4 sm:grid-cols-2" style="gap: var(--sp-4);">
                    <div class="field">
                        <label class="label" for="berlaku_mulai">Berlaku mulai <small>(opsional)</small></label>
                        <input class="input <?= isset($errors['berlaku_mulai']) ? 'is-invalid' : '' ?>"
                               type="date" id="berlaku_mulai" name="berlaku_mulai"
                               value="<?= e($val('berlaku_mulai', (string) ($paket['berlaku_mulai'] ?? ''))) ?>">
                        <?= $err('berlaku_mulai') ?>
                    </div>

                    <div class="field">
                        <label class="label" for="berlaku_akhir">Berlaku hingga <small>(opsional)</small></label>
                        <input class="input <?= isset($errors['berlaku_akhir']) ? 'is-invalid' : '' ?>"
                               type="date" id="berlaku_akhir" name="berlaku_akhir"
                               value="<?= e($val('berlaku_akhir', (string) ($paket['berlaku_akhir'] ?? ''))) ?>">
                        <?= $err('berlaku_akhir') ?>
                    </div>
                </div>

                <div class="divider"></div>

                <div class="flex flex-wrap gap-3">
                    <button type="submit" class="btn btn-primary btn-lg">
                        <?= $modeUbah ? 'Simpan Perubahan' : 'Simpan Paket' ?>
                    </button>
                    <a href="<?= url('admin/paket') ?>" class="btn btn-ghost btn-lg">Batal</a>
                </div>
            </form>
        </div>
    </div>

    <div class="lg:col-span-4">
        <div class="qs-card" style="padding: var(--sp-5);">
            <h3 style="margin: 0 0 var(--sp-3); font-size: var(--text-base);">Cara kerja</h3>
            <ul class="text-secondary" style="margin: 0; padding-left: var(--sp-5); font-size: var(--text-sm);">
                <li>Durasi paket hanya saran; pelanggan bebas memilih jam, potongan tetap dihitung dari skema.</li>
                <li>Isi <em>harga paket tetap</em> bila promo harus tampil dengan angka pasti.</li>
                <li>Kosongkan tanggal berarti paket berlaku terus sampai dinonaktifkan.</li>
                <li>Diskon persen maksimal 90% agar tetap masuk akal.</li>
            </ul>
        </div>

        <div class="qs-card" style="margin-top: var(--sp-4); padding: var(--sp-5);">
            <h3 style="margin: 0 0 var(--sp-3); font-size: var(--text-base);">Contoh penerapan</h3>
            <p class="text-secondary" style="margin: 0 0 var(--sp-2); font-size: var(--text-sm);">
                <span class="mono">3 jam + diskon 15%</span> cocok untuk PS4 reguler.
            </p>
            <p class="text-secondary" style="margin: 0; font-size: var(--text-sm);">
                <span class="mono">Harga tetap Rp50.000</span> untuk paket akhir pekan 4 jam.
            </p>
        </div>
    </div>
</div>

<?php
$scripts = '<script>
(function () {
  var pilih = document.querySelector("[data-tipe-diskon]");
  if (!pilih) return;

  // Sembunyikan isian skema yang tidak sedang dipakai supaya tidak membingungkan
  function tampil() {
    document.querySelectorAll("[data-skema]").forEach(function (kotak) {
      var cocok = kotak.getAttribute("data-skema") === pilih.value;
      kotak.hidden = !cocok;
      kotak.querySelectorAll("input").forEach(function (isi) { isi.disabled = !cocok; });
    });
  }

  pilih.addEventListener("change", tampil);
  tampil();
})();
</script>';
View::set('scripts', $scripts);

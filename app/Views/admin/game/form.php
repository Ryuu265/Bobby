<?php
/**
 * admin/game/form.php — Form tambah/ubah game termasuk penempatan di unit.
 * Checkbox memakai nama unit_id[]; nilai bawaan dibaca dari UnitGame::unitUntuk().
 */

use App\Core\View;

View::set('title', (($mode ?? 'baru') === 'ubah' ? 'Ubah Game' : 'Tambah Game') . ' — ' . config('app.name'));
View::set('page_title', ($mode ?? 'baru') === 'ubah' ? 'Ubah Data Game' : 'Tambah Game');
View::set('page_subtitle', 'Atur informasi game dan centang unit tempat game bisa dimainkan.');


$modeUbah  = ($mode ?? 'baru') === 'ubah';
$game      = $game ?? [];
$units     = $units ?? [];
$terpasang = array_map('intval', $terpasang ?? []);
$errors    = $errors ?? [];
$old       = $old ?? [];

$val = static function (string $key, string $default = '') use ($old): string {
    $nilai = $old[$key] ?? null;
    return ($nilai === null || $nilai === '') ? $default : (string) $nilai;
};
$err = static fn (string $key): string => isset($errors[$key])
    ? '<span class="field-error">' . e($errors[$key][0]) . '</span>'
    : '';

// Old input menang bila form dikirim ulang setelah validasi gagal
$centang = array_key_exists('unit_id', $old)
    ? array_map('intval', (array) $old['unit_id'])
    : $terpasang;

$kelompok = [];
foreach ($units as $u) {
    $kelompok[(string) $u['tipe']][] = $u;
}

$aksi = $modeUbah ? url('admin/game/' . (int) $game['id']) : url('admin/game');
?>
<form method="post" action="<?= e($aksi) ?>" enctype="multipart/form-data" novalidate class="grid gap-5 lg:grid-cols-12" style="align-items: start;">
    <?= csrf_field() ?>
    <?php if ($modeUbah): ?>
        <input type="hidden" name="_current_id" value="<?= (int) $game['id'] ?>">
    <?php endif; ?>
    <!-- jaga agar key unit_id selalu ada walau tidak ada yang dicentang -->
    <input type="hidden" name="unit_id[]" value="">

    <div class="lg:col-span-8">
        <div class="qs-card">
            <div class="flex flex-wrap items-center justify-between gap-2" style="margin-bottom: var(--sp-5);">
                <h2 class="display" style="margin: 0; font-size: var(--text-xl);">
                    <?= $modeUbah ? 'Data Game' : 'Game Baru' ?>
                </h2>
                <span class="badge <?= $modeUbah ? 'badge-cyan' : 'badge-violet' ?>">
                    <?= $modeUbah ? 'Mode ubah' : 'Mode tambah' ?>
                </span>
            </div>

            <div class="field">
                <label class="label" for="judul">Judul game</label>
                <input class="input <?= isset($errors['judul']) ? 'is-invalid' : '' ?>"
                       id="judul" name="judul" required minlength="2" maxlength="150"
                       value="<?= e($val('judul', (string) ($game['judul'] ?? ''))) ?>"
                       placeholder="eFootball 2024">
                <?= $err('judul') ?>
                <p class="text-muted" style="margin: 6px 0 0; font-size: var(--text-xs);">
                    Kombinasi judul + platform harus unik.
                </p>
            </div>

            <div class="grid gap-4 sm:grid-cols-3" style="gap: var(--sp-4);">
                <div class="field">
                    <label class="label" for="platform">Platform</label>
                    <select class="select <?= isset($errors['platform']) ? 'is-invalid' : '' ?>" id="platform" name="platform">
                        <?php foreach ($daftarPlatform as $p): ?>
                            <option value="<?= e($p) ?>" <?= $val('platform', (string) ($game['platform'] ?? 'Multi')) === $p ? 'selected' : '' ?>>
                                <?= e($p) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <?= $err('platform') ?>
                </div>

                <div class="field">
                    <label class="label" for="rating">Rating usia</label>
                    <select class="select <?= isset($errors['rating']) ? 'is-invalid' : '' ?>" id="rating" name="rating">
                        <?php foreach ($daftarRating as $r): ?>
                            <option value="<?= e($r) ?>" <?= $val('rating', (string) ($game['rating'] ?? '7+')) === $r ? 'selected' : '' ?>>
                                <?= e($r) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <?= $err('rating') ?>
                </div>

                <div class="field">
                    <label class="label" for="status">Status</label>
                    <select class="select <?= isset($errors['status']) ? 'is-invalid' : '' ?>" id="status" name="status">
                        <?php foreach ($daftarStatus as $s): ?>
                            <option value="<?= e($s) ?>" <?= $val('status', (string) ($game['status'] ?? 'aktif')) === $s ? 'selected' : '' ?>>
                                <?= e(status_label($s)) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <?= $err('status') ?>
                </div>
            </div>

            <div class="field">
                <label class="label" for="genre">Genre</label>
                <input class="input <?= isset($errors['genre']) ? 'is-invalid' : '' ?>"
                       id="genre" name="genre" required maxlength="60" list="saran-genre"
                       value="<?= e($val('genre', (string) ($game['genre'] ?? ''))) ?>"
                       placeholder="Sport, Racing, atau Fighting">
                <datalist id="saran-genre">
                    <?php foreach ($genre as $g): ?>
                        <option value="<?= e((string) $g) ?>"></option>
                    <?php endforeach; ?>
                </datalist>
                <?= $err('genre') ?>
                <p class="text-muted" style="margin: 6px 0 0; font-size: var(--text-xs);">
                    Satu genre saja agar filter katalog rapi. Contoh yang sudah dipakai bisa dipilih dari daftar.
                </p>
            </div>

            <div class="field">
                <label class="label" for="deskripsi">Deskripsi <small>(opsional, maks 1000 karakter)</small></label>
                <textarea class="textarea <?= isset($errors['deskripsi']) ? 'is-invalid' : '' ?>"
                          id="deskripsi" name="deskripsi" maxlength="1000" rows="4"
                          placeholder="Sinopsis singkat, mode main, jumlah pemain lokal."><?= e($val('deskripsi', (string) ($game['deskripsi'] ?? ''))) ?></textarea>
                <?= $err('deskripsi') ?>
            </div>

            <div class="field">
                <label class="label" for="cover">Cover game <small>(opsional, maks 2 MB)</small></label>
                <input class="input <?= isset($errors['cover']) ? 'is-invalid' : '' ?>"
                       type="file" id="cover" name="cover" accept="image/jpeg,image/png,image/webp">
                <?= $err('cover') ?>
            </div>

            <div class="divider"></div>

            <div class="flex flex-wrap gap-3">
                <button type="submit" class="btn btn-primary btn-lg">
                    <?= $modeUbah ? 'Simpan Perubahan' : 'Simpan Game' ?>
                </button>
                <a href="<?= url('admin/game') ?>" class="btn btn-ghost btn-lg">Batal</a>
            </div>
        </div>
    </div>

    <!-- ============ PENEMPATAN UNIT ============ -->
    <div class="lg:col-span-4">
        <div class="qs-card" style="padding: var(--sp-5);">
            <div class="flex items-center justify-between gap-2" style="margin-bottom: var(--sp-3);">
                <h3 style="margin: 0; font-size: var(--text-base);">Terpasang di unit</h3>
                <span class="badge badge-cyan badge-no-dot" data-hitung-unit><?= count($centang) ?> dipilih</span>
            </div>
            <p class="text-secondary" style="margin: 0 0 var(--sp-4); font-size: var(--text-xs);">
                Hanya unit yang dicentang yang menawarkan game ini ke pelanggan.
            </p>

            <?php if ($units === []): ?>
                <div class="empty-state" style="padding: var(--sp-6) var(--sp-2);">
                    <p style="margin: 0;">Belum ada unit terdaftar.</p>
                </div>
            <?php else: ?>
                <div style="max-height: 380px; overflow-y: auto;">
                    <?php foreach ($kelompok as $tipe => $daftar): ?>
                        <p class="side-group-title" style="padding: var(--sp-3) 0 var(--sp-2);"><?= e((string) $tipe) ?></p>
                        <div class="grid gap-2">
                            <?php foreach ($daftar as $u): ?>
                                <?php $uid = (int) $u['id']; $dipilih = in_array($uid, $centang, true); ?>
                                <label class="check" style="padding: 6px 0;">
                                    <input type="checkbox" name="unit_id[]" value="<?= $uid ?>" <?= $dipilih ? 'checked' : '' ?>>
                                    <span>
                                        <span class="mono" style="font-size: var(--text-sm);"><?= e($u['kode_unit']) ?></span>
                                        <span class="text-muted" style="font-size: var(--text-xs);"> · <?= e($u['nama']) ?></span>
                                    </span>
                                </label>
                            <?php endforeach; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <div class="flex flex-wrap gap-2" style="margin-top: var(--sp-4);">
                <button type="button" class="btn btn-outline btn-sm" data-pilih="semua">Pilih semua</button>
                <button type="button" class="btn btn-ghost btn-sm" data-pilih="kosong">Kosongkan</button>
            </div>
        </div>

        <?php if ($modeUbah && !empty($game['cover_path'])): ?>
            <div class="qs-card" style="margin-top: var(--sp-4); padding: var(--sp-5);">
                <h3 style="margin: 0 0 var(--sp-3); font-size: var(--text-base);">Cover saat ini</h3>
                <div class="unit-card__media" style="margin: 0; aspect-ratio: 3 / 4;">
                    <img src="<?= e(upload_url($game['cover_path'])) ?>" alt="Cover <?= e($game['judul']) ?>" loading="lazy">
                </div>
            </div>
        <?php endif; ?>
    </div>
</form>

<?php
$scripts = '<script>
(function () {
  var kotak = document.getElementsByName("unit_id[]");
  var label = document.querySelector("[data-hitung-unit]");
  if (!kotak.length) return;

  function hitung() {
    var dipilih = 0;
    kotak.forEach(function (k) { if (k.type === "checkbox" && k.checked) dipilih++; });
    if (label) label.textContent = dipilih + " dipilih";
  }

  kotak.forEach(function (k) {
    if (k.type === "checkbox") k.addEventListener("change", hitung);
  });

  document.querySelectorAll("[data-pilih]").forEach(function (tombol) {
    tombol.addEventListener("click", function () {
      var isi = tombol.getAttribute("data-pilih") === "semua";
      kotak.forEach(function (k) { if (k.type === "checkbox") k.checked = isi; });
      hitung();
    });
  });
})();
</script>';
View::set('scripts', $scripts);

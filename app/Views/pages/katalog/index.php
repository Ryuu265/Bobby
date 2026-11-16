<?php
/**
 * pages/katalog/index.php — Katalog unit PlayStation.
 * Filter tipe/status + kartu unit lengkap dengan grid slot ketersediaan hari ini.
 *
 * @var array $units
 * @var array $slot        unit_id => daftar slot hari ini
 * @var string $tipe
 * @var string $status
 * @var array $daftarTipe
 * @var array $daftarStatus
 * @var string $hariIni
 * @var array|null $authUser
 */

use App\Core\View;
use App\Models\Setting;

View::set('title', 'Katalog Unit — ' . Setting::get('nama_rental', config('app.name')));
View::set('meta_description', 'Daftar unit PS5, PS4, dan PS3 yang bisa disewa beserta ketersediaan jam hari ini.');

/** Tamu diarahkan login dulu sebelum bisa memesan. */
$tautanPesan = static fn (int $id): string => $authUser
    ? url('booking?unit=' . $id)
    : route('login.form');

$kelasSlot = static function (array $s): string {
    if ($s['tersedia']) {
        return '';
    }
    return $s['alasan'] === 'lewat' ? ' is-past' : ' is-taken';
};

$judulStatus = static fn (string $k): string => status_label($k);
$adaFilter = $tipe !== '' || $status !== '';
$jumlahKosong = count(array_filter(
    $units,
    static fn (array $u): bool => ($slot[(int) $u['id']] ?? []) !== []
        && in_array(true, array_column($slot[(int) $u['id']] ?? [], 'tersedia'), true)
));
?>
<section class="section" style="padding-bottom: var(--sp-6);">
    <div class="container-page">
        <span class="badge badge-cyan" style="margin-bottom: var(--sp-3);">Katalog Unit</span>
        <h1 class="display" style="font-size: var(--text-3xl); margin-bottom: var(--sp-2);">Pilih Slot Bermain Anda</h1>
        <p class="text-secondary" style="margin:0; max-width: 68ch;">
            Semua tarif di bawah adalah harga per jam per unit. Kotak di bawah kartu menunjukkan
            jam yang masih kosong untuk hari ini, <?= e(tgl_indo($hariIni)) ?> — dihitung langsung dari pesanan yang ada.
        </p>
    </div>
</section>

<section style="padding-bottom: var(--sp-8);">
    <div class="container-page">
        <form method="get" action="<?= url('katalog') ?>" class="qs-panel" style="padding: var(--sp-4) var(--sp-5);">
            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4" style="align-items: end;">
                <div class="field" style="margin-bottom: 0;">
                    <label class="label" for="tipe">Jenis konsol</label>
                    <select class="select" id="tipe" name="tipe">
                        <option value="">Semua konsol</option>
                        <?php foreach ($daftarTipe as $t): ?>
                            <option value="<?= e($t) ?>" <?= $tipe === $t ? 'selected' : '' ?>><?= e($t) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="field" style="margin-bottom: 0;">
                    <label class="label" for="status">Status unit</label>
                    <select class="select" id="status" name="status">
                        <option value="">Semua status</option>
                        <?php foreach ($daftarStatus as $s): ?>
                            <option value="<?= e($s) ?>" <?= $status === $s ? 'selected' : '' ?>><?= e($judulStatus($s)) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="flex flex-wrap gap-2">
                    <button type="submit" class="btn btn-primary">Terapkan Filter</button>
                    <?php if ($adaFilter): ?>
                        <a href="<?= url('katalog') ?>" class="btn btn-ghost">Reset</a>
                    <?php endif; ?>
                </div>

                <p class="text-muted" style="margin:0; font-size: var(--text-xs); line-height: 1.7;">
                    <?= count($units) ?> unit ditampilkan · <?= $jumlahKosong ?> masih punya slot kosong hari ini
                </p>
            </div>
        </form>
    </div>
</section>

<section style="padding-bottom: var(--sp-12);">
    <div class="container-page">

        <?php if ($units === []): ?>
            <div class="qs-card empty-state">
                <h2 class="display" style="font-size: var(--text-lg);">Belum ada unit yang cocok</h2>
                <p class="text-secondary" style="max-width: 46ch; margin-inline: auto;">
                    Coba ubah filter konsol atau status, atau lihat daftar game yang tersedia
                    sebelum menentukan pilihan slot.
                </p>
                <div class="flex flex-wrap gap-2 justify-center" style="margin-top: var(--sp-4);">
                    <a href="<?= url('katalog') ?>" class="btn btn-outline btn-sm">Hapus Filter</a>
                    <a href="<?= url('katalog/game') ?>" class="btn btn-outline btn-sm">Lihat Daftar Game</a>
                </div>
            </div>
        <?php else: ?>
            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                <?php foreach ($units as $u): ?>
                    <?php
                    $unitId = (int) $u['id'];
                    $slotUnit = $slot[$unitId] ?? [];
                    $kosong = count(array_filter($slotUnit, static fn (array $s): bool => $s['tersedia']));
                    $statusNow = (string) $u['status_realtime'];
                    ?>
                    <article class="qs-card qs-card--hoverable unit-card">
                        <a href="<?= url('katalog/unit/' . $u['kode_unit']) ?>" class="unit-card__media block"
                           aria-label="Lihat detail <?= e($u['nama']) ?>">
                            <?php if (!empty($u['foto_path'])): ?>
                                <img src="<?= e(upload_url($u['foto_path'])) ?>" alt="Foto <?= e($u['nama']) ?>" loading="lazy">
                            <?php else: ?>
                                <span class="unit-card__code"><?= e($u['kode_unit']) ?></span>
                            <?php endif; ?>
                        </a>

                        <div class="flex items-start justify-between gap-3">
                            <div class="min-w-0">
                                <h2 style="margin:0; font-size: var(--text-base); font-weight:600;"><?= e($u['nama']) ?></h2>
                                <p class="text-muted" style="margin:2px 0 0; font-size: var(--text-xs);">
                                    <?= e($u['tipe']) ?> · <?= e(status_label((string) $u['kategori'])) ?> ·
                                    <?= (int) $u['kapasitas'] ?> pemain · <?= (int) $u['jumlah_game'] ?> game
                                </p>
                            </div>
                            <span class="badge <?= status_class($statusNow) ?>"><?= e($judulStatus($statusNow)) ?></span>
                        </div>

                        <p class="display" style="margin: var(--sp-3) 0 0; font-size: var(--text-xl); color: var(--cyan);">
                            <?= rupiah($u['harga_per_jam']) ?><span class="text-muted" style="font-size: var(--text-xs);"> / jam</span>
                        </p>

                        <div style="margin-top: var(--sp-4);">
                            <div class="flex items-center justify-between gap-2" style="margin-bottom: var(--sp-2);">
                                <span class="text-muted" style="font-size: var(--text-xs); text-transform: uppercase; letter-spacing: .06em;">
                                    Slot hari ini
                                </span>
                                <span class="text-<?= $kosong > 0 ? 'success' : 'danger' ?>" style="font-size: var(--text-xs); font-weight:600;">
                                    <?= $kosong ?> kosong
                                </span>
                            </div>

                            <?php if ($slotUnit === []): ?>
                                <p class="text-muted" style="margin:0; font-size: var(--text-xs);">
                                    Jam operasional hari ini sudah lewat. Silakan pilih tanggal lain di form pemesanan.
                                </p>
                            <?php else: ?>
                                <div class="slot-grid" role="list" aria-label="Ketersediaan jam untuk <?= e($u['kode_unit']) ?>">
                                    <?php foreach ($slotUnit as $s): ?>
                                        <span class="slot<?= $kelasSlot($s) ?>" role="listitem"
                                              title="<?= $s['tersedia'] ? 'Jam ' . e($s['label']) . ' masih kosong' : 'Jam ' . e($s['label']) . ' ' . ($s['alasan'] === 'lewat' ? 'sudah terlewat' : 'sudah terisi') ?>">
                                            <?= e($s['label']) ?>
                                        </span>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </div>

                        <div class="flex flex-wrap gap-2" style="margin-top: var(--sp-4);">
                            <a href="<?= url('katalog/unit/' . $u['kode_unit']) ?>" class="btn btn-outline btn-sm">Detail Unit</a>
                            <?php if ($statusNow === 'maintenance'): ?>
                                <button type="button" class="btn btn-sm" disabled>Sedang Perawatan</button>
                            <?php else: ?>
                                <a href="<?= e($tautanPesan($unitId)) ?>" class="btn btn-primary btn-sm">Pesan Unit Ini</a>
                            <?php endif; ?>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>

            <p class="text-muted" style="font-size: var(--text-xs); margin-top: var(--sp-5);">
                Catatan: kotak bergaris merah berarti jam tersebut sudah dipesan orang lain,
                sedangkan kotak pudar berarti jamnya sudah terlewat hari ini.
            </p>
        <?php endif; ?>

    </div>
</section>

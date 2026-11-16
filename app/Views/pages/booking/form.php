<?php
/**
 * pages/booking/form.php — Form pemesanan (inti aplikasi).
 *
 * Alur: pilih unit -> pilih tanggal & durasi -> klik slot jam -> pilih paket &
 * metode bayar -> kirim. Ketersediaan slot dan total harga dimuat ulang secara
 * live oleh public/assets/js/booking.js lewat /jadwal/{id} dan /harga.
 *
 * @var array $units
 * @var array $unit
 * @var array $daftarTanggal
 * @var string $tanggal
 * @var int $durasi
 * @var int $durasiMaks
 * @var int $controller
 * @var int $maksController
 * @var array $paket
 * @var int $paketDipilih
 * @var string $metode
 * @var array $slots
 * @var array $operasional
 * @var array $harga
 * @var float $tarifController
 * @var string $rekening
 * @var array $errors
 * @var array|null $authUser
 */

use App\Core\View;
use App\Models\Setting;

View::set('title', 'Pesan Slot — ' . Setting::get('nama_rental', config('app.name')));

$err = static fn (string $key): string => isset($errors[$key]) ? '<span class="field-error">' . e($errors[$key][0]) . '</span>' : '';

$kelasSlot = static fn (array $s): string => $s['tersedia'] ? '' : ($s['alasan'] === 'lewat' ? ' is-past' : ' is-taken');
$jamTerpilih = (string) old('jam');

/** Angka uang untuk rincian: nilai negatif (diskon) diberi tanda minus di depan. */
$uang = static fn (float|int|string $n): string => ($n < 0 ? '-' : '') . rupiah(abs((float) $n));

$tutupLabel = $operasional['tutup'] === 24 ? '24.00' : sprintf('%02d.00', $operasional['tutup']);

/** Label singkat diskon paket untuk kartu pilihan. */
$labelPaket = static function (array $p): string {
    if ((float) $p['harga'] > 0) {
        return 'Paket ' . rupiah((float) $p['harga']);
    }
    if ($p['tipe_diskon'] === 'persen') {
        return 'Diskon ' . rtrim(rtrim(number_format((float) $p['diskon_persen'], 2, ',', ''), '0'), '.') . '%';
    }
    return 'Potongan ' . rupiah((float) $p['diskon_nilai']);
};

// Data ringan untuk JS (kode unit, tarif, status real-time)
$dataUnit = json_encode(
    array_map(static fn (array $u): array => [
        'id'     => (int) $u['id'],
        'kode'   => $u['kode_unit'],
        'tipe'   => $u['tipe'],
        'harga'  => (float) $u['harga_per_jam'],
        'status' => (string) $u['status_realtime'],
    ], $units),
    JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE
);

View::set('scripts', '<script id="dataUnit" type="application/json">' . $dataUnit . '</script>'
    . '<script src="' . asset('js/booking.js') . '" defer></script>');
?>
<section class="section" style="padding-bottom: var(--sp-6);">
    <div class="container-page">
        <p class="text-muted" style="margin:0 0 var(--sp-3); font-size: var(--text-sm);">
            <a href="<?= url('katalog') ?>">Katalog Unit</a> &rsaquo; Form Pemesanan
        </p>
        <h1 class="display" style="font-size: var(--text-3xl); margin-bottom: var(--sp-2);">Pesan Slot Bermain</h1>
        <p class="text-secondary" style="margin:0; max-width: 68ch;">
            Jam yang Anda pilih langsung dikunci begitu pesanan dikirim. Batas pembatalan mandiri
            <?= (int) Setting::get('batas_menit_batal', 60) ?> menit sebelum jadwal mulai.
        </p>
    </div>
</section>

<form id="formBooking" method="post" action="<?= url('booking') ?>"
      data-jadwal-url="<?= url('jadwal') ?>" data-harga-url="<?= url('harga') ?>" novalidate>
    <?= csrf_field() ?>

    <div class="container-page grid gap-4 pb-12 lg:grid-cols-3">

        <!-- ================= KOLOM UTAMA ================= -->
        <div class="lg:col-span-2 grid gap-4" style="align-content: start;">

            <!-- 1. Unit, tanggal, durasi -->
            <div class="qs-panel">
                <div class="qs-panel__head">
                    <h2 class="display" style="font-size: var(--text-base); margin:0;">1 · Unit &amp; Waktu</h2>
                    <span class="badge <?= status_class((string) $unit['status_realtime']) ?>" id="badgeUnitStatus">
                        <?= e(status_label((string) $unit['status_realtime'])) ?>
                    </span>
                </div>
                <div class="qs-panel__body">

                    <div class="field">
                        <label class="label" for="unit">Pilih unit</label>
                        <select class="select <?= isset($errors['unit']) ? 'is-invalid' : '' ?>" id="unit" name="unit" required>
                            <?php foreach ($units as $u): ?>
                                <option value="<?= (int) $u['id'] ?>" <?= (int) $unit['id'] === (int) $u['id'] ? 'selected' : '' ?>>
                                    <?= e($u['kode_unit']) ?> · <?= e($u['tipe']) ?> ·
                                    <?= e(rupiah($u['harga_per_jam'])) ?>/jam
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <?= $err('unit') ?>
                        <p class="text-muted" style="margin: var(--sp-2) 0 0; font-size: var(--text-xs);" id="unitInfo">
                            <?= e($unit['kode_unit']) ?> — <?= e($unit['nama']) ?> ·
                            kapasitas <?= (int) $unit['kapasitas'] ?> pemain ·
                            tarif <?= e(rupiah($unit['harga_per_jam'])) ?>/jam
                        </p>
                    </div>

                    <!-- Ikhtisar status semua unit (real-time) -->
                    <div class="field">
                        <span class="label">Status unit saat ini</span>
                        <div class="flex flex-wrap gap-2">
                            <?php foreach ($units as $u): ?>
                                <span class="badge <?= status_class((string) $u['status_realtime']) ?> badge-no-dot"
                                      data-status-unit="<?= (int) $u['id'] ?>"
                                      title="<?= e($u['nama']) ?>">
                                    <?= e($u['kode_unit']) ?> · <?= e(status_label((string) $u['status_realtime'])) ?>
                                </span>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <div class="grid gap-4 sm:grid-cols-3">
                        <div class="field">
                            <label class="label" for="tanggal">Tanggal</label>
                            <select class="select <?= isset($errors['tanggal']) ? 'is-invalid' : '' ?>" id="tanggal" name="tanggal" required>
                                <?php foreach ($daftarTanggal as $d): ?>
                                    <option value="<?= e($d) ?>" <?= $d === $tanggal ? 'selected' : '' ?>><?= e(tgl_indo($d)) ?></option>
                                <?php endforeach; ?>
                            </select>
                            <?= $err('tanggal') ?>
                        </div>

                        <div class="field">
                            <label class="label" for="durasi">Durasi sewa</label>
                            <select class="select <?= isset($errors['durasi']) ? 'is-invalid' : '' ?>" id="durasi" name="durasi" required>
                                <?php for ($d = 1; $d <= $durasiMaks; $d++): ?>
                                    <option value="<?= $d ?>" <?= $d === $durasi ? 'selected' : '' ?>><?= $d ?> jam</option>
                                <?php endfor; ?>
                            </select>
                            <?= $err('durasi') ?>
                        </div>

                        <div class="field">
                            <label class="label" for="controller">
                                Controller <small>tambahan</small>
                            </label>
                            <input class="input <?= isset($errors['controller']) ? 'is-invalid' : '' ?>"
                                   type="number" id="controller" name="controller" min="0" max="<?= $maksController ?>" step="1"
                                   value="<?= (int) $controller ?>">
                            <?= $err('controller') ?>
                            <small class="text-muted">Rp<?= rupiah($tarifController, false) ?>/jam per controller</small>
                        </div>
                    </div>

                </div>
            </div>

            <!-- 2. Slot jam -->
            <div class="qs-panel">
                <div class="qs-panel__head">
                    <h2 class="display" style="font-size: var(--text-base); margin:0;">2 · Jam Mulai</h2>
                    <span class="text-muted" style="font-size: var(--text-xs);" id="operasionalInfo">
                        Operasional <?= e(sprintf('%02d.00', $operasional['buka'])) ?> - <?= e($tutupLabel) ?>
                    </span>
                </div>
                <div class="qs-panel__body">

                    <input type="hidden" name="jam" id="start_time" value="<?= $jamTerpilih ?>">

                    <div class="slot-grid" id="slotGrid">
                        <?php if ($slots === []): ?>
                            <p class="text-secondary" style="margin:0; font-size: var(--text-sm);">
                                Belum ada slot untuk tanggal ini. Ubah tanggal atau durasi sewa Anda.
                            </p>
                        <?php else: ?>
                            <?php foreach ($slots as $s): ?>
                                <button type="button"
                                        class="slot<?= $kelasSlot($s) ?><?= $s['value'] === $jamTerpilih ? ' is-selected' : '' ?>"
                                        data-value="<?= e($s['value']) ?>"
                                        <?= $s['tersedia'] ? '' : 'disabled' ?>
                                        title="<?= $s['tersedia'] ? 'Pesan mulai jam ' . e($s['label']) : ($s['alasan'] === 'lewat' ? 'Sudah terlewat' : 'Sudah terisi pesanan lain') ?>">
                                    <?= e($s['label']) ?>
                                </button>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>

                    <div class="flex flex-wrap gap-3" style="margin-top: var(--sp-4); font-size: var(--text-xs); color: var(--text-muted);">
                        <span class="flex items-center gap-2"><span class="slot" style="padding:.2rem .5rem; font-size:10px;">&nbsp;</span> Kosong</span>
                        <span class="flex items-center gap-2"><span class="slot is-taken" style="padding:.2rem .5rem; font-size:10px;">&nbsp;</span> Terisi</span>
                        <span class="flex items-center gap-2"><span class="slot is-past" style="padding:.2rem .5rem; font-size:10px;">&nbsp;</span> Terlewat</span>
                        <span class="flex items-center gap-2"><span class="slot is-selected" style="padding:.2rem .5rem; font-size:10px;">&nbsp;</span> Pilihan Anda</span>
                    </div>

                    <p class="text-secondary" style="margin: var(--sp-4) 0 0; font-size: var(--text-sm);" id="slotInfo" role="status" aria-live="polite">
                        <?php if ($jamTerpilih !== ''): ?>
                            Mulai pukul <strong><?= e(jam($jamTerpilih)) ?></strong> selama <?= (int) $durasi ?> jam.
                        <?php else: ?>
                            Klik salah satu jam di atas untuk memulai pesanan.
                        <?php endif; ?>
                    </p>
                    <?= $err('jam') ?>
                    <span class="field-error" id="slotPesan" style="display:none;"></span>
                </div>
            </div>

            <!-- 3. Paket -->
            <div class="qs-panel">
                <div class="qs-panel__head">
                    <h2 class="display" style="font-size: var(--text-base); margin:0;">3 · Paket &amp; Promo</h2>
                    <span class="text-muted" style="font-size: var(--text-xs);">opsional</span>
                </div>
                <div class="qs-panel__body">
                    <?php if ($paket === []): ?>
                        <p class="text-secondary" style="margin:0; font-size: var(--text-sm);">
                            Tidak ada paket yang berlaku pada tanggal ini. Tenang, tarif reguler tetap dihitung per jam.
                        </p>
                    <?php else: ?>
                        <div class="grid gap-3 sm:grid-cols-2">
                            <label class="choice">
                                <input type="radio" name="paket" value="" <?= $paketDipilih === 0 ? 'checked' : '' ?>>
                                <span class="flex items-center justify-between gap-2">
                                    <strong style="font-size: var(--text-sm); font-weight:600;">Tanpa paket</strong>
                                    <span class="badge badge-neutral badge-no-dot">Tarif reguler</span>
                                </span>
                                <span class="text-muted" style="display:block; margin-top:4px; font-size: var(--text-xs);">
                                    Bayar sesuai durasi dan controller yang dipilih.
                                </span>
                            </label>

                            <?php foreach ($paket as $p): ?>
                                <label class="choice">
                                    <input type="radio" name="paket" value="<?= (int) $p['id'] ?>"
                                           <?= $paketDipilih === (int) $p['id'] ? 'checked' : '' ?>>
                                    <span class="flex items-center justify-between gap-2">
                                        <strong style="font-size: var(--text-sm); font-weight:600;"><?= e($p['nama']) ?></strong>
                                        <span class="badge badge-violet badge-no-dot"><?= e($labelPaket($p)) ?></span>
                                    </span>
                                    <span class="text-muted" style="display:block; margin-top:4px; font-size: var(--text-xs);">
                                        Untuk sewa <?= (int) $p['durasi_jam'] ?> jam<?= !empty($p['deskripsi']) ? ' · ' . e($p['deskripsi']) : '' ?>
                                    </span>
                                </label>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                    <?= $err('paket') ?>
                </div>
            </div>

            <!-- 4. Metode bayar & catatan -->
            <div class="qs-panel">
                <div class="qs-panel__head">
                    <h2 class="display" style="font-size: var(--text-base); margin:0;">4 · Pembayaran &amp; Catatan</h2>
                </div>
                <div class="qs-panel__body">
                    <div class="field">
                        <span class="label">Metode pembayaran</span>
                        <div class="grid gap-3 sm:grid-cols-2">
                            <?php foreach (['transfer' => 'Transfer bank', 'bayar_di_tempat' => 'Bayar di tempat'] as $nilai => $label): ?>
                                <label class="choice">
                                    <input type="radio" name="metode" value="<?= e($nilai) ?>"
                                           <?= $metode === $nilai ? 'checked' : '' ?> data-metode>
                                    <strong style="font-size: var(--text-sm); font-weight:600;"><?= e($label) ?></strong>
                                    <span class="text-muted" style="display:block; margin-top:4px; font-size: var(--text-xs);">
                                        <?= $nilai === 'transfer'
                                            ? 'Unggah bukti transfer setelah pesanan jadi.'
                                            : 'Bayar tunai/kasir saat datang, dibayar paling lambat sebelum sewa selesai.' ?>
                                    </span>
                                </label>
                            <?php endforeach; ?>
                        </div>
                        <?= $err('metode') ?>
                    </div>

                    <div class="qs-card qs-card--flat" id="panelRekening" style="padding: var(--sp-4); margin-bottom: var(--sp-5);"
                         <?= $metode === 'transfer' ? '' : 'hidden' ?>>
                        <h3 class="display" style="font-size: var(--text-sm); letter-spacing: .08em; margin-bottom: var(--sp-2);">
                            Rekening Tujuan
                        </h3>
                        <p class="mono" style="margin:0; font-size: var(--text-base); color: var(--cyan);">
                            <?= e($rekening) ?>
                        </p>
                        <p class="text-muted" style="margin: var(--sp-2) 0 0; font-size: var(--text-xs);">
                            a.n. <?= e(Setting::get('nama_rental', config('app.name'))) ?>. Simpan bukti transfer untuk diunggah
                            di halaman detail pesanan.
                        </p>
                    </div>

                    <div class="field" style="margin-bottom: 0;">
                        <label class="label" for="catatan">Catatan untuk operator <small>(opsional)</small></label>
                        <textarea class="textarea <?= isset($errors['catatan']) ? 'is-invalid' : '' ?>"
                                  id="catatan" name="catatan" maxlength="255"
                                  placeholder="Contoh: tolong siapkan EA FC 25 dan 1 controller ekstra."><?= old('catatan') ?></textarea>
                        <?= $err('catatan') ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- ================= RINGKASAN BIAYA ================= -->
        <aside style="position: sticky; top: calc(var(--header-h) + var(--sp-4)); align-self: start;">
            <div class="qs-card">
                <h2 class="display" style="font-size: var(--text-base); margin-bottom: var(--sp-4);">Ringkasan Biaya</h2>

                <div class="flex items-center justify-between gap-3" style="margin-bottom: var(--sp-4);">
                    <div>
                        <p style="margin:0; font-weight:600; font-size: var(--text-sm);" id="ringkasanUnit"><?= e($unit['kode_unit']) ?></p>
                        <p class="text-muted" style="margin:2px 0 0; font-size: var(--text-xs);" id="ringkasanTanggal"><?= e(tgl_indo($tanggal)) ?></p>
                    </div>
                    <span class="text-cyan mono" style="font-size: var(--text-sm);" id="ringkasanTarif">
                        <?= e(rupiah($harga['harga_per_jam'])) ?>/jam
                    </span>
                </div>

                <ul style="list-style:none; margin:0; padding:0;" id="ringkasanRincian">
                    <?php foreach ($harga['rincian'] as $r): ?>
                        <li class="flex items-start justify-between gap-3 py-2" style="border-bottom: 1px solid var(--border-soft);">
                            <span class="text-secondary" style="font-size: var(--text-sm);"><?= e($r['label']) ?></span>
                            <span class="mono" style="font-size: var(--text-sm); white-space:nowrap;"><?= e($uang($r['nilai'])) ?></span>
                        </li>
                    <?php endforeach; ?>
                </ul>

                <dl class="grid gap-2" style="margin: var(--sp-4) 0 0; grid-template-columns: 1fr auto;">
                    <dt class="text-secondary" style="font-size: var(--text-sm);">Subtotal</dt>
                    <dd class="mono" style="margin:0; text-align:right; font-size: var(--text-sm);" id="ringkasanSubtotal">
                        <?= e(rupiah($harga['subtotal'])) ?>
                    </dd>
                    <dt class="text-secondary" style="font-size: var(--text-sm);">Diskon</dt>
                    <dd class="mono text-success" style="margin:0; text-align:right; font-size: var(--text-sm);" id="ringkasanDiskon">
                        <?= e(rupiah($harga['diskon'])) ?>
                    </dd>
                </dl>

                <hr class="divider">

                <div class="flex items-end justify-between gap-3">
                    <span class="display" style="font-size: var(--text-sm); color: var(--text-secondary);">Total</span>
                    <span class="display" style="font-size: var(--text-2xl); color: var(--cyan);" id="ringkasanTotal">
                        <?= e(rupiah($harga['total'])) ?>
                    </span>
                </div>

                <p class="text-muted" style="margin: var(--sp-2) 0 0; font-size: var(--text-xs);">
                    Rincian final dihitung ulang oleh sistem saat pesanan dikirim.
                </p>

                <?php if (!$authUser): ?>
                    <a href="<?= route('login.form') ?>" class="btn btn-primary btn-block btn-lg" style="margin-top: var(--sp-4);">
                        Masuk untuk Memesan
                    </a>
                <?php else: ?>
                    <button type="submit" class="btn btn-primary btn-block btn-lg" id="tombolKirim" style="margin-top: var(--sp-4);">
                        Pesan &amp; Kunci Jadwal
                    </button>
                    <p class="text-muted" style="margin: var(--sp-3) 0 0; font-size: var(--text-xs); text-align:center;" id="pesanValidasi">
                        Pesanan dibuat atas nama <?= e($authUser['nama']) ?>.
                    </p>
                <?php endif; ?>
            </div>
        </aside>

    </div>
</form>

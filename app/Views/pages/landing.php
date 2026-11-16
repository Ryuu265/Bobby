<?php
/**
 * pages/landing.php — Beranda Qengamestation.
 * Isi: hero + status live, keunggulan, katalog unit singkat, tabel harga,
 * paket/promo, jam operasional & lokasi, CTA.
 *
 * CONTOH REFERENSI gaya markup: lihat pola kelas & penggunaan token di sini.
 */

use App\Core\Pricing;
use App\Core\Schedule;
use App\Core\View;
use App\Models\Game;
use App\Models\Setting;
use App\Models\Unit;

View::set('title', Setting::get('nama_rental', config('app.name')) . ' — Sewa PS5, PS4 & PS3');
View::set('meta_description', Setting::get('tagline', ''));

$jamHariIni = Schedule::jamOperasional(date('Y-m-d'));
$perkiraanJam = [];
foreach (['PS5' => 20000, 'PS4' => 12000, 'PS3' => 8000] as $tipe => $fallback) {
    $termurah = null;
    foreach ($units as $u) {
        if ($u['tipe'] === $tipe && $u['status'] !== 'maintenance') {
            $termurah = $termurah === null ? (float) $u['harga_per_jam'] : min($termurah, (float) $u['harga_per_jam']);
        }
    }
    $perkiraanJam[$tipe] = $termurah ?? (float) $fallback;
}
$games = array_slice(Game::katalog(), 0, 8);
?>

<!-- ============ HERO ============ -->
<section class="relative overflow-hidden">
    <div class="container-page grid gap-10 py-14 md:py-20 lg:grid-cols-12 lg:items-center">

        <div class="lg:col-span-7">
            <span class="badge badge-cyan mb-4">
                Buka pukul <?= e(sprintf('%02d.00', $jamHariIni['buka'])) ?> - <?= $jamHariIni['tutup'] === 24 ? '24.00' : e(sprintf('%02d.00', $jamHariIni['tutup'])) ?> WIB
            </span>

            <h1 class="display" style="font-size: var(--text-5xl); margin-bottom: var(--sp-4); color: var(--text-primary);">
                Sewa PlayStation<br>
                <span style="color: var(--cyan);">tanpa drama jadwal</span>
            </h1>

            <p class="text-body-long" style="font-size: var(--text-lg); color: var(--text-secondary);">
                <?= e(Setting::get('tagline', 'Main santai, bayar hemat.')) ?>
                Cek unit yang benar-benar kosong, pesan dua menit selesai, lalu tinggal datang.
            </p>

            <div class="flex flex-wrap gap-3" style="margin-top: var(--sp-6);">
                <a href="<?= url('booking') ?>" class="btn btn-primary btn-lg">
                    Pesan Sekarang
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round"><path d="M5 12h14M13 5l7 7-7 7"/></svg>
                </a>
                <a href="<?= url('katalog') ?>" class="btn btn-outline btn-lg">Lihat Semua Unit</a>
            </div>

            <dl class="grid grid-cols-3 gap-6" style="margin-top: var(--sp-10); max-width: 520px;">
                <?php foreach ([
                    ['label' => 'Unit tersedia', 'nilai' => (int) $jumlahTersedia . ' unit'],
                    ['label' => 'Mulai dari', 'nilai' => rupiah(min($perkiraanJam), false) . '/jam'],
                    ['label' => 'Game terpasang', 'nilai' => count(Game::katalog()) . '+ judul'],
                ] as $stat): ?>
                    <div>
                        <dt class="text-muted" style="font-size: var(--text-xs); text-transform: uppercase; letter-spacing: .07em;"><?= e($stat['label']) ?></dt>
                        <dd class="display" style="font-size: var(--text-2xl); margin: 4px 0 0; color: var(--text-primary);"><?= e($stat['nilai']) ?></dd>
                    </div>
                <?php endforeach; ?>
            </dl>
        </div>

        <!-- Papan status live -->
        <div class="lg:col-span-5">
            <div class="qs-card" style="padding: var(--sp-5);">
                <div class="flex items-center justify-between" style="margin-bottom: var(--sp-4);">
                    <h2 class="display" style="font-size: var(--text-lg); margin: 0;">Status Hari Ini</h2>
                    <span class="badge badge-success">Live</span>
                </div>

                <?php if (empty($statusHari)): ?>
                    <p class="text-secondary" style="margin:0;">Belum ada unit terdaftar.</p>
                <?php else: ?>
                    <ul class="grid gap-2" style="list-style:none; margin:0; padding:0;">
                        <?php foreach ($statusHari as $baris): /** @var array $u */ $u = $baris['unit']; ?>
                            <li class="flex items-center justify-between gap-3 rounded-lg px-3 py-2"
                                style="background: var(--ink-800); border: 1px solid var(--border-soft);">
                                <span class="flex items-center gap-3">
                                    <span class="mono" style="font-size: var(--text-sm); color: var(--text-primary);"><?= e($u['kode_unit']) ?></span>
                                    <span class="text-muted" style="font-size: var(--text-xs);"><?= e($u['tipe']) ?></span>
                                </span>
                                <span class="flex items-center gap-3">
                                    <span class="text-muted" style="font-size: var(--text-xs);"><?= (int) $baris['slot'] ?> slot kosong</span>
                                    <span class="badge <?= status_class($baris['status']) ?>"><?= e(status_label($baris['status'])) ?></span>
                                </span>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>

                <a href="<?= url('booking') ?>" class="btn btn-outline btn-block" style="margin-top: var(--sp-4);">Cek Jadwal &amp; Pesan</a>
            </div>
        </div>
    </div>
</section>

<!-- ============ KEUNGGULAN ============ -->
<section class="section" style="padding-top: 0;">
    <div class="container-page">
        <h2 class="display" style="font-size: var(--text-2xl);">Kenapa main di sini?</h2>
        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4" style="margin-top: var(--sp-6);">
            <?php foreach ([
                ['cyan',   'Jadwal transparan', 'Slot kosong terlihat real-time, tidak ada cerita "kok sudah dipakai orang".'],
                ['violet', 'Harga jelas',       'Terhitung otomatis: jam sewa + controller tambahan, tanpa biaya tersembunyi.'],
                ['pink',   'Perawatan rutin',   'Unit dengan status maintenance otomatis terkunci dari pemesanan.'],
                ['cyan',   'Bayar fleksibel',   'Transfer bank atau bayar di tempat, bukti upload langsung terverifikasi.'],
            ] as [$warna, $judul, $isi]): ?>
                <article class="qs-card qs-card--hoverable">
                    <span class="badge badge-<?= $warna ?>" style="margin-bottom: var(--sp-3);"><?= e($judul) ?></span>
                    <p class="text-secondary" style="margin:0; font-size: var(--text-sm);"><?= e($isi) ?></p>
                </article>
            <?php endforeach; ?>
        </div>
        <p class="text-secondary" style="margin-top: var(--sp-5); max-width: 70ch;">
            <?= e(Setting::get('deskripsi_tempat', '')) ?>
        </p>
    </div>
</section>

<!-- ============ KATALOG UNIT SINGKAT ============ -->
<section class="section" style="padding-top: 0;">
    <div class="container-page">
        <div class="flex flex-wrap items-end justify-between gap-3">
            <div>
                <h2 class="display" style="font-size: var(--text-2xl); margin-bottom: 4px;">Pilihan Unit</h2>
                <p class="text-secondary" style="margin:0; font-size: var(--text-sm);">Klik unit untuk melihat game &amp; jadwalnya.</p>
            </div>
            <a href="<?= url('katalog') ?>" class="btn btn-outline btn-sm">Lihat Semua (<?= count($units) ?>)</a>
        </div>

        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3" style="margin-top: var(--sp-6);">
            <?php foreach ($unggulan as $u): ?>
                <a href="<?= url('katalog/unit/' . $u['kode_unit']) ?>" class="qs-card qs-card--hoverable unit-card block">
                    <div class="unit-card__media">
                        <?php if ($u['foto_path']): ?>
                            <img src="<?= e(upload_url($u['foto_path'])) ?>" alt="Foto <?= e($u['nama']) ?>" loading="lazy">
                        <?php else: ?>
                            <span class="unit-card__code"><?= e($u['kode_unit']) ?></span>
                        <?php endif; ?>
                    </div>
                    <div class="flex items-start justify-between gap-3">
                        <div>
                            <h3 style="margin:0; font-size: var(--text-base); font-weight:600;"><?= e($u['nama']) ?></h3>
                            <p class="text-muted" style="margin:2px 0 0; font-size: var(--text-xs);">
                                <?= e($u['tipe']) ?> · <?= (int) $u['kapasitas'] ?> pemain · <?= (int) $u['jumlah_game'] ?> game
                            </p>
                        </div>
                        <span class="badge <?= status_class($u['status_realtime']) ?>"><?= e(status_label($u['status_realtime'])) ?></span>
                    </div>
                    <p class="text-secondary" style="margin: var(--sp-3) 0 0; font-size: var(--text-sm);">
                        <?= e(mb_substr((string) $u['deskripsi'], 0, 90)) ?>
                    </p>
                    <p class="display" style="margin: var(--sp-3) 0 0; font-size: var(--text-xl); color: var(--cyan);">
                        <?= rupiah($u['harga_per_jam']) ?><span class="text-muted" style="font-size: var(--text-xs);"> / jam</span>
                    </p>
                </a>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<!-- ============ TABEL HARGA ============ -->
<section class="section" style="padding-top: 0;">
    <div class="container-page">
        <div class="qs-panel">
            <div class="qs-panel__head">
                <h2 class="display" style="font-size: var(--text-lg); margin:0;">Daftar Harga Sewa</h2>
                <span class="text-muted" style="font-size: var(--text-xs);">Berlaku <?= tgl_indo(date('Y-m-d')) ?></span>
            </div>
            <div class="qs-panel__body" style="padding: 0;">
                <div class="table-wrap" style="border:0; border-radius: 0;">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Jenis</th>
                                <th>Harga / jam</th>
                                <th>3 jam</th>
                                <th>5 jam</th>
                                <th>Catatan</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach (['PS5', 'PS4', 'PS3'] as $tipe): ?>
                                <?php $h = $perkiraanJam[$tipe]; ?>
                                <tr>
                                    <td><strong><?= $tipe ?></strong></td>
                                    <td class="num text-cyan"><?= rupiah($h) ?></td>
                                    <td class="num"><?= rupiah($h * 3) ?></td>
                                    <td class="num"><?= rupiah($h * 5) ?></td>
                                    <td class="text-secondary">
                                        <?php if ($tipe === 'PS5'): ?>DualSense, 4K
                                        <?php elseif ($tipe === 'PS4'): ?>Paling ramai, cocok mabar
                                        <?php else: ?>Pilihan klasik hemat
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            <tr>
                                <td><strong>Controller tambahan</strong></td>
                                <td class="num text-violet"><?= rupiah($tarifController) ?></td>
                                <td class="num" colspan="2">Dihitung per jam pemakaian</td>
                                <td class="text-secondary">Stok terbatas, tulis saat memesan</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <p class="text-muted" style="font-size: var(--text-xs); margin-top: var(--sp-3);">
            Harga dapat berbeda per unit (VIP/ruangan). Rincian final selalu muncul di form sebelum memesan.
        </p>
    </div>
</section>

<!-- ============ PAKET & PROMO ============ -->
<?php if ($paket !== []): ?>
<section class="section" style="padding-top: 0;">
    <div class="container-page">
        <h2 class="display" style="font-size: var(--text-2xl);">Paket &amp; Promo</h2>
        <div class="grid gap-4 md:grid-cols-3" style="margin-top: var(--sp-6);">
            <?php foreach (array_slice($paket, 0, 3) as $p): ?>
                <article class="qs-card qs-card--hoverable">
                    <div class="flex items-center justify-between">
                        <h3 style="margin:0; font-size: var(--text-base);"><?= e($p['nama']) ?></h3>
                        <span class="badge badge-violet"><?= (int) $p['durasi_jam'] ?> jam</span>
                    </div>
                    <p class="text-secondary" style="margin: var(--sp-3) 0; font-size: var(--text-sm);"><?= e($p['deskripsi']) ?></p>
                    <p class="display" style="margin:0; font-size: var(--text-2xl); color: var(--text-primary);">
                        <?= rupiah($p['estimasi_harga']) ?>
                    </p>
                    <p class="text-success" style="margin:2px 0 0; font-size: var(--text-sm);">
                        Hemat hingga <?= rupiah($p['estimasi_hemat']) ?>
                    </p>
                    <a href="<?= url('booking') ?>" class="btn btn-outline btn-sm" style="margin-top: var(--sp-4);">Pakai Paket Ini</a>
                </article>
            <?php endforeach; ?>
        </div>
    </div>
</section>
<?php endif; ?>

<!-- ============ GAME POPULER ============ -->
<section class="section" style="padding-top: 0;">
    <div class="container-page">
        <div class="flex flex-wrap items-end justify-between gap-3">
            <h2 class="display" style="font-size: var(--text-2xl); margin:0;">Game Tersedia</h2>
            <a href="<?= url('katalog/game') ?>" class="btn btn-outline btn-sm">Semua Game</a>
        </div>
        <div class="grid gap-3 grid-cols-2 sm:grid-cols-3 lg:grid-cols-4" style="margin-top: var(--sp-5);">
            <?php foreach ($games as $g): ?>
                <a href="<?= url('katalog/game/' . $g['id']) ?>" class="qs-card qs-card--hoverable" style="padding: var(--sp-4);">
                    <p style="margin:0; font-weight:600; font-size: var(--text-sm);"><?= e($g['judul']) ?></p>
                    <p class="text-muted" style="margin:4px 0 0; font-size: var(--text-xs);">
                        <?= e($g['platform']) ?> · <?= e($g['genre']) ?> · <?= e($g['rating']) ?>
                    </p>
                </a>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<!-- ============ LOKASI & JAM OPERASIONAL ============ -->
<section class="section" style="padding-top: 0;">
    <div class="container-page grid gap-4 lg:grid-cols-2">
        <div class="qs-card">
            <h2 class="display" style="font-size: var(--text-lg);">Jam Operasional</h2>
            <ul style="list-style:none; margin:0; padding:0;">
                <li class="flex justify-between gap-4 py-3" style="border-bottom: 1px solid var(--border-soft);">
                    <span class="text-secondary">Hari biasa</span>
                    <strong style="font-weight:600;"><?= e(Setting::get('jam_hari_biasa', '-')) ?></strong>
                </li>
                <li class="flex justify-between gap-4 py-3" style="border-bottom: 1px solid var(--border-soft);">
                    <span class="text-secondary">Akhir pekan &amp; libur</span>
                    <strong style="font-weight:600;"><?= e(Setting::get('jam_akhir_pekan', '-')) ?></strong>
                </li>
                <li class="flex justify-between gap-4 py-3">
                    <span class="text-secondary">Pemesanan online</span>
                    <strong style="font-weight:600;">24 jam, hingga H<?= (int) config('booking.advance_days') ?></strong>
                </li>
            </ul>
        </div>

        <div class="qs-card">
            <h2 class="display" style="font-size: var(--text-lg);">Lokasi &amp; Kontak</h2>
            <p style="margin:0 0 var(--sp-2); font-weight:600;"><?= e(Setting::get('nama_rental', config('app.name'))) ?></p>
            <p class="text-secondary" style="margin:0 0 var(--sp-4);"><?= e(Setting::get('alamat', '-')) ?></p>
            <div class="flex flex-wrap gap-2">
                <a class="btn btn-outline btn-sm" href="https://wa.me/<?= e(preg_replace('/[^0-9]/', '', (string) Setting::get('telepon', ''))) ?>" rel="noopener">
                    WhatsApp <?= e(Setting::get('telepon', '')) ?>
                </a>
                <a class="btn btn-outline btn-sm" href="<?= e(Setting::get('peta_url', '#')) ?>" rel="noopener noreferrer" target="_blank">Buka Peta</a>
            </div>
        </div>
    </div>
</section>

<!-- ============ CTA AKHIR ============ -->
<section class="section" style="padding-top: 0;">
    <div class="container-page">
        <div class="qs-card text-center" style="padding: var(--sp-10) var(--sp-5);">
            <h2 class="display" style="font-size: var(--text-3xl);">Siap main?</h2>
            <p class="text-secondary" style="max-width: 52ch; margin-inline:auto;">
                Pilih unit, tentukan jam, dan sistem mengunci jadwal untuk Anda begitu pesanan dikirim.
            </p>
            <div class="flex flex-wrap gap-3 justify-center" style="margin-top: var(--sp-5);">
                <a href="<?= url('booking') ?>" class="btn btn-primary btn-lg">Buat Pesanan</a>
                <?php if (!$authUser): ?>
                    <a href="<?= url('daftar') ?>" class="btn btn-outline btn-lg">Daftar Akun Dulu</a>
                <?php endif; ?>
            </div>
        </div>
    </div>
</section>

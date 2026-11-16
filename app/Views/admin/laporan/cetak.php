<?php
/**
 * admin/laporan/cetak.php — Halaman cetak ("Simpan sebagai PDF").
 * Sengaja TIDAK memakai layout admin: tidak ada sidebar, tombol, atau glow.
 * Warna cetak diolah lewat penimpaan variabel token di dalam @media print
 * sehingga teks menjadi gelap di atas kertas putih (hemat tinta).
 *
 * Data dari ReportController::cetak(): data, dari, sampai, cetak, operator, tautan.
 */

use App\Models\Setting;

$kolom     = $data['kolom'];
$baris     = $data['baris'];
$lanskap   = count($kolom) > 6;

/** Ubah nilai mentah menjadi teks siap baca. */
$format = static function (string $tipe, mixed $nilai): string {
    $text = trim((string) $nilai);

    return match ($tipe) {
        'uang'    => rupiah($text === '' ? 0 : (float) $text),
        'angka'   => number_format((float) $text),
        'jam'     => number_format((float) $text) . ' jam',
        'persen'  => $text === '' ? '-' : number_format((float) $text, 1) . '%',
        'tanggal' => $text === '' ? '-' : tgl_indo($text),
        'waktu'   => $text === '' ? '-' : jam($text),
        'status'  => status_label($text),
        default   => $text,
    };
};

/** Kolom numerik dijumlahkan pada baris total. */
$tipeNumerik = ['uang', 'angka', 'jam'];
$jumlah      = [];
foreach ($kolom as $i => $k) {
    if (in_array($k['tipe'], $tipeNumerik, true)) {
        $jumlah[$i] = array_sum(array_map(static fn (array $r): float => (float) ($r[$i] ?? 0), $baris));
    }
}
$nama    = (string) Setting::get('nama_rental', config('app.name'));
$alamat  = (string) Setting::get('alamat', '-');
$telepon = (string) Setting::get('telepon', '-');
$email   = (string) Setting::get('email', '-');
?>
<!DOCTYPE html>
<html lang="id" class="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title><?= e($data['judul'] . ' — ' . $nama) ?></title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&family=Rajdhani:wght@500;600;700&display=swap" rel="stylesheet">

    <link rel="stylesheet" href="<?= asset('css/tokens.css') ?>">
    <link rel="stylesheet" href="<?= asset('css/app.css') ?>">

    <style>
        /* ---- Tampilan layar: tetap mengikuti tema gelap ---- */
        .cetak {
            max-width: <?= $lanskap ? '1180px' : '860px' ?>;
            margin: 0 auto;
            padding: var(--sp-6) var(--sp-4) var(--sp-12);
        }
        .cetak__toolbar {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            gap: var(--sp-2);
            margin-bottom: var(--sp-5);
        }
        .cetak__kop {
            display: flex;
            flex-wrap: wrap;
            justify-content: space-between;
            gap: var(--sp-4);
            padding-bottom: var(--sp-4);
            border-bottom: 2px solid var(--border-strong);
        }
        .cetak__merek {
            font-family: var(--font-display);
            font-size: var(--text-2xl);
            letter-spacing: 0.02em;
            text-transform: uppercase;
            margin: 0;
        }
        .cetak__meta dt { color: var(--text-muted); font-size: var(--text-xs); text-transform: uppercase; letter-spacing: 0.06em; }
        .cetak__meta dd { margin: 0 0 var(--sp-2); font-size: var(--text-sm); }
        .cetak__kanan { text-align: right; min-width: 240px; }
        .cetak__judul { font-size: var(--text-lg); margin: 0 0 var(--sp-2); }
        .cetak__ringkasan {
            display: flex;
            flex-wrap: wrap;
            gap: var(--sp-3);
            margin: var(--sp-5) 0;
        }
        .cetak__ringkas {
            flex: 1 1 180px;
            border: 1px solid var(--border-soft);
            border-radius: var(--radius-sm);
            padding: var(--sp-3) var(--sp-4);
        }
        .cetak__catatan {
            font-size: var(--text-sm);
            color: var(--text-secondary);
            max-width: 96ch;
        }
        .cetak__ttd {
            display: flex;
            flex-wrap: wrap;
            gap: var(--sp-8);
            margin-top: var(--sp-10);
            font-size: var(--text-sm);
        }
        .cetak__ttd span { display: block; width: 190px; border-top: 1px solid var(--border-strong); margin-top: var(--sp-10); padding-top: var(--sp-2); }

        /* ---- Saat dicetak: teks gelap di atas kertas putih ---- */
        @media print {
            /* Token dipetakan ulang (bukan warna baru) agar hemat tinta */
            :root {
                --ink-850: var(--text-strong);
                --ink-700: var(--text-strong);
                --ink-800: var(--text-strong);
                --text-primary: var(--ink-900);
                --text-secondary: var(--ink-900);
                --border-soft: var(--text-muted);
                --border-medium: var(--text-muted);
                --border-strong: var(--ink-900);
            }
            html, body {
                background: var(--text-strong) !important;
                background-image: none !important;
                color: var(--ink-900) !important;
                font-size: 11px;
            }
            @page {
                size: A4 <?= $lanskap ? 'landscape' : 'portrait' ?>;
                margin: 14mm 12mm;
            }
            .cetak { max-width: none; margin: 0; padding: 0; }
            .cetak__toolbar,
            .skip-link { display: none !important; }
            .table-wrap { border: 0; overflow: visible; }
            .table { min-width: 0; font-size: 11px; }
            .table th, .table td { padding: 4px 6px; }
            .table thead { display: table-header-group; } /* header berulang tiap halaman */
            .table tbody tr { break-inside: avoid; page-break-inside: avoid; }
            .cetak__kop { break-after: avoid; page-break-after: avoid; }
            .cetak__ringkas, .cetak__ttd { border-color: var(--text-muted); }
            a { color: inherit; text-decoration: none; }
        }
    </style>
</head>
<body>
<div class="cetak">

    <!-- ====== Toolbar: hanya tampil di layar ====== -->
    <div class="cetak__toolbar qs-panel">
        <div class="qs-panel__body flex flex-wrap items-center gap-2">
            <button type="button" class="btn btn-primary btn-sm" onclick="window.print()">Cetak / Simpan PDF</button>
            <a class="btn btn-outline btn-sm" href="<?= e($tautan['ekspor']) ?>">Unduh CSV</a>
            <a class="btn btn-ghost btn-sm" href="<?= e($tautan['kembali']) ?>">Kembali ke Laporan</a>
            <p class="text-muted" style="margin: 0 0 0 auto; font-size: var(--text-xs);">
                Pilih tujuan pencetakan "Simpan sebagai PDF" pada dialog browser.
            </p>
        </div>
    </div>

    <!-- ====== Kop ====== -->
    <header class="cetak__kop">
        <div>
            <p class="cetak__merek"><?= e($nama) ?></p>
            <p class="text-secondary" style="margin: 4px 0 0; font-size: var(--text-sm); max-width: 42ch;"><?= e($alamat) ?></p>
            <p class="text-muted" style="margin: 2px 0 0; font-size: var(--text-xs);">
                <?= e($telepon) ?> · <?= e($email) ?>
            </p>
        </div>
        <div class="cetak__kanan">
            <h1 class="display cetak__judul"><?= e($data['judul']) ?></h1>
            <dl class="cetak__meta" style="margin: 0;">
                <dt>Periode</dt>
                <dd><?= e($data['periode'] ?? tgl_indo($dari) . ' s/d ' . tgl_indo($sampai)) ?></dd>
                <dt>Dicetak</dt>
                <dd><?= e(tgl_waktu($cetak)) ?></dd>
                <dt>Oleh</dt>
                <dd><?= e($operator) ?></dd>
            </dl>
        </div>
    </header>

    <!-- ====== Ringkasan ====== -->
    <?php if ($data['ringkasan'] !== []): ?>
        <section class="cetak__ringkasan">
            <?php foreach ($data['ringkasan'] as $r): ?>
                <div class="cetak__ringkas">
                    <p class="stat__label" style="margin: 0;"><?= e($r['label']) ?></p>
                    <p class="stat__value" style="font-size: var(--text-xl); margin: 2px 0 0;"><?= e($r['nilai']) ?></p>
                </div>
            <?php endforeach; ?>
        </section>
    <?php endif; ?>

    <!-- ====== Tabel data ====== -->
    <section>
        <?php if ($baris === []): ?>
            <p class="cetak__catatan" style="border: 1px solid var(--border-soft); border-radius: var(--radius-sm); padding: var(--sp-4);">
                Tidak ada data pada periode ini. Periksa kembali rentang tanggal atau buat laporan
                dengan periode yang lebih panjang.
            </p>
        <?php else: ?>
            <div class="table-wrap">
                <table class="table">
                    <thead>
                        <tr>
                            <th style="width: 34px;">No</th>
                            <?php foreach ($kolom as $k): ?>
                                <th<?= in_array($k['tipe'], $tipeNumerik, true) ? ' class="num"' : '' ?>><?= e($k['label']) ?></th>
                            <?php endforeach; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($baris as $i => $r): ?>
                            <tr>
                                <td class="num"><?= $i + 1 ?></td>
                                <?php foreach ($kolom as $j => $k): ?>
                                    <td<?= in_array($k['tipe'], $tipeNumerik, true) ? ' class="num"' : '' ?>>
                                        <?= e($format($k['tipe'], $r[$j] ?? '')) ?>
                                    </td>
                                <?php endforeach; ?>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                    <?php if ($jumlah !== []): ?>
                        <tfoot>
                            <tr>
                                <td class="num" style="font-weight: 700;">TOTAL</td>
                                <?php foreach ($kolom as $j => $k): ?>
                                    <td<?= isset($jumlah[$j]) ? ' class="num"' : '' ?> style="font-weight: 700;">
                                        <?= isset($jumlah[$j]) ? e($format($k['tipe'], $jumlah[$j])) : '' ?>
                                    </td>
                                <?php endforeach; ?>
                            </tr>
                        </tfoot>
                    <?php endif; ?>
                </table>
            </div>
        <?php endif; ?>
    </section>

    <!-- ====== Catatan kaki ====== -->
    <footer>
        <p class="cetak__catatan" style="margin-top: var(--sp-5);">
            Dokumen dihasilkan otomatis oleh sistem <?= e($nama) ?> pada <?= e(tgl_waktu($cetak)) ?>.
            Angka mengikuti status pesanan terakhir pada saat pencetakan, sehingga pesanan yang masih
            menunggu verifikasi belum dihitung sebagai pendapatan.
            <?= $data['jenis'] === 'pesanan' ? 'Laporan pesanan memuat maksimal 500 baris terbaru pada periode terpilih.' : '' ?>
            Periksa kembali sebelum dipakai sebagai lampiran tagihan.
        </p>
        <div class="cetak__ttd">
            <div>
                <span>Ditandatangani</span>
            </div>
            <div>
                <span>Mengetahui, Pelanggan</span>
            </div>
        </div>
    </footer>
</div>
</body>
</html>

<?php
/**
 * ReportController — Laporan, ekspor CSV, dan halaman cetak (PDF).
 *
 * Prinsip: satu sumber data (dataset) dipakai tiga tempat, sehingga tabel
 * di layar, berkas CSV, dan halaman cetak selalu angkanya sama.
 */

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Controller;
use App\Core\Exporter;
use App\Core\Schedule;
use App\Models\ActivityLog;
use App\Models\Booking;
use App\Models\Unit;

class ReportController extends Controller
{
    /** Jenis laporan yang bisa diekspor/dicetak. */
    private const JENIS = ['harian', 'bulanan', 'unit', 'pesanan'];

    /** Batas baris ekspor pesanan (menghindari memori membengkak). */
    private const EKSPOR_PESANAN = 500;

    /* =====================================================================
     * HALAMAN
     * =================================================================== */

    /** Pusat laporan: form periode + tautan ke sub-laporan. */
    public function index(): void
    {
        [$dari, $sampai] = $this->rentang(30);

        $harian  = Booking::laporanHarian($dari, $sampai, 60);
        $unitKin = array_slice(Unit::kinerja($dari, $sampai), 0, 5);

        $bulan = Booking::laporanBulanan(date('Y-m-01'), date('Y-m-d'));

        $this->adminView('admin/laporan/index', [
            'dari'   => $dari,
            'sampai' => $sampai,
            'ringkas' => [
                'omzet'   => array_sum(array_map('floatval', array_column($harian, 'omzet'))),
                'pesanan' => array_sum(array_map('intval', array_column($harian, 'jumlah_pesanan'))),
                'jam'     => array_sum(array_map('intval', array_column($harian, 'total_jam'))),
                'diskon'  => array_sum(array_map('floatval', array_column($harian, 'total_diskon'))),
            ],
            'unitTop'  => $unitKin,
            'bulanIni' => $bulan[0] ?? null,
            'tautan'   => [
                'harian'   => route('admin.laporan.harian'),
                'bulanan'  => route('admin.laporan.bulanan'),
                'unit'     => route('admin.laporan.unit'),
                'aktivitas'=> route('admin.laporan.aktivitas'),
                'ekspor'   => route('admin.laporan.ekspor'),
                'cetak'    => route('admin.laporan.cetak'),
            ],
        ]);
    }

    /** Laporan harian (per tanggal). */
    public function harian(): void
    {
        [$dari, $sampai] = $this->rentang(30);
        $baris = Booking::laporanHarian($dari, $sampai, 90);

        $this->adminView('admin/laporan/harian', [
            'dari'  => $dari,
            'sampai'=> $sampai,
            'baris' => $baris,
            'total' => [
                'pesanan'  => array_sum(array_map('intval', array_column($baris, 'jumlah_pesanan'))),
                'jam'      => array_sum(array_map('intval', array_column($baris, 'total_jam'))),
                'omzet'    => array_sum(array_map('floatval', array_column($baris, 'omzet'))),
                'diskon'   => array_sum(array_map('floatval', array_column($baris, 'total_diskon'))),
                'controller' => array_sum(array_map('floatval', array_column($baris, 'omzet_controller'))),
                'lunas'    => array_sum(array_map('intval', array_column($baris, 'sudah_lunas'))),
            ],
            'grafik' => $this->grafik($baris, 'tanggal', 'omzet'),
        ]);
    }

    /** Laporan bulanan + perbandingan antar bulan. */
    public function bulanan(): void
    {
        [$dari, $sampai] = $this->rentangBulan(6);
        $baris = $this->bulananDenganPerbandingan($dari, $sampai);

        $this->adminView('admin/laporan/bulanan', [
            'dari'  => $dari,
            'sampai'=> $sampai,
            'baris' => $baris,
            'total' => [
                'pesanan' => array_sum(array_map('intval', array_column($baris, 'jumlah_pesanan'))),
                'jam'     => array_sum(array_map('intval', array_column($baris, 'total_jam'))),
                'omzet'   => array_sum(array_map('floatval', array_column($baris, 'omzet'))),
                'selesai' => array_sum(array_map('intval', array_column($baris, 'jumlah_selesai'))),
            ],
            'grafik' => array_values(array_map(
                static fn (array $b): array => ['label' => (string) $b['bulan'], 'nilai' => (float) $b['omzet']],
                array_reverse($baris)
            )),
        ]);
    }

    /** Kinerja unit + kepadatan jadwal hari ini. */
    public function unit(): void
    {
        [$dari, $sampai] = $this->rentang(30);
        $hari     = date('Y-m-d');
        $baris    = Unit::kinerja($dari, $sampai);
        $hari_ini = Schedule::kepadatanBanyak(array_column($baris, 'id'), $hari);

        foreach ($baris as $i => $u) {
            $baris[$i]['hari_ini'] = $hari_ini[(int) $u['id']] ?? null;
        }

        $jamOperasional = Schedule::jamOperasional($hari);

        $this->adminView('admin/laporan/unit', [
            'dari'    => $dari,
            'sampai'  => $sampai,
            'hari'    => $hari,
            'baris'   => $baris,
            'jam'     => $jamOperasional,
            'total'   => [
                'unit'    => count($baris),
                'pesanan' => array_sum(array_map('intval', array_column($baris, 'jumlah_pesanan'))),
                'omzet'   => array_sum(array_map('floatval', array_column($baris, 'omzet'))),
                'jam'     => array_sum(array_map('intval', array_column($baris, 'total_jam'))),
                'rataRata' => $baris === [] ? 0.0 : array_sum(array_map(
                    static fn (array $u): float => (float) ($u['hari_ini']['persen'] ?? 0),
                    $baris
                )) / count($baris),
            ],
            'grafik'  => array_slice(array_map(
                static fn (array $u): array => ['label' => (string) $u['kode_unit'], 'nilai' => (float) $u['omzet']],
                $baris
            ), 0, 10),
        ]);
    }

    /** Jejak aktivitas (audit trail). */
    public function aktivitas(): void
    {
        $modul   = (string) $this->request->input('modul', '');
        $halaman = max(1, $this->request->int('halaman', 1));
        $perHalaman = 25;

        $hasil   = ActivityLog::terfilter($modul, $halaman, $perHalaman);
        $terakhir = max(1, (int) ceil(((int) $hasil['total']) / $perHalaman));

        $this->adminView('admin/laporan/aktivitas', [
            'modul'    => $modul,
            'halaman'  => min($halaman, $terakhir),
            'terakhir' => $terakhir,
            'total'    => (int) $hasil['total'],
            'baris'    => $hasil['rows'],
            'modulList'=> ['auth', 'booking', 'payment', 'unit', 'game', 'paket', 'user'],
        ]);
    }

    /* =====================================================================
     * EKSPOR & CETAK
     * =================================================================== */

    /** Unduhan CSV: ?jenis=harian|bulanan|unit|pesanan&dari=...&sampai=... */
    public function ekspor(): void
    {
        $jenis = $this->jenisAktif();
        [$dari, $sampai] = $jenis === 'bulanan' ? $this->rentangBulan(6) : $this->rentang(30);

        $data  = $this->dataset($jenis, $dari, $sampai);
        $nama  = sprintf(
            'laporan-%s-%s-sampai-%s',
            $data['jenis'],
            $dari,
            $sampai
        );

        Exporter::csv(
            $nama,
            array_column($data['kolom'], 'label'),
            $data['baris']
        );
    }

    /** Halaman cetak / "Simpan sebagai PDF" (tanpa layout admin). */
    public function cetak(): void
    {
        $jenis = $this->jenisAktif();
        [$dari, $sampai] = $jenis === 'bulanan' ? $this->rentangBulan(6) : $this->rentang(30);

        $this->fragment('admin/laporan/cetak', [
            'data'   => $this->dataset($jenis, $dari, $sampai),
            'dari'   => $dari,
            'sampai' => $sampai,
            'cetak'  => date('Y-m-d H:i:s'),
            'operator' => $this->user()['nama'] ?? '-',
            'tautan' => [
                // route() hanya mengisi placeholder {id}, jadi query string
                // dibangun manual di sini.
                'ekspor'  => route('admin.laporan.ekspor') . '?' . http_build_query([
                    'jenis' => $jenis, 'dari' => $dari, 'sampai' => $sampai,
                ]),
                'kembali' => route('admin.laporan'),
            ],
        ]);
    }

    /* =====================================================================
     * PENGUMPUL DATA
     * =================================================================== */

    /**
     * Satu bentuk data untuk layar, CSV, dan cetak.
     * @return array{jenis:string,judul:string,kolom:array,baris:array,ringkasan:array}
     */
    private function dataset(string $jenis, string $dari, string $sampai): array
    {
        $periode = tgl_indo($dari) . ' s/d ' . tgl_indo($sampai);

        if ($jenis === 'bulanan') {
            $baris = $this->bulananDenganPerbandingan($dari, $sampai);
            return [
                'jenis'  => 'bulanan',
                'judul'  => 'Laporan Pendapatan Bulanan',
                'periode'=> $periode,
                'kolom'  => [
                    ['label' => 'Bulan', 'tipe' => 'teks'],
                    ['label' => 'Jumlah Pesanan', 'tipe' => 'angka'],
                    ['label' => 'Total Jam', 'tipe' => 'jam'],
                    ['label' => 'Omzet (Rp)', 'tipe' => 'uang'],
                    ['label' => 'Total Diskon (Rp)', 'tipe' => 'uang'],
                    ['label' => 'Pesanan Selesai', 'tipe' => 'angka'],
                    ['label' => 'Pertumbuhan (%)', 'tipe' => 'persen'],
                ],
                'baris' => array_map(static fn (array $b): array => [
                    (string) $b['bulan'],
                    (int) $b['jumlah_pesanan'],
                    (int) $b['total_jam'],
                    (float) $b['omzet'],
                    (float) $b['total_diskon'],
                    (int) $b['jumlah_selesai'],
                    $b['pertumbuhan'] === null ? '' : round((float) $b['pertumbuhan'], 1),
                ], $baris),
                'ringkasan' => [
                    ['label' => 'Total omzet periode', 'nilai' => rupiah(array_sum(
                        array_map(static fn (array $b): float => (float) $b['omzet'], $baris)
                    ))],
                    ['label' => 'Total pesanan', 'nilai' => (string) array_sum(
                        array_map(static fn (array $b): int => (int) $b['jumlah_pesanan'], $baris)
                    )],
                ],
            ];
        }

        if ($jenis === 'unit') {
            $hari = date('Y-m-d');
            $baris = Unit::kinerja($dari, $sampai);
            $padat = Schedule::kepadatanBanyak(array_column($baris, 'id'), $hari);
            foreach ($baris as $i => $u) {
                $baris[$i]['hari_ini'] = $padat[(int) $u['id']] ?? null;
            }

            return [
                'jenis'  => 'unit',
                'judul'  => 'Laporan Kinerja Unit',
                'periode'=> $periode,
                'kolom'  => [
                    ['label' => 'Kode Unit', 'tipe' => 'teks'],
                    ['label' => 'Nama Unit', 'tipe' => 'teks'],
                    ['label' => 'Tipe', 'tipe' => 'teks'],
                    ['label' => 'Status', 'tipe' => 'status'],
                    ['label' => 'Harga/Jam (Rp)', 'tipe' => 'uang'],
                    ['label' => 'Jumlah Pesanan', 'tipe' => 'angka'],
                    ['label' => 'Total Jam', 'tipe' => 'jam'],
                    ['label' => 'Omzet (Rp)', 'tipe' => 'uang'],
                    ['label' => 'Kepadatan Hari Ini (%)', 'tipe' => 'persen'],
                ],
                'baris' => array_map(static fn (array $u): array => [
                    (string) $u['kode_unit'],
                    (string) $u['nama'],
                    (string) $u['tipe'],
                    (string) $u['status'],
                    (float) $u['harga_per_jam'],
                    (int) $u['jumlah_pesanan'],
                    (int) $u['total_jam'],
                    (float) $u['omzet'],
                    (int) ($u['hari_ini']['persen'] ?? 0),
                ], $baris),
                'ringkasan' => [
                    ['label' => 'Jumlah unit', 'nilai' => (string) count($baris)],
                    ['label' => 'Total omzet', 'nilai' => rupiah(array_sum(
                        array_map(static fn (array $u): float => (float) $u['omzet'], $baris)
                    ))],
                ],
            ];
        }

        if ($jenis === 'pesanan') {
            // Rentang tanggal dikirim ke SQL; LIMIT hanya pembatas jumlah baris,
            // bukan penyaring (menyaring setelah LIMIT membuat ekspor bolong).
            $baris = Booking::ekspor($dari, $sampai, self::EKSPOR_PESANAN);

            return [
                'jenis'  => 'pesanan',
                'judul'  => 'Laporan Pesanan',
                'periode'=> $periode,
                'kolom'  => [
                    ['label' => 'Nomor Faktur', 'tipe' => 'teks'],
                    ['label' => 'Tanggal', 'tipe' => 'tanggal'],
                    ['label' => 'Mulai', 'tipe' => 'waktu'],
                    ['label' => 'Selesai', 'tipe' => 'waktu'],
                    ['label' => 'Unit', 'tipe' => 'teks'],
                    ['label' => 'Pelanggan', 'tipe' => 'teks'],
                    ['label' => 'Telepon', 'tipe' => 'teks'],
                    ['label' => 'Durasi (Jam)', 'tipe' => 'angka'],
                    ['label' => 'Subtotal (Rp)', 'tipe' => 'uang'],
                    ['label' => 'Diskon (Rp)', 'tipe' => 'uang'],
                    ['label' => 'Total (Rp)', 'tipe' => 'uang'],
                    ['label' => 'Metode Bayar', 'tipe' => 'status'],
                    ['label' => 'Status Pesanan', 'tipe' => 'status'],
                    ['label' => 'Status Bayar', 'tipe' => 'status'],
                ],
                'baris' => array_map(static fn (array $b): array => [
                    (string) $b['invoice_number'],
                    (string) $b['booking_date'],
                    (string) $b['start_time'],
                    (string) $b['end_time'],
                    (string) $b['kode_unit'],
                    (string) $b['nama_pelanggan'],
                    (string) ($b['telepon'] ?? ''),
                    (int) $b['durasi_jam'],
                    (float) $b['subtotal'],
                    (float) $b['diskon'],
                    (float) $b['total_harga'],
                    (string) $b['metode_bayar'],
                    (string) $b['status_pesanan'],
                    (string) $b['status_bayar'],
                ], $baris),
                'ringkasan' => array_values(array_filter([
                    ['label' => 'Jumlah pesanan', 'nilai' => (string) count($baris)],
                    ['label' => 'Total nilai', 'nilai' => rupiah(array_sum(
                        array_map(static fn (array $b): float => (float) $b['total_harga'], $baris)
                    ))],
                    count($baris) >= self::EKSPOR_PESANAN
                        ? ['label' => 'Catatan', 'nilai' => 'Baris dibatasi ' . self::EKSPOR_PESANAN
                            . ', perpendek rentang tanggal untuk ekspor lengkap']
                        : null,
                ])),
            ];
        }

        // Default: laporan harian
        $baris = Booking::laporanHarian($dari, $sampai, 366);

        return [
            'jenis'  => 'harian',
            'judul'  => 'Laporan Pendapatan Harian',
            'periode'=> $periode,
            'kolom'  => [
                ['label' => 'Tanggal', 'tipe' => 'tanggal'],
                ['label' => 'Jumlah Pesanan', 'tipe' => 'angka'],
                ['label' => 'Dibatalkan', 'tipe' => 'angka'],
                ['label' => 'Total Jam', 'tipe' => 'jam'],
                ['label' => 'Omzet (Rp)', 'tipe' => 'uang'],
                ['label' => 'Total Diskon (Rp)', 'tipe' => 'uang'],
                ['label' => 'Omzet Controller (Rp)', 'tipe' => 'uang'],
                ['label' => 'Bayar Transfer', 'tipe' => 'angka'],
                ['label' => 'Bayar di Tempat', 'tipe' => 'angka'],
                ['label' => 'Sudah Lunas', 'tipe' => 'angka'],
            ],
            'baris' => array_map(static fn (array $b): array => [
                (string) $b['tanggal'],
                (int) $b['jumlah_pesanan'],
                (int) $b['jumlah_batal'],
                (int) $b['total_jam'],
                (float) $b['omzet'],
                (float) $b['total_diskon'],
                (float) $b['omzet_controller'],
                (int) $b['bayar_transfer'],
                (int) $b['bayar_tempat'],
                (int) $b['sudah_lunas'],
            ], $baris),
            'ringkasan' => [
                ['label' => 'Total omzet', 'nilai' => rupiah(array_sum(
                    array_map(static fn (array $b): float => (float) $b['omzet'], $baris)
                ))],
                ['label' => 'Total pesanan', 'nilai' => (string) array_sum(
                    array_map(static fn (array $b): int => (int) $b['jumlah_pesanan'], $baris)
                )],
                ['label' => 'Rata-rata per hari', 'nilai' => $baris === [] ? '-' : rupiah(
                    array_sum(array_map(static fn (array $b): float => (float) $b['omzet'], $baris)) / count($baris)
                )],
            ],
        ];
    }

    /** Laporan bulanan + selisih terhadap bulan sebelumnya. */
    private function bulananDenganPerbandingan(string $dari, string $sampai): array
    {
        $baris = Booking::laporanBulanan($dari, $sampai);

        // Dibaca dari bulan tertua agar persentase membandingkan ke belakang
        $tersusun = array_reverse($baris);
        $sebelum  = null;
        foreach ($tersusun as $i => $b) {
            $omzet = (float) $b['omzet'];
            $tersusun[$i]['pertumbuhan'] = ($sebelum === null || $sebelum <= 0.0)
                ? null
                : round(($omzet - $sebelum) / $sebelum * 100, 1);
            $sebelum = $omzet;
        }

        return array_reverse($tersusun);
    }

    /* =====================================================================
     * UTILITAS
     * =================================================================== */

    /** Jenis laporan dari query, fallback ke "harian". */
    private function jenisAktif(): string
    {
        $jenis = strtolower((string) $this->request->input('jenis', 'harian'));
        return in_array($jenis, self::JENIS, true) ? $jenis : 'harian';
    }

    /** Rentang tanggal harian/unit/pesanan: default 30 hari terakhir. */
    private function rentang(int $hari): array
    {
        $dariDefault   = date('Y-m-d', strtotime('-' . ($hari - 1) . ' day'));
        $sampaiDefault = date('Y-m-d');

        $dari   = self::tanggalAman((string) $this->request->input('dari', ''), $dariDefault);
        $sampai = self::tanggalAman((string) $this->request->input('sampai', ''), $sampaiDefault);

        return $dari > $sampai ? [$sampai, $dari] : [$dari, $sampai];
    }

    /** Rentang laporan bulanan: default 6 bulan terakhir. */
    private function rentangBulan(int $jumlah): array
    {
        $dariDefault = date('Y-m-01', strtotime('-' . ($jumlah - 1) . ' month'));
        $dari   = self::tanggalAman((string) $this->request->input('dari', ''), $dariDefault);
        $sampai = self::tanggalAman((string) $this->request->input('sampai', ''), date('Y-m-d'));

        return [$dari, $sampai];
    }

    /** Tanggal YYYY-MM-DD yang pasti valid (fallback aman). */
    private static function tanggalAman(string $nilai, string $bawaan): string
    {
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $nilai) !== 1) {
            return $bawaan;
        }
        $ts = strtotime($nilai);
        if ($ts === false || date('Y-m-d', $ts) !== $nilai) {
            return $bawaan;
        }
        return $nilai;
    }

    /** Ubah baris laporan menjadi data grafik [{label, nilai}]. */
    private function grafik(array $baris, string $keyLabel, string $keyNilai): array
    {
        $data = array_map(
            static fn (array $b): array => [
                'label' => date('d/m', (int) strtotime((string) $b[$keyLabel])),
                'nilai' => (float) $b[$keyNilai],
            ],
            $baris
        );
        return array_values(array_reverse($data));
    }

    /**
     * Format nilai sel untuk halaman cetak.
     * Dipakai view agar CSV tetap berisi angka mentah.
     */
    public static function sel(string $tipe, mixed $nilai): string
    {
        return match ($tipe) {
            'uang'    => rupiah($nilai),
            'angka'   => number_format((float) $nilai),
            'jam'     => number_format((float) $nilai, 1) . ' jam',
            'persen'  => number_format((float) $nilai, 1) . '%',
            'tanggal' => self::tanggalPendek(is_string($nilai) ? $nilai : ''),
            'waktu'   => jam(is_string($nilai) ? $nilai : ''),
            'status'  => status_label(is_string($nilai) ? $nilai : ''),
            default   => (string) ($nilai ?? ''),
        };
    }

    /** 2026-11-16 -> 16/11/2026 (valid) atau '-' bila kosong. */
    private static function tanggalPendek(string $tanggal): string
    {
        $ts = $tanggal === '' ? false : strtotime($tanggal);
        return $ts === false ? '-' : date('d/m/Y', $ts);
    }
}

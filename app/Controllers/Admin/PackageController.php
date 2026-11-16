<?php
/**
 * Admin\PackageController — CRUD paket & promo harga sewa.
 *
 * Kolom DATE pada packages tidak ikut dinormalisasi Model::onlyFillable(),
 * jadi nilai kosong dari form diubah menjadi null di sini.
 */

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\Logger;
use App\Models\Package;
use PDOException;

class PackageController extends Controller
{
    private const TIPE_DISKON = ['persen', 'nominal'];
    private const STATUS = ['aktif', 'nonaktif'];

    /** Daftar paket + estimasi penghematan + keadaan berlaku. */
    public function index(): void
    {
        // Estimasi hanya tersedia untuk paket berstatus aktif (lihat Package::denganEstimasi)
        $estimasi = [];
        foreach (Package::denganEstimasi() as $p) {
            $estimasi[(int) $p['id']] = $p;
        }

        $hariIni = date('Y-m-d');
        $paket = array_map(static function (array $p) use ($estimasi, $hariIni): array {
            $p['keadaan'] = self::keadaan($p, $hariIni);
            $p['estimasi_hemat'] = isset($estimasi[(int) $p['id']])
                ? (float) $estimasi[(int) $p['id']]['estimasi_hemat']
                : null;
            $p['estimasi_harga'] = isset($estimasi[(int) $p['id']])
                ? (float) $estimasi[(int) $p['id']]['estimasi_harga']
                : null;
            return $p;
        }, Package::daftar());

        $this->adminView('admin/paket/index', [
            'paket'      => $paket,
            'hariIni'    => $hariIni,
            'total'      => count($paket),
            'berlaku'    => count(array_filter($paket, static fn (array $p): bool => $p['keadaan'] === 'berlaku')),
            'nonaktif'   => (new Package())->countWhere(['status' => 'nonaktif']),
            'kedaluwarsa' => count(array_filter(
                $paket,
                static fn (array $p): bool => $p['keadaan'] === 'kedaluwarsa'
            )),
        ]);
    }

    public function create(): void
    {
        $this->adminView('admin/paket/form', [
            'mode'            => 'baru',
            'paket'           => [],
            'daftarTipeDiskon' => self::TIPE_DISKON,
            'daftarStatus'    => self::STATUS,
        ]);
    }

    public function store(): void
    {
        $data = $this->validate($this->aturan(), $this->label());
        $payload = $this->payload($data);

        try {
            $id = (new Package())->create($payload);
        } catch (PDOException $e) {
            flash('danger', 'Paket gagal disimpan. Periksa kembali nilai angkanya.');
            back();
        }

        Logger::write(Auth::id(), 'paket', 'buat', 'packages', $id,
            'Paket ' . $payload['nama'] . ' (' . $payload['durasi_jam'] . ' jam) ditambahkan.');

        flash('success', 'Paket ' . $payload['nama'] . ' berhasil ditambahkan.');
        redirect('admin/paket');
    }

    public function edit(string $id): void
    {
        $paket = (new Package())->findOrFail((int) $id);

        $this->adminView('admin/paket/form', [
            'mode'            => 'ubah',
            'paket'           => $paket,
            'daftarTipeDiskon' => self::TIPE_DISKON,
            'daftarStatus'    => self::STATUS,
        ]);
    }

    public function update(string $id): void
    {
        $lama = (new Package())->findOrFail((int) $id);
        $data = $this->validate($this->aturan(), $this->label());
        $payload = $this->payload($data, $lama);

        try {
            (new Package())->update((int) $lama['id'], $payload);
        } catch (PDOException $e) {
            flash('danger', 'Paket gagal diperbarui. Periksa kembali nilai angkanya.');
            back();
        }

        Logger::write(Auth::id(), 'paket', 'ubah', 'packages', (int) $lama['id'],
            'Paket ' . $lama['nama'] . ' diperbarui.');

        flash('success', 'Paket ' . $payload['nama'] . ' berhasil diperbarui.');
        redirect('admin/paket');
    }

    /** bookings.package_id memakai ON DELETE SET NULL, jadi paket boleh dihapus. */
    public function destroy(string $id): void
    {
        $paket = (new Package())->findOrFail((int) $id);
        $nama = (string) $paket['nama'];

        (new Package())->delete((int) $paket['id']);
        Logger::write(Auth::id(), 'paket', 'hapus', 'packages', (int) $paket['id'],
            'Paket ' . $nama . ' dihapus.');

        flash('success', 'Paket ' . $nama . ' telah dihapus. Pesanan lama tetap tersimpan tanpa paket.');
        redirect('admin/paket');
    }

    /* =====================================================================
     * UTILITAS INTERNAL
     * ===================================================================== */

    /** Keadaan paket hari ini: berlaku | belum_mulai | kedaluwarsa | nonaktif. */
    private static function keadaan(array $p, string $hariIni): string
    {
        if ($p['status'] !== 'aktif') {
            return 'nonaktif';
        }
        if (!empty($p['berlaku_mulai']) && $hariIni < (string) $p['berlaku_mulai']) {
            return 'belum_mulai';
        }
        if (!empty($p['berlaku_akhir']) && $hariIni > (string) $p['berlaku_akhir']) {
            return 'kedaluwarsa';
        }
        return 'berlaku';
    }

    private function aturan(): array
    {
        return [
            'nama'          => 'required|min:3|max:120',
            'deskripsi'     => 'max:255',
            'durasi_jam'    => 'required|integer|minval:1|maxval:24',
            'tipe_diskon'   => 'in:persen,nominal',
            'diskon_persen' => 'numeric|minval:0|maxval:90',
            'diskon_nilai'  => 'numeric|minval:0',
            'harga'         => 'numeric|minval:0',
            'berlaku_mulai' => 'date',
            'berlaku_akhir' => 'date|after:berlaku_mulai',
            'status'        => 'in:aktif,nonaktif',
        ];
    }

    private function label(): array
    {
        return [
            'nama'          => 'Nama paket',
            'deskripsi'     => 'Deskripsi',
            'durasi_jam'    => 'Durasi (jam)',
            'tipe_diskon'   => 'Tipe diskon',
            'diskon_persen' => 'Diskon persen',
            'diskon_nilai'  => 'Diskon nominal',
            'harga'         => 'Harga paket',
            'berlaku_mulai' => 'Tanggal mulai berlaku',
            'berlaku_akhir' => 'Tanggal akhir berlaku',
            'status'        => 'Status',
        ];
    }

    /** Kolom tanggal kosong => null; nilai angka dinormalisasi ke rupiah penuh. */
    private function payload(array $d, array $lama = []): array
    {
        $pilihan = static function (mixed $nilai, string $key, array $boleh, array $lama): string {
            if (is_string($nilai) && in_array($nilai, $boleh, true)) {
                return $nilai;
            }
            return (string) ($lama[$key] ?? $boleh[0]);
        };

        $tanggal = static function (mixed $nilai): ?string {
            $nilai = trim((string) ($nilai ?? ''));
            if ($nilai === '') {
                return null;
            }
            $ts = strtotime($nilai);
            return $ts === false ? null : date('Y-m-d', $ts);
        };

        $deskripsi = trim((string) ($d['deskripsi'] ?? ''));

        return [
            'nama'          => trim((string) $d['nama']),
            'deskripsi'     => $deskripsi === '' ? null : $deskripsi,
            'durasi_jam'    => max(1, min(24, (int) $d['durasi_jam'])),
            'tipe_diskon'   => $pilihan($d['tipe_diskon'] ?? null, 'tipe_diskon', self::TIPE_DISKON, $lama),
            'diskon_persen' => round($this->request->float('diskon_persen'), 2),
            'diskon_nilai'  => round($this->request->float('diskon_nilai')),
            'harga'         => round($this->request->float('harga')),
            'berlaku_mulai' => $tanggal($d['berlaku_mulai'] ?? null),
            'berlaku_akhir' => $tanggal($d['berlaku_akhir'] ?? null),
            'status'        => $pilihan($d['status'] ?? null, 'status', self::STATUS, $lama),
        ];
    }
}

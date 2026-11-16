<?php
/**
 * Admin\UnitController — CRUD master data unit PlayStation.
 *
 * Catatan penting:
 *  - Foto unit disimpan lewat Uploader ke folder uploads/units.
 *  - Unit hanya boleh dihapus bila belum pernah dipakai pesanan (FK bookings ON DELETE RESTRICT).
 *  - Status yang tampil di tabel selalu hasil hitung ulang Schedule::statusUnit(),
 *    kolom `units.status` hanya dipakai untuk kebutuhan filter/manual (maintenance).
 */

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\Logger;
use App\Core\Uploader;
use App\Models\Booking;
use App\Models\Unit;
use RuntimeException;

class UnitController extends Controller
{
    private const TIPE = ['PS3', 'PS4', 'PS5'];
    private const KATEGORI = ['reguler', 'vip', 'ruangan'];
    private const STATUS = ['tersedia', 'dipakai', 'dipesan', 'maintenance'];

    /** Daftar unit + ringkasan status. */
    public function index(): void
    {
        $tipe = $this->whitelist((string) $this->request->input('tipe', ''), self::TIPE);
        $status = $this->whitelist((string) $this->request->input('status', ''), self::STATUS);

        $this->adminView('admin/unit/index', [
            'units'         => Unit::katalog($tipe, $status),
            'statistik'     => Unit::statistik(),
            'tipe'          => $tipe,
            'status'        => $status,
            'daftarTipe'    => self::TIPE,
            'daftarStatus'  => self::STATUS,
            'totalSemua'    => (new Unit())->countWhere(),
        ]);
    }

    /** Form tambah unit; kode otomatis disiapkan untuk tiap tipe. */
    public function create(): void
    {
        $this->adminView('admin/unit/form', [
            'mode'           => 'baru',
            'unit'           => [],
            'saran'          => $this->saranKode(),
            'daftarTipe'     => self::TIPE,
            'daftarKategori' => self::KATEGORI,
            'daftarStatus'   => self::STATUS,
        ]);
    }

    /** Simpan unit baru. */
    public function store(): void
    {
        $data = $this->validate($this->aturan(), $this->label(), $this->aturanUpload());

        $baru = $this->fotoBaru();
        $payload = $this->payload($data);
        if ($baru !== null) {
            $payload['foto_path'] = $baru;
        }

        $id = (new Unit())->create($payload);
        Logger::write(Auth::id(), 'unit', 'buat', 'units', $id,
            'Unit ' . $payload['kode_unit'] . ' (' . $payload['tipe'] . ') ditambahkan.');

        flash('success', 'Unit ' . $payload['kode_unit'] . ' berhasil ditambahkan.');
        redirect('admin/unit');
    }

    /** Form ubah unit. */
    public function edit(string $id): void
    {
        $unit = (new Unit())->findOrFail((int) $id);

        $this->adminView('admin/unit/form', [
            'mode'           => 'ubah',
            'unit'           => $unit,
            'saran'          => $this->saranKode(),
            'daftarTipe'     => self::TIPE,
            'daftarKategori' => self::KATEGORI,
            'daftarStatus'   => self::STATUS,
        ]);
    }

    /** Perbarui unit; foto lama dihapus bila digantikan berkas baru. */
    public function update(string $id): void
    {
        $unit = (new Unit())->findOrFail((int) $id);

        // _current_id dibaca aturan `unique` agar baris ini tidak menabrak dirinya sendiri
        $data = $this->validate($this->aturan(), $this->label(), $this->aturanUpload());

        $payload = $this->payload($data, $unit);
        $baru = $this->fotoBaru();
        if ($baru !== null) {
            Uploader::delete($unit['foto_path'] ?? null);
            $payload['foto_path'] = $baru;
        }

        (new Unit())->update((int) $unit['id'], $payload);
        Logger::write(Auth::id(), 'unit', 'ubah', 'units', (int) $unit['id'],
            'Unit ' . $unit['kode_unit'] . ' diperbarui menjadi ' . $payload['kode_unit'] . '.');

        flash('success', 'Unit ' . $payload['kode_unit'] . ' berhasil diperbarui.');
        redirect('admin/unit');
    }

    /** Ganti status operasional unit (whitelist kolom ENUM). */
    public function ubahStatus(string $id): void
    {
        $unit = (new Unit())->findOrFail((int) $id);
        $status = $this->whitelist((string) $this->request->input('status', ''), self::STATUS);

        if ($status === '') {
            flash('danger', 'Status unit tidak dikenali.');
            back();
        }

        Unit::ubahStatus((int) $unit['id'], $status, Auth::id());
        flash('success', 'Status ' . $unit['kode_unit'] . ' diubah menjadi ' . status_label($status) . '.');
        back();
    }

    /** Hapus unit hanya bila tidak punya riwayat pesanan. */
    public function destroy(string $id): void
    {
        $unit = (new Unit())->findOrFail((int) $id);
        $bekas = (string) $unit['kode_unit'];

        if ((new Booking())->countWhere(['unit_id' => (int) $unit['id']]) > 0) {
            flash('danger', 'Unit tidak dapat dihapus karena punya riwayat pesanan — ubah status menjadi maintenance saja.');
            redirect('admin/unit');
        }

        Uploader::delete($unit['foto_path'] ?? null);
        (new Unit())->delete((int) $unit['id']);
        Logger::write(Auth::id(), 'unit', 'hapus', 'units', (int) $unit['id'],
            'Unit ' . $bekas . ' dihapus dari master data.');

        flash('success', 'Unit ' . $bekas . ' telah dihapus.');
        redirect('admin/unit');
    }

    /* =====================================================================
     * UTILITAS INTERNAL
     * ===================================================================== */

    /**
     * Saran kode_unit berikutnya per tipe (PS5-04, PS4-07, ...).
     * @return array<string, string>
     */
    private function saranKode(): array
    {
        $saran = [];
        foreach (self::TIPE as $t) {
            $saran[$t] = Unit::kodeBerikutnya($t);
        }
        return $saran;
    }

    private function aturan(): array
    {
        return [
            'kode_unit'     => 'required|max:20|unique:units,kode_unit',
            'nama'          => 'required|min:3|max:100',
            'tipe'          => 'in:PS3,PS4,PS5',
            'kategori'      => 'in:reguler,vip,ruangan',
            'harga_per_jam' => 'required|numeric|minval:1000',
            'kapasitas'     => 'integer|minval:1|maxval:8',
            'status'        => 'in:tersedia,dipakai,dipesan,maintenance',
            'deskripsi'     => 'max:255',
        ];
    }

    private function label(): array
    {
        return [
            'kode_unit'     => 'Kode unit',
            'nama'          => 'Nama unit',
            'tipe'          => 'Tipe konsol',
            'kategori'      => 'Kategori',
            'harga_per_jam' => 'Harga per jam',
            'kapasitas'     => 'Kapasitas pemain',
            'status'        => 'Status',
            'deskripsi'     => 'Deskripsi',
            'foto'          => 'Foto unit',
        ];
    }

    /** Aturan khusus berkas unggahan (diproses Validator terpisah). */
    private function aturanUpload(): array
    {
        return ['foto' => 'image|max:' . (int) config('upload.max_size_kb', 2048)];
    }

    /** Susun kolom siap simpan; nilai kosong untuk kolom nullable diubah null. */
    private function payload(array $d, array $lama = []): array
    {
        $pilihan = static function (mixed $nilai, string $key, array $boleh, array $lama): string {
            if (is_string($nilai) && in_array($nilai, $boleh, true)) {
                return $nilai;
            }
            return (string) ($lama[$key] ?? $boleh[0]);
        };

        $deskripsi = trim((string) ($d['deskripsi'] ?? ''));

        return [
            'kode_unit'     => strtoupper(trim((string) $d['kode_unit'])),
            'nama'          => trim((string) $d['nama']),
            'tipe'          => $pilihan($d['tipe'] ?? null, 'tipe', self::TIPE, $lama),
            'kategori'      => $pilihan($d['kategori'] ?? null, 'kategori', self::KATEGORI, $lama),
            'harga_per_jam' => round($this->request->float('harga_per_jam')),
            'kapasitas'     => max(1, min(8, $this->request->int('kapasitas', (int) ($lama['kapasitas'] ?? 2)))),
            'status'        => $pilihan($d['status'] ?? null, 'status', self::STATUS, $lama),
            'deskripsi'     => $deskripsi === '' ? null : $deskripsi,
        ];
    }

    /** Nilai input hanya diterima bila ada dalam daftar whitelist. */
    private function whitelist(string $nilai, array $boleh): string
    {
        return in_array($nilai, $boleh, true) ? $nilai : '';
    }

    /**
     * Simpan berkas foto yang diunggah (null bila tidak ada unggahan).
     * Pesan gagal Uploader diteruskan sebagai flash agar tidak menjadi halaman 500.
     */
    private function fotoBaru(): ?string
    {
        try {
            return Uploader::store($this->request->file('foto'), 'units');
        } catch (RuntimeException $e) {
            flash('danger', $e->getMessage());
            back();
        }
    }
}

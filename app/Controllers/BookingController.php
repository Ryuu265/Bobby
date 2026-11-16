<?php
/**
 * BookingController — form pemesanan + endpoint JSON ketersediaan & harga.
 *
 * Endpoint jadwal/harga sengaja publik (tanpa login) supaya halaman katalog
 * bisa menampilkan papan ketersediaan. Tidak ada data pribadi di dalamnya.
 */

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Pricing;
use App\Core\Schedule;
use App\Models\Booking;
use App\Models\Setting;
use App\Models\Unit;
use RuntimeException;

class BookingController extends Controller
{
    /** Batas controller tambahan yang boleh diminta pelanggan. */
    private const MAKS_CONTROLLER = 6;

    /** Halaman form pemesanan. */
    public function form(): void
    {
        $units = Unit::bisaDipesan();
        foreach ($units as &$u) {
            $u['status_realtime'] = Schedule::statusUnit($u);
        }
        unset($u);

        if ($units === []) {
            flash('warning', 'Belum ada unit yang dapat dipesan. Silakan coba lagi nanti.');
            redirect('katalog');
        }

        $daftarTanggal = Schedule::rentangTanggal((int) config('booking.advance_days', 14));
        $lama = is_array($_SESSION['_old'] ?? null) ? $_SESSION['_old'] : [];

        // Nilai awal: old input (setelah gagal) -> query string -> unit pertama
        $unitId = (int) ($lama['unit'] ?? $this->request->int('unit'));
        $terpilih = null;
        foreach ($units as $u) {
            if ((int) $u['id'] === $unitId) {
                $terpilih = $u;
                break;
            }
        }
        $terpilih ??= $units[0];

        $tanggal = (string) ($lama['tanggal'] ?? $this->request->input('tanggal', ''));
        if (!in_array($tanggal, $daftarTanggal, true)) {
            $tanggal = $daftarTanggal[0];
        }

        $durasiMaks = (int) config('booking.max_durasi', 8);
        $durasi = (int) ($lama['durasi'] ?? $this->request->int('durasi', 1));
        $durasi = max((int) config('booking.min_durasi', 1), min($durasi, $durasiMaks));

        $controller = (int) ($lama['controller'] ?? $this->request->int('controller', 0));
        $controller = max(0, min($controller, self::MAKS_CONTROLLER));

        $paketDipilih = (int) ($lama['paket'] ?? $this->request->int('paket', 0));
        $paket = Pricing::paketAktif($tanggal);
        $paketValid = array_filter(
            $paket,
            static fn (array $p): bool => (int) $p['id'] === $paketDipilih
        );

        $metode = (string) ($lama['metode'] ?? $this->request->input('metode', 'transfer'));
        if (!in_array($metode, ['transfer', 'bayar_di_tempat'], true)) {
            $metode = 'transfer';
        }

        $harga = Pricing::hitung($terpilih, $tanggal, $durasi, $controller, $paketValid !== [] ? $paketDipilih : null);

        $this->view('pages/booking/form', [
            'units'        => $units,
            'unit'         => $terpilih,
            'daftarTanggal' => $daftarTanggal,
            'tanggal'      => $tanggal,
            'durasi'       => $durasi,
            'durasiMaks'   => $durasiMaks,
            'controller'   => $controller,
            'maksController' => self::MAKS_CONTROLLER,
            'paket'        => $paket,
            'paketDipilih' => $paketValid !== [] ? $paketDipilih : 0,
            'metode'       => $metode,
            'slots'        => Schedule::slots((int) $terpilih['id'], $tanggal, $durasi),
            'operasional'  => Schedule::jamOperasional($tanggal),
            'harga'        => $harga,
            'tarifController' => Pricing::tarifController(),
            'rekening'     => (string) Setting::get('rekening_bank', '-'),
        ]);
    }

    /** Simpan pesanan baru. */
    public function simpan(): void
    {
        $user = $this->user();
        if ($user === null) {
            flash('warning', 'Silakan masuk terlebih dahulu untuk membuat pesanan.');
            redirect('masuk');
        }

        $data = $this->validate([
            'unit'       => 'required|integer|exists:units,id',
            'tanggal'    => 'required|date',
            'jam'        => 'required|time',
            'durasi'     => 'required|integer|minval:1|maxval:' . (int) config('booking.max_durasi', 8),
            'controller' => 'integer|minval:0|maxval:' . self::MAKS_CONTROLLER,
            'paket'      => 'integer',
            'metode'     => 'required|in:transfer,bayar_di_tempat',
            'catatan'    => 'max:255',
        ], [
            'unit'       => 'Unit',
            'tanggal'    => 'Tanggal',
            'jam'        => 'Jam mulai',
            'durasi'     => 'Durasi sewa',
            'controller' => 'Jumlah controller tambahan',
            'paket'      => 'Paket',
            'metode'     => 'Metode pembayaran',
            'catatan'    => 'Catatan',
        ]);

        $paketId = (string) ($data['paket'] ?? '') !== '' ? (int) $data['paket'] : null;

        // Penjaga mandiri. Aturan bisnis Booking::pesan sudah menutup bentrok jadwal,
        // tetapi isian wajib diperiksa ulang di sini supaya form yang dikirim
        // sebagian (mis. oleh bot) tidak memicu peringatan dan tetap ditolak rapi.
        $rentang = Schedule::rentangTanggal((int) config('booking.advance_days', 14));
        $wajib = [
            trim((string) ($data['unit'] ?? '')) !== '',
            in_array((string) ($data['tanggal'] ?? ''), $rentang, true),
            preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', (string) ($data['jam'] ?? '')) === 1,
            (int) ($data['durasi'] ?? 0) >= (int) config('booking.min_durasi', 1),
            (int) ($data['durasi'] ?? 0) <= (int) config('booking.max_durasi', 8),
            in_array((string) ($data['metode'] ?? ''), ['transfer', 'bayar_di_tempat'], true),
        ];
        if (in_array(false, $wajib, true)) {
            $_SESSION['_old'] = $data;
            flash('danger', 'Isian pesanan belum lengkap atau tanggalnya di luar rentang pemesanan.');
            redirect('booking');
        }

        try {
            $b = Booking::pesan([
                'user_id'           => (int) $user['id'],
                'unit_id'           => (int) $data['unit'],
                'booking_date'      => (string) $data['tanggal'],
                'start_time'        => (string) $data['jam'],
                'durasi_jam'        => (int) $data['durasi'],
                'controller_tambahan' => max(0, min((int) ($data['controller'] ?? 0), self::MAKS_CONTROLLER)),
                'package_id'        => $paketId,
                'metode_bayar'      => (string) $data['metode'],
                'catatan_pelanggan' => trim((string) ($data['catatan'] ?? '')) !== ''
                    ? (string) $data['catatan'] : null,
            ]);
        } catch (RuntimeException $e) {
            // Slot bentrok / di luar jam operasional: kembalikan isian form apa adanya
            $_SESSION['_old'] = $data;
            flash('danger', $e->getMessage());
            redirect('booking');
        }

        flash('success', 'Pesanan ' . ($b['invoice_number'] ?? '') . ' berhasil dibuat. Silakan selesaikan pembayarannya.');
        redirect(route('booking.detail', ['id' => (int) ($b['id'] ?? 0)]));
    }

    /**
     * JSON ketersediaan slot satu unit.
     * GET /jadwal/{unit_id}?tanggal=Y-m-d&durasi=n
     */
    public function jadwal(string $unit_id): void
    {
        $unit = (new Unit())->find((int) $unit_id);
        if ($unit === null) {
            $this->json(['pesan' => 'Unit tidak ditemukan.'], 404);
        }

        $unitId = (int) $unit['id'];
        $tanggal = $this->tanggalMvalid();
        $durasi = $this->durasiTerpilih();

        $slots = array_map(
            static fn (array $s): array => [
                'value'    => $s['value'],
                'label'    => $s['label'],
                'tersedia' => $s['tersedia'],
                'alasan'   => $s['alasan'],
            ],
            Schedule::slots($unitId, $tanggal, $durasi)
        );

        $operasional = Schedule::jamOperasional($tanggal);

        $this->json([
            'unit_id'       => $unitId,
            'kode_unit'     => $unit['kode_unit'],
            'tipe'          => $unit['tipe'],
            'tanggal'       => $tanggal,
            'buka'          => $operasional['buka'],
            'tutup'         => $operasional['tutup'],
            'durasi'        => $durasi,
            'harga_per_jam' => (float) $unit['harga_per_jam'],
            'status'        => Schedule::statusUnit($unit),
            'slots'         => $slots,
            'terisi'        => Schedule::terisi($unitId, $tanggal),
        ]);
    }

    /**
     * JSON perhitungan biaya.
     * GET /harga?unit_id=&tanggal=&durasi=&controller=&package_id=
     */
    public function kalkulasi(): void
    {
        $unit = (new Unit())->find($this->request->int('unit_id'));
        if ($unit === null) {
            $this->json(['pesan' => 'Unit tidak ditemukan.'], 404);
        }

        $tanggal = $this->tanggalMvalid();
        $durasi = $this->durasiTerpilih();
        $controller = max(0, min($this->request->int('controller', 0), self::MAKS_CONTROLLER));
        $paketId = $this->request->int('package_id', 0);

        $hitung = Pricing::hitung(
            $unit,
            $tanggal,
            $durasi,
            $controller,
            $paketId > 0 ? $paketId : null
        );

        $this->json($hitung);
    }

    /** Tanggal valid dalam rentang pemesanan, fallback hari ini. */
    private function tanggalMvalid(): string
    {
        $tanggal = (string) $this->request->input('tanggal', date('Y-m-d'));
        $daftar = Schedule::rentangTanggal((int) config('booking.advance_days', 14));
        return in_array($tanggal, $daftar, true) ? $tanggal : $daftar[0];
    }

    /** Durasi aman 1..max_durasi. */
    private function durasiTerpilih(): int
    {
        $durasi = (int) $this->request->input('durasi', 1);
        return max((int) config('booking.min_durasi', 1), min($durasi, (int) config('booking.max_durasi', 8)));
    }
}

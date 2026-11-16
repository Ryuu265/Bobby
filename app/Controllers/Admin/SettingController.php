<?php
/**
 * SettingController — pengaturan operasional rental (key-value).
 *
 * Hak akses: hanya role "admin" yang boleh menyimpan. Operator boleh melihat
 * (tautan menu sudah disembunyikan di sidebar), tetapi POST ditolak 403.
 */

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\Error;
use App\Core\Schedule;
use App\Models\Setting;

class SettingController extends Controller
{
    /** Kunci bertipe angka beserta batas nilainya. */
    private const KUNCI_ANGKA = [
        'tarif_controller'      => ['label' => 'Biaya controller tambahan', 'min' => 0, 'max' => 1_000_000],
        'batas_menit_batal'     => ['label' => 'Batas menit pembatalan', 'min' => 0, 'max' => 1440],
        'jam_operasional_buka'  => ['label' => 'Jam buka', 'min' => 0, 'max' => 23],
        'jam_operasional_tutup' => ['label' => 'Jam tutup', 'min' => 1, 'max' => 24],
    ];

    public function index(): void
    {
        $this->adminView('admin/pengaturan/index', [
            'daftar'        => Setting::daftar(),
            'jamOperasional'=> Schedule::jamOperasional(date('Y-m-d')),
            'hanyaAdmin'    => Auth::isOnlyAdmin(),
        ]);
    }

    public function perbarui(): void
    {
        if (!Auth::isOnlyAdmin()) {
            Error::abort(403, 'Hanya admin yang boleh mengubah pengaturan operasional.');
        }

        // Hanya kunci yang benar-benar dirender form yang divalidasi. Kalau salah
        // satu kunci belum ada di tabel settings, aturan 'required' akan membuat
        // seluruh halaman pengaturan mustahil disimpan.
        $tersedia = array_column(Setting::daftar(), 'key_name');

        $rules = [];
        $labels = [];
        foreach (self::KUNCI_ANGKA as $kunci => $aturan) {
            if (!in_array($kunci, $tersedia, true)) {
                continue;
            }
            $rules[$kunci]  = 'required|numeric|minval:' . $aturan['min'] . '|maxval:' . $aturan['max'];
            $labels[$kunci] = $aturan['label'];
        }

        $data = $rules === [] ? [] : $this->validate($rules, $labels);

        // Jam operasional harus masuk akal: tutup selalu setelah buka
        $buka  = (int) ($data['jam_operasional_buka'] ?? Setting::get('jam_operasional_buka', 10));
        $tutup = (int) ($data['jam_operasional_tutup'] ?? Setting::get('jam_operasional_tutup', 24));
        if ($tutup <= $buka) {
            flash('danger', 'Jam tutup harus lebih besar daripada jam buka (format 24 jam).');
            $this->redirect('admin/pengaturan');
        }

        $payload = [];
        foreach (Setting::daftar() as $setting) {
            $kunci = (string) $setting['key_name'];
            if (!array_key_exists($kunci, $data)) {
                continue;
            }

            $nilai = trim((string) $data[$kunci]);
            if ($setting['tipe'] === 'angka') {
                $nilai = $this->bersihkanAngka($kunci, $nilai);
            }
            $payload[$kunci] = $nilai;
        }

        Setting::setBanyak($payload);
        flash('success', 'Pengaturan disimpan (' . count($payload) . ' kunci). Nilai baru langsung dipakai halaman publik.');

        $this->redirect('admin/pengaturan');
    }

    /**
     * Normalisasi nilai angka: "30.000" / "30,000" -> 30000,
     * kunci jam disimpan sebagai bilangan bulat.
     */
    private function bersihkanAngka(string $kunci, string $nilai): string
    {
        $bersih = str_replace([',', '.', ' '], ['', '', ''], $nilai);
        if (!is_numeric($bersih)) {
            return (string) (float) str_replace(',', '.', $nilai);
        }

        $angka = $kunci === 'tarif_controller' ? (float) $bersih : (int) $bersih;
        return (string) $angka;
    }
}

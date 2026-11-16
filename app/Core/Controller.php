<?php
/**
 * Controller — kelas dasar semua controller.
 * Menyediakan shortcut render, redirect, validasi, dan cek hak akses.
 */

namespace App\Core;

use App\Models\Setting;

abstract class Controller
{
    protected Request $request;

    public function __construct()
    {
        $this->request = Request::instance();
    }

    /** Render view dengan layout default. */
    protected function view(string $view, array $data = [], string $layout = 'layouts/app'): void
    {
        View::display($view, $data + $this->shared(), $layout);
    }

    /** Render view halaman admin (layout sidebar). */
    protected function adminView(string $view, array $data = []): void
    {
        $this->view($view, $data, 'layouts/admin');
    }

    /** Render tanpa layout, mis. untuk respons AJAX. */
    protected function fragment(string $view, array $data = []): void
    {
        View::display($view, $data, null);
    }

    /** Data yang selalu tersedia di view. */
    protected function shared(): array
    {
        return [
            'authUser'   => Auth::user(),
            'isAdmin'    => Auth::isAdmin(),
            'errors'     => $_SESSION['_errors'] ?? [],
            'old'        => $_SESSION['_old'] ?? [],
            'settings'   => Setting::allCached(),
        ];
        // CATATAN: pesan flash sengaja TIDAK dibaca di sini. Helper flash()
        // sekali baca akan menghapus nilainya, sehingga partials/flash.php
        // (yang disisipkan layout) tidak pernah menemukan pesan lagi.
    }

    protected function redirect(string $path): never
    {
        redirect($path);
    }

    protected function json(mixed $data, int $status = 200): never
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    /**
     * Validasi input. Bila gagal: simpan error + old input, flash pesan,
     * lalu kembali ke halaman sebelumnya.
     */
    protected function validate(array $rules, array $labels = [], array $uploads = []): array
    {
        $validator = Validator::make($this->request->all(), $rules, $labels, $uploads);
        $validator->validate();

        if ($validator->fails()) {
            $_SESSION['_errors'] = $validator->errors();
            $_SESSION['_old'] = $this->request->all();
            flash('danger', 'Periksa kembali isian Anda: ' . ($validator->firstError() ?? 'data belum valid.'));
            back();
        }
        unset($_SESSION['_errors'], $_SESSION['_old']);
        return $this->request->all();
    }

    /** Ambil data di tengah-tengah (untuk form edit) lalu buang dari sesi. */
    protected function errors(): array
    {
        $errors = $_SESSION['_errors'] ?? [];
        unset($_SESSION['_errors']);
        return $errors;
    }

    protected function user(): ?array
    {
        return Auth::user();
    }

    /** Tolak akses bila bukan pemilik data (kecuali admin/operator). */
    protected function authorizeOwner(int $pemilikId, string $pesan = 'Data ini bukan milik Anda.'): void
    {
        if (Auth::isAdmin()) {
            return;
        }
        if ($pemilikId !== (int) Auth::id()) {
            Error::abort(403, $pesan);
        }
    }
}

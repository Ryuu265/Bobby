<?php
/**
 * Middleware — gerbang hak akses & perlindungan CSRF.
 * 'tamu'    : hanya untuk yang belum login
 * 'auth'    : wajib login (pelanggan/operator/admin)
 * 'admin'   : wajib login dengan role admin atau operator
 * 'csrf'    : pastikan token form cocok (dipakai otomatis pada POST)
 */

namespace App\Core;

class Middleware
{
    public static function handle(string $name, Request $request): void
    {
        switch ($name) {
            case 'tamu':
                if (Auth::check()) {
                    redirect(Auth::isAdmin() ? 'admin/dashboard' : '');
                }
                break;

            case 'auth':
                if (!Auth::check()) {
                    flash('warning', 'Silakan masuk terlebih dahulu untuk mengakses halaman tersebut.');
                    $_SESSION['_after_login'] = $request->uri();
                    redirect('masuk');
                }
                break;

            case 'admin':
                if (!Auth::check()) {
                    flash('warning', 'Silakan masuk terlebih dahulu.');
                    $_SESSION['_after_login'] = $request->uri();
                    redirect('masuk');
                }
                if (!Auth::isAdmin()) {
                    Error::abort(403, 'Halaman ini khusus admin/operator.');
                }
                break;

            case 'csrf':
                // Validasi token hanya untuk request yang mengubah data
                if (in_array($request->method(), ['POST', 'PUT', 'PATCH', 'DELETE'], true)) {
                    Csrf::verify($request->input('_token'));
                }
                break;

            default:
                throw new \RuntimeException("Middleware tidak dikenal: {$name}");
        }
    }
}

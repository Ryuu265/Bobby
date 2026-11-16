<?php
/**
 * Error — halaman galat ber-brand (404, 403, 419, 500).
 */

namespace App\Core;

class Error
{
    private const JUDUL = [
        403 => 'Akses ditolak',
        404 => 'Halaman tidak ditemukan',
        419 => 'Sesi kedaluwarsa',
        500 => 'Terjadi kesalahan',
    ];

    /** Hentikan request dan tampilkan halaman galat. */
    public static function abort(int $code, string $message = ''): never
    {
        http_response_code($code);

        $messages = [
            403 => $message ?: 'Anda tidak punya hak akses ke halaman ini.',
            404 => $message ?: 'Halaman yang Anda cari tidak ditemukan.',
            419 => $message ?: 'Form sudah kedaluwarsa, silakan muat ulang halaman.',
            500 => $message ?: 'Maaf, terjadi kesalahan pada sistem.',
        ];

        try {
            View::display('pages/error', [
                'kode'    => $code,
                'judul'   => self::JUDUL[$code] ?? 'Galat',
                'pesan'   => $messages[$code] ?? $message,
                'detail'  => config('app.debug') && isset($messages[500]) ? null : null,
            ]);
        } catch (\Throwable) {
            // Fallback bila layout ikut bermasalah
            echo '<h1>' . $code . ' — ' . htmlspecialchars($messages[$code] ?? 'Galat', ENT_QUOTES, 'UTF-8') . '</h1>';
        }
        exit;
    }

    /** Tampilkan halaman 500 beserta stack trace hanya saat debug aktif. */
    public static function exception(\Throwable $e): void
    {
        http_response_code(500);
        error_log('[Qengamestation] ' . $e::class . ': ' . $e->getMessage()
            . ' @ ' . $e->getFile() . ':' . $e->getLine());

        if (PHP_SAPI === 'cli') {
            fwrite(STDERR, $e->getMessage() . PHP_EOL);
            return;
        }

        View::display('pages/error', [
            'kode'   => 500,
            'judul'  => self::JUDUL[500],
            'pesan'  => 'Kami sedang memperbaiki masalah ini. Silakan coba beberapa saat lagi.',
            'detail' => config('app.debug')
                ? $e::class . ': ' . $e->getMessage() . "\n" . $e->getTraceAsString()
                : null,
        ]);
    }
}

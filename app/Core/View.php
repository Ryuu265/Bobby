<?php
/**
 * View — mesin template sederhana berbasis file PHP.
 * Mendukung layout, section, @include partial, dan data ke view.
 *
 * konvensi folder:  app/Views/
 *   layouts/app.php, layouts/admin.php
 *   partials/navbar.php, partials/sidebar.php, partials/flash.php
 *   pages/..., admin/...
 */

namespace App\Core;

use RuntimeException;

class View
{
    private static array $sections = [];
    private static array $sectionStack = [];

    /** Render view dengan layout. $data tersedia sebagai variabel di template. */
    public static function render(string $view, array $data = [], ?string $layout = 'layouts/app'): string
    {
        $content = self::capture($view, $data);

        if ($layout === null) {
            return $content;
        }

        self::$sections['content'] = $content;
        return self::capture($layout, $data + ['yield' => &$content]);
    }

    /** Render view dan langsung kirim ke browser. */
    public static function display(string $view, array $data = [], ?string $layout = 'layouts/app'): void
    {
        echo self::render($view, $data, $layout);
    }

    /** Render tanpa layout (untuk respons AJAX/parsial). */
    public static function partial(string $view, array $data = []): string
    {
        return self::capture($view, $data);
    }

    private static function capture(string $view, array $data): string
    {
        // Nama view boleh ditulis 'admin/unit/index' atau 'admin.unit.index'.
        $relatif = ltrim(str_replace(['.', '\\'], '/', $view), '/');
        $file = APP_ROOT . '/app/Views/' . $relatif . '.php';
        if (!is_file($file)) {
            throw new RuntimeException("View tidak ditemukan: {$view}");
        }

        extract($data, EXTR_SKIP);
        ob_start();
        try {
            include $file;
        } catch (\Throwable $e) {
            ob_end_clean();
            throw new RuntimeException(
                'Error pada view ' . $view . ': ' . $e->getMessage(),
                0,
                $e
            );
        }
        return (string) ob_get_clean();
    }

    /* ---------------- Section / Start-Stop ---------------- */

    /** Isi section sederhana tanpa buffer (dipakai untuk title, scripts, dll). */
    public static function set(string $name, string $value): void
    {
        self::$sections[$name] = ($name === 'scripts' || $name === 'styles')
            ? (self::$sections[$name] ?? '') . $value
            : $value;
    }

    public static function start(string $name): void
    {
        self::$sectionStack[] = $name;
        ob_start();
    }

    public static function stop(): void
    {
        $name = array_pop(self::$sectionStack);
        self::$sections[$name] = (self::$sections[$name] ?? '') . (string) ob_get_clean();
    }

    /** Hasil section, dengan default bila kosong. */
    public static function section(string $name, string $default = ''): string
    {
        return self::$sections[$name] ?? $default;
    }

    public static function hasSection(string $name): bool
    {
        return isset(self::$sections[$name]) && trim(self::$sections[$name]) !== '';
    }

    /** Include partial dan kembalikan string (dipakai di template). */
    public static function include(string $view, array $data = []): string
    {
        return self::capture($view, $data);
    }
}

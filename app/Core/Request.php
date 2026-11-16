<?php
/**
 * Request — membungkus data HTTP masuk (GET/POST/files) dengan akses aman.
 */

namespace App\Core;

class Request
{
    private static ?Request $instance = null;
    private array $input;
    private array $query;
    private array $files;

    private function __construct()
    {
        // Trim otomatis untuk string biasa; array bersarang diproses rekursif
        $this->input = self::clean($_POST);
        $this->query = self::clean($_GET);
        $this->files = $_FILES;
    }

    public static function instance(): Request
    {
        return self::$instance ??= new self();
    }

    private static function clean(array $data): array
    {
        $out = [];
        foreach ($data as $key => $value) {
            if (is_array($value)) {
                $out[$key] = is_list($value)
                    ? array_values(array_filter($value, static fn ($v) => $v !== null && $v !== ''))
                    : self::clean($value);
            } elseif ($value !== null) {
                $out[$key] = trim($value);
            }
        }
        return $out;
    }

    public function isPost(): bool
    {
        return strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST';
    }

    public function method(): string
    {
        $method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
        // Dukungan metode palsu lewat input _method (PUT/DELETE pada form HTML)
        if ($method === 'POST' && isset($this->input['_method'])) {
            $fake = strtoupper((string) $this->input['_method']);
            if (in_array($fake, ['PUT', 'PATCH', 'DELETE'], true)) {
                return $fake;
            }
        }
        return $method;
    }

    /** Ambil input, default string kosong agar view tidak error. */
    public function input(string $key, mixed $default = ''): mixed
    {
        return $this->input[$key] ?? $this->query[$key] ?? $default;
    }

    public function int(string $key, int $default = 0): int
    {
        $value = $this->input($key, null);
        return $value === null || $value === '' ? $default : (int) $value;
    }

    public function float(string $key, float $default = 0.0): float
    {
        $value = trim((string) $this->input($key, ''));
        if ($value === '') {
            return $default;
        }

        // Kenali gaya Indonesia: "20.000,50" -> 20000.50 ; "1,5" -> 1.5
        if (str_contains($value, ',') && str_contains($value, '.')) {
            $value = str_replace('.', '', $value);
            $value = str_replace(',', '.', $value);
        } elseif (str_contains($value, ',')) {
            $value = str_replace(',', '.', $value);
        } elseif (preg_match('/^-?\d{1,3}(\.\d{3})+$/', $value)) {
            // Titik sebagai pemisah ribuan saja (contoh: 150.000)
            $value = str_replace('.', '', $value);
        }

        return is_numeric($value) ? (float) $value : $default;
    }

    public function bool(string $key): bool
    {
        return filter_var($this->input($key, false), FILTER_VALIDATE_BOOLEAN);
    }

    /** Array input, mis. daftar game_id yang dicentang. */
    public function array(string $key): array
    {
        $value = $this->input($key, []);
        return is_array($value) ? $value : [$value];
    }

    public function all(): array
    {
        return $this->input + $this->query;
    }

    public function has(string $key): bool
    {
        return array_key_exists($key, $this->input) || array_key_exists($key, $this->query);
    }

    public function filled(string $key): bool
    {
        $value = $this->input($key, null);
        return $value !== null && $value !== '';
    }

    /** Ambil file upload yang valid strukturnya. */
    public function file(string $key): ?array
    {
        $file = $this->files[$key] ?? null;
        if (!is_array($file) || ($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
            return null;
        }
        return $file;
    }

    public function ip(): string
    {
        return (string) ($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0');
    }

    /** Path URI saat ini (sudah dibersihkan dari folder dasar aplikasi). */
    public function uri(): string
    {
        $uri = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
        $base = rtrim(dirname($_SERVER['SCRIPT_NAME'] ?? '/index.php'), '/');
        if ($base !== '' && $base !== '/' && str_starts_with($uri, $base)) {
            $uri = substr($uri, strlen($base));
        }
        $uri = '/' . ltrim($uri, '/');
        return $uri === '/index.php' ? '/' : $uri;
    }

    /** Ambil segmen URL: uri(0) = 'admin', uri(1) = 'unit'. */
    public function segment(int $index): ?string
    {
        $parts = array_values(array_filter(explode('/', $this->uri()), static fn ($p) => $p !== ''));
        return $parts[$index] ?? null;
    }
}

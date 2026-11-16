<?php
/**
 * Router — pemeta URI ke method controller.
 * Mendukung parameter dinamis {id}, middleware (auth/admin), dan named routes.
 */

namespace App\Core;

use RuntimeException;

class Router
{
    private static ?Router $instance = null;

    /** @var array<string, array<int, array>> Rute per method HTTP */
    private array $routes = [];

    /** @var array<string, string> Peta nama rute -> path */
    private array $names = [];

    /** Middleware yang sedang berlaku untuk group berikutnya. */
    private array $groupMiddleware = [];

    /** Prefix path untuk group berikutnya. */
    private string $groupPrefix = '';

    /** Instance aktif, dipakai helper route() di view. */
    public static function instance(): Router
    {
        return self::$instance ??= new self();
    }

    public static function setCurrent(Router $router): void
    {
        self::$instance = $router;
    }

    public function get(string $path, string|callable $handler, ?string $name = null): void
    {
        $this->add('GET', $path, $handler, $name);
    }

    public function post(string $path, string|callable $handler, ?string $name = null): void
    {
        $this->add('POST', $path, $handler, $name);
    }

    /** Form HTML hanya mendukung GET/POST, jadi PUT/DELETE dikirim via _method. */
    public function put(string $path, string|callable $handler, ?string $name = null): void
    {
        $this->add('PUT', $path, $handler, $name);
    }

    public function delete(string $path, string|callable $handler, ?string $name = null): void
    {
        $this->add('DELETE', $path, $handler, $name);
    }

    /** Daftar rute dengan middleware & prefix bersama. */
    public function group(array $options, callable $callback): void
    {
        $previousMw = $this->groupMiddleware;
        $previousPrefix = $this->groupPrefix;

        $this->groupMiddleware = array_merge($previousMw, (array) ($options['middleware'] ?? []));
        $this->groupPrefix = $previousPrefix . rtrim((string) ($options['prefix'] ?? ''), '/');

        $callback($this);

        $this->groupMiddleware = $previousMw;
        $this->groupPrefix = $previousPrefix;
    }

    private function add(string $method, string $path, string|callable $handler, ?string $name): void
    {
        $path = $this->groupPrefix . '/' . ltrim($path, '/');
        $path = '/' . ltrim(preg_replace('#/+#', '/', $path) ?? $path, '/');
        $path = rtrim($path, '/') ?: '/';

        $this->routes[$method][] = [
            'path'    => $path,
            'regex'   => $this->toRegex($path),
            'handler' => $handler,
            'middleware' => $this->groupMiddleware,
            'name'    => $name,
        ];

        if ($name !== null) {
            $this->names[$name] = $path;
        }
    }

    /** '/admin/unit/{id}/edit' -> '#^/admin/unit/(?P<id>[^/]+)/edit$#' */
    private function toRegex(string $path): string
    {
        $regex = preg_replace_callback(
            '#\{([a-zA-Z_][a-zA-Z0-9_]*)\}#',
            static fn (array $m): string => '(?P<' . $m[1] . '>[^/]+)',
            $path
        );
        return '#^' . str_replace('#', '\#', (string) $regex) . '$#';
    }

    /** Cocokkan request lalu panggil handler. */
    public function dispatch(Request $request): void
    {
        $method = $request->method();
        $uri = $request->uri();

        foreach ($this->routes[$method] ?? [] as $route) {
            if (preg_match($route['regex'], $uri, $matches)) {
                $params = array_filter($matches, 'is_string', ARRAY_FILTER_USE_KEY);

                // Setiap request yang mengubah data selalu dicek token CSRF-nya
                if (in_array($method, ['POST', 'PUT', 'PATCH', 'DELETE'], true)) {
                    Middleware::handle('csrf', $request);
                }

                // Jalankan middleware rute (auth, admin, tamu)
                foreach ($route['middleware'] as $mw) {
                    Middleware::handle($mw, $request);
                }

                $handler = $route['handler'];
                if (is_callable($handler)) {
                    $handler(...array_values($params));
                    return;
                }

                [$class, $action] = explode('@', $handler, 2);
                $fqcn = 'App\\Controllers\\' . ltrim($class, '\\');
                if (!class_exists($fqcn) || !method_exists($fqcn, $action)) {
                    throw new RuntimeException("Handler {$fqcn}::{$action}() tidak ditemukan.");
                }
                $controller = new $fqcn();
                $controller->{$action}(...array_values($params));
                return;
            }
        }

        // Tidak ada rute yang cocok
        Error::abort(404, 'Halaman yang Anda cari tidak ditemukan.');
    }

    /** Ambil path dari nama rute: route('admin.dashboard') */
    public function path(string $name, array $params = []): string
    {
        $path = $this->names[$name] ?? '/' . $name;
        foreach ($params as $key => $value) {
            $path = str_replace('{' . $key . '}', rawurlencode((string) $value), $path);
        }
        return $path;
    }

    public function all(): array
    {
        return $this->routes;
    }
}

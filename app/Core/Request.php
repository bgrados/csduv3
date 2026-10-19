<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Representa la solicitud HTTP entrante.
 */
final class Request
{
    private const SPOOFABLE_METHODS = ['PUT', 'PATCH', 'DELETE'];

    /**
     * @param array<string, mixed> $query
     * @param array<string, mixed> $body
     * @param array<string, mixed> $files
     * @param array<string, mixed> $server
     */
    private function __construct(
        private string $method,
        private string $path,
        private array $query,
        private array $body,
        private array $files,
        private array $server
    ) {
    }

    public static function capture(): self
    {
        $method = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));
        $query = $_GET;
        $body = $_POST;

        // Permite enviar formularios con PUT/PATCH/DELETE mediante un campo oculto.
        if ($method === 'POST') {
            $spoofed = strtoupper((string) ($body['_method'] ?? ''));

            if (in_array($spoofed, self::SPOOFABLE_METHODS, true)) {
                $method = $spoofed;
            }
        }

        $path = self::normalizePath(self::requestUri());

        return new self($method, $path, $query, $body, $_FILES, $_SERVER);
    }

    public function method(): string
    {
        return $this->method === 'HEAD' ? 'GET' : $this->method;
    }

    public function path(): string
    {
        return $this->path;
    }

    public function isGet(): bool
    {
        return $this->method() === 'GET';
    }

    public function isPost(): bool
    {
        return $this->method() === 'POST';
    }

    public function isSecure(): bool
    {
        return (!empty($this->server['HTTPS']) && $this->server['HTTPS'] !== 'off')
            || (($this->server['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
    }

    public function input(string $key, mixed $default = null): mixed
    {
        return $this->body[$key] ?? $this->query[$key] ?? $default;
    }

    /** Devuelve un valor de texto recortado. */
    public function string(string $key, string $default = ''): string
    {
        $value = $this->input($key, $default);

        if (is_array($value)) {
            return $default;
        }

        return trim((string) $value);
    }

    public function integer(string $key, int $default = 0): int
    {
        $value = $this->input($key, null);

        if ($value === null || $value === '') {
            return $default;
        }

        if (!is_numeric($value)) {
            return $default;
        }

        return (int) $value;
    }

    public function boolean(string $key): bool
    {
        $value = $this->input($key, null);

        if (is_bool($value)) {
            return $value;
        }

        if ($value === null) {
            return false;
        }

        return in_array(strtolower((string) $value), ['1', 'true', 'on', 'yes', 'si', 'sí'], true);
    }

    /** @return array<string, mixed> */
    public function all(): array
    {
        return array_merge($this->query, $this->body);
    }

    /** @return array<string, string> */
    public function only(array $keys): array
    {
        $result = [];

        foreach ($keys as $key) {
            $value = $this->input($key);

            if ($value !== null && !is_array($value)) {
                $result[$key] = (string) $value;
            }
        }

        return $result;
    }

    public function has(string $key): bool
    {
        return array_key_exists($key, $this->body) || array_key_exists($key, $this->query);
    }

    /** @return array<string, mixed>|null */
    public function file(string $key): ?array
    {
        $file = $this->files[$key] ?? null;

        return is_array($file) ? $file : null;
    }

    public function ip(): string
    {
        $candidates = [
            $this->server['HTTP_CF_CONNECTING_IP'] ?? null,
            $this->server['HTTP_X_FORWARDED_FOR'] ?? null,
            $this->server['REMOTE_ADDR'] ?? null,
        ];

        foreach ($candidates as $candidate) {
            if (!is_string($candidate) || $candidate === '') {
                continue;
            }

            $ip = trim(explode(',', $candidate)[0]);

            if (filter_var($ip, FILTER_VALIDATE_IP) !== false) {
                return $ip;
            }
        }

        return '0.0.0.0';
    }

    public function userAgent(): string
    {
        $agent = $this->server['HTTP_USER_AGENT'] ?? '';

        return is_string($agent) ? substr($agent, 0, 255) : '';
    }

    public function header(string $name): ?string
    {
        $key = 'HTTP_' . strtoupper(str_replace('-', '_', $name));
        $value = $this->server[$key] ?? null;

        return is_string($value) ? $value : null;
    }

    public function expectsJson(): bool
    {
        $accept = $this->header('Accept') ?? '';

        return str_contains($accept, 'application/json');
    }

    /** Ruta solicitada antes del front controller, útil para reintentos. */
    public function fullUrl(): string
    {
        $uri = (string) ($this->server['REQUEST_URI'] ?? '/');

        return rtrim((string) Config::get('app.url', ''), '/') . $uri;
    }

    private static function requestUri(): string
    {
        $uri = (string) ($_SERVER['REQUEST_URI'] ?? '/');

        return $uri === '' ? '/' : $uri;
    }

    /**
     * Devuelve la ruta normalizada, quitando la ruta base de instalación.
     */
    private static function normalizePath(string $uri): string
    {
        $path = parse_url($uri, PHP_URL_PATH);

        if (!is_string($path) || $path === '') {
            $path = '/';
        }

        $base = self::basePath();

        if ($base !== '' && str_starts_with($path, $base)) {
            $path = substr($path, strlen($base));
        }

        $path = '/' . trim($path, '/');

        return $path === '/' ? '/' : rtrim($path, '/');
    }

    /**
     * Ruta base bajo la que se sirve la aplicación ('' si está en la raíz del
     * subdominio, '/carpeta' si se instala en un subdirectorio).
     */
    public static function basePath(): string
    {
        $url = (string) Config::get('app.url', '');

        if ($url !== '') {
            $path = parse_url($url, PHP_URL_PATH);

            if (is_string($path) && $path !== '' && $path !== '/') {
                return '/' . trim($path, '/');
            }

            return '';
        }

        $script = (string) ($_SERVER['SCRIPT_NAME'] ?? '');
        $directory = str_replace('\\', '/', dirname($script));

        return ($directory === '/' || $directory === '.') ? '' : rtrim($directory, '/');
    }
}

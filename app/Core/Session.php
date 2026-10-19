<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Manejo de sesiones del servidor.
 */
final class Session
{
    private static bool $started = false;

    public static function start(): void
    {
        if (self::$started || session_status() === PHP_SESSION_ACTIVE) {
            self::$started = true;
            return;
        }

        $config = (array) Config::get('security.session', []);
        $name = (string) ($config['name'] ?? 'csduv3_sesion');
        $secure = (bool) ($config['secure'] ?? false) || self::isHttps();

        if (PHP_VERSION_ID >= 70300) {
            session_set_cookie_params([
                'lifetime' => (int) ($config['lifetime'] ?? 0),
                'path'     => (string) ($config['path'] ?? '/'),
                'domain'   => '',
                'secure'   => $secure,
                'httponly' => (bool) ($config['http_only'] ?? true),
                'samesite' => (string) ($config['same_site'] ?? 'Lax'),
            ]);
        }

        session_name($name);
        session_start();

        self::$started = true;
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        self::start();

        return $_SESSION[$key] ?? $default;
    }

    public static function put(string $key, mixed $value): void
    {
        self::start();

        $_SESSION[$key] = $value;
    }

    public static function has(string $key): bool
    {
        self::start();

        return isset($_SESSION[$key]);
    }

    public static function forget(string $key): void
    {
        self::start();

        unset($_SESSION[$key]);
    }

    /**
     * Guarda un mensaje de un solo uso.
     *
     * Con un solo argumento, recupera y elimina el mensaje almacenado.
     */
    public static function flash(string $key, mixed $value = null): mixed
    {
        self::start();

        if ($value === null) {
            $stored = $_SESSION['_flash'][$key] ?? null;

            if (array_key_exists($key, $_SESSION['_flash'] ?? [])) {
                unset($_SESSION['_flash'][$key]);
            }

            return $stored;
        }

        $_SESSION['_flash'][$key] = $value;

        return null;
    }

    /** @return array<string, mixed> */
    public static function pullFlashes(): array
    {
        self::start();

        $flashes = $_SESSION['_flash'] ?? [];
        unset($_SESSION['_flash']);

        return is_array($flashes) ? $flashes : [];
    }

    public static function regenerate(bool $deleteOldSession = true): void
    {
        self::start();

        if (session_status() === PHP_SESSION_ACTIVE) {
            session_regenerate_id($deleteOldSession);
        }
    }

    public static function id(): string
    {
        self::start();

        return (string) session_id();
    }

    public static function destroy(): void
    {
        self::start();

        $_SESSION = [];

        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', [
                'expires'  => time() - 42000,
                'path'     => $params['path'],
                'domain'   => $params['domain'],
                'secure'   => (bool) $params['secure'],
                'httponly' => (bool) $params['httponly'],
                'samesite' => 'Lax',
            ]);
        }

        session_destroy();
        self::$started = false;
    }

    private static function isHttps(): bool
    {
        return (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
            || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
    }
}

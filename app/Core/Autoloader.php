<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Cargador de clases basado en PSR-4, sin dependencias externas.
 *
 * No requiere Composer en el servidor: basta con subir los archivos por FTP
 * o mediante el Administrador de archivos de cPanel.
 */
final class Autoloader
{
    /** @var array<string, string> Prefijos de espacio de nombres => directorio base */
    private static array $prefixes = [];

    public static function register(): void
    {
        self::addNamespace('App\\', dirname(__DIR__));
        spl_autoload_register([self::class, 'load']);
    }

    public static function addNamespace(string $prefix, string $directory): void
    {
        $prefix = trim($prefix, '\\') . '\\';
        self::$prefixes[$prefix] = rtrim($directory, '/\\') . DIRECTORY_SEPARATOR;
    }

    private static function load(string $class): void
    {
        foreach (self::$prefixes as $prefix => $directory) {
            if (!str_starts_with($class, $prefix)) {
                continue;
            }

            $relative = substr($class, strlen($prefix));
            $file = $directory . str_replace('\\', DIRECTORY_SEPARATOR, $relative) . '.php';

            if (is_file($file)) {
                require $file;
                return;
            }
        }
    }
}

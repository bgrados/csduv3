<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Repositorio de configuración con acceso por notación de punto.
 *
 * Ejemplo: Config::get('app.name'), Config::get('database.host').
 */
final class Config
{
    /** @var array<string, mixed> */
    private static array $items = [];

    /**
     * Carga todos los archivos .php de un directorio. El nombre del archivo
     * (sin extensión) se usa como clave raíz.
     */
    public static function load(string $directory): void
    {
        $directory = rtrim($directory, '/\\');
        $files = glob($directory . DIRECTORY_SEPARATOR . '*.php');

        if ($files === false) {
            return;
        }

        sort($files);

        foreach ($files as $file) {
            $data = require $file;

            if (!is_array($data)) {
                continue;
            }

            self::$items[basename($file, '.php')] = $data;
        }
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        $value = self::$items;

        foreach (explode('.', $key) as $segment) {
            if (!is_array($value) || !array_key_exists($segment, $value)) {
                return $default;
            }

            $value = $value[$segment];
        }

        return $value;
    }

    public static function set(string $key, mixed $value): void
    {
        $segments = explode('.', $key);
        $target = &self::$items;

        foreach ($segments as $index => $segment) {
            if ($index === count($segments) - 1) {
                $target[$segment] = $value;
                return;
            }

            if (!isset($target[$segment]) || !is_array($target[$segment])) {
                $target[$segment] = [];
            }

            $target = &$target[$segment];
        }
    }

    /** @return array<string, mixed> */
    public static function all(): array
    {
        return self::$items;
    }
}

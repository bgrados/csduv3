<?php

declare(strict_types=1);

namespace App\Core;

use RuntimeException;
use Throwable;

/**
 * Motor de vistas basado en archivos PHP planos.
 *
 * Las vistas usan la notación con punto: 'socios.index' =>
 * app/Views/socios/index.php
 */
final class View
{
    /** @var array<string, mixed> Datos disponibles en todas las vistas. */
    private static array $shared = [];

    /** @param array<string, mixed> $data */
    public static function share(array $data): void
    {
        self::$shared = array_merge(self::$shared, $data);
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function render(string $view, array $data = [], ?string $layout = 'layouts/app'): string
    {
        $content = self::capture($view, $data);

        if ($layout === null) {
            return $content;
        }

        return self::capture($layout, array_merge($data, ['content' => $content]));
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function display(string $view, array $data = [], ?string $layout = 'layouts/app'): void
    {
        echo self::render($view, $data, $layout);
    }

    public static function exists(string $view): bool
    {
        return is_file(self::resolve($view));
    }

    public static function path(string $view): string
    {
        return self::resolve($view);
    }

    /**
     * @param array<string, mixed> $data
     */
    private static function capture(string $view, array $data): string
    {
        $file = self::resolve($view);

        if (!is_file($file)) {
            throw new RuntimeException('No se encontró la vista: ' . $view);
        }

        $payload = array_merge(self::$shared, $data);
        extract($payload, EXTR_SKIP);

        ob_start();

        try {
            include $file;
        } catch (Throwable $exception) {
            ob_end_clean();

            throw $exception;
        }

        return (string) ob_get_clean();
    }

    private static function resolve(string $view): string
    {
        $base = (string) Config::get('app.views_path', dirname(__DIR__) . '/Views');
        $relative = str_replace(['.', '\\'], '/', $view);

        return rtrim($base, '/\\') . '/' . $relative . '.php';
    }
}

<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Respuestas HTTP.
 */
final class Response
{
    public static function redirect(string $url, int $status = 302): never
    {
        if (!headers_sent()) {
            header('Location: ' . $url, true, $status);
        }

        exit;
    }

    public static function json(mixed $data, int $status = 200): never
    {
        if (!headers_sent()) {
            http_response_code($status);
            header('Content-Type: application/json; charset=UTF-8');
            header('X-Content-Type-Options: nosniff');
        }

        echo (string) json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        exit;
    }

    public static function text(string $content, int $status = 200): never
    {
        if (!headers_sent()) {
            http_response_code($status);
            header('Content-Type: text/plain; charset=UTF-8');
        }

        echo $content;

        exit;
    }

    /**
     * Entrega un archivo del disco al navegador.
     */
    public static function archivo(string $rutaAbsoluta, string $cache = 'private, no-store'): never
    {
        if (!is_file($rutaAbsoluta)) {
            abort(404, 'El archivo solicitado no existe.');
        }

        $mime = 'application/octet-stream';
        $info = @getimagesize($rutaAbsoluta);

        if ($info !== false && !empty($info['mime'])) {
            $mime = (string) $info['mime'];
        } elseif (function_exists('mime_content_type')) {
            $detectado = @mime_content_type($rutaAbsoluta);

            if (is_string($detectado) && $detectado !== '') {
                $mime = $detectado;
            }
        }

        if (!headers_sent()) {
            header('Content-Type: ' . $mime);
            header('Content-Length: ' . (string) filesize($rutaAbsoluta));
            header('Cache-Control: ' . $cache);
            header('X-Content-Type-Options: nosniff');
        }

        readfile($rutaAbsoluta);
        exit;
    }

    public static function noContent(): never
    {
        if (!headers_sent()) {
            http_response_code(204);
        }

        exit;
    }

    /**
     * Redirige a la página anterior cuando es seguro hacerlo.
     */
    public static function back(string $fallback = '/'): never
    {
        $referer = $_SERVER['HTTP_REFERER'] ?? '';

        if (is_string($referer) && $referer !== '' && self::isSameOrigin($referer)) {
            self::redirect($referer);
        }

        self::redirect(url($fallback));
    }

    private static function isSameOrigin(string $url): bool
    {
        $host = parse_url($url, PHP_URL_HOST);

        if (!is_string($host) || $host === '') {
            return false;
        }

        $configured = parse_url((string) Config::get('app.url', ''), PHP_URL_HOST);

        if (is_string($configured) && $configured !== '') {
            return strcasecmp($host, $configured) === 0;
        }

        $current = (string) ($_SERVER['HTTP_HOST'] ?? '');

        return strcasecmp($host, $current) === 0;
    }
}

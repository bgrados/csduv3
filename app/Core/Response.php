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

<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Registro de eventos de la aplicación en archivos de texto.
 *
 * Los datos sensibles (contraseñas, tokens, claves) se enmascaran antes de
 * escribirse en el archivo de registro.
 */
final class Logger
{
    /** @var array<string, int> */
    private const LEVELS = [
        'debug'    => 0,
        'info'     => 1,
        'warning'  => 2,
        'error'    => 3,
        'critical' => 4,
    ];

    /** @var array<int, string> */
    private const SENSITIVE_PATTERNS = [
        'password',
        'passwd',
        'contraseña',
        'contrasena',
        'secret',
        'token',
        'authorization',
        'api_key',
        'apikey',
        'app_key',
        'cookie',
        'session',
    ];

    public static function debug(string $message, array $context = []): void
    {
        self::write('debug', $message, $context);
    }

    public static function info(string $message, array $context = []): void
    {
        self::write('info', $message, $context);
    }

    public static function warning(string $message, array $context = []): void
    {
        self::write('warning', $message, $context);
    }

    public static function error(string $message, array $context = []): void
    {
        self::write('error', $message, $context);
    }

    public static function critical(string $message, array $context = []): void
    {
        self::write('critical', $message, $context);
    }

    public static function path(): string
    {
        $directory = (string) Config::get('app.storage_path', dirname(__DIR__, 2) . '/storage');

        return $directory . DIRECTORY_SEPARATOR . 'logs' . DIRECTORY_SEPARATOR . 'app-' . date('Y-m-d') . '.log';
    }

    private static function write(string $level, string $message, array $context = []): void
    {
        if (!self::shouldLog($level)) {
            return;
        }

        $file = self::path();
        $directory = dirname($file);

        if (!is_dir($directory)) {
            @mkdir($directory, 0775, true);
        }

        $line = sprintf(
            '[%s] %s: %s%s%s',
            date('Y-m-d H:i:s'),
            strtoupper($level),
            $message,
            $context === [] ? '' : ' ' . self::encodeContext($context),
            PHP_EOL
        );

        @file_put_contents($file, $line, FILE_APPEND | LOCK_EX);
    }

    private static function shouldLog(string $level): bool
    {
        $configured = (string) Config::get('log.level', 'info');
        $minimum = self::LEVELS[$configured] ?? self::LEVELS['info'];
        $current = self::LEVELS[$level] ?? self::LEVELS['info'];

        return $current >= $minimum;
    }

    private static function encodeContext(array $context): string
    {
        return (string) json_encode(
            self::mask($context),
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PARTIAL_OUTPUT_ON_ERROR
        );
    }

    private static function mask(array $data): array
    {
        $masked = [];

        foreach ($data as $key => $value) {
            if (is_array($value)) {
                $masked[$key] = self::mask($value);
                continue;
            }

            if (is_string($key) && self::isSensitiveKey($key)) {
                $masked[$key] = '***oculto***';
                continue;
            }

            $masked[$key] = $value;
        }

        return $masked;
    }

    private static function isSensitiveKey(string $key): bool
    {
        $key = strtolower($key);

        foreach (self::SENSITIVE_PATTERNS as $pattern) {
            if (str_contains($key, $pattern)) {
                return true;
            }
        }

        return false;
    }
}

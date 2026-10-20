<?php

declare(strict_types=1);

namespace App\Core;

use ErrorException;
use Throwable;

/**
 * Manejo centralizado de errores y excepciones.
 *
 * En desarrollo se muestra el detalle técnico; en producción se muestra un
 * mensaje genérico y el detalle queda únicamente en el archivo de registro.
 */
final class ErrorHandler
{
    private static bool $debug = false;

    public static function register(bool $debug): void
    {
        self::$debug = $debug;

        error_reporting(E_ALL);
        ini_set('display_errors', $debug ? '1' : '0');
        ini_set('log_errors', '1');
        ini_set('error_log', self::phpErrorLog());

        set_error_handler([self::class, 'handleError']);
        set_exception_handler([self::class, 'handleException']);
        register_shutdown_function([self::class, 'handleShutdown']);
    }

    public static function handleError(int $severity, string $message, string $file = '', int $line = 0): bool
    {
        // Los errores suprimidos con @ no deben interrumpir la ejecución.
        if ((error_reporting() & $severity) === 0) {
            return false;
        }

        $context = ['file' => $file, 'line' => $line];

        if (in_array($severity, [E_WARNING, E_USER_WARNING, E_NOTICE, E_USER_NOTICE, E_DEPRECATED], true)) {
            Logger::warning('Aviso PHP: ' . $message, $context);
            return true;
        }

        throw new ErrorException($message, 0, $severity, $file, $line);
    }

    public static function handleException(Throwable $exception): void
    {
        Logger::critical('Excepción no controlada', [
            'type'    => $exception::class,
            'message' => $exception->getMessage(),
            'file'    => $exception->getFile(),
            'line'    => $exception->getLine(),
        ]);

        self::renderFatal(500, 'Error interno del servidor', $exception);
    }

    public static function handleShutdown(): void
    {
        $error = error_get_last();

        if ($error === null || !in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true)) {
            return;
        }

        Logger::critical('Error fatal', [
            'message' => $error['message'],
            'file'    => $error['file'],
            'line'    => $error['line'],
        ]);

        if (!headers_sent()) {
            self::renderFatal(500, 'Error interno del servidor', null, $error['message']);
        }
    }

    private static function renderFatal(int $status, string $title, ?Throwable $exception = null, string $detail = ''): void
    {
        if (!headers_sent()) {
            http_response_code($status);
            header('Content-Type: text/html; charset=UTF-8');
        }

        $message = 'Ocurrió un problema al procesar la solicitud. '
            . 'El incidente quedó registrado. Vuelva a intentarlo más tarde.';

        $technical = '';

        if (self::$debug) {
            if ($exception !== null) {
                $technical = $exception->getMessage() . "\n\n" . $exception->getTraceAsString();
            } elseif ($detail !== '') {
                $technical = $detail;
            }
        }

        $safeTitle = htmlspecialchars($title, ENT_QUOTES, 'UTF-8');
        $safeMessage = htmlspecialchars($message, ENT_QUOTES, 'UTF-8');
        $safeTechnical = htmlspecialchars($technical, ENT_QUOTES, 'UTF-8');

        echo <<<HTML
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>{$safeTitle} - CSDUV3</title>
<style>
body{font-family:system-ui,-apple-system,"Segoe UI",Roboto,sans-serif;background:#f5f7f2;color:#18251b;margin:0;padding:2rem;display:flex;justify-content:center}
.card{background:#fff;border:1px solid #dbe5dc;border-top:5px solid #fefe08;border-radius:18px;max-width:820px;width:100%;padding:2rem;box-shadow:0 20px 48px rgba(6,59,34,.16)}
h1{margin:0 0 .5rem;font-size:1.35rem;color:#063b22}
p{margin:.25rem 0 0;color:#56665a;line-height:1.55}
pre{margin-top:1.25rem;background:#063b22;color:#e3f0e6;padding:1rem;border-radius:8px;overflow:auto;font-size:.8rem;white-space:pre-wrap}
</style>
</head>
<body>
<main class="card">
<h1>{$safeTitle}</h1>
<p>{$safeMessage}</p>
HTML;

        if ($safeTechnical !== '') {
            echo "<pre>Detalle técnico (solo en desarrollo):\n{$safeTechnical}</pre>";
        }

        echo "\n</main>\n</body>\n</html>";
    }

    private static function phpErrorLog(): string
    {
        $storage = (string) Config::get('app.storage_path', dirname(__DIR__, 2) . '/storage');
        $directory = $storage . DIRECTORY_SEPARATOR . 'logs';

        if (!is_dir($directory)) {
            @mkdir($directory, 0775, true);
        }

        return $directory . DIRECTORY_SEPARATOR . 'php-' . date('Y-m-d') . '.log';
    }
}

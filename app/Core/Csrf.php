<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Protección CSRF para formularios que modifican información.
 */
final class Csrf
{
    private const SESSION_KEY = '_csrf_token';
    private const FIELD_NAME = '_csrf_token';

    public static function token(): string
    {
        Session::start();

        $token = $_SESSION[self::SESSION_KEY] ?? null;

        if (!is_string($token) || strlen($token) < 32) {
            $token = bin2hex(random_bytes(32));
            $_SESSION[self::SESSION_KEY] = $token;
        }

        return $token;
    }

    public static function field(): string
    {
        return sprintf(
            '<input type="hidden" name="%s" value="%s">',
            self::FIELD_NAME,
            htmlspecialchars(self::token(), ENT_QUOTES, 'UTF-8')
        );
    }

    public static function validate(?string $token): bool
    {
        if ($token === null || $token === '') {
            return false;
        }

        $stored = Session::get(self::SESSION_KEY);

        if (!is_string($stored) || $stored === '') {
            return false;
        }

        return hash_equals($stored, $token);
    }

    /**
     * Valida el token enviado en el cuerpo de la solicitud.
     */
    public static function check(): bool
    {
        $token = $_POST[self::FIELD_NAME] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? null;

        return self::validate(is_string($token) ? $token : null);
    }

    /**
     * Valida el token o finaliza la solicitud con error 419.
     */
    public static function verify(): void
    {
        if (self::check()) {
            return;
        }

        Logger::warning('Token CSRF inválido o ausente', [
            'path' => $_SERVER['REQUEST_URI'] ?? '',
            'ip'   => $_SERVER['REMOTE_ADDR'] ?? '',
        ]);

        abort(419, 'La sesión del formulario expiró. Vuelva a intentarlo.');
    }
}

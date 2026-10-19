<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Reglas mínimas de contraseñas.
 *
 * No se impone una política tan estricta que empuje a los usuarios a anotar la
 * contraseña en un papel; se exige una longitud razonable y variedad mínima.
 */
final class Password
{
    public const LONGITUD_MINIMA = 10;

    /**
     * Devuelve la lista de problemas encontrados (vacía si la contraseña es válida).
     *
     * @return array<int, string>
     */
    public static function errores(string $password): array
    {
        $errores = [];

        if (mb_strlen($password) < self::LONGITUD_MINIMA) {
            $errores[] = 'La contraseña debe tener al menos ' . self::LONGITUD_MINIMA . ' caracteres.';
        }

        if (preg_match('/[A-Za-zÁÉÍÓÚÜÑáéíóúüñ]/u', $password) !== 1) {
            $errores[] = 'La contraseña debe incluir al menos una letra.';
        }

        if (preg_match('/\d/', $password) !== 1) {
            $errores[] = 'La contraseña debe incluir al menos un número.';
        }

        if (preg_match('/^(.)\1+$/', $password) === 1) {
            $errores[] = 'La contraseña no puede ser un solo carácter repetido.';
        }

        foreach (['123456', 'password', 'contrasena', 'contraseña', 'csduv3', 'admin', 'qwerty'] as $comun) {
            if (mb_stripos($password, $comun) !== false) {
                $errores[] = 'La contraseña no debe contener palabras comunes como «' . $comun . '».';
                break;
            }
        }

        return $errores;
    }

    public static function esValida(string $password): bool
    {
        return self::errores($password) === [];
    }
}

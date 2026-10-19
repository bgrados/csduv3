<?php

declare(strict_types=1);

namespace App\Core;

use Throwable;

/**
 * Límites de uso para formularios públicos y para el inicio de sesión.
 *
 * Se apoya en tablas que ya existen (solicitudes de alquiler y accesos) para no
 * depender de almacenamiento en memoria, que no funciona de forma fiable en
 * hosting compartido.
 */
final class RateLimiter
{
    /**
     * Evita que una misma IP envíe demasiadas solicitudes de alquiler.
     */
    public static function demasiadasSolicitudes(string $ip, int $maximo = 5, int $minutos = 60): bool
    {
        $tabla = self::tabla('solicitudes_alquiler');

        try {
            $sql = sprintf(
                'SELECT COUNT(*) FROM `%s` WHERE `ip` = :ip AND `created_at` >= (NOW() - INTERVAL %d MINUTE)',
                $tabla,
                max(1, $minutos)
            );

            $statement = Database::connection()->prepare($sql);
            $statement->execute(['ip' => $ip]);

            return (int) $statement->fetchColumn() >= max(1, $maximo);
        } catch (Throwable $exception) {
            // Si la tabla aún no existe, no se bloquea el portal.
            Logger::warning('No se pudo comprobar el límite de solicitudes', ['error' => $exception->getMessage()]);

            return false;
        }
    }

    /**
     * Evita ataques de fuerza bruta desde una misma IP.
     */
    public static function demasiadosIntentosLogin(string $ip, int $maximo = 20, int $minutos = 15): bool
    {
        $tabla = self::tabla('accesos');

        try {
            $sql = sprintf(
                'SELECT COUNT(*) FROM `%s`
                 WHERE `ip` = :ip AND `exito` = 0
                   AND `created_at` >= (NOW() - INTERVAL %d MINUTE)',
                $tabla,
                max(1, $minutos)
            );

            $statement = Database::connection()->prepare($sql);
            $statement->execute(['ip' => $ip]);

            return (int) $statement->fetchColumn() >= max(1, $maximo);
        } catch (Throwable $exception) {
            Logger::warning('No se pudo comprobar el límite de intentos', ['error' => $exception->getMessage()]);

            return false;
        }
    }

    private static function tabla(string $nombre): string
    {
        $prefijo = (string) Config::get('database.prefix', 'csduv3_');

        return $prefijo . $nombre;
    }
}

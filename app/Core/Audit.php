<?php

declare(strict_types=1);

namespace App\Core;

use Throwable;

/**
 * Registro de auditoría de acciones importantes.
 *
 * Nunca interrumpe la operación principal: si no se puede escribir el registro,
 * se anota el problema en el archivo de eventos y se continúa.
 */
final class Audit
{
    /**
     * @param array<string, mixed> $datos
     */
    public static function log(
        ?int $usuarioId,
        string $accion,
        ?string $entidadTipo = null,
        ?int $entidadId = null,
        ?string $descripcion = null,
        array $datos = []
    ): void {
        try {
            $prefijo = (string) Config::get('database.prefix', 'csduv3_');
            $tabla = $prefijo . 'auditoria';

            $sql = sprintf(
                'INSERT INTO `%s`
                    (`usuario_id`, `accion`, `entidad_tipo`, `entidad_id`, `descripcion`, `datos`, `ip`, `user_agent`)
                 VALUES
                    (:usuario_id, :accion, :entidad_tipo, :entidad_id, :descripcion, :datos, :ip, :user_agent)',
                $tabla
            );

            $statement = Database::connection()->prepare($sql);
            $statement->execute([
                'usuario_id'   => $usuarioId,
                'accion'       => substr($accion, 0, 60),
                'entidad_tipo' => $entidadTipo === null ? null : substr($entidadTipo, 0, 60),
                'entidad_id'   => $entidadId,
                'descripcion'  => $descripcion === null ? null : substr($descripcion, 0, 300),
                'datos'        => $datos === [] ? null : json_encode($datos, JSON_UNESCAPED_UNICODE | JSON_PARTIAL_OUTPUT_ON_ERROR),
                'ip'           => self::ip(),
                'user_agent'   => self::userAgent(),
            ]);
        } catch (Throwable $exception) {
            Logger::warning('No se pudo registrar la auditoría', ['error' => $exception->getMessage()]);
        }
    }

    private static function ip(): string
    {
        $candidatas = [
            $_SERVER['HTTP_CF_CONNECTING_IP'] ?? null,
            $_SERVER['HTTP_X_FORWARDED_FOR'] ?? null,
            $_SERVER['REMOTE_ADDR'] ?? null,
        ];

        foreach ($candidatas as $candidata) {
            if (!is_string($candidata) || $candidata === '') {
                continue;
            }

            $ip = trim(explode(',', $candidata)[0]);

            if (filter_var($ip, FILTER_VALIDATE_IP) !== false) {
                return $ip;
            }
        }

        return '0.0.0.0';
    }

    private static function userAgent(): string
    {
        $agente = $_SERVER['HTTP_USER_AGENT'] ?? '';

        return is_string($agente) ? substr($agente, 0, 255) : '';
    }
}

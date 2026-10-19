<?php

declare(strict_types=1);

namespace App\Models;

/**
 * Consulta del registro de auditoría (solo lectura).
 */
final class Auditoria extends Model
{
    protected static string $table = 'auditoria';

    /**
     * @return array<int, array<string, mixed>>
     */
    public static function listar(?string $busqueda, ?string $accion, int $limite, int $desplazamiento): array
    {
        $limite = max(1, min(200, $limite));
        $desplazamiento = max(0, $desplazamiento);

        $params = [];
        $condiciones = [];

        if ($busqueda !== null && $busqueda !== '') {
            $condiciones[] = '(a.`descripcion` LIKE :q OR a.`accion` LIKE :q OR u.`nombres` LIKE :q OR u.`apellidos` LIKE :q)';
            $params['q'] = '%' . $busqueda . '%';
        }

        if ($accion !== null && $accion !== '') {
            $condiciones[] = 'a.`accion` = :accion';
            $params['accion'] = $accion;
        }

        $filtro = $condiciones === [] ? '' : 'WHERE ' . implode(' AND ', $condiciones);

        $sql = sprintf(
            'SELECT a.*, u.`nombres`, u.`apellidos`, u.`usuario`
             FROM `%s` a
             LEFT JOIN `%s` u ON u.`id` = a.`usuario_id`
             %s
             ORDER BY a.`created_at` DESC, a.`id` DESC
             LIMIT %d OFFSET %d',
            self::table(),
            self::prefijada('usuarios'),
            $filtro,
            $limite,
            $desplazamiento
        );

        return self::fetchAll($sql, $params);
    }

    public static function total(?string $busqueda, ?string $accion): int
    {
        $params = [];
        $condiciones = [];

        if ($busqueda !== null && $busqueda !== '') {
            $condiciones[] = '(a.`descripcion` LIKE :q OR a.`accion` LIKE :q OR u.`nombres` LIKE :q OR u.`apellidos` LIKE :q)';
            $params['q'] = '%' . $busqueda . '%';
        }

        if ($accion !== null && $accion !== '') {
            $condiciones[] = 'a.`accion` = :accion';
            $params['accion'] = $accion;
        }

        $filtro = $condiciones === [] ? '' : 'WHERE ' . implode(' AND ', $condiciones);

        $sql = sprintf(
            'SELECT COUNT(*)
             FROM `%s` a
             LEFT JOIN `%s` u ON u.`id` = a.`usuario_id`
             %s',
            self::table(),
            self::prefijada('usuarios'),
            $filtro
        );

        return (int) self::scalar($sql, $params);
    }

    /**
     * Acciones distintas registradas, para el selector de filtros.
     *
     * @return array<int, string>
     */
    public static function acciones(): array
    {
        $filas = self::fetchAll(
            sprintf('SELECT DISTINCT `accion` FROM `%s` ORDER BY `accion` ASC', self::table())
        );

        return array_map(static fn (array $fila): string => (string) $fila['accion'], $filas);
    }

    protected static function prefijada(string $tabla): string
    {
        $prefijo = (string) \App\Core\Config::get('database.prefix', 'csduv3_');

        return $prefijo . $tabla;
    }
}

<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

/**
 * Roles del sistema.
 */
final class Rol extends Model
{
    protected static string $table = 'roles';

    /** @return array<int, array<string, mixed>> */
    public static function todos(): array
    {
        return self::fetchAll(
            sprintf('SELECT * FROM `%s` ORDER BY `es_sistema` DESC, `nombre` ASC', self::table())
        );
    }

    /** @return array<string, mixed>|null */
    public static function porClave(string $clave): ?array
    {
        return self::fetchOne(
            sprintf('SELECT * FROM `%s` WHERE `clave` = :clave LIMIT 1', self::table()),
            ['clave' => $clave]
        );
    }

    /** @return array<string, mixed>|null */
    public static function porId(int $id): ?array
    {
        return self::find($id);
    }

    /**
     * @return array<int, int>
     */
    public static function permisosDe(int $rolId): array
    {
        $sql = sprintf(
            'SELECT `permiso_id` FROM `%s` WHERE `rol_id` = :rol_id',
            self::prefijada('roles_permisos')
        );

        $filas = self::fetchAll($sql, ['rol_id' => $rolId]);

        return array_map(static fn (array $fila): int => (int) $fila['permiso_id'], $filas);
    }

    /**
     * Reemplaza la matriz de permisos de un rol dentro de una transacción.
     *
     * @param array<int, int> $permisos
     */
    public static function reemplazarPermisos(int $rolId, array $permisos): void
    {
        $tabla = self::prefijada('roles_permisos');

        Database::transaction(static function ($pdo) use ($tabla, $rolId, $permisos): void {
            $delete = $pdo->prepare(sprintf('DELETE FROM `%s` WHERE `rol_id` = :rol_id', $tabla));
            $delete->execute(['rol_id' => $rolId]);

            if ($permisos === []) {
                return;
            }

            $insert = $pdo->prepare(
                sprintf('INSERT INTO `%s` (`rol_id`, `permiso_id`) VALUES (:rol_id, :permiso_id)', $tabla)
            );

            foreach (array_unique($permisos) as $permisoId) {
                $insert->execute(['rol_id' => $rolId, 'permiso_id' => $permisoId]);
            }
        });
    }

    public static function contar(): int
    {
        return (int) self::scalar(sprintf('SELECT COUNT(*) FROM `%s`', self::table()));
    }

    public static function contarUsuarios(int $rolId): int
    {
        return (int) self::scalar(
            sprintf('SELECT COUNT(*) FROM `%s` WHERE `rol_id` = :rol_id', self::prefijada('usuarios')),
            ['rol_id' => $rolId]
        );
    }

    protected static function prefijada(string $tabla): string
    {
        $prefijo = (string) \App\Core\Config::get('database.prefix', 'csduv3_');

        return $prefijo . $tabla;
    }
}

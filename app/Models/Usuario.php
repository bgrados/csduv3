<?php

declare(strict_types=1);

namespace App\Models;

/**
 * Usuarios del sistema y sus permisos.
 */
final class Usuario extends Model
{
    protected static string $table = 'usuarios';

    /** @return array<string, mixed>|null */
    public static function findByLogin(string $login): ?array
    {
        $sql = sprintf(
            'SELECT * FROM `%s` WHERE `usuario` = :login OR `email` = :correo LIMIT 1',
            self::table()
        );

        return self::fetchOne($sql, ['login' => $login, 'correo' => $login]);
    }

    /** @return array<string, mixed>|null */
    public static function findConRol(int $id): ?array
    {
        $sql = sprintf(
            'SELECT u.*, r.nombre AS rol_nombre, r.clave AS rol_clave
             FROM `%s` u
             LEFT JOIN `%s` r ON r.`id` = u.`rol_id`
             WHERE u.`id` = :id
             LIMIT 1',
            self::table(),
            Rol::table()
        );

        return self::fetchOne($sql, ['id' => $id]);
    }

    /**
     * Devuelve los permisos del rol en forma de mapa clave => true.
     *
     * @return array<string, bool>
     */
    public static function permisosDeRol(int $rolId): array
    {
        $sql = sprintf(
            'SELECT p.`clave`
             FROM `%s` rp
             INNER JOIN `%s` p ON p.`id` = rp.`permiso_id`
             WHERE rp.`rol_id` = :rol_id',
            self::prefijada('roles_permisos'),
            Permiso::table()
        );

        $filas = self::fetchAll($sql, ['rol_id' => $rolId]);
        $permisos = [];

        foreach ($filas as $fila) {
            $permisos[(string) $fila['clave']] = true;
        }

        return $permisos;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public static function listar(?string $busqueda, int $limite, int $desplazamiento): array
    {
        $limite = max(1, min(200, $limite));
        $desplazamiento = max(0, $desplazamiento);

        $params = [];
        $filtro = '';

        if ($busqueda !== null && $busqueda !== '') {
            $filtro = 'WHERE (u.`nombres` LIKE :q1 OR u.`apellidos` LIKE :q2 OR u.`usuario` LIKE :q3 OR u.`email` LIKE :q4)';
            $like = '%' . $busqueda . '%';
            $params['q1'] = $like;
            $params['q2'] = $like;
            $params['q3'] = $like;
            $params['q4'] = $like;
        }

        $sql = sprintf(
            'SELECT u.*, r.`nombre` AS rol_nombre
             FROM `%s` u
             LEFT JOIN `%s` r ON r.`id` = u.`rol_id`
             %s
             ORDER BY u.`apellidos` ASC, u.`nombres` ASC
             LIMIT %d OFFSET %d',
            self::table(),
            Rol::table(),
            $filtro,
            $limite,
            $desplazamiento
        );

        return self::fetchAll($sql, $params);
    }

    public static function total(?string $busqueda): int
    {
        $params = [];
        $filtro = '';

        if ($busqueda !== null && $busqueda !== '') {
            $filtro = 'WHERE (`nombres` LIKE :q1 OR `apellidos` LIKE :q2 OR `usuario` LIKE :q3 OR `email` LIKE :q4)';
            $like = '%' . $busqueda . '%';
            $params['q1'] = $like;
            $params['q2'] = $like;
            $params['q3'] = $like;
            $params['q4'] = $like;
        }

        $sql = sprintf('SELECT COUNT(*) FROM `%s` %s', self::table(), $filtro);

        return (int) self::scalar($sql, $params);
    }

    /**
     * Cuenta administradores activos. Permite evitar que se desactive la última
     * cuenta con acceso total al sistema.
     */
    public static function contarAdministradoresActivos(?int $excepto = null): int
    {
        $sql = sprintf(
            'SELECT COUNT(*)
             FROM `%s` u
             INNER JOIN `%s` r ON r.`id` = u.`rol_id`
             WHERE r.`clave` = \'administrador\' AND u.`activo` = 1',
            self::table(),
            Rol::table()
        );

        $params = [];

        if ($excepto !== null) {
            $sql .= ' AND u.`id` <> :excepto';
            $params['excepto'] = $excepto;
        }

        return (int) self::scalar($sql, $params);
    }

    public static function contarActivos(): int
    {
        return (int) self::scalar(
            sprintf('SELECT COUNT(*) FROM `%s` WHERE `activo` = 1', self::table())
        );
    }

    public static function contar(): int
    {
        return (int) self::scalar(sprintf('SELECT COUNT(*) FROM `%s`', self::table()));
    }

    /**
     * @param array<string, mixed> $datos
     */
    public static function crear(array $datos): int
    {
        return self::insertar([
            'nombres'             => $datos['nombres'],
            'apellidos'           => $datos['apellidos'],
            'usuario'             => $datos['usuario'],
            'email'               => $datos['email'] ?: null,
            'password_hash'       => $datos['password_hash'],
            'rol_id'              => $datos['rol_id'] ?: null,
            'telefono'            => $datos['telefono'] ?: null,
            'cargo_institucional' => $datos['cargo_institucional'] ?: null,
            'activo'              => $datos['activo'] ?? 1,
            'debe_cambiar_password' => $datos['debe_cambiar_password'] ?? 0,
        ]);
    }

    /**
     * @param array<string, mixed> $datos
     */
    public static function actualizar(int $id, array $datos): void
    {
        $sql = sprintf(
            'UPDATE `%s`
             SET `nombres` = :nombres,
                 `apellidos` = :apellidos,
                 `usuario` = :usuario,
                 `email` = :email,
                 `rol_id` = :rol_id,
                 `telefono` = :telefono,
                 `cargo_institucional` = :cargo,
                 `activo` = :activo
             WHERE `id` = :id',
            self::table()
        );

        self::run($sql, [
            'nombres'   => $datos['nombres'],
            'apellidos' => $datos['apellidos'],
            'usuario'   => $datos['usuario'],
            'email'     => $datos['email'] ?: null,
            'rol_id'    => $datos['rol_id'] ?: null,
            'telefono'  => $datos['telefono'] ?: null,
            'cargo'     => $datos['cargo_institucional'] ?: null,
            'activo'    => $datos['activo'] ?? 1,
            'id'        => $id,
        ]);
    }

    public static function cambiarPassword(int $id, string $password, bool $debeCambiar = false): void
    {
        $sql = sprintf(
            'UPDATE `%s`
             SET `password_hash` = :hash,
                 `debe_cambiar_password` = :debe,
                 `intentos_fallidos` = 0,
                 `bloqueado_hasta` = NULL
             WHERE `id` = :id',
            self::table()
        );

        self::run($sql, [
            'hash'  => password_hash($password, PASSWORD_DEFAULT),
            'debe'  => $debeCambiar ? 1 : 0,
            'id'    => $id,
        ]);
    }

    public static function cambiarEstado(int $id, bool $activo): void
    {
        $sql = sprintf('UPDATE `%s` SET `activo` = :activo WHERE `id` = :id', self::table());

        self::run($sql, ['activo' => $activo ? 1 : 0, 'id' => $id]);
    }

    public static function registrarIntentoFallido(int $id, int $maximo, int $minutos): void
    {
        $minutos = max(1, $minutos);

        $sql = sprintf(
            'UPDATE `%s`
             SET `intentos_fallidos` = `intentos_fallidos` + 1,
                 `bloqueado_hasta` = IF(
                     `intentos_fallidos` + 1 >= :maximo,
                     DATE_ADD(NOW(), INTERVAL %d MINUTE),
                     `bloqueado_hasta`
                 )
             WHERE `id` = :id',
            self::table(),
            $minutos
        );

        self::run($sql, ['maximo' => max(1, $maximo), 'id' => $id]);
    }

    public static function registrarAccesoExitoso(int $id): void
    {
        $sql = sprintf(
            'UPDATE `%s`
             SET `ultimo_acceso` = NOW(),
                 `intentos_fallidos` = 0,
                 `bloqueado_hasta` = NULL
             WHERE `id` = :id',
            self::table()
        );

        self::run($sql, ['id' => $id]);
    }

    public static function registrarAcceso(?int $usuarioId, string $login, bool $exito, string $ip, string $userAgent): void
    {
        $sql = sprintf(
            'INSERT INTO `%s`
                (`usuario_id`, `usuario_intentado`, `exito`, `ip`, `user_agent`)
             VALUES (:usuario_id, :login, :exito, :ip, :user_agent)',
            self::prefijada('accesos')
        );

        self::run($sql, [
            'usuario_id' => $usuarioId,
            'login'      => substr($login, 0, 60),
            'exito'      => $exito ? 1 : 0,
            'ip'         => $ip,
            'user_agent' => substr($userAgent, 0, 255),
        ]);
    }

    /**
     * Nombre completo listo para mostrar.
     *
     * @param array<string, mixed> $usuario
     */
    public static function nombreCompleto(array $usuario): string
    {
        return trim(((string) ($usuario['nombres'] ?? '')) . ' ' . ((string) ($usuario['apellidos'] ?? '')));
    }

    protected static function prefijada(string $tabla): string
    {
        $prefijo = (string) \App\Core\Config::get('database.prefix', 'csduv3_');

        return $prefijo . $tabla;
    }
}

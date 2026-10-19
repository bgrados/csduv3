<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Config;
use App\Core\Database;
use PDO;

/**
 * Modelo base.
 *
 * Centraliza el acceso a PDO y evita repetir el manejo del prefijo de tablas.
 * Todas las consultas usan parámetros vinculados.
 */
abstract class Model
{
    /** Nombre de la tabla sin prefijo. */
    protected static string $table = '';

    protected static string $primaryKey = 'id';

    public static function table(): string
    {
        $prefijo = (string) Config::get('database.prefix', 'csduv3_');

        return $prefijo . static::$table;
    }

    protected static function db(): PDO
    {
        return Database::connection();
    }

    /** @return array<string, mixed>|null */
    public static function find(int $id): ?array
    {
        $sql = sprintf(
            'SELECT * FROM `%s` WHERE `%s` = :id LIMIT 1',
            static::table(),
            static::$primaryKey
        );

        return static::fetchOne($sql, ['id' => $id]);
    }

    /**
     * @param array<string, mixed> $params
     * @return array<string, mixed>|null
     */
    protected static function fetchOne(string $sql, array $params = []): ?array
    {
        $statement = static::db()->prepare($sql);
        $statement->execute($params);
        $row = $statement->fetch();

        return is_array($row) ? $row : null;
    }

    /**
     * @param array<string, mixed> $params
     * @return array<int, array<string, mixed>>
     */
    protected static function fetchAll(string $sql, array $params = []): array
    {
        $statement = static::db()->prepare($sql);
        $statement->execute($params);

        $rows = $statement->fetchAll();

        return is_array($rows) ? $rows : [];
    }

    /**
     * @param array<string, mixed> $params
     */
    protected static function run(string $sql, array $params = []): int
    {
        $statement = static::db()->prepare($sql);
        $statement->execute($params);

        return $statement->rowCount();
    }

    protected static function scalar(string $sql, array $params = []): mixed
    {
        $statement = static::db()->prepare($sql);
        $statement->execute($params);

        return $statement->fetchColumn();
    }

    /**
     * Inserta un registro y devuelve su identificador.
     *
     * @param array<string, mixed> $datos
     */
    protected static function insertar(array $datos): int
    {
        $columnas = array_keys($datos);
        $marcadores = array_map(static fn (string $columna): string => ':' . $columna, $columnas);

        $sql = sprintf(
            'INSERT INTO `%s` (`%s`) VALUES (%s)',
            static::table(),
            implode('`, `', $columnas),
            implode(', ', $marcadores)
        );

        $statement = static::db()->prepare($sql);

        foreach ($datos as $columna => $valor) {
            $statement->bindValue(':' . $columna, $valor, $valor === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
        }

        $statement->execute();

        return (int) static::db()->lastInsertId();
    }
}

<?php

declare(strict_types=1);

namespace App\Core;

use PDO;
use PDOException;
use RuntimeException;
use Throwable;

/**
 * Conexión única (singleton) a la base de datos mediante PDO.
 *
 * Todas las consultas del sistema deben usar consultas preparadas.
 */
final class Database
{
    private static ?PDO $connection = null;

    /** Último error de conexión, para diagnóstico interno. */
    private static ?string $lastError = null;

    public static function connection(): PDO
    {
        if (self::$connection instanceof PDO) {
            return self::$connection;
        }

        $host = (string) Config::get('database.host', 'localhost');
        $port = (int) Config::get('database.port', 3306);
        $name = (string) Config::get('database.database', '');
        $user = (string) Config::get('database.username', '');
        $pass = (string) Config::get('database.password', '');
        $charset = (string) Config::get('database.charset', 'utf8mb4');

        if ($name === '') {
            throw new RuntimeException('No se ha configurado el nombre de la base de datos (DB_DATABASE).');
        }

        $dsn = sprintf('mysql:host=%s;port=%d;dbname=%s;charset=%s', $host, $port, $name, $charset);

        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
            PDO::ATTR_STRINGIFY_FETCHES  => false,
            PDO::ATTR_PERSISTENT         => false,
        ];

        try {
            self::$connection = new PDO($dsn, $user, $pass, $options);
        } catch (PDOException $exception) {
            self::$lastError = $exception->getMessage();
            Logger::critical('Fallo de conexión a la base de datos', [
                'host'   => $host,
                'dbname' => $name,
                'error'  => $exception->getMessage(),
            ]);

            throw new RuntimeException('No se pudo conectar a la base de datos.', 0, $exception);
        }

        self::$lastError = null;
        self::applyTimezone(self::$connection);

        return self::$connection;
    }

    /**
     * Comprueba la conexión sin lanzar excepciones (para diagnóstico).
     */
    public static function isAvailable(): bool
    {
        try {
            self::connection();
            return true;
        } catch (Throwable) {
            return false;
        }
    }

    public static function lastError(): ?string
    {
        return self::$lastError;
    }

    public static function serverVersion(): ?string
    {
        try {
            $version = self::connection()->getAttribute(PDO::ATTR_SERVER_VERSION);
            return is_string($version) ? $version : null;
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * Comprueba si una tabla existe en la base de datos activa.
     */
    public static function tableExists(string $table): bool
    {
        try {
            $statement = self::connection()->prepare(
                'SELECT COUNT(*) FROM information_schema.tables
                 WHERE table_schema = DATABASE() AND table_name = :table'
            );
            $statement->execute(['table' => $table]);

            return (int) $statement->fetchColumn() > 0;
        } catch (Throwable) {
            return false;
        }
    }

    /**
     * Ejecuta una operación dentro de una transacción.
     *
     * @template T
     * @param callable(PDO): T $callback
     * @return T
     */
    public static function transaction(callable $callback): mixed
    {
        $pdo = self::connection();
        $pdo->beginTransaction();

        try {
            $result = $callback($pdo);
            $pdo->commit();

            return $result;
        } catch (Throwable $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            Logger::error('Transacción revertida', ['error' => $exception->getMessage()]);

            throw $exception;
        }
    }

    private static function applyTimezone(PDO $pdo): void
    {
        $offset = (string) Config::get('database.timezone_offset', '-05:00');

        // Solo se aceptan desplazamientos del tipo +HH:MM o -HH:MM.
        if (preg_match('/^[+-]\d{2}:\d{2}$/', $offset) !== 1) {
            return;
        }

        try {
            $pdo->exec("SET time_zone = '" . $offset . "'");
        } catch (Throwable) {
            // Si el hosting no permite cambiar la zona horaria, se continúa.
        }
    }
}

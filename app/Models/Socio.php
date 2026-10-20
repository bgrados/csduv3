<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Config;
use PDO;
use Throwable;

/**
 * Padrón de socios del club.
 *
 * Un socio nunca se elimina: la baja se registra en `socios_bajas` y el estado
 * del socio pasa a «baja», de modo que su historial se conserva.
 */
final class Socio extends Model
{
    protected static string $table = 'socios';

    public const PREFIJO_CODIGO = 'SOC-';

    public const ESTADOS = [
        'activo'    => 'Activo',
        'pendiente' => 'Pendiente de regularización',
        'inactivo'  => 'Inactivo',
        'baja'      => 'Baja',
    ];

    /** Estados que pueden asignarse desde el formulario de alta o edición. */
    public const ESTADOS_EDITABLES = [
        'activo'    => 'Activo',
        'pendiente' => 'Pendiente de regularización',
        'inactivo'  => 'Inactivo',
    ];

    public const TIPOS_DOCUMENTO = [
        'DNI'       => 'DNI',
        'CE'        => 'Carné de extranjería',
        'PASAPORTE' => 'Pasaporte',
        'OTRO'      => 'Otro documento',
    ];

    public const SEXOS = [
        'M'    => 'Masculino',
        'F'    => 'Femenino',
        'OTRO' => 'No especifica',
    ];

    public static function contarActivos(): int
    {
        return (int) self::scalar(
            sprintf('SELECT COUNT(*) FROM `%s` WHERE `estado` = \'activo\'', self::table())
        );
    }

    public static function contarPendientes(): int
    {
        return (int) self::scalar(
            sprintf('SELECT COUNT(*) FROM `%s` WHERE `estado` = \'pendiente\'', self::table())
        );
    }

    public static function contar(): int
    {
        return (int) self::scalar(sprintf('SELECT COUNT(*) FROM `%s`', self::table()));
    }

    /**
     * Listado paginado del padrón con su categoría.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function listar(
        ?string $busqueda,
        ?string $estado,
        ?int $categoriaId,
        int $limite,
        int $desplazamiento
    ): array {
        $limite = max(1, min(200, $limite));
        $desplazamiento = max(0, $desplazamiento);

        [$filtro, $params] = self::filtros($busqueda, $estado, $categoriaId, 's');

        $sql = sprintf(
            'SELECT s.*, c.`nombre` AS categoria_nombre
             FROM `%s` s
             LEFT JOIN `%s` c ON c.`id` = s.`categoria_id`
             %s
             ORDER BY s.`apellidos` ASC, s.`nombres` ASC, s.`id` ASC
             LIMIT %d OFFSET %d',
            self::table(),
            CategoriaSocio::table(),
            $filtro,
            $limite,
            $desplazamiento
        );

        return self::fetchAll($sql, $params);
    }

    public static function total(?string $busqueda, ?string $estado, ?int $categoriaId): int
    {
        [$filtro, $params] = self::filtros($busqueda, $estado, $categoriaId, '');

        return (int) self::scalar(
            sprintf('SELECT COUNT(*) FROM `%s` %s', self::table(), $filtro),
            $params
        );
    }

    /**
     * Busca un socio por su código, sin distinguir mayúsculas.
     *
     * @return array<string, mixed>|null
     */
    public static function findPorCodigo(string $codigo): ?array
    {
        $codigo = trim($codigo);

        if ($codigo === '') {
            return null;
        }

        $sql = sprintf(
            'SELECT s.*, c.`nombre` AS categoria_nombre
             FROM `%s` s
             LEFT JOIN `%s` c ON c.`id` = s.`categoria_id`
             WHERE s.`codigo` = :codigo
             LIMIT 1',
            self::table(),
            CategoriaSocio::table()
        );

        return self::fetchOne($sql, ['codigo' => $codigo]);
    }

    /** @return array<string, mixed>|null */
    public static function findConCategoria(int $id): ?array
    {
        $sql = sprintf(
            'SELECT s.*, c.`nombre` AS categoria_nombre
             FROM `%s` s
             LEFT JOIN `%s` c ON c.`id` = s.`categoria_id`
             WHERE s.`id` = :id
             LIMIT 1',
            self::table(),
            CategoriaSocio::table()
        );

        return self::fetchOne($sql, ['id' => $id]);
    }

    /**
     * @param array<string, mixed> $datos
     */
    public static function crear(array $datos): int
    {
        return self::insertar([
            'codigo'           => $datos['codigo'],
            'nombres'          => $datos['nombres'],
            'apellidos'        => $datos['apellidos'],
            'tipo_documento'   => $datos['tipo_documento'],
            'numero_documento' => self::vacioANulo($datos['numero_documento'] ?? null),
            'fecha_nacimiento' => self::vacioANulo($datos['fecha_nacimiento'] ?? null),
            'sexo'             => self::vacioANulo($datos['sexo'] ?? null),
            'estado_civil'     => self::vacioANulo($datos['estado_civil'] ?? null),
            'direccion'        => self::vacioANulo($datos['direccion'] ?? null),
            'distrito'         => self::vacioANulo($datos['distrito'] ?? null),
            'telefono'         => self::vacioANulo($datos['telefono'] ?? null),
            'telefono_alterno' => self::vacioANulo($datos['telefono_alterno'] ?? null),
            'email'            => self::vacioANulo($datos['email'] ?? null),
            'fecha_ingreso'    => self::vacioANulo($datos['fecha_ingreso'] ?? null),
            'categoria_id'     => self::enteroANulo($datos['categoria_id'] ?? null),
            'estado'           => $datos['estado'],
            'observaciones'    => self::vacioANulo($datos['observaciones'] ?? null),
            'creado_por'       => self::enteroANulo($datos['creado_por'] ?? null),
            'actualizado_por'  => self::enteroANulo($datos['actualizado_por'] ?? null),
        ]);
    }

    /**
     * @param array<string, mixed> $datos
     */
    public static function actualizar(int $id, array $datos): void
    {
        $sql = sprintf(
            'UPDATE `%s`
             SET `codigo` = :codigo,
                 `nombres` = :nombres,
                 `apellidos` = :apellidos,
                 `tipo_documento` = :tipo_documento,
                 `numero_documento` = :numero_documento,
                 `fecha_nacimiento` = :fecha_nacimiento,
                 `sexo` = :sexo,
                 `estado_civil` = :estado_civil,
                 `direccion` = :direccion,
                 `distrito` = :distrito,
                 `telefono` = :telefono,
                 `telefono_alterno` = :telefono_alterno,
                 `email` = :email,
                 `fecha_ingreso` = :fecha_ingreso,
                 `categoria_id` = :categoria_id,
                 `estado` = :estado,
                 `observaciones` = :observaciones,
                 `actualizado_por` = :actualizado_por
             WHERE `id` = :id',
            self::table()
        );

        self::run($sql, [
            'codigo'           => $datos['codigo'],
            'nombres'          => $datos['nombres'],
            'apellidos'        => $datos['apellidos'],
            'tipo_documento'   => $datos['tipo_documento'],
            'numero_documento' => self::vacioANulo($datos['numero_documento'] ?? null),
            'fecha_nacimiento' => self::vacioANulo($datos['fecha_nacimiento'] ?? null),
            'sexo'             => self::vacioANulo($datos['sexo'] ?? null),
            'estado_civil'     => self::vacioANulo($datos['estado_civil'] ?? null),
            'direccion'        => self::vacioANulo($datos['direccion'] ?? null),
            'distrito'         => self::vacioANulo($datos['distrito'] ?? null),
            'telefono'         => self::vacioANulo($datos['telefono'] ?? null),
            'telefono_alterno' => self::vacioANulo($datos['telefono_alterno'] ?? null),
            'email'            => self::vacioANulo($datos['email'] ?? null),
            'fecha_ingreso'    => self::vacioANulo($datos['fecha_ingreso'] ?? null),
            'categoria_id'     => self::enteroANulo($datos['categoria_id'] ?? null),
            'estado'           => $datos['estado'],
            'observaciones'    => self::vacioANulo($datos['observaciones'] ?? null),
            'actualizado_por'  => self::enteroANulo($datos['actualizado_por'] ?? null),
            'id'               => $id,
        ]);
    }

    /**
     * Registra la baja y cambia el estado del socio en una sola transacción.
     *
     * La fila del socio no se elimina: solo se actualiza su estado.
     */
    public static function registrarBaja(
        int $socioId,
        string $fechaBaja,
        ?string $motivo,
        bool $esRenuncia,
        ?int $usuarioId
    ): void {
        $db = self::db();
        $db->beginTransaction();

        try {
            $sql = sprintf(
                'INSERT INTO `%s`
                    (`socio_id`, `fecha_baja`, `motivo`, `es_renuncia`, `registrado_por`)
                 VALUES
                    (:socio_id, :fecha_baja, :motivo, :es_renuncia, :registrado_por)',
                self::prefijada('socios_bajas')
            );

            $statement = $db->prepare($sql);
            $statement->bindValue(':socio_id', $socioId, PDO::PARAM_INT);
            $statement->bindValue(':fecha_baja', $fechaBaja);
            $statement->bindValue(
                ':motivo',
                $motivo,
                $motivo === null ? PDO::PARAM_NULL : PDO::PARAM_STR
            );
            $statement->bindValue(':es_renuncia', $esRenuncia ? 1 : 0, PDO::PARAM_INT);
            $statement->bindValue(
                ':registrado_por',
                $usuarioId,
                $usuarioId === null ? PDO::PARAM_NULL : PDO::PARAM_INT
            );
            $statement->execute();

            self::run(
                sprintf(
                    'UPDATE `%s` SET `estado` = \'baja\', `actualizado_por` = :usuario WHERE `id` = :id',
                    self::table()
                ),
                ['usuario' => $usuarioId, 'id' => $socioId]
            );

            $db->commit();
        } catch (Throwable $exception) {
            $db->rollBack();

            throw $exception;
        }
    }

    /** @return array<string, mixed>|null */
    public static function bajaDe(int $socioId): ?array
    {
        $sql = sprintf(
            'SELECT b.*,
                    u.`nombres` AS registrado_por_nombres,
                    u.`apellidos` AS registrado_por_apellidos
             FROM `%s` b
             LEFT JOIN `%s` u ON u.`id` = b.`registrado_por`
             WHERE b.`socio_id` = :socio_id
             ORDER BY b.`fecha_baja` DESC, b.`id` DESC
             LIMIT 1',
            self::prefijada('socios_bajas'),
            self::prefijada('usuarios')
        );

        return self::fetchOne($sql, ['socio_id' => $socioId]);
    }

    public static function existeCodigo(string $codigo, ?int $excluirId = null): bool
    {
        $sql = sprintf('SELECT COUNT(*) FROM `%s` WHERE `codigo` = :codigo', self::table());
        $params = ['codigo' => $codigo];

        if ($excluirId !== null && $excluirId > 0) {
            $sql .= ' AND `id` <> :id';
            $params['id'] = $excluirId;
        }

        return (int) self::scalar($sql, $params) > 0;
    }

    /**
     * Comprueba la unicidad del par tipo de documento / número.
     *
     * La tabla tiene una clave única compuesta y MySQL permite varios NULL en
     * `numero_documento`; por eso la comprobación solo aplica cuando hay número.
     */
    public static function existeDocumento(string $tipo, string $numero, ?int $excluirId = null): bool
    {
        $numero = trim($numero);

        if ($numero === '') {
            return false;
        }

        $sql = sprintf(
            'SELECT COUNT(*) FROM `%s` WHERE `tipo_documento` = :tipo AND `numero_documento` = :numero',
            self::table()
        );
        $params = ['tipo' => $tipo, 'numero' => $numero];

        if ($excluirId !== null && $excluirId > 0) {
            $sql .= ' AND `id` <> :id';
            $params['id'] = $excluirId;
        }

        return (int) self::scalar($sql, $params) > 0;
    }

    /**
     * Siguiente código correlativo disponible (SOC-0001, SOC-0002…).
     */
    public static function siguienteCodigo(): string
    {
        $sql = sprintf(
            'SELECT MAX(CAST(SUBSTRING(`codigo`, %d) AS UNSIGNED))
             FROM `%s`
             WHERE `codigo` LIKE :patron',
            strlen(self::PREFIJO_CODIGO) + 1,
            self::table()
        );

        $maximo = (int) self::scalar($sql, ['patron' => self::PREFIJO_CODIGO . '%']);

        return self::PREFIJO_CODIGO . str_pad((string) ($maximo + 1), 4, '0', STR_PAD_LEFT);
    }

    /**
     * @return array{0: string, 1: array<string, mixed>}
     */
    private static function filtros(
        ?string $busqueda,
        ?string $estado,
        ?int $categoriaId,
        string $alias
    ): array {
        $prefijo = $alias === '' ? '' : $alias . '.';
        $condiciones = [];
        $params = [];

        $busqueda = $busqueda === null ? '' : trim($busqueda);

        if ($busqueda !== '') {
            $condiciones[] = sprintf(
                '(%1$s`codigo` LIKE :q1 OR %1$s`numero_documento` LIKE :q2'
                . ' OR %1$s`nombres` LIKE :q3 OR %1$s`apellidos` LIKE :q4)',
                $prefijo
            );

            $like = '%' . $busqueda . '%';
            $params['q1'] = $like;
            $params['q2'] = $like;
            $params['q3'] = $like;
            $params['q4'] = $like;
        }

        if ($estado !== null && isset(self::ESTADOS[$estado])) {
            $condiciones[] = $prefijo . '`estado` = :estado';
            $params['estado'] = $estado;
        }

        if ($categoriaId !== null && $categoriaId > 0) {
            $condiciones[] = $prefijo . '`categoria_id` = :categoria_id';
            $params['categoria_id'] = $categoriaId;
        }

        return [
            $condiciones === [] ? '' : 'WHERE ' . implode(' AND ', $condiciones),
            $params,
        ];
    }

    protected static function prefijada(string $tabla): string
    {
        $prefijo = (string) Config::get('database.prefix', 'csduv3_');

        return $prefijo . $tabla;
    }

    private static function vacioANulo(mixed $valor): ?string
    {
        return $valor === null || $valor === '' ? null : (string) $valor;
    }

    private static function enteroANulo(mixed $valor): ?int
    {
        $entero = (int) ($valor ?? 0);

        return $entero > 0 ? $entero : null;
    }
}

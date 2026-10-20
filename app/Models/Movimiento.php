<?php

declare(strict_types=1);

namespace App\Models;

/**
 * Movimientos de caja (ingresos y egresos).
 *
 * Es un registro financiero **administrativo**: no constituye contabilidad
 * tributaria ni comprobantes electrónicos autorizados por SUNAT.
 *
 * Los movimientos no se eliminan: se anulan, y queda la traza en
 * `csduv3_movimientos_auditoria`.
 */
final class Movimiento extends Model
{
    protected static string $table = 'movimientos';

    public const TIPOS = [
        'ingreso' => 'Ingreso',
        'egreso'  => 'Egreso',
    ];

    public const ESTADOS = [
        'registrado' => 'Registrado',
        'anulado'    => 'Anulado',
    ];

    /**
     * @return array{ingresos: float, egresos: float, saldo: float, cantidad: int}
     */
    public static function resumenPeriodo(string $inicio, string $fin): array
    {
        $sql = sprintf(
            'SELECT
                COALESCE(SUM(CASE WHEN `tipo` = \'ingreso\' THEN `monto` ELSE 0 END), 0) AS ingresos,
                COALESCE(SUM(CASE WHEN `tipo` = \'egreso\' THEN `monto` ELSE 0 END), 0) AS egresos,
                COUNT(*) AS cantidad
             FROM `%s`
             WHERE `estado` = \'registrado\'
               AND `fecha` BETWEEN :inicio AND :fin',
            self::table()
        );

        $fila = self::fetchOne($sql, ['inicio' => $inicio, 'fin' => $fin]);

        if ($fila === null) {
            return ['ingresos' => 0.0, 'egresos' => 0.0, 'saldo' => 0.0, 'cantidad' => 0];
        }

        $ingresos = (float) $fila['ingresos'];
        $egresos = (float) $fila['egresos'];

        return [
            'ingresos' => $ingresos,
            'egresos'  => $egresos,
            'saldo'    => $ingresos - $egresos,
            'cantidad' => (int) $fila['cantidad'],
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public static function ultimos(int $limite = 5): array
    {
        $limite = max(1, min(20, $limite));

        $sql = sprintf(
            'SELECT m.`id`, m.`tipo`, m.`fecha`, m.`concepto`, m.`monto`,
                    c.`nombre` AS categoria_nombre
             FROM `%s` m
             LEFT JOIN `%s` c ON c.`id` = m.`categoria_id`
             WHERE m.`estado` = \'registrado\'
             ORDER BY m.`fecha` DESC, m.`id` DESC
             LIMIT %d',
            self::table(),
            self::prefijada('categorias_movimiento'),
            $limite
        );

        return self::fetchAll($sql);
    }

    /**
     * Listado paginado de movimientos con sus catálogos.
     *
     * @param array<string, mixed> $filtros
     * @return array<int, array<string, mixed>>
     */
    public static function listar(array $filtros, int $limite, int $desplazamiento): array
    {
        $limite = max(1, min(200, $limite));
        $desplazamiento = max(0, $desplazamiento);

        [$filtro, $params] = self::condiciones($filtros, 'm');

        $sql = sprintf(
            'SELECT m.*,
                    c.`nombre` AS categoria_nombre,
                    mp.`nombre` AS medio_pago_nombre,
                    p.`nombre` AS periodo_nombre,
                    s.`codigo` AS socio_codigo,
                    CONCAT(s.`nombres`, \' \', s.`apellidos`) AS socio_nombre
             FROM `%s` m
             LEFT JOIN `%s` c ON c.`id` = m.`categoria_id`
             LEFT JOIN `%s` mp ON mp.`id` = m.`medio_pago_id`
             LEFT JOIN `%s` p ON p.`id` = m.`periodo_id`
             LEFT JOIN `%s` s ON s.`id` = m.`socio_id`
             %s
             ORDER BY m.`fecha` DESC, m.`id` DESC
             LIMIT %d OFFSET %d',
            self::table(),
            self::prefijada('categorias_movimiento'),
            self::prefijada('medios_pago'),
            self::prefijada('periodos'),
            self::prefijada('socios'),
            $filtro,
            $limite,
            $desplazamiento
        );

        return self::fetchAll($sql, $params);
    }

    /**
     * @param array<string, mixed> $filtros
     */
    public static function total(array $filtros): int
    {
        [$filtro, $params] = self::condiciones($filtros, 'm');

        return (int) self::scalar(
            sprintf('SELECT COUNT(*) FROM `%s` m %s', self::table(), $filtro),
            $params
        );
    }

    /**
     * @return array<string, mixed>|null
     */
    public static function findConCatalogos(int $id): ?array
    {
        $sql = sprintf(
            'SELECT m.*,
                    c.`nombre` AS categoria_nombre,
                    mp.`nombre` AS medio_pago_nombre,
                    p.`nombre` AS periodo_nombre,
                    s.`codigo` AS socio_codigo,
                    CONCAT(s.`nombres`, \' \', s.`apellidos`) AS socio_nombre
             FROM `%s` m
             LEFT JOIN `%s` c ON c.`id` = m.`categoria_id`
             LEFT JOIN `%s` mp ON mp.`id` = m.`medio_pago_id`
             LEFT JOIN `%s` p ON p.`id` = m.`periodo_id`
             LEFT JOIN `%s` s ON s.`id` = m.`socio_id`
             WHERE m.`id` = :id LIMIT 1',
            self::table(),
            self::prefijada('categorias_movimiento'),
            self::prefijada('medios_pago'),
            self::prefijada('periodos'),
            self::prefijada('socios')
        );

        return self::fetchOne($sql, ['id' => $id]);
    }

    /**
     * @param array<string, mixed> $datos
     */
    public static function crear(array $datos): int
    {
        return self::insertar([
            'tipo'          => (string) $datos['tipo'],
            'fecha'         => (string) $datos['fecha'],
            'concepto'      => (string) $datos['concepto'],
            'monto'         => (string) $datos['monto'],
            'categoria_id'  => self::enteroANulo($datos['categoria_id'] ?? null),
            'medio_pago_id' => self::enteroANulo($datos['medio_pago_id'] ?? null),
            'periodo_id'    => self::enteroANulo($datos['periodo_id'] ?? null),
            'socio_id'      => self::enteroANulo($datos['socio_id'] ?? null),
            'referencia'    => self::vacioANulo($datos['referencia'] ?? null),
            'observaciones' => self::vacioANulo($datos['observaciones'] ?? null),
            'responsable_id' => self::enteroANulo($datos['responsable_id'] ?? null),
            'estado'        => 'registrado',
        ]);
    }

    /**
     * @param array<string, mixed> $datos
     */
    public static function actualizar(int $id, array $datos): void
    {
        self::run(
            sprintf(
                'UPDATE `%s`
                 SET `tipo` = :tipo,
                     `fecha` = :fecha,
                     `concepto` = :concepto,
                     `monto` = :monto,
                     `categoria_id` = :categoria_id,
                     `medio_pago_id` = :medio_pago_id,
                     `periodo_id` = :periodo_id,
                     `socio_id` = :socio_id,
                     `referencia` = :referencia,
                     `observaciones` = :observaciones,
                     `responsable_id` = :responsable_id
                 WHERE `id` = :id',
                self::table()
            ),
            [
                'tipo'           => (string) $datos['tipo'],
                'fecha'          => (string) $datos['fecha'],
                'concepto'       => (string) $datos['concepto'],
                'monto'          => (string) $datos['monto'],
                'categoria_id'   => self::enteroANulo($datos['categoria_id'] ?? null),
                'medio_pago_id'  => self::enteroANulo($datos['medio_pago_id'] ?? null),
                'periodo_id'     => self::enteroANulo($datos['periodo_id'] ?? null),
                'socio_id'       => self::enteroANulo($datos['socio_id'] ?? null),
                'referencia'     => self::vacioANulo($datos['referencia'] ?? null),
                'observaciones'  => self::vacioANulo($datos['observaciones'] ?? null),
                'responsable_id' => self::enteroANulo($datos['responsable_id'] ?? null),
                'id'             => $id,
            ]
        );
    }

    /**
     * Anula un movimiento. Los movimientos no se eliminan.
     */
    public static function anular(int $id, int $usuarioId, string $motivo): void
    {
        self::run(
            sprintf(
                'UPDATE `%s`
                 SET `estado` = \'anulado\',
                     `anulado_por` = :anulado_por,
                     `anulado_at` = NOW(),
                     `motivo_anulacion` = :motivo
                 WHERE `id` = :id',
                self::table()
            ),
            [
                'anulado_por' => $usuarioId,
                'motivo'      => $motivo,
                'id'          => $id,
            ]
        );
    }

    /**
     * Resumen por categoría en un rango de fechas.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function resumenPorCategoria(string $inicio, string $fin): array
    {
        $sql = sprintf(
            'SELECT m.`tipo`,
                    COALESCE(c.`nombre`, \'Sin categoría\') AS categoria,
                    COUNT(*) AS operaciones,
                    COALESCE(SUM(m.`monto`), 0) AS total
             FROM `%s` m
             LEFT JOIN `%s` c ON c.`id` = m.`categoria_id`
             WHERE m.`estado` = \'registrado\'
               AND m.`fecha` BETWEEN :inicio AND :fin
             GROUP BY m.`tipo`, c.`nombre`
             ORDER BY m.`tipo` ASC, total DESC',
            self::table(),
            self::prefijada('categorias_movimiento')
        );

        return self::fetchAll($sql, ['inicio' => $inicio, 'fin' => $fin]);
    }

    /**
     * Totales mensuales de ingresos y egresos.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function resumenMensual(string $inicio, string $fin): array
    {
        $sql = sprintf(
            'SELECT DATE_FORMAT(`fecha`, \'%%Y-%%m\') AS periodo,
                    COALESCE(SUM(CASE WHEN `tipo` = \'ingreso\' THEN `monto` ELSE 0 END), 0) AS ingresos,
                    COALESCE(SUM(CASE WHEN `tipo` = \'egreso\' THEN `monto` ELSE 0 END), 0) AS egresos
             FROM `%s`
             WHERE `estado` = \'registrado\'
               AND `fecha` BETWEEN :inicio AND :fin
             GROUP BY periodo
             ORDER BY periodo ASC',
            self::table()
        );

        return self::fetchAll($sql, ['inicio' => $inicio, 'fin' => $fin]);
    }

    /**
     * Historial de cambios de un movimiento.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function auditoria(int $movimientoId): array
    {
        return self::fetchAll(
            sprintf(
                'SELECT a.*, u.`nombres` AS usuario_nombres, u.`apellidos` AS usuario_apellidos
                 FROM `%s` a
                 LEFT JOIN `%s` u ON u.`id` = a.`usuario_id`
                 WHERE a.`movimiento_id` = :movimiento_id
                 ORDER BY a.`created_at` DESC, a.`id` DESC',
                self::prefijada('movimientos_auditoria'),
                self::prefijada('usuarios')
            ),
            ['movimiento_id' => $movimientoId]
        );
    }

    /**
     * Registra la traza de un movimiento.
     *
     * @param array<string, mixed>|null $anteriores
     * @param array<string, mixed>|null $nuevos
     */
    public static function registrarAuditoria(
        int $movimientoId,
        string $accion,
        ?array $anteriores,
        ?array $nuevos,
        ?int $usuarioId,
        ?string $motivo = null
    ): void {
        self::insertar([
            'movimiento_id'    => $movimientoId,
            'accion'          => $accion,
            'datos_anteriores' => $anteriores === null ? null : json_encode($anteriores, JSON_UNESCAPED_UNICODE),
            'datos_nuevos'     => $nuevos === null ? null : json_encode($nuevos, JSON_UNESCAPED_UNICODE),
            'usuario_id'      => $usuarioId,
            'motivo'          => self::vacioANulo($motivo),
        ]);
    }

    /**
     * @param array<string, mixed> $filtros
     * @return array{0: string, 1: array<string, mixed>}
     */
    private static function condiciones(array $filtros, string $alias): array
    {
        $condiciones = [];
        $params = [];

        $busqueda = trim((string) ($filtros['q'] ?? ''));

        if ($busqueda !== '') {
            $condiciones[] = sprintf(
                '(%s.`concepto` LIKE :q1 OR %s.`referencia` LIKE :q2)',
                $alias,
                $alias
            );
            $params['q1'] = '%' . $busqueda . '%';
            $params['q2'] = '%' . $busqueda . '%';
        }

        $tipo = (string) ($filtros['tipo'] ?? '');

        if (in_array($tipo, ['ingreso', 'egreso'], true)) {
            $condiciones[] = sprintf('%s.`tipo` = :tipo', $alias);
            $params['tipo'] = $tipo;
        }

        $estado = (string) ($filtros['estado'] ?? '');

        if (in_array($estado, ['registrado', 'anulado'], true)) {
            $condiciones[] = sprintf('%s.`estado` = :estado', $alias);
            $params['estado'] = $estado;
        }

        $categoriaId = (int) ($filtros['categoria_id'] ?? 0);

        if ($categoriaId > 0) {
            $condiciones[] = sprintf('%s.`categoria_id` = :categoria_id', $alias);
            $params['categoria_id'] = $categoriaId;
        }

        $desde = (string) ($filtros['desde'] ?? '');

        if ($desde !== '') {
            $condiciones[] = sprintf('%s.`fecha` >= :desde', $alias);
            $params['desde'] = $desde;
        }

        $hasta = (string) ($filtros['hasta'] ?? '');

        if ($hasta !== '') {
            $condiciones[] = sprintf('%s.`fecha` <= :hasta', $alias);
            $params['hasta'] = $hasta;
        }

        if ($condiciones === []) {
            return ['', $params];
        }

        return ['WHERE ' . implode(' AND ', $condiciones), $params];
    }

    protected static function prefijada(string $tabla): string
    {
        $prefijo = (string) \App\Core\Config::get('database.prefix', 'csduv3_');

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

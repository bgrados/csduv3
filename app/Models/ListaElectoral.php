<?php

declare(strict_types=1);

namespace App\Models;

/**
 * Listas o agrupaciones participantes en un proceso electoral.
 */
final class ListaElectoral extends Model
{
    protected static string $table = 'listas_electorales';

    public const ESTADOS = [
        'inscrita'  => 'Inscrita',
        'retirada'  => 'Retirada',
        'tachada'   => 'Tachada',
        'ganadora'  => 'Ganadora',
    ];

    /**
     * @return array<int, array<string, mixed>>
     */
    public static function listarPorProceso(int $procesoId): array
    {
        $sql = sprintf(
            'SELECT l.*,
                    (SELECT COUNT(*) FROM `%s` c WHERE c.`lista_id` = l.`id`) AS total_candidatos,
                    r.`votos` AS votos,
                    r.`votos_nulos` AS votos_nulos,
                    r.`votos_blancos` AS votos_blancos,
                    r.`es_ganadora` AS es_ganadora,
                    r.`observaciones` AS resultado_observaciones
             FROM `%s` l
             LEFT JOIN `%s` r ON r.`proceso_id` = l.`proceso_id` AND r.`lista_id` = l.`id`
             WHERE l.`proceso_id` = :proceso_id
             ORDER BY l.`orden` ASC, l.`nombre` ASC',
            self::prefijada('candidatos'),
            self::table(),
            self::prefijada('resultados_electorales')
        );

        return self::fetchAll($sql, ['proceso_id' => $procesoId]);
    }

    /**
     * @return array<string, mixed>|null
     */
    public static function find(int $id): ?array
    {
        return self::fetchOne(
            sprintf('SELECT * FROM `%s` WHERE `id` = :id LIMIT 1', self::table()),
            ['id' => $id]
        );
    }

    public static function crear(array $datos): int
    {
        return self::insertar([
            'proceso_id'    => (int) $datos['proceso_id'],
            'nombre'        => (string) $datos['nombre'],
            'lema'          => self::vacioANulo($datos['lema'] ?? null),
            'orden'         => (int) ($datos['orden'] ?? 1),
            'estado'        => (string) ($datos['estado'] ?? 'inscrita'),
            'observaciones' => self::vacioANulo($datos['observaciones'] ?? null),
        ]);
    }

    public static function cambiarEstado(int $id, string $estado): void
    {
        self::run(
            sprintf('UPDATE `%s` SET `estado` = :estado WHERE `id` = :id', self::table()),
            ['estado' => $estado, 'id' => $id]
        );
    }

    public static function nombreExiste(int $procesoId, string $nombre, ?int $excluirId = null): bool
    {
        $sql = sprintf(
            'SELECT COUNT(*) FROM `%s` WHERE `proceso_id` = :proceso_id AND `nombre` = :nombre',
            self::table()
        );
        $params = ['proceso_id' => $procesoId, 'nombre' => $nombre];

        if ($excluirId !== null && $excluirId > 0) {
            $sql .= ' AND `id` <> :id';
            $params['id'] = $excluirId;
        }

        return (int) self::scalar($sql, $params) > 0;
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
}

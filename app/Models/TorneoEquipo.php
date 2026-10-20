<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Config;

/**
 * Equipos inscritos en un torneo.
 */
final class TorneoEquipo extends Model
{
    protected static string $table = 'torneo_equipos';

    /** @return array<int, array<string, mixed>> */
    public static function listarPorTorneo(int $torneoId): array
    {
        return self::fetchAll(
            sprintf(
                'SELECT e.*,
                        (SELECT COUNT(*) FROM `%s` p WHERE p.`equipo_id` = e.`id`) AS total_jugadores
                 FROM `%s` e
                 WHERE e.`torneo_id` = :torneo_id
                 ORDER BY e.`nombre` ASC',
                self::prefijada('torneo_participantes'),
                self::table()
            ),
            ['torneo_id' => $torneoId]
        );
    }

    /** @return array<string, mixed>|null */
    public static function findDeTorneo(int $id, int $torneoId): ?array
    {
        return self::fetchOne(
            sprintf(
                'SELECT * FROM `%s` WHERE `id` = :id AND `torneo_id` = :torneo_id LIMIT 1',
                self::table()
            ),
            ['id' => $id, 'torneo_id' => $torneoId]
        );
    }

    /**
     * @param array<string, mixed> $datos
     */
    public static function crear(array $datos): int
    {
        return self::insertar([
            'torneo_id'        => (int) $datos['torneo_id'],
            'nombre'           => (string) $datos['nombre'],
            'abreviatura'      => self::vacioANulo($datos['abreviatura'] ?? null),
            'delegado_nombre'  => self::vacioANulo($datos['delegado_nombre'] ?? null),
            'delegado_telefono' => self::vacioANulo($datos['delegado_telefono'] ?? null),
            'observaciones'    => self::vacioANulo($datos['observaciones'] ?? null),
        ]);
    }

    public static function eliminar(int $id): void
    {
        self::run(
            sprintf('DELETE FROM `%s` WHERE `id` = :id', self::table()),
            ['id' => $id]
        );
    }

    public static function nombreExiste(int $torneoId, string $nombre, ?int $excluirId = null): bool
    {
        $sql = sprintf(
            'SELECT COUNT(*) FROM `%s` WHERE `torneo_id` = :torneo_id AND `nombre` = :nombre',
            self::table()
        );
        $params = ['torneo_id' => $torneoId, 'nombre' => $nombre];

        if ($excluirId !== null && $excluirId > 0) {
            $sql .= ' AND `id` <> :id';
            $params['id'] = $excluirId;
        }

        return (int) self::scalar($sql, $params) > 0;
    }

    public static function tieneEncuentros(int $equipoId): bool
    {
        return (int) self::scalar(
            sprintf(
                'SELECT COUNT(*) FROM `%s` WHERE `equipo_local_id` = :local OR `equipo_visitante_id` = :visitante',
                self::prefijada('encuentros')
            ),
            ['local' => $equipoId, 'visitante' => $equipoId]
        ) > 0;
    }

    public static function contarPorTorneo(int $torneoId): int
    {
        return (int) self::scalar(
            sprintf('SELECT COUNT(*) FROM `%s` WHERE `torneo_id` = :torneo_id', self::table()),
            ['torneo_id' => $torneoId]
        );
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
}

<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Config;

/**
 * Encuentros programados y sus resultados.
 */
final class Encuentro extends Model
{
    protected static string $table = 'encuentros';

    public const ESTADOS = [
        'programado' => 'Programado',
        'jugado'     => 'Jugado',
        'suspendido' => 'Suspendido',
        'cancelado'  => 'Cancelado',
        'walkover'   => 'Walkover',
    ];

    /** @return array<int, array<string, mixed>> */
    public static function listarPorTorneo(int $torneoId): array
    {
        return self::fetchAll(
            self::consultaConEquipos() . '
             WHERE e.`torneo_id` = :torneo_id
             ORDER BY COALESCE(j.`numero`, 9999) ASC, e.`fecha` ASC, e.`hora` ASC, e.`id` ASC',
            ['torneo_id' => $torneoId]
        );
    }

    /**
     * Próximos partidos programados, para la agenda deportiva pública.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function proximos(int $limite = 4): array
    {
        $limite = max(1, min(20, $limite));

        $sql = sprintf(
            'SELECT e.`id`, e.`fecha`, e.`hora`, e.`estado`,
                    l.`nombre` AS local_nombre,
                    v.`nombre` AS visitante_nombre,
                    t.`nombre` AS torneo_nombre,
                    i.`nombre` AS instalacion_nombre
             FROM `%s` e
             LEFT JOIN `%s` l ON l.`id` = e.`equipo_local_id`
             LEFT JOIN `%s` v ON v.`id` = e.`equipo_visitante_id`
             LEFT JOIN `%s` t ON t.`id` = e.`torneo_id`
             LEFT JOIN `%s` i ON i.`id` = e.`instalacion_id`
             WHERE e.`estado` = \'programado\'
               AND e.`fecha` >= CURDATE()
             ORDER BY e.`fecha` ASC, e.`hora` ASC
             LIMIT %d',
            self::table(),
            self::prefijada('torneo_equipos'),
            self::prefijada('torneo_equipos'),
            self::prefijada('torneos'),
            self::prefijada('instalaciones'),
            $limite
        );

        return self::fetchAll($sql);
    }

    /** @return array<string, mixed>|null */
    public static function findConEquipos(int $id): ?array
    {
        return self::fetchOne(
            self::consultaConEquipos() . ' WHERE e.`id` = :id LIMIT 1',
            ['id' => $id]
        );
    }

    /**
     * @param array<string, mixed> $datos
     */
    public static function crear(array $datos): int
    {
        return self::insertar([
            'torneo_id'           => (int) $datos['torneo_id'],
            'jornada_id'          => self::enteroANulo($datos['jornada_id'] ?? null),
            'equipo_local_id'     => (int) $datos['equipo_local_id'],
            'equipo_visitante_id' => (int) $datos['equipo_visitante_id'],
            'instalacion_id'      => self::enteroANulo($datos['instalacion_id'] ?? null),
            'fecha'               => (string) $datos['fecha'],
            'hora'                => self::vacioANulo($datos['hora'] ?? null),
            'estado'              => 'programado',
            'observaciones'       => self::vacioANulo($datos['observaciones'] ?? null),
            'registrado_por'      => self::enteroANulo($datos['registrado_por'] ?? null),
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
                 SET `instalacion_id` = :instalacion_id,
                     `fecha` = :fecha,
                     `hora` = :hora,
                     `marcador_local` = :marcador_local,
                     `marcador_visitante` = :marcador_visitante,
                     `estado` = :estado,
                     `observaciones` = :observaciones,
                     `registrado_por` = :registrado_por
                 WHERE `id` = :id',
                self::table()
            ),
            [
                'instalacion_id'      => self::enteroANulo($datos['instalacion_id'] ?? null),
                'fecha'               => (string) $datos['fecha'],
                'hora'                => self::vacioANulo($datos['hora'] ?? null),
                'marcador_local'      => self::marcadorANulo($datos['marcador_local'] ?? null),
                'marcador_visitante'  => self::marcadorANulo($datos['marcador_visitante'] ?? null),
                'estado'              => (string) $datos['estado'],
                'observaciones'       => self::vacioANulo($datos['observaciones'] ?? null),
                'registrado_por'      => self::enteroANulo($datos['registrado_por'] ?? null),
                'id'                  => $id,
            ]
        );
    }

    public static function eliminarPorTorneo(int $torneoId): void
    {
        self::run(
            sprintf('DELETE FROM `%s` WHERE `torneo_id` = :torneo_id', self::table()),
            ['torneo_id' => $torneoId]
        );
    }

    public static function contarPorTorneo(int $torneoId): int
    {
        return (int) self::scalar(
            sprintf('SELECT COUNT(*) FROM `%s` WHERE `torneo_id` = :torneo_id', self::table()),
            ['torneo_id' => $torneoId]
        );
    }

    public static function contarJugados(int $torneoId): int
    {
        return (int) self::scalar(
            sprintf(
                'SELECT COUNT(*) FROM `%s` WHERE `torneo_id` = :torneo_id AND `estado` IN (\'jugado\', \'walkover\')',
                self::table()
            ),
            ['torneo_id' => $torneoId]
        );
    }

    private static function consultaConEquipos(): string
    {
        return sprintf(
            'SELECT e.*,
                    j.`numero` AS jornada_numero,
                    j.`nombre` AS jornada_nombre,
                    l.`nombre` AS local_nombre,
                    l.`abreviatura` AS local_abreviatura,
                    v.`nombre` AS visitante_nombre,
                    v.`abreviatura` AS visitante_abreviatura,
                    i.`nombre` AS instalacion_nombre
             FROM `%s` e
             LEFT JOIN `%s` j ON j.`id` = e.`jornada_id`
             LEFT JOIN `%s` l ON l.`id` = e.`equipo_local_id`
             LEFT JOIN `%s` v ON v.`id` = e.`equipo_visitante_id`
             LEFT JOIN `%s` i ON i.`id` = e.`instalacion_id`',
            self::table(),
            self::prefijada('jornadas'),
            TorneoEquipo::table(),
            TorneoEquipo::table(),
            Instalacion::table()
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

    private static function enteroANulo(mixed $valor): ?int
    {
        $entero = (int) ($valor ?? 0);

        return $entero > 0 ? $entero : null;
    }

    private static function marcadorANulo(mixed $valor): ?int
    {
        if ($valor === null || $valor === '') {
            return null;
        }

        $entero = (int) $valor;

        return $entero >= 0 ? $entero : null;
    }
}

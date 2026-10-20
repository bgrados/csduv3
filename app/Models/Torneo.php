<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;
use RuntimeException;

/**
 * Torneos, fixture y posiciones.
 *
 * El esquema permite varias modalidades. La generación automática de fixture
 * implementada en esta versión es la de todos contra todos; las demás
 * modalidades pueden registrarse y programarse manualmente.
 */
final class Torneo extends Model
{
    protected static string $table = 'torneos';

    public const MODALIDADES = [
        'todos_contra_todos' => 'Todos contra todos',
    ];

    public const ESTADOS = [
        'planificado' => 'Planificado',
        'en_curso'    => 'En curso',
        'finalizado'  => 'Finalizado',
        'anulado'     => 'Anulado',
    ];

    /**
     * Listado administrativo de torneos.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function listar(?string $busqueda = null, ?int $deporteId = null, string $estado = ''): array
    {
        $condiciones = [];
        $params = [];

        $busqueda = $busqueda === null ? '' : trim($busqueda);

        if ($busqueda !== '') {
            $condiciones[] = '(t.`nombre` LIKE :q1 OR t.`categoria` LIKE :q2)';
            $like = '%' . $busqueda . '%';
            $params['q1'] = $like;
            $params['q2'] = $like;
        }

        if ($deporteId !== null && $deporteId > 0) {
            $condiciones[] = 't.`deporte_id` = :deporte_id';
            $params['deporte_id'] = $deporteId;
        }

        if (isset(self::ESTADOS[$estado])) {
            $condiciones[] = 't.`estado` = :estado';
            $params['estado'] = $estado;
        }

        $sql = sprintf(
            'SELECT t.*, d.`nombre` AS deporte_nombre,
                    (SELECT COUNT(*) FROM `%s` e WHERE e.`torneo_id` = t.`id`) AS total_equipos,
                    (SELECT COUNT(*) FROM `%s` e WHERE e.`torneo_id` = t.`id`) AS total_encuentros,
                    (SELECT COUNT(*) FROM `%s` e
                     WHERE e.`torneo_id` = t.`id` AND e.`estado` IN (\'jugado\', \'walkover\')) AS encuentros_jugados
             FROM `%s` t
             INNER JOIN `%s` d ON d.`id` = t.`deporte_id`
             %s
             ORDER BY CASE t.`estado`
                          WHEN \'en_curso\' THEN 0
                          WHEN \'planificado\' THEN 1
                          WHEN \'finalizado\' THEN 2
                          ELSE 3
                      END ASC,
                      t.`fecha_inicio` DESC,
                      t.`id` DESC',
            TorneoEquipo::table(),
            self::prefijada('encuentros'),
            self::prefijada('encuentros'),
            self::table(),
            Deporte::table(),
            $condiciones === [] ? '' : 'WHERE ' . implode(' AND ', $condiciones)
        );

        return self::fetchAll($sql, $params);
    }

    /**
     * Torneos visibles en el portal público.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function publicos(): array
    {
        $sql = sprintf(
            'SELECT t.*, d.`nombre` AS deporte_nombre,
                    (SELECT COUNT(*) FROM `%s` e WHERE e.`torneo_id` = t.`id`) AS total_equipos,
                    (SELECT COUNT(*) FROM `%s` e WHERE e.`torneo_id` = t.`id`) AS total_encuentros
             FROM `%s` t
             INNER JOIN `%s` d ON d.`id` = t.`deporte_id`
             WHERE t.`estado` IN (\'planificado\', \'en_curso\', \'finalizado\')
             ORDER BY CASE t.`estado`
                          WHEN \'en_curso\' THEN 0
                          WHEN \'planificado\' THEN 1
                          ELSE 2
                      END ASC,
                      t.`fecha_inicio` ASC,
                      t.`id` ASC',
            TorneoEquipo::table(),
            self::prefijada('encuentros'),
            self::table(),
            Deporte::table()
        );

        return self::fetchAll($sql);
    }

    /** @return array<string, mixed>|null */
    public static function findConDeporte(int $id): ?array
    {
        return self::fetchOne(
            sprintf(
                'SELECT t.*, d.`nombre` AS deporte_nombre,
                        (SELECT COUNT(*) FROM `%s` e WHERE e.`torneo_id` = t.`id`) AS total_equipos,
                        (SELECT COUNT(*) FROM `%s` e WHERE e.`torneo_id` = t.`id`) AS total_encuentros
                 FROM `%s` t
                 INNER JOIN `%s` d ON d.`id` = t.`deporte_id`
                 WHERE t.`id` = :id
                 LIMIT 1',
                TorneoEquipo::table(),
                self::prefijada('encuentros'),
                self::table(),
                Deporte::table()
            ),
            ['id' => $id]
        );
    }

    /** @return array<string, mixed>|null */
    public static function findPublico(int $id): ?array
    {
        $torneo = self::findConDeporte($id);

        if ($torneo === null || (string) $torneo['estado'] === 'anulado') {
            return null;
        }

        return $torneo;
    }

    /**
     * @param array<string, mixed> $datos
     */
    public static function crear(array $datos): int
    {
        return self::insertar([
            'deporte_id'   => (int) $datos['deporte_id'],
            'nombre'       => (string) $datos['nombre'],
            'categoria'    => self::vacioANulo($datos['categoria'] ?? null),
            'modalidad'    => (string) $datos['modalidad'],
            'reglamento'   => self::vacioANulo($datos['reglamento'] ?? null),
            'fecha_inicio' => self::vacioANulo($datos['fecha_inicio'] ?? null),
            'fecha_fin'    => self::vacioANulo($datos['fecha_fin'] ?? null),
            'estado'       => (string) $datos['estado'],
            'observaciones' => self::vacioANulo($datos['observaciones'] ?? null),
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
                 SET `deporte_id` = :deporte_id,
                     `nombre` = :nombre,
                     `categoria` = :categoria,
                     `modalidad` = :modalidad,
                     `reglamento` = :reglamento,
                     `fecha_inicio` = :fecha_inicio,
                     `fecha_fin` = :fecha_fin,
                     `estado` = :estado,
                     `observaciones` = :observaciones
                 WHERE `id` = :id',
                self::table()
            ),
            [
                'deporte_id'   => (int) $datos['deporte_id'],
                'nombre'       => (string) $datos['nombre'],
                'categoria'    => self::vacioANulo($datos['categoria'] ?? null),
                'modalidad'    => (string) $datos['modalidad'],
                'reglamento'   => self::vacioANulo($datos['reglamento'] ?? null),
                'fecha_inicio' => self::vacioANulo($datos['fecha_inicio'] ?? null),
                'fecha_fin'    => self::vacioANulo($datos['fecha_fin'] ?? null),
                'estado'       => (string) $datos['estado'],
                'observaciones' => self::vacioANulo($datos['observaciones'] ?? null),
                'id'           => $id,
            ]
        );
    }

    public static function cambiarEstado(int $id, string $estado): void
    {
        self::run(
            sprintf('UPDATE `%s` SET `estado` = :estado WHERE `id` = :id', self::table()),
            ['estado' => $estado, 'id' => $id]
        );
    }

    /**
     * Genera un fixture de todos contra todos y devuelve el número de partidos.
     *
     * @throws RuntimeException cuando no hay equipos suficientes o ya existe un fixture.
     */
    public static function generarFixture(
        int $torneoId,
        string $fechaInicio,
        bool $reemplazar,
        ?int $usuarioId = null
    ): int {
        $torneo = self::find($torneoId);

        if ($torneo === null) {
            throw new RuntimeException('El torneo indicado no existe.');
        }

        if ((string) $torneo['modalidad'] !== 'todos_contra_todos') {
            throw new RuntimeException('La generación automática solo está disponible para torneos de todos contra todos.');
        }

        $equipos = TorneoEquipo::listarPorTorneo($torneoId);

        if (count($equipos) < 2) {
            throw new RuntimeException('Inscriba al menos dos equipos antes de generar el fixture.');
        }

        if (Encuentro::contarPorTorneo($torneoId) > 0 && !$reemplazar) {
            throw new RuntimeException('El torneo ya tiene un fixture. Confirme que desea reemplazarlo.');
        }

        return (int) Database::transaction(static function () use ($torneoId, $fechaInicio, $reemplazar, $usuarioId, $equipos): int {
            if ($reemplazar) {
                Encuentro::eliminarPorTorneo($torneoId);
            }

            // Si no hay partidos pero quedaron jornadas de una generación
            // anterior, se eliminan para evitar duplicar la numeración.
            Jornada::eliminarPorTorneo($torneoId);

            $ids = array_map(static fn (array $equipo): int => (int) $equipo['id'], $equipos);
            $total = count($ids);

            if ($total % 2 === 1) {
                $ids[] = null;
                $total++;
            }

            $rondas = $total - 1;
            $mitad = intdiv($total, 2);
            $rotacion = $ids;
            $partidos = 0;

            for ($ronda = 0; $ronda < $rondas; $ronda++) {
                $fecha = date('Y-m-d', strtotime($fechaInicio . ' +' . $ronda . ' week'));
                $jornadaId = Jornada::crear($torneoId, $ronda + 1, 'Fecha ' . ($ronda + 1), $fecha);

                for ($indice = 0; $indice < $mitad; $indice++) {
                    $local = $rotacion[$indice];
                    $visitante = $rotacion[$total - 1 - $indice];

                    if ($local === null || $visitante === null) {
                        continue;
                    }

                    if ($ronda % 2 === 1) {
                        [$local, $visitante] = [$visitante, $local];
                    }

                    Encuentro::crear([
                        'torneo_id'           => $torneoId,
                        'jornada_id'          => $jornadaId,
                        'equipo_local_id'     => $local,
                        'equipo_visitante_id' => $visitante,
                        'instalacion_id'      => null,
                        'fecha'               => $fecha,
                        'hora'                => null,
                        'registrado_por'      => $usuarioId,
                    ]);

                    $partidos++;
                }

                $ultimo = array_pop($rotacion);
                array_splice($rotacion, 1, 0, [$ultimo]);
            }

            return $partidos;
        });
    }

    /**
     * Calcula la tabla de posiciones de un torneo.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function posiciones(int $torneoId, int $deporteId): array
    {
        $equipos = TorneoEquipo::listarPorTorneo($torneoId);
        $encuentros = Encuentro::listarPorTorneo($torneoId);
        $puntos = DeportePunto::mapaPorDeporte($deporteId);

        $tabla = [];

        foreach ($equipos as $equipo) {
            $id = (int) $equipo['id'];
            $tabla[$id] = [
                'equipo_id'     => $id,
                'nombre'        => (string) $equipo['nombre'],
                'abreviatura'   => (string) ($equipo['abreviatura'] ?? ''),
                'jugados'       => 0,
                'ganados'       => 0,
                'empatados'     => 0,
                'perdidos'      => 0,
                'goles_favor'   => 0,
                'goles_contra'  => 0,
                'diferencia'    => 0,
                'puntos'        => 0.0,
            ];
        }

        foreach ($encuentros as $encuentro) {
            $estado = (string) ($encuentro['estado'] ?? '');

            if (!in_array($estado, ['jugado', 'walkover'], true)) {
                continue;
            }

            if ($encuentro['marcador_local'] === null || $encuentro['marcador_visitante'] === null) {
                continue;
            }

            $localId = (int) $encuentro['equipo_local_id'];
            $visitanteId = (int) $encuentro['equipo_visitante_id'];

            if (!isset($tabla[$localId], $tabla[$visitanteId])) {
                continue;
            }

            $golesLocal = (int) $encuentro['marcador_local'];
            $golesVisitante = (int) $encuentro['marcador_visitante'];

            $tabla[$localId]['jugados']++;
            $tabla[$visitanteId]['jugados']++;
            $tabla[$localId]['goles_favor'] += $golesLocal;
            $tabla[$localId]['goles_contra'] += $golesVisitante;
            $tabla[$visitanteId]['goles_favor'] += $golesVisitante;
            $tabla[$visitanteId]['goles_contra'] += $golesLocal;

            if ($golesLocal > $golesVisitante) {
                $tabla[$localId]['ganados']++;
                $tabla[$visitanteId]['perdidos']++;
                $tabla[$localId]['puntos'] += $puntos['victoria'];
                $tabla[$visitanteId]['puntos'] += $puntos['derrota'];
            } elseif ($golesLocal < $golesVisitante) {
                $tabla[$visitanteId]['ganados']++;
                $tabla[$localId]['perdidos']++;
                $tabla[$visitanteId]['puntos'] += $puntos['victoria'];
                $tabla[$localId]['puntos'] += $puntos['derrota'];
            } else {
                $tabla[$localId]['empatados']++;
                $tabla[$visitanteId]['empatados']++;
                $tabla[$localId]['puntos'] += $puntos['empate'];
                $tabla[$visitanteId]['puntos'] += $puntos['empate'];
            }
        }

        foreach ($tabla as $id => $fila) {
            $tabla[$id]['diferencia'] = $fila['goles_favor'] - $fila['goles_contra'];
        }

        $filas = array_values($tabla);

        usort($filas, static function (array $a, array $b): int {
            if ($a['puntos'] !== $b['puntos']) {
                return $b['puntos'] <=> $a['puntos'];
            }

            if ($a['diferencia'] !== $b['diferencia']) {
                return $b['diferencia'] <=> $a['diferencia'];
            }

            if ($a['goles_favor'] !== $b['goles_favor']) {
                return $b['goles_favor'] <=> $a['goles_favor'];
            }

            return strcasecmp((string) $a['nombre'], (string) $b['nombre']);
        });

        foreach ($filas as $indice => $fila) {
            $filas[$indice]['posicion'] = $indice + 1;
        }

        return $filas;
    }

    public static function contar(): int
    {
        return (int) self::scalar(sprintf('SELECT COUNT(*) FROM `%s`', self::table()));
    }

    public static function contarEnCurso(): int
    {
        return (int) self::scalar(
            sprintf('SELECT COUNT(*) FROM `%s` WHERE `estado` = \'en_curso\'', self::table())
        );
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

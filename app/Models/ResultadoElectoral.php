<?php

declare(strict_types=1);

namespace App\Models;

/**
 * Registro administrativo de resultados de un proceso electoral.
 *
 * No es un sistema de votación electrónica: solo guarda los resultados que
 * la organización declara y quién los registró.
 */
final class ResultadoElectoral extends Model
{
    protected static string $table = 'resultados_electorales';

    /**
     * @return array<int, array<string, mixed>>
     */
    public static function listarPorProceso(int $procesoId): array
    {
        $sql = sprintf(
            'SELECT r.*, l.`nombre` AS lista_nombre
             FROM `%s` r
             LEFT JOIN `%s` l ON l.`id` = r.`lista_id`
             WHERE r.`proceso_id` = :proceso_id
             ORDER BY r.`votos` DESC, l.`orden` ASC',
            self::table(),
            self::prefijada('listas_electorales')
        );

        return self::fetchAll($sql, ['proceso_id' => $procesoId]);
    }

    /**
     * @return array<string, mixed>|null
     */
    public static function findPorLista(int $procesoId, int $listaId): ?array
    {
        return self::fetchOne(
            sprintf(
                'SELECT * FROM `%s` WHERE `proceso_id` = :proceso_id AND `lista_id` = :lista_id LIMIT 1',
                self::table()
            ),
            ['proceso_id' => $procesoId, 'lista_id' => $listaId]
        );
    }

    public static function guardar(int $procesoId, int $listaId, array $datos, ?int $usuarioId): void
    {
        $existente = self::findPorLista($procesoId, $listaId);

        if ($existente === null) {
            self::insertar([
                'proceso_id'     => $procesoId,
                'lista_id'       => $listaId,
                'votos'          => (int) ($datos['votos'] ?? 0),
                'votos_nulos'    => (int) ($datos['votos_nulos'] ?? 0),
                'votos_blancos'  => (int) ($datos['votos_blancos'] ?? 0),
                'es_ganadora'    => (int) ($datos['es_ganadora'] ?? 0) === 1 ? 1 : 0,
                'registrado_por' => $usuarioId,
                'observaciones'  => self::vacioANulo($datos['observaciones'] ?? null),
            ]);

            return;
        }

        self::run(
            sprintf(
                'UPDATE `%s`
                 SET `votos` = :votos,
                     `votos_nulos` = :votos_nulos,
                     `votos_blancos` = :votos_blancos,
                     `es_ganadora` = :es_ganadora,
                     `registrado_por` = :registrado_por,
                     `observaciones` = :observaciones
                 WHERE `id` = :id',
                self::table()
            ),
            [
                'votos'          => (int) ($datos['votos'] ?? 0),
                'votos_nulos'    => (int) ($datos['votos_nulos'] ?? 0),
                'votos_blancos'  => (int) ($datos['votos_blancos'] ?? 0),
                'es_ganadora'    => (int) ($datos['es_ganadora'] ?? 0) === 1 ? 1 : 0,
                'registrado_por' => $usuarioId,
                'observaciones'  => self::vacioANulo($datos['observaciones'] ?? null),
                'id'             => (int) $existente['id'],
            ]
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

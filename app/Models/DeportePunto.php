<?php

declare(strict_types=1);

namespace App\Models;

/**
 * Reglas de puntuación por disciplina.
 *
 * Si una disciplina no tiene reglas cargadas se usan los valores tradicionales
 * del fútbol: 3 puntos por victoria, 1 por empate y 0 por derrota.
 */
final class DeportePunto extends Model
{
    protected static string $table = 'deporte_puntos';

    /** @var array<string, float> */
    public const POR_DEFECTO = [
        'victoria' => 3.0,
        'empate'   => 1.0,
        'derrota'  => 0.0,
    ];

    /** @return array<int, array<string, mixed>> */
    public static function porDeporte(int $deporteId): array
    {
        return self::fetchAll(
            sprintf(
                'SELECT * FROM `%s` WHERE `deporte_id` = :deporte_id ORDER BY `orden` ASC, `id` ASC',
                self::table()
            ),
            ['deporte_id' => $deporteId]
        );
    }

    /**
     * Devuelve el mapa concepto => puntos, completando los valores faltantes.
     *
     * @return array<string, float>
     */
    public static function mapaPorDeporte(int $deporteId): array
    {
        $mapa = self::POR_DEFECTO;

        foreach (self::porDeporte($deporteId) as $regla) {
            $concepto = (string) ($regla['concepto'] ?? '');

            if (array_key_exists($concepto, $mapa)) {
                $mapa[$concepto] = (float) ($regla['puntos'] ?? 0);
            }
        }

        return $mapa;
    }
}

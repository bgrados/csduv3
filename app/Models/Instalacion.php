<?php

declare(strict_types=1);

namespace App\Models;

/**
 * Instalaciones y ambientes que el club pone a disposición.
 */
final class Instalacion extends Model
{
    protected static string $table = 'instalaciones';

    /** @return array<int, array<string, mixed>> */
    public static function activas(): array
    {
        return self::fetchAll(
            sprintf(
                'SELECT * FROM `%s` WHERE `activo` = 1 ORDER BY `tipo` ASC, `nombre` ASC',
                self::table()
            )
        );
    }

    /** @return array<string, mixed>|null */
    public static function activaPorId(int $id): ?array
    {
        return self::fetchOne(
            sprintf('SELECT * FROM `%s` WHERE `id` = :id AND `activo` = 1 LIMIT 1', self::table()),
            ['id' => $id]
        );
    }

    /**
     * Instalaciones activas con sus tarifas vigentes.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function activasConTarifas(): array
    {
        $instalaciones = self::activas();

        foreach ($instalaciones as $indice => $instalacion) {
            $instalaciones[$indice]['tarifas'] = Tarifa::vigentesPorInstalacion((int) $instalacion['id']);
        }

        return $instalaciones;
    }

    public static function contarActivas(): int
    {
        return (int) self::scalar(
            sprintf('SELECT COUNT(*) FROM `%s` WHERE `activo` = 1', self::table())
        );
    }
}

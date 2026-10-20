<?php

declare(strict_types=1);

namespace App\Models;

/**
 * Resumen con cifras del club para el pie del portal público.
 *
 * Si la base de datos no está disponible, devuelve un array vacío y el pie
 * se muestra sin cifras en lugar de romper la página.
 */
final class Resumen
{
    /**
     * @return array{ambientes: int, noticias: int, negocios: int, evento: array<string, mixed>|null}
     */
    public static function datos(): array
    {
        $vacio = ['ambientes' => 0, 'noticias' => 0, 'negocios' => 0, 'evento' => null];

        try {
            return [
                'ambientes' => Instalacion::contarPublicadas(),
                'noticias'  => Foto::contarPublicadas(),
                'negocios'  => Negocio::contarPublicados(),
                'evento'    => Actividad::proximaPublica(),
            ];
        } catch (\Throwable) {
            return $vacio;
        }
    }
}

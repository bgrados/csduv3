<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Iconos SVG en línea.
 *
 * Se incluyen en el propio HTML para que la aplicación no dependa de fuentes
 * externas ni de CDN (importante en hosting compartido y en modo sin conexión).
 */
final class Icons
{
    /** @var array<string, string> Trazos SVG de 24x24. */
    private const ICONS = [
        'panel'        => '<rect x="3" y="3" width="7" height="9" rx="1.5"/><rect x="14" y="3" width="7" height="5" rx="1.5"/><rect x="14" y="12" width="7" height="9" rx="1.5"/><rect x="3" y="16" width="7" height="5" rx="1.5"/>',
        'socios'       => '<path d="M16 20v-1.5a4 4 0 0 0-4-4H7a4 4 0 0 0-4 4V20"/><circle cx="9.5" cy="7.5" r="3.5"/><path d="M17 4.5a3.5 3.5 0 0 1 0 6.6"/><path d="M21 20v-1.4a4 4 0 0 0-3-3.8"/>',
        'directiva'    => '<path d="M12 3 3 7.5V9h18V7.5L12 3Z"/><path d="M5 9v8M9.5 9v8M14.5 9v8M19 9v8"/><path d="M3 20h18"/>',
        'elecciones'   => '<path d="M9 12.5 11 15l4.5-5"/><rect x="3" y="3" width="18" height="18" rx="2.5"/><path d="M3 8h18"/>',
        'alquileres'   => '<path d="M3 21h18"/><path d="M5 21V7l7-4 7 4v14"/><path d="M10 21v-6h4v6"/><path d="M9 11h.01M15 11h.01"/>',
        'caja'         => '<rect x="3" y="6" width="18" height="13" rx="2.5"/><path d="M3 10h18"/><circle cx="16.5" cy="14.5" r="1.2"/>',
        'deportes'     => '<path d="M8 21h8"/><path d="M12 17v4"/><path d="M6 4h12v5a6 6 0 0 1-12 0V4Z"/><path d="M6 6H4a2 2 0 0 0 2 4"/><path d="M18 6h2a2 2 0 0 1-2 4"/>',
        'calendario'   => '<rect x="3" y="5" width="18" height="16" rx="2.5"/><path d="M3 10h18"/><path d="M8 3v4M16 3v4"/>',
        'documentos'   => '<path d="M4 20V5a2 2 0 0 1 2-2h6l6 6v11a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2Z"/><path d="M12 3v6h6"/><path d="M9 14h6M9 17.5h4"/>',
        'administracion' => '<path d="M12 3 5 6v6c0 4.5 3 7.6 7 9 4-1.4 7-4.5 7-9V6l-7-3Z"/><path d="M9.5 12.5 11.5 14.5 15 11"/>',
        'auditoria'    => '<path d="M4 4h11l5 5v11a2 2 0 0 1-2 2H4Z"/><path d="M9 12h6M9 16h4"/><path d="M14 4v6h6"/>',
        'diagnostico'  => '<path d="M3 12h4l2-5 3 10 2-5h7"/>',
        'menu'         => '<path d="M4 7h16M4 12h16M4 17h16"/>',
        'buscar'       => '<circle cx="11" cy="11" r="7"/><path d="m20 20-3.6-3.6"/>',
        'usuario'      => '<circle cx="12" cy="8" r="4"/><path d="M4 21v-1a6 6 0 0 1 6-6h4a6 6 0 0 1 6 6v1"/>',
        'salir'        => '<path d="M15 4h3a2 2 0 0 1 2 2v12a2 2 0 0 1-2 2h-3"/><path d="M10 8l-4 4 4 4"/><path d="M6 12h9"/>',
        'alerta'       => '<path d="M12 4 2.5 20h19L12 4Z"/><path d="M12 10v4.5"/><path d="M12 17.5h.01"/>',
        'exito'        => '<circle cx="12" cy="12" r="9"/><path d="m8 12.5 2.5 2.5L16 9.5"/>',
        'refrescar'    => '<path d="M20 12a8 8 0 1 1-2.3-5.6"/><path d="M20 4v4h-4"/>',
    ];

    public static function exists(string $name): bool
    {
        return isset(self::ICONS[$name]);
    }

    public static function render(string $name, string $class = 'icon', int $size = 20): string
    {
        $paths = self::ICONS[$name] ?? self::ICONS['panel'];

        return sprintf(
            '<svg class="%s" width="%d" height="%d" viewBox="0 0 24 24" fill="none" '
            . 'stroke="currentColor" stroke-width="1.7" stroke-linecap="round" '
            . 'stroke-linejoin="round" aria-hidden="true" focusable="false">%s</svg>',
            htmlspecialchars($class, ENT_QUOTES, 'UTF-8'),
            $size,
            $size,
            $paths
        );
    }
}

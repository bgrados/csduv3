<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Redes sociales del club.
 *
 * Cada red trae su color de marca y el glifo SVG correspondiente. El SVG va
 * incluido en el propio HTML (sin CDN ni fuentes externas) para que el pie de
 * página se vea igual con o sin conexión, como el resto de la aplicación.
 */
final class Redes
{
    /**
     * @var array<string, array{etiqueta: string, clase: string, svg: string}>
     */
    private const REDES = [
        'facebook' => [
            'etiqueta' => 'Facebook',
            'clase'    => 'redes__icono--facebook',
            'svg'      => '<path d="M13.6 21v-7.1h2.4l.4-2.8h-2.8V9.3c0-.8.22-1.36 1.4-1.36h1.5V5.45a20 20 0 0 0-2.15-.11c-2.1 0-3.55 1.3-3.55 3.66v2.2H8.4v2.8h2.4V21Z"/>',
        ],
        'instagram' => [
            'etiqueta' => 'Instagram',
            'clase'    => 'redes__icono--instagram',
            'svg'      => '<rect x="3.2" y="3.2" width="17.6" height="17.6" rx="5" fill="none" stroke="currentColor" stroke-width="1.9"/><circle cx="12" cy="12" r="4.1" fill="none" stroke="currentColor" stroke-width="1.9"/><circle cx="17.1" cy="6.9" r="1.25"/>',
        ],
        'youtube' => [
            'etiqueta' => 'YouTube',
            'clase'    => 'redes__icono--youtube',
            'svg'      => '<path fill-rule="evenodd" d="M21.6 7.4a2.6 2.6 0 0 0-1.83-1.84C18.2 5.2 12 5.2 12 5.2s-6.2 0-7.77.36A2.6 2.6 0 0 0 2.4 7.4C2.05 8.98 2.05 12 2.05 12s0 3.02.35 4.6a2.6 2.6 0 0 0 1.83 1.84C5.8 18.8 12 18.8 12 18.8s6.2 0 7.77-.36a2.6 2.6 0 0 0 1.83-1.84C21.95 15.02 21.95 12 21.95 12s0-3.02-.35-4.6ZM10.1 14.85V9.15L15.05 12Z"/>',
        ],
        'tiktok' => [
            'etiqueta' => 'TikTok',
            'clase'    => 'redes__icono--tiktok',
            'svg'      => '<path d="M16.6 5.82a4.3 4.3 0 0 1-1.06-2.83h-3.12v12.45a2.6 2.6 0 0 1-2.61 2.52 2.61 2.61 0 0 1-2.61-2.61 2.61 2.61 0 0 1 2.61-2.61c.27 0 .53.04.78.12V9.73a5.73 5.73 0 0 0-.78-.05 5.68 5.68 0 0 0-5.68 5.68 5.68 5.68 0 0 0 5.68 5.68 5.68 5.68 0 0 0 5.73-5.68V9.01a7.3 7.3 0 0 0 4.3 1.38V7.28a4.3 4.3 0 0 1-3.75-1.46Z"/>',
        ],
        'x' => [
            'etiqueta' => 'X',
            'clase'    => 'redes__icono--x',
            'svg'      => '<path d="M17.53 3h3.05l-6.66 7.61L21.75 21h-6.13l-4.82-6.3L5.22 21H2.17l7.12-8.14L2.25 3h6.29l4.36 5.76ZM16.77 19.36h1.69L7.5 4.54H5.68Z"/>',
        ],
        'whatsapp' => [
            'etiqueta' => 'WhatsApp',
            'clase'    => 'redes__icono--whatsapp',
            'svg'      => '<path d="M12 2a10 10 0 0 0-8.6 15.1L2 22l5.05-1.32A10 10 0 1 0 12 2Zm5.8 14.1c-.24.68-1.4 1.3-1.94 1.36-.5.05-.98.24-3.3-.7-2.78-1.1-4.54-3.94-4.68-4.12-.14-.18-1.12-1.5-1.12-2.86s.72-2.02.96-2.3c.24-.28.52-.34.7-.34h.5c.16 0 .38-.06.6.46.22.54.76 1.86.82 2 .06.14.1.3.02.5-.08.18-.14.3-.28.46-.14.16-.3.36-.42.48-.14.14-.28.28-.12.56.16.28.72 1.18 1.54 1.92 1.06.94 1.94 1.22 2.22 1.36.28.14.44.12.6-.08.16-.18.7-.82.88-1.1.18-.28.36-.22.6-.14.24.1 1.54.72 1.8.86.26.14.44.2.5.32.06.1.06.62-.18 1.28Z"/>',
        ],
    ];

    /**
     * Redes que el club puede configurar.
     *
     * @return array<string, array{etiqueta: string, clase: string, svg: string}>
     */
    public static function disponibles(): array
    {
        return self::REDES;
    }

    public static function existe(string $red): bool
    {
        return isset(self::REDES[$red]);
    }

    /**
     * Etiqueta legible de una red (Facebook, Instagram...).
     */
    public static function etiqueta(string $red): string
    {
        return self::REDES[$red]['etiqueta'] ?? $red;
    }

    /**
     * Clase CSS que aplica el color de la marca.
     */
    public static function clase(string $red): string
    {
        return self::REDES[$red]['clase'] ?? 'redes__icono';
    }

    /**
     * Dibuja el glifo de la red.
     */
    public static function render(string $red, int $size = 18): string
    {
        $datos = self::REDES[$red] ?? null;

        if ($datos === null) {
            return '';
        }

        return sprintf(
            '<svg class="redes__glifo" width="%d" height="%d" viewBox="0 0 24 24" fill="currentColor" '
            . 'aria-hidden="true" focusable="false">%s</svg>',
            $size,
            $size,
            $datos['svg']
        );
    }
}

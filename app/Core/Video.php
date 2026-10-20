<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Videos de YouTube y de Facebook.
 *
 * A partir del enlace que pega el club en el panel se obtiene el reproductor
 * y la miniatura. Los reproductores se insertan con `loading="lazy"` para no
 * cargar nada extra mientras el visitante no llega a esa parte de la página.
 */
final class Video
{
    public const TIPOS = [
        'youtube'  => 'YouTube',
        'facebook' => 'Facebook',
    ];

    /**
     * Detecta el video de un enlace.
     *
     * @return array{tipo: string, id: string, embed: string, miniatura: string}|null
     */
    public static function detectar(string $url): ?array
    {
        $url = trim($url);

        if ($url === '') {
            return null;
        }

        $youtube = self::detectarYoutube($url);

        if ($youtube !== null) {
            return $youtube;
        }

        return self::detectarFacebook($url);
    }

    /**
     * ¿El enlace corresponde a un video de YouTube o de Facebook?
     */
    public static function esValido(string $url): bool
    {
        return self::detectar($url) !== null;
    }

    /**
     * URL del reproductor (vacía si el enlace no es válido).
     */
    public static function embed(?string $url): string
    {
        if ($url === null) {
            return '';
        }

        $datos = self::detectar($url);

        return $datos['embed'] ?? '';
    }

    /**
     * Miniatura del video (solo YouTube la publica sin credenciales).
     */
    public static function miniatura(?string $url): string
    {
        if ($url === null) {
            return '';
        }

        $datos = self::detectar($url);

        return $datos['miniatura'] ?? '';
    }

    /**
     * Nombre de la plataforma de un enlace.
     */
    public static function plataforma(?string $url): string
    {
        if ($url === null) {
            return '';
        }

        $datos = self::detectar($url);

        return $datos === null ? '' : (self::TIPOS[$datos['tipo']] ?? '');
    }

    /**
     * Dibuja el reproductor del video.
     */
    public static function render(?string $url, string $titulo = '', string $clase = 'video'): string
    {
        $embed = self::embed($url);

        if ($embed === '') {
            return '';
        }

        $titulo = $titulo !== '' ? $titulo : 'Video del club';

        return sprintf(
            '<div class="%s"><iframe src="%s" title="%s" loading="lazy" frameborder="0" '
            . 'allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture" '
            . 'allowfullscreen referrerpolicy="strict-origin-when-cross-origin"></iframe></div>',
            htmlspecialchars($clase, ENT_QUOTES, 'UTF-8'),
            htmlspecialchars($embed, ENT_QUOTES, 'UTF-8'),
            htmlspecialchars($titulo, ENT_QUOTES, 'UTF-8')
        );
    }

    /**
     * Contenido de la página de Facebook del club (publicación de la página).
     *
     * Es el bloque oficial de Facebook: muestra las publicaciones de la página
     * sin necesidad de tokens ni de aplicaciones.
     */
    public static function paginaFacebook(string $url, int $alto = 260, string $titulo = 'Facebook del club'): string
    {
        $url = trim($url);

        if ($url === '') {
            return '';
        }

        $alto = max(160, min(900, $alto));

        $origen = 'https://www.facebook.com/plugins/page.php?href=' . rawurlencode($url)
            . '&tabs=timeline&width=320&height=' . $alto
            . '&small_header=true&adapt_container_width=true&hide_cover=true&show_facepile=false';

        return sprintf(
            '<div class="facebook-embed"><iframe src="%s" title="%s" loading="lazy" frameborder="0" '
            . 'allowtransparency="true" allow="encrypted-media; picture-in-picture" '
            . 'referrerpolicy="strict-origin-when-cross-origin"></iframe></div>',
            htmlspecialchars($origen, ENT_QUOTES, 'UTF-8'),
            htmlspecialchars($titulo, ENT_QUOTES, 'UTF-8')
        );
    }

    /**
     * @return array{tipo: string, id: string, embed: string, miniatura: string}|null
     */
    private static function detectarYoutube(string $url): ?array
    {
        $host = self::host($url);
        $ruta = (string) parse_url($url, PHP_URL_PATH);
        $consulta = [];

        parse_str((string) parse_url($url, PHP_URL_QUERY), $consulta);

        $id = null;
        $lista = null;

        if ($host === 'youtube.com' || $host === 'm.youtube.com' || $host === 'music.youtube.com') {
            if ($ruta === '/watch') {
                $id = isset($consulta['v']) ? (string) $consulta['v'] : null;
            } elseif (preg_match('#^/(embed|shorts|live|v)/([A-Za-z0-9_-]{6,})#', $ruta, $coincidencia) === 1) {
                $id = $coincidencia[2];
            } elseif (isset($consulta['list'])) {
                $lista = (string) $consulta['list'];
            }
        } elseif ($host === 'youtu.be') {
            $id = trim($ruta, '/');
        }

        if ($id !== null && preg_match('/^[A-Za-z0-9_-]{6,}$/', $id) === 1) {
            return [
                'tipo'     => 'youtube',
                'id'       => $id,
                'embed'    => 'https://www.youtube-nocookie.com/embed/' . $id,
                'miniatura' => 'https://i.ytimg.com/vi/' . $id . '/hqdefault.jpg',
            ];
        }

        if ($lista !== null && preg_match('/^[A-Za-z0-9_-]{6,}$/', $lista) === 1) {
            return [
                'tipo'      => 'youtube',
                'id'        => $lista,
                'embed'     => 'https://www.youtube-nocookie.com/embed/videoseries?list=' . $lista,
                'miniatura' => '',
            ];
        }

        return null;
    }

    /**
     * @return array{tipo: string, id: string, embed: string, miniatura: string}|null
     */
    private static function detectarFacebook(string $url): ?array
    {
        $host = self::host($url);

        if ($host !== 'facebook.com' && $host !== 'fb.com' && $host !== 'm.facebook.com' && $host !== 'fb.watch') {
            return null;
        }

        $ruta = (string) parse_url($url, PHP_URL_PATH);
        $consulta = [];

        parse_str((string) parse_url($url, PHP_URL_QUERY), $consulta);

        $esVideo = str_contains($ruta, '/videos/')
            || str_contains($ruta, '/reel/')
            || str_contains($ruta, '/share/v/')
            || str_starts_with($ruta, '/watch/')
            || isset($consulta['v']);

        if (!$esVideo) {
            return null;
        }

        return [
            'tipo'      => 'facebook',
            'id'        => '',
            'embed'     => 'https://www.facebook.com/plugins/video.php?href=' . rawurlencode($url) . '&show_text=0',
            'miniatura' => '',
        ];
    }

    /**
     * Dominio del enlace, sin «www.» y en minúsculas.
     */
    private static function host(string $url): string
    {
        $host = strtolower((string) parse_url($url, PHP_URL_HOST));

        return (string) preg_replace('/^www\./', '', $host);
    }
}

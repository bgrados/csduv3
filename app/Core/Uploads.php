<?php

declare(strict_types=1);

namespace App\Core;

use RuntimeException;

/**
 * Subida y custodia de archivos del sistema.
 *
 * Los archivos se guardan **siempre** dentro de `storage/uploads`, fuera de la
 * carpeta pública, con un nombre aleatorio. Se sirven por el controlador, que
 * comprueba el acceso antes de entregarlos.
 */
final class Uploads
{
    /** Tamaño máximo por defecto: 5 MB. */
    public const TAMANO_MAXIMO = 5242880;

    /** Ancho máximo de una imagen antes de convertirla a WebP. */
    public const ANCHO_MAXIMO = 2000;

    /** Calidad de la conversión a WebP. */
    public const CALIDAD_WEBP = 78;

    /** Extensiones admitidas y su tipo de contenido esperado. */
    public const TIPOS = [
        'pdf'  => 'application/pdf',
        'jpg'  => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'png'  => 'image/png',
        'gif'  => 'image/gif',
        'webp' => 'image/webp',
        'doc'  => 'application/msword',
        'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'xls'  => 'application/vnd.ms-excel',
        'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'odt'  => 'application/vnd.oasis.opendocument.text',
        'ods'  => 'application/vnd.oasis.opendocument.spreadsheet',
        'csv'  => 'text/csv',
        'txt'  => 'text/plain',
    ];

    /**
     * Extensiones admitidas según la configuración del sistema.
     *
     * @return array<int, string>
     */
    public static function extensionesPermitidas(): array
    {
        $imagenes = Config::get('security.uploads.allowed_images', []);
        $documentos = Config::get('security.uploads.allowed_documents', []);

        $lista = array_merge(is_array($imagenes) ? $imagenes : [], is_array($documentos) ? $documentos : []);

        return array_values(array_filter(array_map(
            static fn (mixed $extension): string => strtolower(trim((string) $extension)),
            $lista
        )));
    }

    /**
     * Tipos MIME admitidos, tomados de la configuración del sistema.
     *
     * @return array<int, string>
     */
    public static function mimesPermitidos(): array
    {
        $permitidos = Config::get('security.uploads.allowed_mimes', []);

        return is_array($permitidos) ? array_values(array_filter($permitidos, 'is_string')) : [];
    }

    /**
     * Tamaño máximo configurado en el sistema.
     */
    public static function tamanoMaximo(): int
    {
        $mb = (int) Config::get('security.uploads.max_size_mb', 5);

        return max(1, $mb) * 1024 * 1024;
    }

    /**
     * Guarda un archivo subido y devuelve sus datos.
     *
     * @param array<string, mixed>|null $archivo Entrada de `$_FILES`.
     * @return array{ruta: string, nombre_original: string, tipo_mime: string, tamano: int}
     * @throws RuntimeException cuando el archivo no es válido.
     */
    public static function guardar(?array $archivo, string $carpeta, int $tamanoMaximo = 0): array
    {
        if ($tamanoMaximo <= 0) {
            $tamanoMaximo = self::tamanoMaximo();
        }

        if ($archivo === null) {
            throw new RuntimeException('No se recibió ningún archivo.');
        }

        $codigo = (int) ($archivo['error'] ?? UPLOAD_ERR_NO_FILE);

        if ($codigo === UPLOAD_ERR_NO_FILE) {
            throw new RuntimeException('No se ha seleccionado ningún archivo.');
        }

        if ($codigo !== UPLOAD_ERR_OK) {
            throw new RuntimeException('No se pudo subir el archivo. Intente nuevamente.');
        }

        $tamano = (int) ($archivo['size'] ?? 0);

        if ($tamano <= 0) {
            throw new RuntimeException('El archivo está vacío.');
        }

        if ($tamano > $tamanoMaximo) {
            throw new RuntimeException(
                'El archivo supera el máximo de ' . (int) round($tamanoMaximo / 1048576) . ' MB.'
            );
        }

        $original = (string) ($archivo['name'] ?? 'archivo');
        $extension = strtolower((string) pathinfo($original, PATHINFO_EXTENSION));
        $permitidas = self::extensionesPermitidas();

        if (!in_array($extension, $permitidas, true)) {
            throw new RuntimeException(
                'El tipo de archivo no está permitido (PDF, imágenes o documentos de Office).'
            );
        }

        $temporal = (string) ($archivo['tmp_name'] ?? '');

        if ($temporal === '' || !is_uploaded_file($temporal)) {
            throw new RuntimeException('La carga del archivo no es válida.');
        }

        $mime = self::mimeReal($temporal) ?? (string) ($archivo['type'] ?? (self::TIPOS[$extension] ?? 'application/octet-stream'));
        $permitidos = self::mimesPermitidos();

        if ($permitidos !== [] && !in_array($mime, $permitidos, true)) {
            throw new RuntimeException('El contenido del archivo no corresponde a un tipo permitido.');
        }

        $carpeta = trim(str_replace(['..', '\\'], ['', '/'], $carpeta), '/');
        $directorio = storage_path('uploads/' . $carpeta);

        if (!is_dir($directorio) && !mkdir($directorio, 0750, true) && !is_dir($directorio)) {
            throw new RuntimeException('No se pudo preparar la carpeta de archivos.');
        }

        $nombre = bin2hex(random_bytes(16)) . '.' . $extension;
        $destino = $directorio . DIRECTORY_SEPARATOR . $nombre;

        if (!move_uploaded_file($temporal, $destino)) {
            throw new RuntimeException('No se pudo guardar el archivo en el servidor.');
        }

        @chmod($destino, 0640);

        // Las imágenes se convierten a WebP y se redimensionan para que pesen
        // menos. Si el servidor no tiene GD con WebP, o si la conversión no
        // ahorra peso, se conserva el archivo original.
        $procesada = self::procesarImagen($destino, $extension, $tamano);

        if ($procesada !== null) {
            @unlink($destino);

            return [
                'ruta'            => $carpeta . '/' . $procesada['nombre'],
                'nombre_original' => $original,
                'tipo_mime'       => 'image/webp',
                'tamano'          => $procesada['tamano'],
            ];
        }

        return [
            'ruta'            => $carpeta . '/' . $nombre,
            'nombre_original' => $original,
            'tipo_mime'       => $mime,
            'tamano'          => $tamano,
        ];
    }

    /**
     * Convierte una imagen a WebP y la redimensiona.
     *
     * Si el resultado no es más ligero que el original, devuelve `null` y el
     * archivo original se conserva.
     *
     * @return array{nombre: string, tamano: int}|null
     */
    public static function procesarImagen(string $ruta, string $extension, int $tamanoOriginal = 0): ?array
    {
        if (!function_exists('imagewebp') || !function_exists('getimagesize')) {
            return null;
        }

        // Los GIF animados se conservan: GD no conserva la animación.
        if ($extension === 'gif') {
            return null;
        }

        $info = @getimagesize($ruta);

        if ($info === false || empty($info[0]) || empty($info[1])) {
            return null;
        }

        $ancho = (int) $info[0];
        $alto = (int) $info[1];
        $tipo = (int) $info[2];

        $origen = match ($tipo) {
            IMAGETYPE_JPEG => function_exists('imagecreatefromjpeg') ? @imagecreatefromjpeg($ruta) : false,
            IMAGETYPE_PNG  => function_exists('imagecreatefrompng') ? @imagecreatefrompng($ruta) : false,
            IMAGETYPE_WEBP => function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($ruta) : false,
            default        => false,
        };

        if ($origen === false) {
            return null;
        }

        $maximo = self::ANCHO_MAXIMO;

        if ($ancho > $maximo) {
            $nuevoAncho = $maximo;
            $nuevoAlto = (int) max(1, round($alto * ($maximo / $ancho)));
            $redimensionada = imagecreatetruecolor($nuevoAncho, $nuevoAlto);

            if ($redimensionada !== false) {
                imagealphablending($redimensionada, false);
                imagesavealpha($redimensionada, true);
                $transparent = imagecolorallocatealpha($redimensionada, 0, 0, 0, 127);
                imagefilledrectangle($redimensionada, 0, 0, $nuevoAncho, $nuevoAlto, $transparent);
                imagecopyresampled($redimensionada, $origen, 0, 0, 0, 0, $nuevoAncho, $nuevoAlto, $ancho, $alto);
                imagedestroy($origen);
                $origen = $redimensionada;
            }
        }

        $nombre = preg_replace('/\.[A-Za-z0-9]+$/', '.webp', basename($ruta)) ?: 'imagen.webp';
        $destino = dirname($ruta) . DIRECTORY_SEPARATOR . $nombre;

        if (!imagewebp($origen, $destino, self::CALIDAD_WEBP)) {
            imagedestroy($origen);

            return null;
        }

        imagedestroy($origen);
        @chmod($destino, 0640);

        $tamanoNuevo = (int) filesize($destino);

        // Si la conversión no ahorra peso, se conserva el original.
        if ($tamanoOriginal > 0 && $tamanoNuevo >= $tamanoOriginal) {
            @unlink($destino);

            return null;
        }

        return ['nombre' => $nombre, 'tamano' => $tamanoNuevo];
    }

    /**
     * Traduce la ruta relativa guardada a una ruta segura del disco.
     */
    public static function rutaAbsoluta(string $ruta, string $prefijo = ''): ?string
    {
        $ruta = str_replace(['..', "\0"], '', $ruta);
        $base = storage_path('uploads');

        if ($prefijo !== '' && !str_starts_with($ruta, $prefijo)) {
            return null;
        }

        return $base . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $ruta);
    }

    /**
     * Borra un archivo guardado. No hace nada si la ruta no es válida.
     */
    public static function borrar(string $ruta, string $prefijo = ''): void
    {
        $absoluta = self::rutaAbsoluta($ruta, $prefijo);

        if ($absoluta !== null && is_file($absoluta)) {
            @unlink($absoluta);
        }
    }

    public static function esImagen(?string $mime): bool
    {
        if ($mime === null) {
            return false;
        }

        return in_array($mime, ['image/jpeg', 'image/png', 'image/gif', 'image/webp'], true);
    }

    private static function mimeReal(string $ruta): ?string
    {
        if (!function_exists('finfo_open')) {
            return null;
        }

        $finfo = finfo_open(FILEINFO_MIME_TYPE);

        if ($finfo === false) {
            return null;
        }

        $mime = finfo_file($finfo, $ruta);
        finfo_close($finfo);

        return is_string($mime) ? $mime : null;
    }
}

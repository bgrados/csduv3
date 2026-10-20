<?php

declare(strict_types=1);

use App\Core\Uploads;

/*
 |--------------------------------------------------------------------------
 | CSDUV3 - Instalador de noticias y negocios de demostración
 |--------------------------------------------------------------------------
 |
 | Copia las imágenes de ejemplo que están en `public/assets/img/demo` a
 | `storage/uploads` y registra las noticias y los negocios de muestra para
 | poder ver cómo queda la portada.
 |
 | Uso (solo desde la Terminal / SSH):
 |     php tools/instalar_demo_fotos.php            # instala
 |     php tools/instalar_demo_fotos.php --quitar   # desinstala
 |
 | IMPORTANTE
 | ----------
 | Los datos que instala son FICTICIOS y están marcados con el prefijo DEMO-.
 | No pertenecen al club. Sirven únicamente para revisar el diseño de la
 | portada. Cuando el club tenga sus propias fotos y negocios, se desinstalan
 | con la opción `--quitar` y se cargan los reales desde el panel.
 */

if (PHP_SAPI !== 'cli') {
    if (!headers_sent()) {
        http_response_code(404);
    }

    exit('No disponible.');
}

$appPath = dirname(__DIR__);

require $appPath . '/app/Core/Autoloader.php';

\App\Core\Autoloader::register();
\App\Core\Env::load($appPath . '/.env');
\App\Core\Config::load($appPath . '/config');

date_default_timezone_set((string) \App\Core\Config::get('app.timezone', 'America/Lima'));

use App\Core\Database;
use App\Models\Foto;
use App\Models\Negocio;

/**
 * Escribe una línea en la salida estándar.
 */
function informar(string $mensaje = ''): void
{
    fwrite(STDOUT, $mensaje . PHP_EOL);
}

function abortar(string $mensaje): never
{
    fwrite(STDERR, 'Error: ' . $mensaje . PHP_EOL);
    exit(1);
}

$quitar = in_array('--quitar', $argv, true);

if (!Database::isAvailable()) {
    abortar('no se pudo conectar a la base de datos. Revise el archivo .env.');
}

$pdo = Database::connection();
$prefijo = (string) \App\Core\Config::get('database.prefix', 'csduv3_');

foreach (['fotos', 'negocios'] as $tabla) {
    $existe = (int) $pdo->query(
        sprintf(
            "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = '%s%s'",
            $prefijo,
            $tabla
        )
    )->fetchColumn();

    if ($existe === 0) {
        abortar(sprintf(
            'la tabla `%s%s` no existe. Aplique primero las migraciones: php tools/migrar.php',
            $prefijo,
            $tabla
        ));
    }
}

$carpetaDestino = storage_path('uploads/demo');
$origen = $appPath . '/public/assets/img/demo';

// ---------------------------------------------------------------------------
//  Retiro
// ---------------------------------------------------------------------------

if ($quitar) {
    $pdo->beginTransaction();

    try {
        $pdo->exec(sprintf("DELETE FROM `%s%s` WHERE `titulo` LIKE 'DEMO-%%'", $prefijo, 'fotos'));
        $pdo->exec(sprintf("DELETE FROM `%s%s` WHERE `nombre` LIKE 'DEMO-%%'", $prefijo, 'negocios'));
        $pdo->commit();
    } catch (Throwable $exception) {
        $pdo->rollBack();
        abortar('no se pudieron borrar los datos de demostración: ' . $exception->getMessage());
    }

    foreach (['demo', 'fotos', 'negocios'] as $carpeta) {
        $ruta = storage_path('uploads/' . $carpeta);

        if (!is_dir($ruta)) {
            continue;
        }

        foreach (glob($ruta . '/demo-*') ?: [] as $archivo) {
            if (is_file($archivo)) {
                @unlink($archivo);
            }
        }
    }

    informar('Se retiraron las noticias y los negocios de demostración.');
    exit(0);
}

// ---------------------------------------------------------------------------
//  Instalación
// ---------------------------------------------------------------------------

if (!is_dir($carpetaDestino) && !mkdir($carpetaDestino, 0750, true) && !is_dir($carpetaDestino)) {
    abortar('no se pudo preparar la carpeta de imágenes de demostración');
}

/**
 * Copia una imagen de ejemplo y devuelve la ruta relativa para la base de datos.
 *
 * @param string $prefijo Carpeta dentro de storage/uploads donde se guarda
 *                           (debe coincidir con la que usa el portal).
 */
function copiarEjemplo(string $origen, string $prefijo, string $archivo): ?array
{
    $rutaOrigen = $origen . '/' . $archivo;

    if (!is_file($rutaOrigen)) {
        return null;
    }

    $carpeta = storage_path('uploads/' . $prefijo);

    if (!is_dir($carpeta) && !mkdir($carpeta, 0750, true) && !is_dir($carpeta)) {
        return null;
    }

    $nombre = 'demo-' . pathinfo($archivo, PATHINFO_FILENAME) . '.webp';
    $destino = $carpeta . DIRECTORY_SEPARATOR . $nombre;

    if (!copy($rutaOrigen, $destino)) {
        return null;
    }

    @chmod($destino, 0640);

    $informacion = @getimagesize($destino);
    $mime = is_array($informacion) && !empty($informacion['mime']) ? (string) $informacion['mime'] : 'image/webp';

    return [
        'ruta'            => $prefijo . '/' . $nombre,
        'nombre_original' => $archivo,
        'tipo_mime'       => $mime,
        'tamano'          => (int) filesize($destino),
    ];
}

$noticias = [
    ['titulo' => 'DEMO- Asamblea general de socios', 'categoria' => 'reunion', 'resumen' => 'Los socios del club se reunieron en asamblea.', 'foto' => 'asamblea-socios.webp'],
    ['titulo' => 'DEMO- Campeonato interno de fulbito', 'categoria' => 'campeonato', 'resumen' => 'Final del torneo de fútbol fulbito del club.', 'foto' => 'campeonato-fulbito.webp'],
    ['titulo' => 'DEMO- Junta directiva del periodo', 'categoria' => 'directiva', 'resumen' => 'Presentación de la junta directiva y su plan de trabajo.', 'foto' => 'junta-directiva.webp'],
    ['titulo' => 'DEMO- Encuentros deportivos de la unidad', 'categoria' => 'deportiva', 'resumen' => 'Participación del club en los MahaRank de la unidad.', 'foto' => 'encuentros-deportivos.webp'],
    ['titulo' => 'DEMO- Actividad de la comunidad', 'categoria' => 'socios', 'resumen' => 'Encuentro de socios y familiares en las instalaciones del club.', 'foto' => 'actividad-socios.webp'],
    ['titulo' => 'DEMO- Mantenimiento de las instalaciones', 'categoria' => 'instalacion', 'resumen' => 'Trabajos de mantenimiento de los ambientes del club.', 'foto' => 'mantenimiento-instalaciones.webp'],
];

$negocios = [
    ['nombre' => 'DEMO- Panadería San Martín', 'categoria' => 'comercio', 'descripcion' => 'Pan y pastelería del barrio.', 'telefono' => '999 000 001', 'web' => 'https://uv3-platform.vercel.app/', 'direccion' => 'Av. Siempre Viva 742', 'horario' => 'Lunes a sábado, 7:00 a 19:00', 'foto' => 'negocios/panaderia-san-martin.webp'],
    ['nombre' => 'DEMO- Farmacia Vida', 'categoria' => 'salud', 'descripcion' => 'Atención primaria y medicamentos.', 'telefono' => '999 000 002', 'web' => 'https://uv3-platform.vercel.app/', 'direccion' => 'Jirón Unión 120', 'horario' => 'Lunes a sábado, 8:00 a 20:00', 'foto' => 'negocios/farmacia-vida.webp'],
    ['nombre' => 'DEMO- Librería La Plaza', 'categoria' => 'comercio', 'descripcion' => 'Libros, útiles escolares y papelería.', 'telefono' => '999 000 003', 'web' => 'https://uv3-platform.vercel.app/', 'direccion' => 'Calle Las Begonias 15', 'horario' => 'Lunes a sábado, 9:00 a 18:00', 'foto' => 'negocios/libreria-la-plaza.webp'],
    ['nombre' => 'DEMO- Peluquería Estilo', 'categoria' => 'servicio', 'descripcion' => 'Corte de pelo, color y cuidado personal.', 'telefono' => '999 000 004', 'web' => 'https://uv3-platform.vercel.app/', 'direccion' => 'Av. Los Álamos 233', 'horario' => 'Martes a sábado, 9:00 a 19:00', 'foto' => 'negocios/peluqueria-estilo.webp'],
    ['nombre' => 'DEMO- Café Vecinal', 'categoria' => 'comercio', 'descripcion' => 'Café de la unidad y pastelería artesanal.', 'telefono' => '999 000 005', 'web' => 'https://uv3-platform.vercel.app/', 'direccion' => 'Plaza Mayor, local 4', 'horario' => 'Todos los días, 7:00 a 21:00', 'foto' => 'negocios/cafe-vecinal.webp'],
];

$insertadas = 0;

// --- Noticias ----------------------------------------------------------------

foreach ($noticias as $indice => $noticia) {
    $foto = copiarEjemplo($origen, 'fotos', (string) $noticia['foto']);

    $pdo->prepare(
        sprintf(
            'INSERT INTO `%s%s`
                (`titulo`, `resumen`, `descripcion`, `categoria`, `archivo`, `nombre_original`,
                 `tipo_mime`, `tamano_bytes`, `fecha_foto`, `visible`, `destacado`, `orden`)
             VALUES
                (:titulo, :resumen, :descripcion, :categoria, :archivo, :nombre_original,
                 :tipo_mime, :tamano, :fecha, 1, :destacado, :orden)'
        , $prefijo, 'fotos')
    )->execute([
        'titulo'          => $noticia['titulo'],
        'resumen'         => $noticia['resumen'],
        'descripcion'     => 'Registro de demostración. No corresponde a una actividad real del club.',
        'categoria'       => $noticia['categoria'],
        'archivo'         => $foto === null ? '' : $foto['ruta'],
        'nombre_original' => $foto === null ? '' : $foto['nombre_original'],
        'tipo_mime'       => $foto === null ? '' : $foto['tipo_mime'],
        'tamano'          => $foto === null ? 0 : $foto['tamano'],
        'fecha'           => date('Y-m-d', strtotime('-' . (($indice + 1) * 8) . ' days')),
        'destacado'       => $indice === 0 ? 1 : 0,
        'orden'           => $indice + 1,
    ]);

    $insertadas++;
}

// --- Negocios ---------------------------------------------------------------

foreach ($negocios as $indice => $negocio) {
    $foto = copiarEjemplo($origen, 'negocios', (string) $negocio['foto']);

    Negocio::crear([
        'nombre'          => $negocio['nombre'],
        'categoria'       => $negocio['categoria'],
        'descripcion'     => $negocio['descripcion'] . ' (dato de demostración).',
        'telefono'        => $negocio['telefono'],
        'whatsapp'        => null,
        'email'           => null,
        'web'             => $negocio['web'],
        'direccion'       => $negocio['direccion'],
        'horario'         => $negocio['horario'],
        'archivo'         => $foto === null ? '' : $foto['ruta'],
        'nombre_original' => $foto === null ? '' : $foto['nombre_original'],
        'tipo_mime'       => $foto === null ? '' : $foto['tipo_mime'],
        'tamano_bytes'    => $foto === null ? 0 : $foto['tamano'],
        'visible'         => 1,
        'destacado'       => $indice < 3 ? 1 : 0,
        'orden'           => $indice + 1,
        'creado_por'      => 0,
    ]);

    $insertadas++;
}

informar();
informar('CSDUV3 · Datos de demostración');
informar('======================================');
informar();
informar(sprintf('Se registraron %d elementos de ejemplo (noticias y negocios).', $insertadas));
informar('Los titulares y nombres empiezan por DEMO- y se pueden retirar con:');
informar('    php tools/instalar_demo_fotos.php --quitar');

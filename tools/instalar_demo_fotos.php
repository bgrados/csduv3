<?php

declare(strict_types=1);

use App\Core\Database;
use App\Models\Foto;
use App\Models\Negocio;

/*
 |--------------------------------------------------------------------------
 | CSDUV3 - Instalador de fotos y negocios de demostración
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
 | portada y de la galería. Cuando el club tenga sus propias fotos, se
 | desinstalan con la opción `--quitar` y se suben las reales desde el panel.
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

$archivos = glob($origen . '/*.jpg') ?: [];
sort($archivos);

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

    if (is_dir($carpetaDestino)) {
        foreach (glob($carpetaDestino . '/*') ?: [] as $archivo) {
            if (is_file($archivo)) {
                @unlink($archivo);
            }
        }
    }

    informar('Se retiraron las noticias y los negocios de demostración.');
    exit(0);
}

if ($archivos === []) {
    abortar('no se encontraron imágenes de ejemplo en public/assets/img/demo');
}

if (!is_dir($carpetaDestino) && !mkdir($carpetaDestino, 0750, true) && !is_dir($carpetaDestino)) {
    abortar('no se pudo preparar la carpeta de imágenes de demostración');
}

$noticias = [
    ['titulo' => 'DEMO- Asamblea general de socios', 'categoria' => 'reunion', 'resumen' => 'Los socios del club se reunieron en asamblea.'],
    ['titulo' => 'DEMO- Campeonato interno de fulbito', 'categoria' => 'campeonato', 'resumen' => 'Final del torneo de fútbol fulbito del club.'],
    ['titulo' => 'DEMO- Junta directiva del periodo', 'categoria' => 'directiva', 'resumen' => 'Presentación de la junta directiva y su plan de trabajo.'],
    ['titulo' => 'DEMO- Encuentros deportivos de la unidad', 'categoria' => 'deportiva', 'resumen' => 'Participacion del club en los MahaRank de la unidad.'],
    ['titulo' => 'DEMO- Actividad de la comunidad', 'categoria' => 'socios', 'resumen' => 'Encuentro de socios y familiares en las instalaciones del club.'],
    ['titulo' => 'DEMO- Mantenimiento de las instalaciones', 'categoria' => 'instalacion', 'resumen' => 'Trabajos de mantenimiento de los ambientes del club.'],
];

$negocios = [
    ['nombre' => 'DEMO- Panadería San Martín', 'categoria' => 'comercio', 'descripcion' => 'Pan y pastelería del barrio.', 'telefono' => '999 000 001', 'web' => 'https://uv3-platform.vercel.app/', 'direccion' => 'Av. Siempre Viva 742', 'horario' => 'Lunes a sábado, 7:00 a 19:00'],
    ['nombre' => 'DEMO- Farmacia Vida', 'categoria' => 'salud', 'descripcion' => 'Atención primaria y medicamentos.', 'telefono' => '999 000 002', 'web' => 'https://uv3-platform.vercel.app/', 'direccion' => 'Jirón Unión 120', 'horario' => 'Lunes a sábado, 8:00 a 20:00'],
    ['nombre' => 'DEMO- Librería La Plaza', 'categoria' => 'comercio', 'descripcion' => 'Libros, útiles escolares y papelería.', 'telefono' => '999 000 003', 'direccion' => 'Calle Las Begonias 15', 'horario' => 'Lunes a sábado, 9:00 a 18:00'],
];

$insertadas = 0;
$carpetas = [];

foreach ($archivos as $indice => $archivo) {
    $noticia = $noticias[$indice] ?? [
        'titulo' => 'DEMO- Noticia de ejemplo ' . ($indice + 1),
        'categoria' => 'evento',
        'resumen' => 'Contenido de demostración para revisar el diseño de la portada.',
    ];

    $nombre = 'demo-noticia-' . ($indice + 1) . '.jpg';
    $destino = $carpetaDestino . DIRECTORY_SEPARATOR . $nombre;

    if (!copy($archivo, $destino)) {
        abortar('no se pudo copiar la imagen ' . basename($archivo));
    }

    @chmod($destino, 0640);
    $carpetas[] = $destino;

    $pdo->prepare(
        sprintf(
            'INSERT INTO `%s%s`
                (`titulo`, `resumen`, `descripcion`, `categoria`, `archivo`, `nombre_original`,
                 `tipo_mime`, `tamano_bytes`, `fecha_foto`, `visible`, `destacado`, `orden`)
             VALUES
                (:titulo, :resumen, :descripcion, :categoria, :archivo, :nombre_original,
                 \'image/jpeg\', :tamano, :fecha, 1, :destacado, :orden)'
        , $prefijo, 'fotos')
    )->execute([
        'titulo'          => $noticia['titulo'],
        'resumen'         => $noticia['resumen'],
        'descripcion'     => 'Registro de demostración. No corresponde a una actividad real del club.',
        'categoria'       => $noticia['categoria'],
        'archivo'         => 'demo/' . $nombre,
        'nombre_original' => basename($archivo),
        'tamano'          => filesize($destino),
        'fecha'           => date('Y-m-d', strtotime('-' . ($indice * 9) . ' days')),
        'destacado'       => $indice < 3 ? 1 : 0,
        'orden'           => $indice + 1,
    ]);

    $insertadas++;
}

foreach ($negocios as $indice => $negocio) {
    Negocio::crear([
        'nombre'      => $negocio['nombre'],
        'categoria'   => $negocio['categoria'],
        'descripcion' => $negocio['descripcion'] . ' (dato de demostración).',
        'telefono'    => $negocio['telefono'],
        'whatsapp'    => null,
        'email'       => null,
        'web'         => $negocio['web'] ?? null,
        'direccion'   => $negocio['direccion'] ?? null,
        'horario'     => $negocio['horario'] ?? null,
        'archivo'     => '',
        'visible'     => 1,
        'destacado'   => $indice < 2 ? 1 : 0,
        'orden'       => $indice + 1,
        'creado_por'  => 0,
    ]);

    $insertadas++;
}

informar();
informar('CSDUV3 · Datos de demostración');
informar('======================================');
informar();
informar(sprintf('Se registraron %d elementos de ejemplo (noticias y negocios).', $insertadas));
informar('Los titulares empiezan por DEMO- y se pueden retirar con:');
informar('    php tools/instalar_demo_fotos.php --quitar');

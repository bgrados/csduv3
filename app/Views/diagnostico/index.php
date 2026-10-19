<?php

declare(strict_types=1);

use App\Core\Icons;

/**
 * Página de diagnóstico del sistema (solo entorno de desarrollo).
 *
 * Todos los valores mostrados se obtienen en tiempo de ejecución.
 *
 * @var string $phpVersion
 * @var bool   $phpVersionOk
 * @var string $sapi
 * @var string $entorno
 * @var bool   $debug
 * @var string $zonaHoraria
 * @var string $fechaHoraActual
 * @var array<int, array{nombre: string, descripcion: string, cargada: bool, opcional: bool}> $extensiones
 * @var bool        $dbConfigurada
 * @var bool        $dbDisponible
 * @var string|null $dbError
 * @var string|null $dbVersion
 * @var array{creadas: int, faltantes: array<int, string>, total: int} $tablas
 * @var array<string, string>  $rutas
 * @var array<string, bool>    $permisosEscritura
 * @var bool        $envPresente
 * @var string      $logPath
 * @var bool        $https
 * @var string      $appUrl
 * @var string      $basePath
 */

$clase = static fn (bool $ok, bool $opcional = false): string
    => $ok ? 'badge--ok' : ($opcional ? 'badge--warn' : 'badge--danger');
?>
<section class="page-head">
    <div>
        <p class="page-head__eyebrow">Solo entorno de desarrollo</p>
        <h2>Diagnóstico del sistema</h2>
        <p class="page-head__lead">
            Comprobaciones ejecutadas en este servidor. Esta página no está disponible
            cuando <code>APP_ENV=production</code>.
        </p>
    </div>
    <div class="page-head__actions">
        <a class="btn btn--ghost" href="<?= e(url('/diagnostico')) ?>">
            <?= Icons::render('refrescar') ?>
            <span>Volver a comprobar</span>
        </a>
    </div>
</section>

<h3 class="section-title">Entorno de ejecución</h3>
<div class="card">
    <table class="table table--compact">
        <tbody>
            <tr>
                <th scope="row">Versión de PHP</th>
                <td>
                    <?= e($phpVersion) ?>
                    <span class="badge <?= e($clase($phpVersionOk)) ?>">
                        <?= $phpVersionOk ? 'compatible' : 'se requiere 8.3 o superior' ?>
                    </span>
                </td>
            </tr>
            <tr><th scope="row">Interfaz de servidor (SAPI)</th><td><?= e($sapi) ?></td></tr>
            <tr><th scope="row">Entorno de la aplicación</th><td><?= e($entorno) ?></td></tr>
            <tr>
                <th scope="row">Errores detallados</th>
                <td><span class="badge <?= e($debug ? 'badge--warn' : 'badge--ok') ?>"><?= $debug ? 'activados' : 'ocultos' ?></span></td>
            </tr>
            <tr><th scope="row">Zona horaria</th><td><?= e($zonaHoraria) ?></td></tr>
            <tr><th scope="row">Fecha y hora del servidor</th><td><?= e($fechaHoraActual) ?></td></tr>
            <tr><th scope="row">URL configurada</th><td><?= e($appUrl) ?></td></tr>
            <tr><th scope="row">Ruta base detectada</th><td><code><?= e($basePath === '' ? '(raíz)' : $basePath) ?></code></td></tr>
            <tr>
                <th scope="row">Conexión HTTPS</th>
                <td><span class="badge <?= e($clase($https)) ?>"><?= $https ? 'sí' : 'no' ?></span></td>
            </tr>
            <tr>
                <th scope="row">Archivo .env</th>
                <td><span class="badge <?= e($clase($envPresente)) ?>"><?= $envPresente ? 'presente' : 'ausente' ?></span></td>
            </tr>
        </tbody>
    </table>
</div>

<h3 class="section-title">Extensiones de PHP</h3>
<div class="card">
    <table class="table table--compact">
        <thead>
            <tr><th scope="col">Extensión</th><th scope="col">Para qué se usa</th><th scope="col">Estado</th></tr>
        </thead>
        <tbody>
            <?php foreach ($extensiones as $extension): ?>
                <tr>
                    <td><code><?= e($extension['nombre']) ?></code></td>
                    <td><?= e($extension['descripcion']) ?></td>
                    <td>
                        <span class="badge <?= e($clase($extension['cargada'], $extension['opcional'])) ?>">
                            <?= $extension['cargada'] ? 'cargada' : ($extension['opcional'] ? 'no cargada (opcional)' : 'no cargada') ?>
                        </span>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<h3 class="section-title">Base de datos</h3>
<div class="card">
    <table class="table table--compact">
        <tbody>
            <tr>
                <th scope="row">Parámetros configurados</th>
                <td><span class="badge <?= e($clase($dbConfigurada)) ?>"><?= $dbConfigurada ? 'sí' : 'no' ?></span></td>
            </tr>
            <tr>
                <th scope="row">Conexión</th>
                <td>
                    <span class="badge <?= e($clase($dbDisponible)) ?>"><?= $dbDisponible ? 'correcta' : 'no disponible' ?></span>
                    <?php if ($dbVersion !== null): ?>
                        <small class="muted">Servidor: <?= e($dbVersion) ?></small>
                    <?php endif; ?>
                </td>
            </tr>
            <?php if (!$dbDisponible && $dbError !== null): ?>
                <tr>
                    <th scope="row">Detalle del error</th>
                    <td><code class="code-block"><?= e($dbError) ?></code></td>
                </tr>
            <?php endif; ?>
            <tr>
                <th scope="row">Tablas del esquema</th>
                <td>
                    <span class="badge <?= e($clase($tablas['creadas'] === $tablas['total'])) ?>">
                        <?= e((string) $tablas['creadas']) ?> de <?= e((string) $tablas['total']) ?>
                    </span>
                </td>
            </tr>
        </tbody>
    </table>

    <?php if ($tablas['faltantes'] !== []): ?>
        <details class="details">
            <summary>Ver tablas pendientes (<?= e((string) count($tablas['faltantes'])) ?>)</summary>
            <ul class="plain-list">
                <?php foreach ($tablas['faltantes'] as $tabla): ?>
                    <li><code><?= e($tabla) ?></code></li>
                <?php endforeach; ?>
            </ul>
        </details>
    <?php endif; ?>
</div>

<h3 class="section-title">Rutas y permisos de escritura</h3>
<div class="card">
    <table class="table table--compact">
        <tbody>
            <?php foreach ($rutas as $nombre => $ruta): ?>
                <tr>
                    <th scope="row"><?= e(ucfirst($nombre)) ?></th>
                    <td><code><?= e($ruta) ?></code></td>
                </tr>
            <?php endforeach; ?>
            <tr>
                <th scope="row">Archivo de registro</th>
                <td><code><?= e($logPath) ?></code></td>
            </tr>
        </tbody>
    </table>

    <table class="table table--compact">
        <thead>
            <tr><th scope="col">Carpeta</th><th scope="col">Escritura</th></tr>
        </thead>
        <tbody>
            <?php foreach ($permisosEscritura as $carpeta => $ok): ?>
                <tr>
                    <td><code><?= e($carpeta) ?></code></td>
                    <td><span class="badge <?= e($clase($ok)) ?>"><?= $ok ? 'permitida' : 'denegada' ?></span></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<?php

declare(strict_types=1);

use App\Core\Icons;

/**
 * Portada del portal con formato de revista.
 *
 * @var bool  $dbDisponible
 * @var array<int, array<string, mixed>> $instalaciones
 * @var array<string, string> $configuracion
 * @var array<int, array<string, mixed>> $noticias
 * @var array<int, array<string, mixed>> $galeria
 * @var array<int, array<string, mixed>> $negocios
 * @var array<int, array<string, mixed>> $eventos
 * @var array<int, array<string, mixed>> $partidos
 * @var bool $contenidoEjemplo
 */

$telefono = trim((string) ($configuracion['telefono'] ?? ''));
$correo = trim((string) ($configuracion['email_institucional'] ?? ''));
$direccion = trim((string) ($configuracion['direccion'] ?? ''));
$visibles = array_slice($instalaciones, 0, 6);

$nombreClub = (string) config('app.name');
$siglas = (string) config('app.short_name');
$meses = [
    1 => 'enero', 'febrero', 'marzo', 'abril', 'mayo', 'junio',
    'julio', 'agosto', 'setiembre', 'octubre', 'noviembre', 'diciembre',
];
$fechaEdicion = (int) date('j') . ' de ' . $meses[(int) date('n')] . ' de ' . date('Y');

// URL de la imagen de una noticia: la del portal si viene de la base de datos,
// o el archivo estático de ejemplo.
$imagen = static function (array $noticia): string {
    $id = (int) ($noticia['id'] ?? 0);

    if ($id > 0 && !empty($noticia['archivo'])) {
        return url('/galeria/imagen/' . (string) $id);
    }

    return asset((string) ($noticia['archivo'] ?? ''));
};

$portada = $noticias[0] ?? null;
$resto = array_slice($noticias, 1);
$destacados = array_merge($resto, $galeria);
$destacados = array_slice($destacados, 0, 8);
$ejemplos = static function (array $elemento): bool {
    return (bool) ($elemento['ejemplo'] ?? false);
};

$tiposEvento = [
    'institucional' => 'Institucional',
    'directiva'      => 'Junta directiva',
    'eleccion'       => 'Elecciones',
    'deportiva'      => 'Deportiva',
    'social'         => 'Social',
    'mantenimiento'  => 'Mantenimiento',
];

$categoriasNegocio = [
    'comercio'    => 'Comercio',
    'servicio'    => 'Servicio',
    'profesional' => 'Profesional',
    'salud'       => 'Salud',
    'educacion'   => 'Educación',
    'otro'        => 'Otro',
];
?>

<section class="revista">
    <header class="revista__cabecera">
        <div class="revista__cabecera-fila">
            <span><?= e($fechaEdicion) ?></span>
            <span class="revista__edicion">Edición de la Unidad Vecinal N.º 3</span>
            <span class="revista__cabecera-enlace">
                <a href="<?= e(url('/ingresar')) ?>">Ingresar</a>
            </span>
        </div>

        <div class="revista__masthead">
            <h1 class="revista__titulo"><?= e($siglas) ?></h1>
            <p class="revista__lema"><?= e($nombreClub) ?></p>
        </div>

        <div class="revista__acciones">
            <a class="btn btn--primary btn--sm" href="<?= e(url('/alquiler')) ?>">Ambientes y tarifas</a>
            <a class="btn btn--ghost btn--sm" href="<?= e(url('/alquiler/solicitud')) ?>">Solicitar alquiler</a>
            <a class="btn btn--ghost btn--sm" href="<?= e(url('/torneos')) ?>">Torneos</a>
            <a class="btn btn--ghost btn--sm" href="<?= e(url('/galeria')) ?>">Galería</a>
            <a class="btn btn--ghost btn--sm" href="<?= e(url('/negocios')) ?>">Negocios</a>
            <a class="btn btn--ghost btn--sm" href="https://uv3-platform.vercel.app/" target="_blank" rel="noopener">
                Portal UV3
            </a>
        </div>
    </header>

    <?php if (!$dbDisponible): ?>
        <div class="alert alert--warning" role="status">
            <p>El portal está temporalmente en mantenimiento. Vuelva a intentarlo en unos minutos.</p>
        </div>
    <?php endif; ?>

    <div class="revista__cuerpo">
        <div class="revista__principal">
            <?php if ($portada !== null): ?>
                <article class="portada">
                    <a class="portada__imagen" href="<?= e(url('/galeria')) ?>">
                        <img src="<?= e($imagen($portada)) ?>" alt="<?= e((string) $portada['titulo']) ?>">
                    </a>
                    <div class="portada__texto">
                        <p class="portada__kicker">
                            <?= e(\App\Models\Foto::CATEGORIAS[(string) $portada['categoria']] ?? (string) $portada['categoria']) ?>
                            <?php if (!empty($portada['fecha_foto'])): ?>
                                · <?= e(fecha_legible((string) $portada['fecha_foto'])) ?>
                            <?php endif; ?>
                            <?php if ($ejemplos($portada)): ?>
                                <span class="badge badge--soft">Ejemplo</span>
                            <?php endif; ?>
                        </p>
                        <h2 class="portada__titular"><?= e((string) $portada['titulo']) ?></h2>
                        <p class="portada__entradilla">
                            <?= e((string) ($portada['resumen'] ?? ($portada['descripcion'] ?? 'Fotografía de la actividad del club.'))) ?>
                        </p>
                        <a class="btn btn--primary btn--sm" href="<?= e(url('/galeria')) ?>">Ver la galería completa</a>
                    </div>
                </article>
            <?php else: ?>
                <div class="card">
                    <h2 class="card__value" style="font-size:1.15rem;">El club en imágenes</h2>
                    <p class="muted">
                        Todavía no hay noticias publicadas. Desde el panel puede subir las fotos de
                        las reuniones, los pneumaticos y la junta directiva.
                    </p>
                    <p><a class="btn btn--ghost btn--sm" href="<?= e(url('/galeria')) ?>">Ir a la galería</a></p>
                </div>
            <?php endif; ?>

            <?php if ($destacados !== []): ?>
                <section class="revista__seccion">
                    <div class="revista__seccion-cabecera">
                        <h2 class="revista__seccion-titulo">Noticias y galería</h2>
                        <a class="revista__seccion-enlace" href="<?= e(url('/galeria')) ?>">Ver todo &rarr;</a>
                    </div>

                    <div class="carrusel" data-carrusel>
                        <div class="carrusel__pista" data-carrusel-pista>
                            <?php foreach ($destacados as $noticia): ?>
                                <article class="carrusel__tarjeta">
                                    <a class="carrusel__imagen" href="<?= e(url('/galeria')) ?>">
                                        <img src="<?= e($imagen($noticia)) ?>" alt="<?= e((string) $noticia['titulo']) ?>" loading="lazy">
                                    </a>
                                    <p class="carrusel__kicker">
                                        <?= e(\App\Models\Foto::CATEGORIAS[(string) $noticia['categoria']] ?? (string) $noticia['categoria']) ?>
                                    </p>
                                    <h3 class="carrusel__titulo"><?= e((string) $noticia['titulo']) ?></h3>
                                    <?php if (!empty($noticia['resumen'])): ?>
                                        <p class="carrusel__resumen"><?= e((string) $noticia['resumen']) ?></p>
                                    <?php endif; ?>
                                    <?php if (!empty($noticia['fecha_foto'])): ?>
                                        <p class="carrusel__fecha"><?= e(fecha_legible((string) $noticia['fecha_foto'])) ?></p>
                                    <?php endif; ?>
                                </article>
                            <?php endforeach; ?>
                        </div>

                        <div class="carrusel__controles">
                            <button type="button" class="carrusel__boton" data-carrusel-prev aria-label="Anterior">&larr;</button>
                            <button type="button" class="carrusel__boton" data-carrusel-next aria-label="Siguiente">&rarr;</button>
                        </div>
                    </div>
                </section>
            <?php endif; ?>

            <section class="revista__seccion">
                <div class="revista__seccion-cabecera">
                    <h2 class="revista__seccion-titulo">Negocios de la unidad</h2>
                    <a class="revista__seccion-enlace" href="<?= e(url('/negocios')) ?>">Directorio completo &rarr;</a>
                </div>

                <?php if ($negocios === []): ?>
                    <div class="card">
                        <p class="muted">
                            El club está preparando el directorio de comercios y servicios de la
                            unidad vecinal.
                        </p>
                    </div>
                <?php else: ?>
                    <div class="carrusel" data-carrusel>
                        <div class="carrusel__pista" data-carrusel-pista>
                            <?php foreach ($negocios as $negocio): ?>
                                <article class="carrusel__tarjeta carrusel__tarjeta--negocio">
                                    <p class="carrusel__kicker">
                                        <?= e($categoriasNegocio[(string) $negocio['categoria']] ?? (string) $negocio['categoria']) ?>
                                    </p>
                                    <h3 class="carrusel__titulo"><?= e((string) $negocio['nombre']) ?></h3>
                                    <?php if (!empty($negocio['descripcion'])): ?>
                                        <p class="carrusel__resumen"><?= e((string) $negocio['descripcion']) ?></p>
                                    <?php endif; ?>
                                    <ul class="carrusel__datos">
                                        <?php if (!empty($negocio['telefono'])): ?>
                                            <li><?= e((string) $negocio['telefono']) ?></li>
                                        <?php endif; ?>
                                        <?php if (!empty($negocio['horario'])): ?>
                                            <li><?= e((string) $negocio['horario']) ?></li>
                                        <?php endif; ?>
                                    </ul>
                                    <?php if (!empty($negocio['web'])): ?>
                                        <a class="btn btn--ghost btn--sm" href="<?= e((string) $negocio['web']) ?>" target="_blank" rel="noopener nofollow">
                                            Visitar
                                        </a>
                                    <?php endif; ?>
                                </article>
                            <?php endforeach; ?>
                        </div>

                        <div class="carrusel__controles">
                            <button type="button" class="carrusel__boton" data-carrusel-prev aria-label="Anterior">&larr;</button>
                            <button type="button" class="carrusel__boton" data-carrusel-next aria-label="Siguiente">&rarr;</button>
                        </div>
                    </div>
                <?php endif; ?>
            </section>
        </div>

        <aside class="revista__columna">
            <section class="recuadro">
                <h2 class="recuadro__titulo">Próximos eventos</h2>

                <?php if ($eventos === []): ?>
                    <p class="muted">Todavía no hay eventos programados.</p>
                <?php else: ?>
                    <ul class="recuadro__lista">
                        <?php foreach ($eventos as $evento): ?>
                            <li class="recuadro__evento">
                                <span class="recuadro__fecha">
                                    <strong><?= e((string) (int) date('j', strtotime((string) $evento['fecha_inicio']))) ?></strong>
                                    <small><?= e(substr((string) $meses[(int) date('n', strtotime((string) $evento['fecha_inicio']))], 0, 3)) ?></small>
                                </span>
                                <span class="recuadro__evento-texto">
                                    <strong><?= e((string) $evento['titulo']) ?></strong>
                                    <small>
                                        <?= e($tiposEvento[(string) $evento['tipo']] ?? (string) $evento['tipo']) ?>
                                        <?php if (!empty($evento['lugar'])): ?>
                                            · <?= e((string) $evento['lugar']) ?>
                                        <?php endif; ?>
                                    </small>
                                    <?php if ($ejemplos($evento)): ?>
                                        <span class="badge badge--soft">Ejemplo</span>
                                    <?php endif; ?>
                                </span>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </section>

            <section class="recuadro">
                <h2 class="recuadro__titulo">Agenda deportiva</h2>

                <?php if ($partidos === []): ?>
                    <p class="muted">No hay partidos programados por ahora.</p>
                <?php else: ?>
                    <ul class="recuadro__lista">
                        <?php foreach ($partidos as $partido): ?>
                            <li class="recuadro__evento">
                                <span class="recuadro__fecha">
                                    <strong><?= e((string) (int) date('j', strtotime((string) $partido['fecha']))) ?></strong>
                                    <small><?= e(substr((string) $meses[(int) date('n', strtotime((string) $partido['fecha']))], 0, 3)) ?></small>
                                </span>
                                <span class="recuadro__evento-texto">
                                    <strong>
                                        <?= e((string) $partido['local_nombre']) ?>
                                        vs
                                        <?= e((string) $partido['visitante_nombre']) ?>
                                    </strong>
                                    <small>
                                        <?= e((string) $partido['torneo_nombre']) ?>
                                        <?php if (!empty($partido['hora'])): ?>
                                            · <?= e(substr((string) $partido['hora'], 0, 5)) ?>
                                        <?php endif; ?>
                                    </small>
                                    <?php if ($ejemplos($partido)): ?>
                                        <span class="badge badge--soft">Ejemplo</span>
                                    <?php endif; ?>
                                </span>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>

                <p class="recuadro__pie">
                    <a class="btn btn--ghost btn--sm" href="<?= e(url('/torneos')) ?>">Ver torneos y posiciones</a>
                </p>
            </section>

            <section class="recuadro recuadro--destacado">
                <h2 class="recuadro__titulo">Alquiler de ambientes</h2>
                <p class="muted">
                    Consulte los ambientes del club, las tarifas publicadas y la
                    disponibilidad por fecha. La reserva la confirma el club.
                </p>
                <p>
                    <a class="btn btn--primary btn--sm" href="<?= e(url('/alquiler')) ?>">Ver ambientes</a>
                    <a class="btn btn--ghost btn--sm" href="<?= e(url('/alquiler/solicitud')) ?>">Solicitar</a>
                </p>
            </section>
        </aside>
    </div>
</section>

<?php if ($visibles !== []): ?>
    <h2 class="section-title">Ambientes publicados</h2>
    <div class="cards cards--3">
        <?php foreach ($visibles as $instalacion): ?>
            <?php $tarifas = $instalacion['tarifas'] ?? []; ?>
            <article class="card card--room">
                <h3><?= e((string) $instalacion['nombre']) ?></h3>
                <p class="muted"><?= e((string) ($instalacion['tipo'] ?? '')) ?></p>

                <?php if (!empty($instalacion['ubicacion'])): ?>
                    <p class="card__hint"><?= e((string) $instalacion['ubicacion']) ?></p>
                <?php endif; ?>

                <?php if (!empty($instalacion['aforo'])): ?>
                    <p class="card__hint">Aforo referencial: <?= e((string) $instalacion['aforo']) ?> personas</p>
                <?php endif; ?>

                <?php if ($tarifas !== []): ?>
                    <?php
                    $montos = array_map(static fn (array $tarifa): float => (float) $tarifa['monto'], $tarifas);
                    $minimo = min($montos);
                    $maximo = max($montos);
                    ?>
                    <p class="card__value">
                        <?= e(dinero((string) $minimo)) ?>
                        <?php if ($maximo > $minimo): ?>
                            <span class="card__unit">hasta <?= e(dinero((string) $maximo)) ?></span>
                        <?php else: ?>
                            <span class="card__unit">/ <?= e((string) ($tarifas[0]['tipo_evento_nombre'] ?? 'uso general')) ?></span>
                        <?php endif; ?>
                    </p>
                    <p class="card__hint">
                        <?= e((string) count($tarifas)) ?> tarifa<?= count($tarifas) === 1 ? '' : 's' ?> publicada<?= count($tarifas) === 1 ? '' : 's' ?>
                    </p>
                <?php else: ?>
                    <p class="muted">El club todavía no publicó las tarifas de este ambiente.</p>
                <?php endif; ?>

                <p>
                    <a class="btn btn--primary btn--sm" href="<?= e(url('/alquiler/reservar?instalacion_id=' . (string) $instalacion['id'])) ?>">
                        Reservar
                    </a>
                    <a class="btn btn--ghost btn--sm" href="<?= e(url('/alquiler/ambiente/' . (string) $instalacion['id'])) ?>">
                        Ver detalle y disponibilidad
                    </a>
                </p>
            </article>
        <?php endforeach; ?>
    </div>

    <p class="section-actions">
        <a class="btn btn--ghost btn--sm" href="<?= e(url('/alquiler')) ?>">Ver todos los ambientes</a>
    </p>
<?php elseif ($dbDisponible): ?>
    <div class="empty-state">
        <span class="empty-state__icon"><?= Icons::render('alquileres', 'icon', 26) ?></span>
        <h2>Ambientes en preparación</h2>
        <p>
            El club todavía no ha publicado sus ambientes y tarifas en el portal.
            Mientras tanto, puede comunicarse directamente con la institución.
        </p>
    </div>
<?php endif; ?>

<h2 class="section-title">Cómo funciona la solicitud</h2>
<ol class="steps">
    <li>
        <strong>Consulte el ambiente y la fecha</strong>
        <span>Revise las características, la tarifa publicada y los horarios ya ocupados.</span>
    </li>
    <li>
        <strong>Envíe su solicitud</strong>
        <span>Complete un formulario corto con sus datos de contacto y los detalles del evento.</span>
    </li>
    <li>
        <strong>El club revisa la solicitud</strong>
        <span>El personal autorizado revisa la disponibilidad y las condiciones. Recibirá un código de seguimiento.</span>
    </li>
    <li>
        <strong>La reserva se confirma solo por el club</strong>
        <span>Enviar la solicitud no confirma el alquiler ni bloquea el horario de forma definitiva.</span>
    </li>
</ol>

<?php if ($telefono !== '' || $correo !== '' || $direccion !== ''): ?>
    <h2 class="section-title">Contacto institucional</h2>
    <div class="card">
        <?php if ($direccion !== ''): ?><p><?= e($direccion) ?></p><?php endif; ?>
        <?php if ($telefono !== ''): ?><p>Teléfono: <?= e($telefono) ?></p><?php endif; ?>
        <?php if ($correo !== ''): ?><p>Correo: <?= e($correo) ?></p><?php endif; ?>
    </div>
<?php endif; ?>

<h2 class="section-title">Instale el portal en su teléfono</h2>
<div class="card">
    <p>
        Este portal puede instalarse como aplicación en Android, iPhone y computadoras.
        Búsquelo en el menú del navegador («Añadir a la pantalla de inicio» o «Instalar aplicación»)
        o utilice el botón <strong>Instalar</strong> cuando aparezca.
    </p>
    <p class="card__hint">
        La aplicación instalada guarda en caché solo las páginas informativas públicas.
        Las solicitudes y los datos privados siempre se consultan en el servidor.
    </p>
    <p>
        <button type="button" class="btn btn--primary btn--sm" data-install-app>Instalar aplicación</button>
    </p>
</div>

<h2 class="section-title">Acceso del personal</h2>
<div class="card">
    <p class="muted">
        El panel administrativo está reservado al personal autorizado del club.
        Requiere usuario y contraseña.
    </p>
    <p>
        <?php if (auth() === null): ?>
            <a class="btn btn--ghost btn--sm" href="<?= e(url('/ingresar')) ?>">Ingresar al panel</a>
        <?php else: ?>
            <a class="btn btn--ghost btn--sm" href="<?= e(url('/panel')) ?>">Ir al panel</a>
        <?php endif; ?>
    </p>
</div>

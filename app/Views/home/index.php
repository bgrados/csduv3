<?php

declare(strict_types=1);

/**
 * Portada pública del sistema.
 *
 * No muestra datos internos: solo la presentación institucional, los ambientes
 * publicados y el acceso al portal de alquileres.
 *
 * @var bool  $dbDisponible
 * @var array<int, array<string, mixed>> $instalaciones
 * @var array<string, string> $configuracion
 * @var array<int, array<string, mixed>> $fotos
 * @var array<int, array<string, mixed>> $negocios
 */

$telefono = trim((string) ($configuracion['telefono'] ?? ''));
$correo = trim((string) ($configuracion['email_institucional'] ?? ''));
$direccion = trim((string) ($configuracion['direccion'] ?? ''));
$visibles = array_slice($instalaciones, 0, 6);
?>

<section class="hero">
    <div class="hero__content">
        <p class="page-head__eyebrow">Portal público</p>
        <h1><?= e((string) config('app.name')) ?></h1>
        <p class="hero__lead">
            Consulte los ambientes disponibles, las tarifas publicadas por el club, la
            disponibilidad por fecha, los torneos en curso y la galería de fotos del
            club. Envíe su solicitud de alquiler desde el teléfono, sin necesidad de
            crear una cuenta.
        </p>
        <div class="hero__actions">
            <a class="btn btn--primary" href="<?= e(url('/alquiler')) ?>">Ver ambientes y tarifas</a>
            <a class="btn btn--ghost" href="<?= e(url('/alquiler/solicitud')) ?>">Solicitar alquiler</a>
            <a class="btn btn--ghost" href="<?= e(url('/torneos')) ?>">Ver torneos</a>
            <a class="btn btn--ghost" href="<?= e(url('/galeria')) ?>">Galería del club</a>
            <a class="btn btn--ghost" href="https://uv3-platform.vercel.app/" target="_blank" rel="noopener">
                Portal de la Unidad Vecinal
            </a>
        </div>
    </div>
</section>

<?php if (!$dbDisponible): ?>
    <div class="alert alert--warning" role="status">
        <p>El portal está temporalmente en mantenimiento. Vuelva a intentarlo en unos minutos.</p>
    </div>
<?php endif; ?>

<?php if ($dbDisponible && $visibles === []): ?>
    <div class="empty-state">
        <span class="empty-state__icon"><?= \App\Core\Icons::render('alquileres', 'icon', 26) ?></span>
        <h2>Ambientes en preparación</h2>
        <p>
            El club todavía no ha publicado sus ambientes y tarifas en el portal.
            Mientras tanto, puede comunicarse directamente con la institución.
        </p>
    </div>
<?php endif; ?>

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
                    <p class="card__value">
                        <?= e(dinero((string) $tarifas[0]['monto'])) ?>
                        <span class="card__unit">/ <?= e((string) ($tarifas[0]['tipo_evento_nombre'] ?? 'uso general')) ?></span>
                    </p>
                <?php else: ?>
                    <p class="muted">Tarifa a consultar con el club.</p>
                <?php endif; ?>

                <p>
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
<?php endif; ?>

<h2 class="section-title">Noticias del club</h2>

<?php if ($fotos === []): ?>
    <div class="card">
        <p class="muted">
            El club está preparando sus noticias: las reuniones, los campeonatos y la
            actividad de la junta directiva.
        </p>
        <p>
            <a class="btn btn--ghost btn--sm" href="<?= e(url('/galeria')) ?>">Ver la galería</a>
        </p>
    </div>
<?php else: ?>
    <?php
    $fotoPrincipal = array_shift($fotos);
    ?>
    <div class="periodico">
        <article class="periodico__portada">
            <a class="periodico__portada-imagen" href="<?= e(url('/galeria/imagen/' . (string) $fotoPrincipal['id'])) ?>">
                <img src="<?= e(url('/galeria/imagen/' . (string) $fotoPrincipal['id'])) ?>" alt="<?= e((string) $fotoPrincipal['titulo']) ?>">
            </a>
            <div class="periodico__portada-texto">
                <p class="periodico__kicker">
                    <?= e(\App\Models\Foto::CATEGORIAS[(string) $fotoPrincipal['categoria']] ?? (string) $fotoPrincipal['categoria']) ?>
                    <?php if (!empty($fotoPrincipal['fecha_foto'])): ?>
                        · <?= e(fecha_legible((string) $fotoPrincipal['fecha_foto'])) ?>
                    <?php endif; ?>
                </p>
                <h3><?= e((string) $fotoPrincipal['titulo']) ?></h3>
                <?php if (!empty($fotoPrincipal['resumen'])): ?>
                    <p><?= e((string) $fotoPrincipal['resumen']) ?></p>
                <?php elseif (!empty($fotoPrincipal['descripcion'])): ?>
                    <p><?= e((string) $fotoPrincipal['descripcion']) ?></p>
                <?php endif; ?>
            </div>
        </article>

        <?php if ($fotos !== []): ?>
            <div class="periodico__notas">
                <?php foreach (array_slice($fotos, 0, 4) as $foto): ?>
                    <a class="periodico__nota" href="<?= e(url('/galeria/imagen/' . (string) $foto['id'])) ?>">
                        <img src="<?= e(url('/galeria/imagen/' . (string) $foto['id'])) ?>" alt="<?= e((string) $foto['titulo']) ?>" loading="lazy">
                        <span>
                            <strong><?= e((string) $foto['titulo']) ?></strong>
                            <small>
                                <?= e(\App\Models\Foto::CATEGORIAS[(string) $foto['categoria']] ?? (string) $foto['categoria']) ?>
                                <?php if (!empty($foto['fecha_foto'])): ?>
                                    · <?= e(fecha_legible((string) $foto['fecha_foto'])) ?>
                                <?php endif; ?>
                            </small>
                        </span>
                    </a>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <p class="section-actions">
        <a class="btn btn--ghost btn--sm" href="<?= e(url('/galeria')) ?>">Ver todas las fotos</a>
    </p>
<?php endif; ?>

<h2 class="section-title">Negocios de la unidad</h2>

<?php
$negociosPortada = array_values(array_filter(
    $negocios,
    static fn (array $negocio): bool => (int) ($negocio['destacado'] ?? 0) === 1
));
?>

<?php if ($negociosPortada === []): ?>
    <div class="card">
        <p class="muted">
            El club está preparando el directorio de comercios y servicios de la
            unidad vecinal. Mientras tanto, puede comunicarse con la institución.
        </p>
    </div>
<?php else: ?>
    <div class="cards cards--3">
        <?php foreach (array_slice($negociosPortada, 0, 6) as $negocio): ?>
            <article class="card negocio">
                <?php if (!empty($negocio['archivo'])): ?>
                    <img class="negocio__logo" src="<?= e(url('/negocios/imagen/' . (string) $negocio['id'])) ?>" alt="<?= e((string) $negocio['nombre']) ?>" loading="lazy">
                <?php endif; ?>

                <p class="card__label">
                    <?= e(\App\Models\Negocio::CATEGORIAS[(string) $negocio['categoria']] ?? (string) $negocio['categoria']) ?>
                </p>
                <h3 class="negocio__nombre"><?= e((string) $negocio['nombre']) ?></h3>

                <?php if (!empty($negocio['descripcion'])): ?>
                    <p class="negocio__descripcion"><?= e((string) $negocio['descripcion']) ?></p>
                <?php endif; ?>

                <ul class="facts">
                    <?php if (!empty($negocio['telefono'])): ?>
                        <li>
                            <span>Teléfono</span>
                            <strong><a href="tel:<?= e(preg_replace('/[^0-9+]/', '', (string) $negocio['telefono'])) ?>"><?= e((string) $negocio['telefono']) ?></a></strong>
                        </li>
                    <?php endif; ?>
                    <?php if (!empty($negocio['horario'])): ?>
                        <li><span>Horario</span><strong><?= e((string) $negocio['horario']) ?></strong></li>
                    <?php endif; ?>
                </ul>

                <?php if (!empty($negocio['web'])): ?>
                    <div class="card__actions">
                        <a class="btn btn--primary btn--sm" href="<?= e((string) $negocio['web']) ?>" target="_blank" rel="noopener nofollow">
                            Visitar
                        </a>
                    </div>
                <?php endif; ?>
            </article>
        <?php endforeach; ?>
    </div>

    <p class="section-actions">
        <a class="btn btn--ghost btn--sm" href="<?= e(url('/negocios')) ?>">Ver el directorio completo</a>
        <a class="btn btn--ghost btn--sm" href="https://uv3-platform.vercel.app/" target="_blank" rel="noopener">
            Portal de la Unidad Vecinal
        </a>
    </p>
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

<?php if ($telefono !== '' || $correo !== '' || $direccion !== ''): ?>
    <h2 class="section-title">Contacto institucional</h2>
    <div class="card">
        <?php if ($direccion !== ''): ?><p><?= e($direccion) ?></p><?php endif; ?>
        <?php if ($telefono !== ''): ?><p>Teléfono: <?= e($telefono) ?></p><?php endif; ?>
        <?php if ($correo !== ''): ?><p>Correo: <?= e($correo) ?></p><?php endif; ?>
    </div>
<?php endif; ?>

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

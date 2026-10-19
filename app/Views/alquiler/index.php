<?php

declare(strict_types=1);

/**
 * Catálogo público de ambientes y tarifas.
 *
 * @var array<int, array<string, mixed>> $instalaciones
 * @var array<int, array<string, mixed>> $tiposEvento
 */

$hora = static fn (?string $valor): string => $valor === null || $valor === '' ? '—' : substr($valor, 0, 5);
?>

<div class="page-head">
    <div>
        <p class="page-head__eyebrow">Portal de alquileres</p>
        <h1>Ambientes y tarifas</h1>
        <p class="page-head__lead">
            Tarifas publicadas por el club. Los importes son referenciales y las condiciones
            finales se acuerdan al confirmar el alquiler.
        </p>
    </div>
    <div class="page-head__actions">
        <a class="btn btn--ghost" href="<?= e(url('/alquiler/disponibilidad')) ?>">Consultar disponibilidad</a>
        <a class="btn btn--primary" href="<?= e(url('/alquiler/solicitud')) ?>">Solicitar alquiler</a>
    </div>
</div>

<?php if ($instalaciones === []): ?>
    <div class="empty-state">
        <span class="empty-state__icon"><?= \App\Core\Icons::render('alquileres', 'icon', 26) ?></span>
        <h2>Sin ambientes publicados</h2>
        <p>
            El club todavía no ha publicado ambientes ni tarifas en este portal.
            Comuníquese con la institución para consultar la disponibilidad.
        </p>
    </div>
<?php else: ?>
    <div class="cards cards--3">
        <?php foreach ($instalaciones as $instalacion): ?>
            <?php $tarifas = $instalacion['tarifas'] ?? []; ?>
            <article class="card card--room">
                <h2><?= e((string) $instalacion['nombre']) ?></h2>
                <p class="muted"><?= e((string) ($instalacion['tipo'] ?? 'Ambiente')) ?></p>

                <?php if (!empty($instalacion['descripcion'])): ?>
                    <p><?= e((string) $instalacion['descripcion']) ?></p>
                <?php endif; ?>

                <ul class="facts">
                    <?php if (!empty($instalacion['ubicacion'])): ?>
                        <li><span>Ubicación</span><strong><?= e((string) $instalacion['ubicacion']) ?></strong></li>
                    <?php endif; ?>
                    <?php if (!empty($instalacion['aforo'])): ?>
                        <li><span>Aforo</span><strong><?= e((string) $instalacion['aforo']) ?> personas</strong></li>
                    <?php endif; ?>
                    <li>
                        <span>Adelanto</span>
                        <strong><?= ((int) ($instalacion['requiere_adelanto'] ?? 0) === 1) ? 'Sí, según condiciones' : 'No especificado' ?></strong>
                    </li>
                </ul>

                <?php if ($tarifas === []): ?>
                    <p class="muted">Sin tarifa publicada. Consulte con el club.</p>
                <?php else: ?>
                    <div class="table-wrap">
                        <table class="table table--compact">
                            <caption class="visually-hidden">Tarifas de <?= e((string) $instalacion['nombre']) ?></caption>
                            <thead>
                            <tr>
                                <th scope="col">Tipo de uso</th>
                                <th scope="col">Franja</th>
                                <th scope="col">Tarifa</th>
                            </tr>
                            </thead>
                            <tbody>
                            <?php foreach ($tarifas as $tarifa): ?>
                                <tr>
                                    <td><?= e((string) ($tarifa['tipo_evento_nombre'] ?? $tarifa['nombre'] ?? 'Uso general')) ?></td>
                                    <td>
                                        <?php if (!empty($tarifa['hora_inicio']) || !empty($tarifa['hora_fin'])): ?>
                                            <?= e($hora($tarifa['hora_inicio'] ?? null)) ?> – <?= e($hora($tarifa['hora_fin'] ?? null)) ?>
                                        <?php else: ?>
                                            —
                                        <?php endif; ?>
                                    </td>
                                    <td><strong><?= e(dinero((string) $tarifa['monto'])) ?></strong></td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>

                <p class="card__actions">
                    <a class="btn btn--primary btn--sm" href="<?= e(url('/alquiler/ambiente/' . (string) $instalacion['id'])) ?>">
                        Ver y verificar fecha
                    </a>
                </p>
            </article>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

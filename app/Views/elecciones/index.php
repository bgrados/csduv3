<?php

declare(strict_types=1);

use App\Core\Icons;

/**
 * Procesos electorales.
 *
 * @var array<int, array<string, mixed>> $procesos
 * @var array<string, string> $estados
 */

$puedeGestionar = can('elecciones.gestionar');

$claseEstado = static function (string $valor): string {
    return match ($valor) {
        'en_proceso' => 'badge--ok',
        'convocado'  => 'badge--info',
        'planificado' => 'badge--soft',
        'finalizado' => 'badge--soft',
        'anulado'    => 'badge--danger',
        default      => 'badge--soft',
    };
};
?>

<div class="page-head">
    <div>
        <p class="page-head__eyebrow">Institucional</p>
        <h1>Elecciones</h1>
        <p class="page-head__lead">
            Procesos electorales del club: listas, candidatos y resultados que declara
            la organización. Es un registro administrativo, no una votación electrónica.
        </p>
    </div>
    <div class="page-head__actions">
        <?php if ($puedeGestionar): ?>
            <a class="btn btn--primary" href="<?= e(url('/elecciones/nuevo')) ?>">
                <?= Icons::render('elecciones', 'icon', 18) ?> Nuevo proceso
            </a>
        <?php endif; ?>
    </div>
</div>

<?php if ($procesos === []): ?>
    <div class="empty-state">
        <span class="empty-state__icon"><?= Icons::render('elecciones', 'icon', 26) ?></span>
        <h2>Todavía no hay procesos electorales</h2>
        <p>
            Registre el proceso electoral cuando el club lo convoque: las listas, los
            candidatos y los resultados se registran sobre ese proceso.
        </p>
        <?php if ($puedeGestionar): ?>
            <p><a class="btn btn--primary" href="<?= e(url('/elecciones/nuevo')) ?>">Crear el primer proceso</a></p>
        <?php endif; ?>
    </div>
<?php else: ?>
    <div class="card">
        <div class="table-wrap">
            <table class="table">
                <thead>
                <tr>
                    <th scope="col">Proceso</th>
                    <th scope="col">Convocatoria</th>
                    <th scope="col">Elección</th>
                    <th scope="col">Listas</th>
                    <th scope="col">Candidatos</th>
                    <th scope="col">Estado</th>
                    <th scope="col"><span class="visually-hidden">Acciones</span></th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($procesos as $proceso): ?>
                    <?php $estado = (string) $proceso['estado']; ?>
                    <tr>
                        <td>
                            <strong><?= e((string) $proceso['nombre']) ?></strong>
                            <?php if (!empty($proceso['descripcion'])): ?>
                                <br><span class="muted"><?= e((string) $proceso['descripcion']) ?></span>
                            <?php endif; ?>
                        </td>
                        <td><?= e(fecha_legible($proceso['fecha_convocatoria'] ?? null)) ?></td>
                        <td><?= e(fecha_legible($proceso['fecha_eleccion'] ?? null)) ?></td>
                        <td><?= e((string) $proceso['total_listas']) ?></td>
                        <td><?= e((string) $proceso['total_candidatos']) ?></td>
                        <td>
                            <span class="badge <?= e($claseEstado($estado)) ?>">
                                <?= e($estados[$estado] ?? $estado) ?>
                            </span>
                        </td>
                        <td class="table__actions">
                            <a class="btn btn--ghost btn--sm" href="<?= e(url('/elecciones/' . (string) $proceso['id'])) ?>">Ver</a>
                            <?php if ($puedeGestionar): ?>
                                <a class="btn btn--ghost btn--sm" href="<?= e(url('/elecciones/' . (string) $proceso['id'] . '/editar')) ?>">Editar</a>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
<?php endif; ?>

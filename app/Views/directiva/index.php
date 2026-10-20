<?php

declare(strict_types=1);

use App\Core\Icons;

/**
 * Junta directiva: periodos de gestión.
 *
 * @var array<int, array<string, mixed>> $directivas
 * @var array<string, mixed>|null $vigente
 * @var array<string, string> $estados
 */

$puedeGestionar = can('directiva.gestionar');

$claseEstado = static function (string $valor): string {
    return match ($valor) {
        'vigente'   => 'badge--ok',
        'concluida' => 'badge--soft',
        'anulada'   => 'badge--danger',
        default     => 'badge--soft',
    };
};
?>

<div class="page-head">
    <div>
        <p class="page-head__eyebrow">Institucional</p>
        <h1>Junta directiva</h1>
        <p class="page-head__lead">
            Periodos de gestión y sus integrantes. El histórico se conserva: un
            periodo concluido no se borra, solo deja de estar vigente.
        </p>
    </div>
    <div class="page-head__actions">
        <a class="btn btn--ghost" href="<?= e(url('/directiva/cargos')) ?>">Cargos</a>
        <?php if ($puedeGestionar): ?>
            <a class="btn btn--primary" href="<?= e(url('/directiva/nuevo')) ?>">
                <?= Icons::render('directiva', 'icon', 18) ?> Nuevo periodo
            </a>
        <?php endif; ?>
    </div>
</div>

<?php if ($directivas === []): ?>
    <div class="empty-state">
        <span class="empty-state__icon"><?= Icons::render('directiva', 'icon', 26) ?></span>
        <h2>Todavía no hay periodos de gestión</h2>
        <p>
            Registre el periodo de gestión actual para poder asentar a los integrantes
            de la junta directiva.
        </p>
        <?php if ($puedeGestionar): ?>
            <p><a class="btn btn--primary" href="<?= e(url('/directiva/nuevo')) ?>">Crear el primer periodo</a></p>
        <?php endif; ?>
    </div>
<?php else: ?>
    <?php if ($vigente !== null): ?>
        <div class="card card--highlight">
            <p class="card__label">Periodo vigente</p>
            <p class="card__value" style="font-size:1.1rem;"><?= e((string) $vigente['nombre']) ?></p>
            <p class="card__hint">
                <?= e(fecha_legible((string) $vigente['fecha_inicio'])) ?>
                <?php if (!empty($vigente['fecha_fin'])): ?>
                    — <?= e(fecha_legible((string) $vigente['fecha_fin'])) ?>
                <?php endif; ?>
                · <?= e((string) $vigente['miembros_vigentes']) ?> integrantes vigentes
            </p>
            <div class="card__actions">
                <a class="btn btn--primary" href="<?= e(url('/directiva/' . (string) $vigente['id'])) ?>">Ver integrantes</a>
            </div>
        </div>
    <?php endif; ?>

    <h2 class="section-title">Periodos de gestión</h2>

    <div class="card">
        <div class="table-wrap">
            <table class="table">
                <thead>
                <tr>
                    <th scope="col">Periodo</th>
                    <th scope="col">Desde</th>
                    <th scope="col">Hasta</th>
                    <th scope="col">Integrantes</th>
                    <th scope="col">Estado</th>
                    <th scope="col"><span class="visually-hidden">Acciones</span></th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($directivas as $directiva): ?>
                    <?php $estado = (string) $directiva['estado']; ?>
                    <tr>
                        <td><strong><?= e((string) $directiva['nombre']) ?></strong></td>
                        <td><?= e(fecha_legible((string) $directiva['fecha_inicio'])) ?></td>
                        <td><?= e(fecha_legible($directiva['fecha_fin'] ?? null)) ?></td>
                        <td>
                            <?= e((string) $directiva['miembros_vigentes']) ?> de <?= e((string) $directiva['total_miembros']) ?>
                        </td>
                        <td>
                            <span class="badge <?= e($claseEstado($estado)) ?>">
                                <?= e($estados[$estado] ?? $estado) ?>
                            </span>
                        </td>
                        <td class="table__actions">
                            <a class="btn btn--ghost btn--sm" href="<?= e(url('/directiva/' . (string) $directiva['id'])) ?>">Ver</a>
                            <?php if ($puedeGestionar): ?>
                                <a class="btn btn--ghost btn--sm" href="<?= e(url('/directiva/' . (string) $directiva['id'] . '/editar')) ?>">Editar</a>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
<?php endif; ?>

<?php

declare(strict_types=1);

use App\Core\Icons;

/**
 * Detalle de un periodo de gestión con sus integrantes.
 *
 * @var array<string, mixed> $directiva
 * @var array<int, array<string, mixed>> $miembros
 * @var array<int, array<string, mixed>> $cargos
 * @var array<string, string> $estados
 * @var array<string, string> $estadosMiembro
 */

$puedeGestionar = can('directiva.gestionar');
$directivaId = (int) $directiva['id'];
$estado = (string) $directiva['estado'];

$claseEstado = static function (string $valor): string {
    return match ($valor) {
        'vigente'   => 'badge--ok',
        'concluida' => 'badge--soft',
        'anulada'   => 'badge--danger',
        default     => 'badge--soft',
    };
};

$claseMiembro = static function (string $valor): string {
    return match ($valor) {
        'vigente'  => 'badge--ok',
        'cesado'   => 'badge--warn',
        'renuncia' => 'badge--danger',
        default    => 'badge--soft',
    };
};

$nombre = static function (array $miembro): string {
    $socio = trim((string) ($miembro['socio_nombre'] ?? ''));

    if ($socio !== '') {
        return $socio . ' (' . (string) $miembro['socio_codigo'] . ')';
    }

    $propio = trim((string) ($miembro['nombre_completo'] ?? ''));

    return $propio !== '' ? $propio : 'Integrante sin nombre';
};
?>

<div class="page-head">
    <div>
        <p class="page-head__eyebrow">
            <a href="<?= e(url('/directiva')) ?>">Junta directiva</a> · periodo de gestión
        </p>
        <h1><?= e((string) $directiva['nombre']) ?></h1>
        <p class="page-head__lead">
            <?= e(fecha_legible((string) $directiva['fecha_inicio'])) ?>
            <?php if (!empty($directiva['fecha_fin'])): ?>
                — <?= e(fecha_legible((string) $directiva['fecha_fin'])) ?>
            <?php endif; ?>
            · <?= e((string) $directiva['total_miembros']) ?> integrantes registrados
        </p>
    </div>
    <div class="page-head__actions">
        <?php if ($puedeGestionar): ?>
            <a class="btn btn--ghost" href="<?= e(url('/directiva/' . $directivaId . '/editar')) ?>">Editar periodo</a>
        <?php endif; ?>
        <a class="btn btn--ghost" href="<?= e(url('/directiva')) ?>">Volver</a>
    </div>
</div>

<div class="cards cards--3">
    <div class="card card--stat">
        <p class="card__label">Estado</p>
        <p class="card__value">
            <span class="badge <?= e($claseEstado($estado)) ?>"><?= e($estados[$estado] ?? $estado) ?></span>
        </p>
        <p class="card__hint">Del periodo de gestión</p>
    </div>

    <div class="card card--stat">
        <p class="card__label">Integrantes vigentes</p>
        <p class="card__value"><?= e((string) $directiva['miembros_vigentes']) ?></p>
        <p class="card__hint">De <?= e((string) $directiva['total_miembros']) ?> registrados</p>
    </div>

    <div class="card card--stat">
        <p class="card__label">Cargos</p>
        <p class="card__value"><?= e((string) count($cargos)) ?></p>
        <p class="card__hint">Cargos activos definidos</p>
    </div>
</div>

<?php if ($puedeGestionar): ?>
    <div class="card">
        <form class="form form--panel" method="post" action="<?= e(url('/directiva/' . $directivaId . '/estado')) ?>">
            <?= csrf_field() ?>
            <div class="field">
                <label for="estado-directiva">Estado del periodo</label>
                <select id="estado-directiva" name="estado">
                    <?php foreach ($estados as $clave => $etiqueta): ?>
                        <option value="<?= e((string) $clave) ?>" <?= $estado === (string) $clave ? 'selected' : '' ?>>
                            <?= e((string) $etiqueta) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="field field--actions">
                <button type="submit" class="btn btn--ghost">Actualizar estado</button>
            </div>
        </form>
    </div>
<?php endif; ?>

<?php if (!empty($directiva['observaciones'])): ?>
    <details class="details">
        <summary>Observaciones</summary>
        <p><?= nl2br(e((string) $directiva['observaciones'])) ?></p>
    </details>
<?php endif; ?>

<h2 class="section-title">Integrantes</h2>

<?php if ($miembros === []): ?>
    <div class="empty-state">
        <span class="empty-state__icon"><?= Icons::render('directiva', 'icon', 26) ?></span>
        <h2>Todavía no hay integrantes</h2>
        <p>Registre a los integrantes de la junta directiva y el cargo que desempeñan.</p>
    </div>
<?php else: ?>
    <div class="card">
        <div class="table-wrap">
            <table class="table">
                <thead>
                <tr>
                    <th scope="col">Cargo</th>
                    <th scope="col">Integrante</th>
                    <th scope="col">Contacto</th>
                    <th scope="col">Vigencia</th>
                    <th scope="col">Estado</th>
                    <?php if ($puedeGestionar): ?>
                        <th scope="col"><span class="visually-hidden">Acciones</span></th>
                    <?php endif; ?>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($miembros as $miembro): ?>
                    <?php $estadoMiembro = (string) $miembro['estado']; ?>
                    <tr>
                        <td><strong><?= e((string) ($miembro['cargo_nombre'] ?? '—')) ?></strong></td>
                        <td><?= e($nombre($miembro)) ?></td>
                        <td>
                            <?php if (!empty($miembro['telefono'])): ?>
                                Tel. <?= e((string) $miembro['telefono']) ?><br>
                            <?php endif; ?>
                            <?php if (!empty($miembro['email'])): ?>
                                <span class="muted"><?= e((string) $miembro['email']) ?></span>
                            <?php endif; ?>
                            <?php if (empty($miembro['telefono']) && empty($miembro['email'])): ?>
                                <span class="muted">—</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?= e(fecha_legible($miembro['fecha_inicio'] ?? null)) ?>
                            <?php if (!empty($miembro['fecha_fin'])): ?>
                                — <?= e(fecha_legible((string) $miembro['fecha_fin'])) ?>
                            <?php endif; ?>
                        </td>
                        <td>
                            <span class="badge <?= e($claseMiembro($estadoMiembro)) ?>">
                                <?= e($estadosMiembro[$estadoMiembro] ?? $estadoMiembro) ?>
                            </span>
                        </td>
                        <?php if ($puedeGestionar): ?>
                            <td class="table__actions">
                                <a class="btn btn--ghost btn--sm" href="<?= e(url('/directiva/' . $directivaId . '/miembros/' . (string) $miembro['id'] . '/editar')) ?>">Editar</a>
                                <?php if ($estadoMiembro === 'vigente'): ?>
                                    <form method="post" action="<?= e(url('/directiva/' . $directivaId . '/miembros/' . (string) $miembro['id'] . '/estado')) ?>">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="estado" value="cesado">
                                        <button type="submit" class="btn btn--ghost btn--sm" data-confirmar="¿Registrar que este integrante cesó en el cargo?">Cesar</button>
                                    </form>
                                <?php else: ?>
                                    <form method="post" action="<?= e(url('/directiva/' . $directivaId . '/miembros/' . (string) $miembro['id'] . '/estado')) ?>">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="estado" value="vigente">
                                        <button type="submit" class="btn btn--ghost btn--sm">Reactivar</button>
                                    </form>
                                <?php endif; ?>
                            </td>
                        <?php endif; ?>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
<?php endif; ?>

<?php if ($puedeGestionar): ?>
    <h2 class="section-title">Registrar integrante</h2>

    <?php if ($cargos === []): ?>
        <div class="notice">
            Primero defina los cargos de la junta directiva en
            <a href="<?= e(url('/directiva/cargos')) ?>">Cargos</a>.
        </div>
    <?php else: ?>
        <form class="form form--panel" method="post" action="<?= e(url('/directiva/' . $directivaId . '/miembros')) ?>">
            <?= csrf_field() ?>
            <div class="field">
                <label for="cargo_id">Cargo <span class="req">*</span></label>
                <select id="cargo_id" name="cargo_id" required>
                    <option value="">Seleccione un cargo</option>
                    <?php foreach ($cargos as $cargo): ?>
                        <option value="<?= e((string) $cargo['id']) ?>" <?= (string) old('cargo_id') === (string) $cargo['id'] ? 'selected' : '' ?>>
                            <?= e((string) $cargo['nombre']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="field">
                <label for="socio_codigo">Código de socio</label>
                <input type="text" id="socio_codigo" name="socio_codigo" maxlength="30" value="<?= e((string) old('socio_codigo')) ?>" placeholder="Si es socio registrado">
                <p class="field__hint">Si no es socio, complete el nombre completo.</p>
            </div>
            <div class="field">
                <label for="nombre_completo">Nombre completo</label>
                <input type="text" id="nombre_completo" name="nombre_completo" maxlength="180" value="<?= e((string) old('nombre_completo')) ?>" placeholder="Para personas que no son socios">
            </div>
            <div class="field">
                <label for="documento">Documento</label>
                <input type="text" id="documento" name="documento" maxlength="30" value="<?= e((string) old('documento')) ?>">
            </div>
            <div class="field">
                <label for="telefono">Teléfono</label>
                <input type="text" id="telefono" name="telefono" maxlength="30" value="<?= e((string) old('telefono')) ?>">
            </div>
            <div class="field">
                <label for="email">Correo electrónico</label>
                <input type="email" id="email" name="email" maxlength="160" value="<?= e((string) old('email')) ?>">
            </div>
            <div class="field">
                <label for="fecha_inicio">Desde</label>
                <input type="date" id="fecha_inicio" name="fecha_inicio" value="<?= e((string) old('fecha_inicio', (string) $directiva['fecha_inicio'])) ?>">
            </div>
            <div class="field">
                <label for="fecha_fin">Hasta</label>
                <input type="date" id="fecha_fin" name="fecha_fin" value="<?= e((string) old('fecha_fin')) ?>">
            </div>
            <div class="field">
                <label for="estado-miembro">Estado</label>
                <select id="estado-miembro" name="estado">
                    <?php foreach ($estadosMiembro as $clave => $etiqueta): ?>
                        <option value="<?= e((string) $clave) ?>" <?= (string) old('estado', 'vigente') === (string) $clave ? 'selected' : '' ?>>
                            <?= e((string) $etiqueta) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="field field--actions">
                <button type="submit" class="btn btn--primary">Registrar integrante</button>
            </div>
        </form>
    <?php endif; ?>
<?php endif; ?>

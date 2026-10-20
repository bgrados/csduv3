<?php

declare(strict_types=1);

use App\Core\Icons;

/**
 * Reservas de los ambientes.
 *
 * @var array<int, array<string, mixed>> $reservas
 * @var array<int, array<string, mixed>> $instalaciones
 * @var array<string, string> $estados
 * @var array<string, mixed> $filtros
 * @var int $total
 * @var int $pagina
 * @var int $paginas
 * @var int $pendientes
 */

$puedeGestionar = can('alquileres.gestionar');
$hayFiltros = $filtros['q'] !== ''
    || $filtros['estado'] !== ''
    || $filtros['instalacion_id'] > 0
    || $filtros['desde'] !== ''
    || $filtros['hasta'] !== '';

$claseEstado = static function (string $valor): string {
    return match ($valor) {
        'pendiente'  => 'badge--warn',
        'confirmada' => 'badge--ok',
        'finalizada' => 'badge--soft',
        'cancelada'  => 'badge--danger',
        default      => 'badge--soft',
    };
};
?>

<div class="page-head">
    <div>
        <p class="page-head__eyebrow">Operación</p>
        <h1>Reservas</h1>
        <p class="page-head__lead">
            Reservas de los ambientes con su tarifa, el precio acordado, el adelanto
            y los pagos registrados. Un horario no puede reservarse dos veces.
        </p>
    </div>
    <div class="page-head__actions">
        <?php if ($puedeGestionar): ?>
            <a class="btn btn--primary" href="<?= e(url('/reservas/nuevo')) ?>">
                <?= Icons::render('alquileres', 'icon', 18) ?> Nueva reserva
            </a>
        <?php endif; ?>
    </div>
</div>

<form class="form form--filters" method="get" action="<?= e(url('/reservas')) ?>">
    <div class="field">
        <label for="q">Buscar</label>
        <input type="search" id="q" name="q" value="<?= e((string) $filtros['q']) ?>" placeholder="Código o solicitante">
    </div>

    <div class="field">
        <label for="estado">Estado</label>
        <select id="estado" name="estado">
            <option value="">Todos</option>
            <?php foreach ($estados as $clave => $etiqueta): ?>
                <option value="<?= e((string) $clave) ?>" <?= $filtros['estado'] === (string) $clave ? 'selected' : '' ?>>
                    <?= e((string) $etiqueta) ?>
                </option>
            <?php endforeach; ?>
        </select>
    </div>

    <div class="field">
        <label for="instalacion_id">Sede</label>
        <select id="instalacion_id" name="instalacion_id">
            <option value="">Todas</option>
            <?php foreach ($instalaciones as $instalacion): ?>
                <option
                    value="<?= e((string) $instalacion['id']) ?>"
                    <?= (int) $filtros['instalacion_id'] === (int) $instalacion['id'] ? 'selected' : '' ?>
                ><?= e((string) $instalacion['nombre']) ?></option>
            <?php endforeach; ?>
        </select>
    </div>

    <div class="field">
        <label for="desde">Desde</label>
        <input type="date" id="desde" name="desde" value="<?= e((string) $filtros['desde']) ?>">
    </div>

    <div class="field">
        <label for="hasta">Hasta</label>
        <input type="date" id="hasta" name="hasta" value="<?= e((string) $filtros['hasta']) ?>">
    </div>

    <div class="field field--actions">
        <button type="submit" class="btn btn--ghost">Filtrar</button>
        <?php if ($hayFiltros): ?>
            <a class="btn btn--ghost" href="<?= e(url('/reservas')) ?>">Limpiar</a>
        <?php endif; ?>
    </div>
</form>

<?php if ($reservas === []): ?>
    <div class="empty-state">
        <span class="empty-state__icon"><?= Icons::render('alquileres', 'icon', 26) ?></span>
        <?php if ($hayFiltros): ?>
            <h2>Sin coincidencias</h2>
            <p>No se encontraron reservas con los filtros seleccionados.</p>
        <?php else: ?>
            <h2>Todavía no hay reservas</h2>
            <p>
                Las reservas se registran aquí o convirtiendo una solicitud recibida
                desde el portal público.
            </p>
        <?php endif; ?>
    </div>
<?php else: ?>
    <div class="card">
        <div class="table-wrap">
            <table class="table">
                <thead>
                <tr>
                    <th scope="col">Código</th>
                    <th scope="col">Sede</th>
                    <th scope="col">Fecha y hora</th>
                    <th scope="col">Solicitante</th>
                    <th scope="col">Precio</th>
                    <th scope="col">Pagado</th>
                    <th scope="col">Estado</th>
                    <th scope="col"><span class="visually-hidden">Acciones</span></th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($reservas as $reserva): ?>
                    <?php
                    $estado = (string) $reserva['estado'];
                    $precio = (float) $reserva['precio_acordado'];
                    $pagado = (float) $reserva['monto_pagado'];
                    ?>
                    <tr>
                        <td><code><?= e((string) $reserva['codigo']) ?></code></td>
                        <td>
                            <strong><?= e((string) ($reserva['instalacion_nombre'] ?? '—')) ?></strong>
                            <?php if (!empty($reserva['tipo_evento_nombre'])): ?>
                                <br><span class="muted"><?= e((string) $reserva['tipo_evento_nombre']) ?></span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?= e(fecha_legible((string) $reserva['fecha_evento'])) ?>
                            <br><span class="muted">
                                <?= e(substr((string) $reserva['hora_inicio'], 0, 5)) ?> – <?= e(substr((string) $reserva['hora_fin'], 0, 5)) ?>
                            </span>
                        </td>
                        <td><?= e((string) $reserva['solicitante_nombre']) ?></td>
                        <td><?= e(dinero((string) $precio)) ?></td>
                        <td>
                            <?= e(dinero((string) $pagado)) ?>
                            <?php if ($precio > 0 && $pagado < $precio): ?>
                                <br><span class="muted">Falta <?= e(dinero((string) ($precio - $pagado))) ?></span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <span class="badge <?= e($claseEstado($estado)) ?>">
                                <?= e($estados[$estado] ?? $estado) ?>
                            </span>
                        </td>
                        <td class="table__actions">
                            <a class="btn btn--ghost btn--sm" href="<?= e(url('/reservas/' . (string) $reserva['id'])) ?>">Ver</a>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <?php $etiqueta = 'reservas'; require __DIR__ . '/../partials/paginacion.php'; ?>
<?php endif; ?>

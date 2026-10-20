<?php

declare(strict_types=1);

/**
 * Listado de solicitudes de alquiler recibidas desde el portal público.
 *
 * @var array<int, array<string, mixed>> $solicitudes
 * @var array<string, string> $estados
 * @var string $estado
 * @var string $busqueda
 * @var int $total
 * @var int $pendientes
 */

$hora = static fn (?string $valor): string => $valor === null || $valor === '' ? '—' : substr($valor, 0, 5);

$puedeGestionar = can('alquileres.gestionar');

$claseEstado = static function (string $valor): string {
    return match ($valor) {
        'pendiente'   => 'badge--warn',
        'en_revision' => 'badge--info',
        'atendida'    => 'badge--ok',
        'rechazada'   => 'badge--danger',
        default       => 'badge--soft',
    };
};
?>

<div class="page-head">
    <div>
        <p class="page-head__eyebrow">Alquileres</p>
        <h1>Solicitudes de alquiler</h1>
        <p class="page-head__lead">
            Solicitudes enviadas desde el portal público. Revisarlas no crea una reserva:
            el registro de la reserva y de los importes corresponde al módulo de alquileres.
        </p>
    </div>
    <div class="page-head__actions">
        <?php if ($pendientes > 0): ?>
            <span class="badge badge--warn"><?= e((string) $pendientes) ?> por revisar</span>
        <?php endif; ?>
        <a class="btn btn--ghost" href="<?= e(url('/alquiler')) ?>">Ver portal público</a>
    </div>
</div>

<form class="form form--filters" method="get" action="<?= e(url('/solicitudes')) ?>">
    <div class="field">
        <label for="estado">Estado</label>
        <select id="estado" name="estado">
            <option value="">Todos</option>
            <?php foreach ($estados as $clave => $etiqueta): ?>
                <option value="<?= e((string) $clave) ?>" <?= $estado === (string) $clave ? 'selected' : '' ?>><?= e((string) $etiqueta) ?></option>
            <?php endforeach; ?>
        </select>
    </div>

    <div class="field">
        <label for="q">Buscar</label>
        <input type="search" id="q" name="q" value="<?= e($busqueda) ?>" placeholder="Código, nombre, teléfono o correo">
    </div>

    <div class="field field--actions">
        <button type="submit" class="btn btn--ghost">Filtrar</button>
        <?php if ($estado !== '' || $busqueda !== ''): ?>
            <a class="btn btn--ghost" href="<?= e(url('/solicitudes')) ?>">Limpiar</a>
        <?php endif; ?>
    </div>
</form>

<?php if ($solicitudes === []): ?>
    <div class="empty-state">
        <span class="empty-state__icon"><?= \App\Core\Icons::render('alquileres', 'icon', 26) ?></span>
        <h2>Sin solicitudes</h2>
        <p>
            No hay solicitudes que coincidan con el filtro seleccionado.
            Las solicitudes enviadas desde el portal público aparecerán aquí.
        </p>
    </div>
<?php else: ?>
    <div class="card">
        <div class="table-wrap">
            <table class="table">
                <thead>
                <tr>
                    <th scope="col">Recibida</th>
                    <th scope="col">Código</th>
                    <th scope="col">Solicitante</th>
                    <th scope="col">Ambiente</th>
                    <th scope="col">Evento</th>
                    <th scope="col">Estado</th>
                    <th scope="col"><span class="visually-hidden">Acciones</span></th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($solicitudes as $solicitud): ?>
                    <tr>
                        <td><?= e(fecha_legible($solicitud['created_at'] ?? null, true)) ?></td>
                        <td><code><?= e((string) $solicitud['codigo']) ?></code></td>
                        <td>
                            <?= e((string) $solicitud['solicitante_nombre']) ?>
                            <br><span class="muted"><?= e((string) $solicitud['solicitante_telefono']) ?></span>
                        </td>
                        <td><?= e((string) $solicitud['instalacion_nombre']) ?></td>
                        <td>
                            <?= e(fecha_legible((string) $solicitud['fecha_evento'])) ?>
                            <br><span class="muted"><?= e($hora($solicitud['hora_inicio'] ?? null)) ?> – <?= e($hora($solicitud['hora_fin'] ?? null)) ?></span>
                        </td>
                        <td>
                            <span class="badge <?= e($claseEstado((string) $solicitud['estado'])) ?>"><?= e($estados[(string) $solicitud['estado']] ?? (string) $solicitud['estado']) ?></span>
                            <?php if ((int) ($solicitud['conflicto_detectado'] ?? 0) === 1): ?>
                                <br><span class="badge badge--danger">posible cruce</span>
                            <?php endif; ?>
                        </td>
                        <td class="acciones-fila">
                            <a class="btn btn--ghost btn--sm" href="<?= e(url('/solicitudes/' . (string) $solicitud['id'])) ?>">Revisar</a>
                            <?php if ($puedeGestionar && in_array((string) $solicitud['estado'], ['pendiente', 'en_revision'], true)): ?>
                                <a class="btn btn--primary btn--sm" href="<?= e(url('/solicitudes/' . (string) $solicitud['id'] . '/reservar')) ?>">
                                    Convertir en reserva
                                </a>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <?php $etiqueta = 'solicitudes'; require __DIR__ . '/../partials/paginacion.php'; ?>
<?php endif; ?>

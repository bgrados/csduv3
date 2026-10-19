<?php

declare(strict_types=1);

use App\Core\Icons;

/**
 * Panel principal.
 *
 * @var bool $dbDisponible
 * @var array<int, array<string, mixed>> $indicadores
 * @var array<int, array<string, mixed>> $solicitudesRecientes
 * @var array<int, array<string, mixed>> $proximasReservas
 * @var array<int, array<string, mixed>> $ultimosMovimientos
 * @var array<int, string> $avisos
 * @var array<int, array<string, mixed>> $pasos
 */

$hora = static fn (?string $valor): string => $valor === null || $valor === '' ? '—' : substr($valor, 0, 5);

$claseEstado = static function (string $estado): string {
    return match ($estado) {
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
        <p class="page-head__eyebrow">Área administrativa</p>
        <h1>Bienvenido, <?= e(usuario_nombre(auth())) ?></h1>
        <p class="page-head__lead">
            Resumen del estado del sistema. Solo verá los indicadores que corresponden
            a los permisos de su rol.
        </p>
    </div>
</div>

<?php foreach ($avisos as $aviso): ?>
    <div class="alert alert--warning" role="status">
        <p><?= e($aviso) ?></p>
    </div>
<?php endforeach; ?>

<?php if ($indicadores === []): ?>
    <div class="empty-state">
        <span class="empty-state__icon"><?= Icons::render('panel', 'icon', 26) ?></span>
        <h2>Sin indicadores para su rol</h2>
        <p>
            Su cuenta no tiene permisos para consultar los módulos que alimentan el panel.
            Solicite a un administrador la asignación del rol correspondiente.
        </p>
    </div>
<?php else: ?>
    <div class="cards cards--4">
        <?php foreach ($indicadores as $indicador): ?>
            <article class="card card--stat<?= ($indicador['tono'] ?? '') === 'atencion' ? ' card--attention' : '' ?>">
                <p class="card__label"><?= Icons::render((string) ($indicador['icono'] ?? 'panel'), 'icon', 16) ?> <?= e((string) $indicador['titulo']) ?></p>
                <p class="card__value">
                    <?= e((string) $indicador['valor']) ?>
                    <?php if (!empty($indicador['url'])): ?>
                        <a class="btn btn--ghost btn--sm" href="<?= e((string) $indicador['url']) ?>">Ver</a>
                    <?php endif; ?>
                </p>
                <p class="card__hint"><?= e((string) ($indicador['detalle'] ?? '')) ?></p>
            </article>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<?php if ($pasos !== []): ?>
    <h2 class="section-title">Tareas recomendadas</h2>
    <ul class="steps">
        <?php foreach ($pasos as $paso): ?>
            <li>
                <strong><?= e((string) $paso['titulo']) ?></strong>
                <span><?= e((string) $paso['detalle']) ?></span>
                <?php if (!empty($paso['url']) && !empty($paso['accion'])): ?>
                    <span class="step-actions">
                        <a class="btn btn--ghost btn--sm" href="<?= e((string) $paso['url']) ?>"><?= e((string) $paso['accion']) ?></a>
                    </span>
                <?php endif; ?>
            </li>
        <?php endforeach; ?>
    </ul>
<?php endif; ?>

<?php if ($solicitudesRecientes !== []): ?>
    <h2 class="section-title">Últimas solicitudes del portal público</h2>
    <div class="card">
        <div class="table-wrap">
            <table class="table">
                <thead>
                <tr>
                    <th scope="col">Código</th>
                    <th scope="col">Solicitante</th>
                    <th scope="col">Ambiente</th>
                    <th scope="col">Fecha</th>
                    <th scope="col">Estado</th>
                    <th scope="col"><span class="visually-hidden">Acciones</span></th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($solicitudesRecientes as $solicitud): ?>
                    <tr>
                        <td><code><?= e((string) $solicitud['codigo']) ?></code></td>
                        <td><?= e((string) $solicitud['solicitante_nombre']) ?></td>
                        <td><?= e((string) $solicitud['instalacion_nombre']) ?></td>
                        <td>
                            <?= e(fecha_legible((string) $solicitud['fecha_evento'])) ?>
                            <span class="muted"><?= e($hora($solicitud['hora_inicio'] ?? null)) ?></span>
                        </td>
                        <td><span class="badge <?= e($claseEstado((string) $solicitud['estado'])) ?>"><?= e((string) $solicitud['estado']) ?></span></td>
                        <td><a class="btn btn--ghost btn--sm" href="<?= e(url('/solicitudes/' . (string) $solicitud['id'])) ?>">Revisar</a></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <p class="card__actions">
            <a class="btn btn--ghost btn--sm" href="<?= e(url('/solicitudes')) ?>">Ver todas las solicitudes</a>
        </p>
    </div>
<?php endif; ?>

<?php if ($proximasReservas !== []): ?>
    <h2 class="section-title">Próximas reservas registradas</h2>
    <div class="card">
        <div class="table-wrap">
            <table class="table">
                <thead>
                <tr>
                    <th scope="col">Código</th>
                    <th scope="col">Ambiente</th>
                    <th scope="col">Fecha</th>
                    <th scope="col">Horario</th>
                    <th scope="col">Importe</th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($proximasReservas as $reserva): ?>
                    <tr>
                        <td><code><?= e((string) ($reserva['codigo'] ?? '—')) ?></code></td>
                        <td><?= e((string) $reserva['instalacion_nombre']) ?></td>
                        <td><?= e(fecha_legible((string) $reserva['fecha_evento'])) ?></td>
                        <td><?= e($hora($reserva['hora_inicio'] ?? null)) ?> – <?= e($hora($reserva['hora_fin'] ?? null)) ?></td>
                        <td><?= e(dinero($reserva['precio_acordado'] ?? 0)) ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
<?php endif; ?>

<?php if ($ultimosMovimientos !== []): ?>
    <h2 class="section-title">Últimos movimientos de caja</h2>
    <div class="card">
        <div class="table-wrap">
            <table class="table">
                <thead>
                <tr>
                    <th scope="col">Fecha</th>
                    <th scope="col">Concepto</th>
                    <th scope="col">Categoría</th>
                    <th scope="col">Tipo</th>
                    <th scope="col">Monto</th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($ultimosMovimientos as $movimiento): ?>
                    <tr>
                        <td><?= e(fecha_legible((string) $movimiento['fecha'])) ?></td>
                        <td><?= e((string) $movimiento['concepto']) ?></td>
                        <td><?= e((string) ($movimiento['categoria_nombre'] ?? '—')) ?></td>
                        <td><span class="badge <?= ((string) $movimiento['tipo'] === 'ingreso') ? 'badge--ok' : 'badge--warn' ?>"><?= e((string) $movimiento['tipo']) ?></span></td>
                        <td><?= e(dinero($movimiento['monto'])) ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
<?php endif; ?>

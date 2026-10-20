<?php

declare(strict_types=1);

use App\Core\Icons;

/**
 * Calendario institucional: vista de mes y próximas actividades.
 *
 * @var array<int, array<string, mixed>> $actividades
 * @var int $anio
 * @var int $mes
 * @var string $tipo
 * @var array<string, string> $tipos
 * @var array<string, string> $estados
 * @var array<int, array<string, mixed>> $proximas
 */

$puedeGestionar = can('calendario.gestionar');

$claseEstado = static function (string $valor): string {
    return match ($valor) {
        'programada'   => 'badge--info',
        'realizada'    => 'badge--ok',
        'cancelada'    => 'badge--danger',
        'reprogramada' => 'badge--warn',
        default        => 'badge--soft',
    };
};

$porDia = [];

foreach ($actividades as $actividad) {
    $clave = substr((string) $actividad['fecha_inicio'], 0, 10);
    $porDia[$clave][] = $actividad;
}

$inicioMes = sprintf('%04d-%02d-01', $anio, $mes);
$marca = strtotime($inicioMes);
$diaSemana = (int) date('N', $marca);
$primerDia = $marca - (($diaSemana - 1) * 86400);
$diasMes = (int) date('t', $marca);
$totalCeldas = (int) (ceil(($diaSemana - 1 + $diasMes) / 7) * 7);

$meses = [
    1 => 'Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio',
    'Julio', 'Agosto', 'Setiembre', 'Octubre', 'Noviembre', 'Diciembre',
];

$mesAnterior = $mes === 1 ? 12 : $mes - 1;
$anioAnterior = $mes === 1 ? $anio - 1 : $anio;
$mesSiguiente = $mes === 12 ? 1 : $mes + 1;
$anioSiguiente = $mes === 12 ? $anio + 1 : $anio;
?>

<div class="page-head">
    <div>
        <p class="page-head__eyebrow">Institucional</p>
        <h1>Calendario</h1>
        <p class="page-head__lead">
            Reuniones, elecciones, actividades y eventos del club. Calendario
            interno: no se publica en el portal público.
        </p>
    </div>
    <div class="page-head__actions">
        <?php if ($puedeGestionar): ?>
            <a class="btn btn--primary" href="<?= e(url('/calendario/nuevo')) ?>">
                <?= Icons::render('calendario', 'icon', 18) ?> Nueva actividad
            </a>
        <?php endif; ?>
    </div>
</div>

<div class="card">
    <div class="table-wrap">
        <table class="table table--calendario">
            <thead>
            <tr>
                <th scope="col">Lunes</th>
                <th scope="col">Martes</th>
                <th scope="col">Miércoles</th>
                <th scope="col">Jueves</th>
                <th scope="col">Viernes</th>
                <th scope="col">Sábado</th>
                <th scope="col">Domingo</th>
            </tr>
            </thead>
            <tbody>
            <?php for ($celda = 0; $celda < $totalCeldas; $celda += 7): ?>
                <tr>
                    <?php for ($dia = 0; $dia < 7; $dia++): ?>
                        <?php
                        $marcaCelda = $primerDia + (($celda + $dia) * 86400);
                        $fecha = date('Y-m-d', $marcaCelda);
                        $delMes = (int) date('n', $marcaCelda) === $mes;
                        $actividadesDelDia = $porDia[$fecha] ?? [];
                        ?>
                        <td class="calendario__celda<?= $delMes ? '' : ' es-otro-mes' ?>">
                            <span class="calendario__numero"><?= e((string) (int) date('j', $marcaCelda)) ?></span>
                            <?php foreach ($actividadesDelDia as $actividad): ?>
                                <span class="calendario__evento calendario__evento--<?= e((string) $actividad['tipo']) ?>"
                                      title="<?= e((string) $actividad['titulo']) ?>">
                                    <?= e((string) $actividad['titulo']) ?>
                                </span>
                            <?php endforeach; ?>
                        </td>
                    <?php endfor; ?>
                </tr>
            <?php endfor; ?>
            </tbody>
        </table>
    </div>
</div>

<div class="cards cards--2" style="margin-top:1rem;">
    <div class="card">
        <div class="form--panel">
            <div class="field">
                <a class="btn btn--ghost" href="<?= e(url('/calendario?anio=' . $anioAnterior . '&mes=' . $mesAnterior)) ?>">
                    &larr; <?= e((string) $meses[$mesAnterior]) ?> <?= e((string) $anioAnterior) ?>
                </a>
            </div>
            <div class="field">
                <strong><?= e((string) $meses[$mes]) ?> <?= e((string) $anio) ?></strong>
            </div>
            <div class="field">
                <a class="btn btn--ghost" href="<?= e(url('/calendario?anio=' . $anioSiguiente . '&mes=' . $mesSiguiente)) ?>">
                    <?= e((string) $meses[$mesSiguiente]) ?> <?= e((string) $anioSiguiente) ?> &rarr;
                </a>
            </div>
        </div>
    </div>

    <div class="card">
        <form class="form form--filters" method="get" action="<?= e(url('/calendario')) ?>">
            <div class="field">
                <label for="anio">Año</label>
                <input type="number" id="anio" name="anio" min="2000" max="2100" value="<?= e((string) $anio) ?>">
            </div>
            <div class="field">
                <label for="mes">Mes</label>
                <select id="mes" name="mes">
                    <?php foreach ($meses as $numero => $nombre): ?>
                        <option value="<?= e((string) $numero) ?>" <?= (int) $mes === $numero ? 'selected' : '' ?>>
                            <?= e((string) $nombre) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="field">
                <label for="tipo">Tipo</label>
                <select id="tipo" name="tipo">
                    <option value="">Todos</option>
                    <?php foreach ($tipos as $clave => $etiqueta): ?>
                        <option value="<?= e((string) $clave) ?>" <?= $tipo === (string) $clave ? 'selected' : '' ?>>
                            <?= e((string) $etiqueta) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="field field--actions">
                <button type="submit" class="btn btn--ghost">Ver mes</button>
                <a class="btn btn--ghost" href="<?= e(url('/calendario')) ?>">Hoy</a>
            </div>
        </form>
    </div>
</div>

<h2 class="section-title">Actividades del mes</h2>

<?php if ($actividades === []): ?>
    <div class="empty-state">
        <span class="empty-state__icon"><?= Icons::render('calendario', 'icon', 26) ?></span>
        <h2>No hay actividades programadas este mes</h2>
        <p>Programe reuniones, elecciones o eventos cuando corresponda.</p>
    </div>
<?php else: ?>
    <div class="card">
        <div class="table-wrap">
            <table class="table">
                <thead>
                <tr>
                    <th scope="col">Fecha</th>
                    <th scope="col">Actividad</th>
                    <th scope="col">Tipo</th>
                    <th scope="col">Lugar</th>
                    <th scope="col">Estado</th>
                    <?php if ($puedeGestionar): ?>
                        <th scope="col"><span class="visually-hidden">Acciones</span></th>
                    <?php endif; ?>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($actividades as $actividad): ?>
                    <?php $estado = (string) $actividad['estado']; ?>
                    <tr>
                        <td>
                            <?= e(fecha_legible(substr((string) $actividad['fecha_inicio'], 0, 10))) ?>
                            <?php if ((int) $actividad['todo_el_dia'] === 0): ?>
                                <br><span class="muted"><?= e(hora((string) $actividad['fecha_inicio'], '—')) ?></span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <strong><?= e((string) $actividad['titulo']) ?></strong>
                            <?php if (!empty($actividad['descripcion'])): ?>
                                <br><span class="muted"><?= e((string) $actividad['descripcion']) ?></span>
                            <?php endif; ?>
                        </td>
                        <td><?= e($tipos[(string) $actividad['tipo']] ?? (string) $actividad['tipo']) ?></td>
                        <td>
                            <?= e((string) ($actividad['lugar'] ?? $actividad['instalacion_nombre'] ?? '—')) ?>
                        </td>
                        <td>
                            <span class="badge <?= e($claseEstado($estado)) ?>">
                                <?= e($estados[$estado] ?? $estado) ?>
                            </span>
                        </td>
                        <?php if ($puedeGestionar): ?>
                            <td class="table__actions">
                                <a class="btn btn--ghost btn--sm" href="<?= e(url('/calendario/' . (string) $actividad['id'] . '/editar')) ?>">Editar</a>
                                <?php if ($estado === 'programada'): ?>
                                    <form method="post" action="<?= e(url('/calendario/' . (string) $actividad['id'] . '/estado')) ?>">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="estado" value="realizada">
                                        <button type="submit" class="btn btn--ghost btn--sm">Marcar realizada</button>
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

<?php if ($proximas !== []): ?>
    <h2 class="section-title">Próximas actividades</h2>

    <div class="card">
        <ul class="plain-list">
            <?php foreach ($proximas as $actividad): ?>
                <li>
                    <strong><?= e((string) $actividad['titulo']) ?></strong>
                    <span class="muted">
                        · <?= e(fecha_legible(substr((string) $actividad['fecha_inicio'], 0, 10))) ?>
                        <?= (int) $actividad['todo_el_dia'] === 0 ? ' · ' . e(hora((string) $actividad['fecha_inicio'], '—')) : '' ?>
                        <?php if (!empty($actividad['lugar'])): ?>
                            · <?= e((string) $actividad['lugar']) ?>
                        <?php endif; ?>
                    </span>
                </li>
            <?php endforeach; ?>
        </ul>
    </div>
<?php endif; ?>

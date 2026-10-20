<?php

declare(strict_types=1);

use App\Core\Icons;

/**
 * Detalle administrativo de un torneo.
 *
 * @var array<string, mixed> $torneo
 * @var array<int, array<string, mixed>> $equipos
 * @var array<int, array<string, mixed>> $encuentros
 * @var array<int, array<string, mixed>> $posiciones
 * @var array<string, string> $estados
 * @var array<string, string> $estadosPartido
 * @var array<string, string> $modalidades
 */

$puedeGestionar = can('deportes.gestionar');
$torneoId = (int) $torneo['id'];
$estadoTorneo = (string) $torneo['estado'];
$totalEquipos = count($equipos);
$totalEncuentros = count($encuentros);
$jugados = count(array_filter(
    $encuentros,
    static fn (array $encuentro): bool => in_array((string) $encuentro['estado'], ['jugado', 'walkover'], true)
));
$bloqueado = $estadoTorneo === 'anulado';
$fixtureGenerado = $totalEncuentros > 0;

$claseEstado = static function (string $valor): string {
    return match ($valor) {
        'en_curso'    => 'badge--ok',
        'planificado' => 'badge--info',
        'finalizado'  => 'badge--soft',
        'anulado'     => 'badge--danger',
        default       => 'badge--soft',
    };
};

$clasePartido = static function (string $valor): string {
    return match ($valor) {
        'jugado', 'walkover' => 'badge--ok',
        'programado'        => 'badge--info',
        'suspendido'        => 'badge--warn',
        'cancelado'         => 'badge--danger',
        default             => 'badge--soft',
    };
};

$marcador = static function (array $encuentro): string {
    if ($encuentro['marcador_local'] === null || $encuentro['marcador_visitante'] === null) {
        return '—';
    }

    return (int) $encuentro['marcador_local'] . ' – ' . (int) $encuentro['marcador_visitante'];
};

$puntos = static function (float|int|string $valor): string {
    $numero = (float) $valor;

    return rtrim(rtrim(number_format($numero, 1, '.', ''), '0'), '.');
};

$rangoFechas = static function (array $torneo): string {
    $inicio = $torneo['fecha_inicio'] ?? null;
    $fin = $torneo['fecha_fin'] ?? null;

    if ($inicio === null && $fin === null) {
        return 'Por definir';
    }

    if ($inicio !== null && $fin !== null) {
        return fecha_legible($inicio) . ' – ' . fecha_legible($fin);
    }

    return $inicio !== null ? 'Desde ' . fecha_legible($inicio) : 'Hasta ' . fecha_legible($fin);
};

$grupos = [];

foreach ($encuentros as $encuentro) {
    $clave = (string) ($encuentro['jornada_id'] ?? '0');

    if (!isset($grupos[$clave])) {
        $grupos[$clave] = [
            'nombre'  => (string) ($encuentro['jornada_nombre'] ?? 'Partidos sin jornada'),
            'numero'  => $encuentro['jornada_numero'] ?? null,
            'partidos' => [],
        ];
    }

    $grupos[$clave]['partidos'][] = $encuentro;
}

$fechaFixture = (string) ($torneo['fecha_inicio'] ?? '');
$fechaFixture = $fechaFixture !== '' ? $fechaFixture : date('Y-m-d');
?>

<div class="page-head">
    <div>
        <p class="page-head__eyebrow">
            <a href="<?= e(url('/deportes')) ?>">Deportes y torneos</a> ·
            <?= e((string) $torneo['deporte_nombre']) ?>
        </p>
        <h1><?= e((string) $torneo['nombre']) ?></h1>
        <p class="page-head__lead">
            <?= e((string) ($torneo['categoria'] ?? '')) ?>
            <?php if (!empty($torneo['categoria'])): ?> · <?php endif; ?>
            <?= e($modalidades[(string) $torneo['modalidad']] ?? (string) $torneo['modalidad']) ?>
        </p>
    </div>
    <div class="page-head__actions">
        <a class="btn btn--ghost" href="<?= e(url('/torneos/' . $torneoId)) ?>">Ver público</a>
        <?php if ($puedeGestionar): ?>
            <a class="btn btn--ghost" href="<?= e(url('/deportes/torneos/' . $torneoId . '/editar')) ?>">Editar torneo</a>
        <?php endif; ?>
        <a class="btn btn--ghost" href="<?= e(url('/deportes')) ?>">Volver</a>
    </div>
</div>

<div class="cards cards--4">
    <div class="card card--stat">
        <p class="card__label">Estado</p>
        <p class="card__value">
            <span class="badge <?= e($claseEstado($estadoTorneo)) ?>"><?= e($estados[$estadoTorneo] ?? $estadoTorneo) ?></span>
        </p>
        <p class="card__hint"><?= e($rangoFechas($torneo)) ?></p>
    </div>

    <div class="card card--stat">
        <p class="card__label">Equipos</p>
        <p class="card__value"><?= e((string) $totalEquipos) ?></p>
        <p class="card__hint">Inscritos en el torneo</p>
    </div>

    <div class="card card--stat">
        <p class="card__label">Partidos</p>
        <p class="card__value"><?= e((string) $totalEncuentros) ?></p>
        <p class="card__hint"><?= e((string) $jugados) ?> con resultado</p>
    </div>

    <div class="card card--stat">
        <p class="card__label">Formato</p>
        <p class="card__value card__value--muted" style="font-size:1rem;">
            <?= e($modalidades[(string) $torneo['modalidad']] ?? (string) $torneo['modalidad']) ?>
        </p>
        <p class="card__hint">Fixture automático disponible</p>
    </div>
</div>

<?php if (!empty($torneo['reglamento']) || !empty($torneo['observaciones'])): ?>
    <details class="details">
        <summary>Reglamento y observaciones</summary>
        <?php if (!empty($torneo['reglamento'])): ?>
            <h2 class="section-title">Reglamento</h2>
            <p><?= nl2br(e((string) $torneo['reglamento'])) ?></p>
        <?php endif; ?>
        <?php if (!empty($torneo['observaciones'])): ?>
            <h2 class="section-title">Observaciones internas</h2>
            <p><?= nl2br(e((string) $torneo['observaciones'])) ?></p>
        <?php endif; ?>
    </details>
<?php endif; ?>

<?php if ($puedeGestionar): ?>
    <div class="card">
        <div class="form--panel" style="margin-bottom:0;border:0;box-shadow:none;padding:0;background:transparent;">
            <div class="field">
                <label for="estado-torneo">Cambiar estado</label>
                <select id="estado-torneo" name="estado" form="form-estado-torneo">
                    <?php foreach ($estados as $clave => $etiqueta): ?>
                        <option value="<?= e((string) $clave) ?>" <?= $estadoTorneo === (string) $clave ? 'selected' : '' ?>>
                            <?= e((string) $etiqueta) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="field field--actions">
                <button type="submit" class="btn btn--ghost" form="form-estado-torneo">Actualizar estado</button>
            </div>
        </div>
        <form id="form-estado-torneo" method="post" action="<?= e(url('/deportes/torneos/' . $torneoId . '/estado')) ?>">
            <?= csrf_field() ?>
        </form>
    </div>
<?php endif; ?>

<h2 class="section-title">Equipos</h2>

<?php if ($bloqueado): ?>
    <div class="notice">El torneo está anulado y no admite modificaciones.</div>
<?php elseif ($fixtureGenerado): ?>
    <div class="notice">
        El fixture ya fue generado. Para inscribir o eliminar equipos, regenere el fixture
        (se eliminarán los resultados registrados).
    </div>
<?php endif; ?>

<?php if ($equipos === []): ?>
    <div class="empty-state">
        <span class="empty-state__icon"><?= Icons::render('socios', 'icon', 26) ?></span>
        <h2>Sin equipos inscritos</h2>
        <p>Inscriba al menos dos equipos para generar el fixture del torneo.</p>
    </div>
<?php else: ?>
    <div class="card">
        <div class="table-wrap">
            <table class="table">
                <thead>
                <tr>
                    <th scope="col">Equipo</th>
                    <th scope="col">Abreviatura</th>
                    <th scope="col">Delegado</th>
                    <th scope="col">Teléfono</th>
                    <?php if ($puedeGestionar && !$fixtureGenerado && !$bloqueado): ?>
                        <th scope="col"><span class="visually-hidden">Acciones</span></th>
                    <?php endif; ?>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($equipos as $equipo): ?>
                    <tr>
                        <td><strong><?= e((string) $equipo['nombre']) ?></strong></td>
                        <td><?= e((string) ($equipo['abreviatura'] ?? '—')) ?></td>
                        <td><?= e((string) ($equipo['delegado_nombre'] ?? '—')) ?></td>
                        <td><?= e((string) ($equipo['delegado_telefono'] ?? '—')) ?></td>
                        <?php if ($puedeGestionar && !$fixtureGenerado && !$bloqueado): ?>
                            <td class="table__actions">
                                <form method="post" action="<?= e(url('/deportes/torneos/' . $torneoId . '/equipos/' . (string) $equipo['id'] . '/eliminar')) ?>" data-confirmar="¿Eliminar este equipo del torneo?">
                                    <?= csrf_field() ?>
                                    <button type="submit" class="btn btn--ghost btn--sm">Eliminar</button>
                                </form>
                            </td>
                        <?php endif; ?>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
<?php endif; ?>

<?php if ($puedeGestionar && !$bloqueado && !$fixtureGenerado): ?>
    <details class="details" <?= old('nombre', null) !== null ? 'open' : '' ?>>
        <summary>Inscribir equipo</summary>
        <form class="form form--panel" method="post" action="<?= e(url('/deportes/torneos/' . $torneoId . '/equipos')) ?>">
            <?= csrf_field() ?>
            <div class="field">
                <label for="equipo-nombre">Nombre <span class="req">*</span></label>
                <input type="text" id="equipo-nombre" name="nombre" maxlength="120" required value="<?= e((string) old('nombre')) ?>" placeholder="Nombre del equipo">
            </div>
            <div class="field">
                <label for="equipo-abreviatura">Abreviatura</label>
                <input type="text" id="equipo-abreviatura" name="abreviatura" maxlength="10" value="<?= e((string) old('abreviatura')) ?>" placeholder="Máx. 10">
            </div>
            <div class="field">
                <label for="equipo-delegado">Delegado</label>
                <input type="text" id="equipo-delegado" name="delegado_nombre" maxlength="180" value="<?= e((string) old('delegado_nombre')) ?>" placeholder="Nombre y apellidos">
            </div>
            <div class="field">
                <label for="equipo-telefono">Teléfono</label>
                <input type="text" id="equipo-telefono" name="delegado_telefono" maxlength="30" value="<?= e((string) old('delegado_telefono')) ?>" placeholder="Contacto">
            </div>
            <div class="field field--actions">
                <button type="submit" class="btn btn--primary">Inscribir equipo</button>
            </div>
        </form>
    </details>
<?php endif; ?>

<h2 class="section-title">Fixture y resultados</h2>

<?php if ($totalEquipos < 2): ?>
    <div class="notice">Inscriba al menos dos equipos para generar el fixture.</div>
<?php elseif (!$fixtureGenerado): ?>
    <div class="card">
        <p class="muted">El sistema generará automáticamente todos los cruces entre los <?= e((string) $totalEquipos) ?> equipos, distribuidos en fechas semanales.</p>
        <?php if ($puedeGestionar && !$bloqueado): ?>
            <form class="form form--panel" method="post" action="<?= e(url('/deportes/torneos/' . $torneoId . '/fixture')) ?>">
                <?= csrf_field() ?>
                <div class="field">
                    <label for="fixture-fecha">Fecha de la primera jornada</label>
                    <input type="date" id="fixture-fecha" name="fecha_inicio" value="<?= e($fechaFixture) ?>" required>
                </div>
                <div class="field field--actions">
                    <button type="submit" class="btn btn--primary">Generar fixture</button>
                </div>
            </form>
        <?php endif; ?>
    </div>
<?php else: ?>
    <?php if ($puedeGestionar && !$bloqueado): ?>
        <details class="details">
            <summary>Regenerar fixture</summary>
            <div class="alert alert--danger" role="alert" style="margin-top:.75rem;">
                <?= Icons::render('alerta') ?>
                <p>Al regenerar se eliminarán los partidos y todos los resultados registrados. Esta acción no se puede deshacer.</p>
            </div>
            <form class="form form--panel" method="post" action="<?= e(url('/deportes/torneos/' . $torneoId . '/fixture')) ?>" data-confirmar="¿Regenerar el fixture y eliminar los resultados actuales?">
                <?= csrf_field() ?>
                <input type="hidden" name="reemplazar" value="1">
                <div class="field">
                    <label for="fixture-fecha-regenerar">Fecha de la primera jornada</label>
                    <input type="date" id="fixture-fecha-regenerar" name="fecha_inicio" value="<?= e($fechaFixture) ?>" required>
                </div>
                <div class="field field--actions">
                    <button type="submit" class="btn btn--danger">Regenerar fixture</button>
                </div>
            </form>
        </details>
    <?php endif; ?>

    <?php foreach ($grupos as $grupo): ?>
        <div class="card" style="margin-top:1rem;">
            <h3 style="margin:0 0 .75rem;font-size:1rem;">
                <?= e((string) $grupo['nombre']) ?>
                <?php if ($grupo['numero'] !== null): ?>
                    <span class="muted">· Fecha <?= e((string) $grupo['numero']) ?></span>
                <?php endif; ?>
            </h3>
            <div class="table-wrap">
                <table class="table">
                    <thead>
                    <tr>
                        <th scope="col">Fecha</th>
                        <th scope="col">Local</th>
                        <th scope="col">Marcador</th>
                        <th scope="col">Visitante</th>
                        <th scope="col">Sede</th>
                        <th scope="col">Estado</th>
                        <?php if ($puedeGestionar && !$bloqueado): ?>
                            <th scope="col"><span class="visually-hidden">Acciones</span></th>
                        <?php endif; ?>
                    </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($grupo['partidos'] as $encuentro): ?>
                        <tr>
                            <td>
                                <?= e(fecha_legible((string) $encuentro['fecha'])) ?>
                                <br><span class="muted"><?= e(hora($encuentro['hora'] ?? null, 'Por programar')) ?></span>
                            </td>
                            <td><strong><?= e((string) ($encuentro['local_nombre'] ?? 'Equipo local')) ?></strong></td>
                            <td><span class="match-score"><?= e($marcador($encuentro)) ?></span></td>
                            <td><strong><?= e((string) ($encuentro['visitante_nombre'] ?? 'Equipo visitante')) ?></strong></td>
                            <td><?= e((string) ($encuentro['instalacion_nombre'] ?? 'Por definir')) ?></td>
                            <td>
                                <span class="badge <?= e($clasePartido((string) $encuentro['estado'])) ?>">
                                    <?= e($estadosPartido[(string) $encuentro['estado']] ?? (string) $encuentro['estado']) ?>
                                </span>
                            </td>
                            <?php if ($puedeGestionar && !$bloqueado): ?>
                                <td class="table__actions">
                                    <a class="btn btn--ghost btn--sm" href="<?= e(url('/deportes/encuentros/' . (string) $encuentro['id'] . '/editar')) ?>">Editar</a>
                                </td>
                            <?php endif; ?>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    <?php endforeach; ?>
<?php endif; ?>

<?php if ($posiciones !== [] && (string) $torneo['modalidad'] === 'todos_contra_todos'): ?>
    <h2 class="section-title">Tabla de posiciones</h2>
    <div class="card">
        <div class="table-wrap">
            <table class="table table--standings">
                <thead>
                <tr>
                    <th scope="col">#</th>
                    <th scope="col">Equipo</th>
                    <th scope="col">PJ</th>
                    <th scope="col">G</th>
                    <th scope="col">E</th>
                    <th scope="col">P</th>
                    <th scope="col">GF</th>
                    <th scope="col">GC</th>
                    <th scope="col">DG</th>
                    <th scope="col">Pts</th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($posiciones as $fila): ?>
                    <tr class="<?= (int) $fila['posicion'] === 1 ? 'is-leader' : '' ?>">
                        <td><?= e((string) $fila['posicion']) ?></td>
                        <td><strong><?= e((string) $fila['nombre']) ?></strong></td>
                        <td><?= e((string) $fila['jugados']) ?></td>
                        <td><?= e((string) $fila['ganados']) ?></td>
                        <td><?= e((string) $fila['empatados']) ?></td>
                        <td><?= e((string) $fila['perdidos']) ?></td>
                        <td><?= e((string) $fila['goles_favor']) ?></td>
                        <td><?= e((string) $fila['goles_contra']) ?></td>
                        <td><?= e((string) $fila['diferencia']) ?></td>
                        <td><strong><?= e($puntos($fila['puntos'])) ?></strong></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <p class="field__hint">PJ: partidos jugados · G: ganados · E: empatados · P: perdidos · GF/GC: goles a favor y en contra · DG: diferencia · Pts: puntos.</p>
    </div>
<?php endif; ?>

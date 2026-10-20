<?php

declare(strict_types=1);

use App\Core\Icons;

/**
 * Detalle público de un torneo.
 *
 * @var array<string, mixed> $torneo
 * @var array<int, array<string, mixed>> $equipos
 * @var array<int, array<string, mixed>> $encuentros
 * @var array<int, array<string, mixed>> $posiciones
 * @var array<string, string> $estados
 * @var array<string, string> $estadosPartido
 * @var array<string, string> $modalidades
 */

$estadoTorneo = (string) $torneo['estado'];
$totalEquipos = count($equipos);
$totalEncuentros = count($encuentros);
$jugados = count(array_filter(
    $encuentros,
    static fn (array $encuentro): bool => in_array((string) $encuentro['estado'], ['jugado', 'walkover'], true)
));

$claseEstado = static function (string $valor): string {
    return match ($valor) {
        'en_curso'    => 'badge--ok',
        'planificado' => 'badge--info',
        'finalizado'  => 'badge--soft',
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
            'nombre'   => (string) ($encuentro['jornada_nombre'] ?? 'Partidos sin jornada'),
            'numero'   => $encuentro['jornada_numero'] ?? null,
            'partidos' => [],
        ];
    }

    $grupos[$clave]['partidos'][] = $encuentro;
}
?>

<div class="page-head">
    <div>
        <p class="page-head__eyebrow">
            <a href="<?= e(url('/torneos')) ?>">Torneos</a> · <?= e((string) $torneo['deporte_nombre']) ?>
        </p>
        <h1><?= e((string) $torneo['nombre']) ?></h1>
        <p class="page-head__lead">
            <?= e((string) ($torneo['categoria'] ?? 'Categoría libre')) ?> ·
            <?= e($modalidades[(string) $torneo['modalidad']] ?? (string) $torneo['modalidad']) ?> ·
            <?= e($rangoFechas($torneo)) ?>
        </p>
    </div>
    <div class="page-head__actions">
        <a class="btn btn--ghost" href="<?= e(url('/torneos')) ?>">Todos los torneos</a>
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
        <p class="card__hint">Participantes</p>
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
        <p class="card__hint">Fixture y posiciones</p>
    </div>
</div>

<?php if (!empty($torneo['reglamento'])): ?>
    <details class="details">
        <summary>Reglamento del torneo</summary>
        <p><?= nl2br(e((string) $torneo['reglamento'])) ?></p>
    </details>
<?php endif; ?>

<?php if ($equipos === []): ?>
    <div class="empty-state">
        <span class="empty-state__icon"><?= Icons::render('deportes', 'icon', 26) ?></span>
        <h2>Equipos por confirmar</h2>
        <p>El fixture y la tabla de posiciones se publicarán cuando se inscriban los equipos.</p>
    </div>
<?php else: ?>
    <h2 class="section-title">Equipos inscritos</h2>
    <div class="card">
        <ul class="plain-list">
            <?php foreach ($equipos as $equipo): ?>
                <li>
                    <strong><?= e((string) $equipo['nombre']) ?></strong>
                    <?php if (!empty($equipo['abreviatura'])): ?>
                        <span class="muted">(<?= e((string) $equipo['abreviatura']) ?>)</span>
                    <?php endif; ?>
                </li>
            <?php endforeach; ?>
        </ul>
    </div>
<?php endif; ?>

<h2 class="section-title">Fixture y resultados</h2>

<?php if ($encuentros === []): ?>
    <div class="empty-state">
        <span class="empty-state__icon"><?= Icons::render('calendario', 'icon', 26) ?></span>
        <h2>Fixture por publicar</h2>
        <p>Los partidos se mostrarán aquí cuando la organización los programe.</p>
    </div>
<?php else: ?>
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

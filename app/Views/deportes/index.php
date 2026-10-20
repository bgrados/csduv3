<?php

declare(strict_types=1);

use App\Core\Icons;

/**
 * Listado administrativo de torneos.
 *
 * @var array<int, array<string, mixed>> $torneos
 * @var array<int, array<string, mixed>> $deportes
 * @var array<string, string> $modalidades
 * @var array<string, string> $estados
 * @var string $busqueda
 * @var int $deporteId
 * @var string $estado
 * @var int $total
 */

$puedeGestionar = can('deportes.gestionar');
$hayFiltros = $busqueda !== '' || $deporteId > 0 || $estado !== '';

$claseEstado = static function (string $valor): string {
    return match ($valor) {
        'en_curso'    => 'badge--ok',
        'planificado' => 'badge--info',
        'finalizado'  => 'badge--soft',
        'anulado'     => 'badge--danger',
        default       => 'badge--soft',
    };
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
?>

<div class="page-head">
    <div>
        <p class="page-head__eyebrow">Operación</p>
        <h1>Deportes y torneos</h1>
        <p class="page-head__lead">
            Organice campeonatos por disciplina, inscriba equipos, genere el fixture y registre
            resultados. La tabla de posiciones se calcula automáticamente.
        </p>
    </div>
    <div class="page-head__actions">
        <a class="btn btn--ghost" href="<?= e(url('/torneos')) ?>">Ver portal de torneos</a>
        <?php if ($puedeGestionar): ?>
            <a class="btn btn--primary" href="<?= e(url('/deportes/torneos/nuevo')) ?>">
                <?= Icons::render('deportes', 'icon', 18) ?> Nuevo torneo
            </a>
        <?php endif; ?>
    </div>
</div>

<form class="form form--filters" method="get" action="<?= e(url('/deportes')) ?>">
    <div class="field">
        <label for="q">Buscar</label>
        <input type="search" id="q" name="q" value="<?= e($busqueda) ?>" placeholder="Nombre o categoría">
    </div>

    <div class="field">
        <label for="deporte_id">Disciplina</label>
        <select id="deporte_id" name="deporte_id">
            <option value="0">Todas</option>
            <?php foreach ($deportes as $deporte): ?>
                <option value="<?= e((string) $deporte['id']) ?>" <?= $deporteId === (int) $deporte['id'] ? 'selected' : '' ?>>
                    <?= e((string) $deporte['nombre']) ?><?= (int) $deporte['activo'] === 1 ? '' : ' (inactiva)' ?>
                </option>
            <?php endforeach; ?>
        </select>
    </div>

    <div class="field">
        <label for="estado">Estado</label>
        <select id="estado" name="estado">
            <option value="">Todos</option>
            <?php foreach ($estados as $clave => $etiqueta): ?>
                <option value="<?= e((string) $clave) ?>" <?= $estado === (string) $clave ? 'selected' : '' ?>>
                    <?= e((string) $etiqueta) ?>
                </option>
            <?php endforeach; ?>
        </select>
    </div>

    <div class="field field--actions">
        <button type="submit" class="btn btn--ghost">Filtrar</button>
        <?php if ($hayFiltros): ?>
            <a class="btn btn--ghost" href="<?= e(url('/deportes')) ?>">Limpiar</a>
        <?php endif; ?>
    </div>
</form>

<?php if ($deportes === []): ?>
    <div class="notice">
        No hay disciplinas deportivas activas. Importe la información inicial de la base de datos
        para habilitar Fútbol y las demás disciplinas.
    </div>
<?php endif; ?>

<?php if ($torneos === []): ?>
    <div class="empty-state">
        <span class="empty-state__icon"><?= Icons::render('deportes', 'icon', 26) ?></span>
        <h2><?= $hayFiltros ? 'Sin coincidencias' : 'Todavía no hay torneos' ?></h2>
        <p>
            <?php if ($hayFiltros): ?>
                No se encontraron torneos con los filtros seleccionados.
            <?php else: ?>
                Cree el primer campeonato, inscriba los equipos y genere el fixture de todos contra todos.
            <?php endif; ?>
        </p>
        <?php if ($puedeGestionar && !$hayFiltros): ?>
            <p><a class="btn btn--primary" href="<?= e(url('/deportes/torneos/nuevo')) ?>">Crear el primer torneo</a></p>
        <?php endif; ?>
    </div>
<?php else: ?>
    <div class="card">
        <div class="table-wrap">
            <table class="table">
                <thead>
                <tr>
                    <th scope="col">Torneo</th>
                    <th scope="col">Disciplina</th>
                    <th scope="col">Fechas</th>
                    <th scope="col">Equipos</th>
                    <th scope="col">Partidos</th>
                    <th scope="col">Estado</th>
                    <th scope="col"><span class="visually-hidden">Acciones</span></th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($torneos as $torneo): ?>
                    <?php
                    $estadoTorneo = (string) $torneo['estado'];
                    $totalEquipos = (int) ($torneo['total_equipos'] ?? 0);
                    $totalEncuentros = (int) ($torneo['total_encuentros'] ?? 0);
                    $jugados = (int) ($torneo['encuentros_jugados'] ?? 0);
                    ?>
                    <tr>
                        <td>
                            <strong><?= e((string) $torneo['nombre']) ?></strong>
                            <?php if (!empty($torneo['categoria'])): ?>
                                <br><span class="muted"><?= e((string) $torneo['categoria']) ?></span>
                            <?php endif; ?>
                            <br><span class="muted"><?= e($modalidades[(string) $torneo['modalidad']] ?? (string) $torneo['modalidad']) ?></span>
                        </td>
                        <td><?= e((string) $torneo['deporte_nombre']) ?></td>
                        <td><?= e($rangoFechas($torneo)) ?></td>
                        <td><?= e((string) $totalEquipos) ?></td>
                        <td>
                            <?= e((string) $totalEncuentros) ?>
                            <?php if ($totalEncuentros > 0): ?>
                                <br><span class="muted"><?= e((string) $jugados) ?> jugados</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <span class="badge <?= e($claseEstado($estadoTorneo)) ?>">
                                <?= e($estados[$estadoTorneo] ?? $estadoTorneo) ?>
                            </span>
                        </td>
                        <td class="table__actions">
                            <a class="btn btn--ghost btn--sm" href="<?= e(url('/deportes/torneos/' . (string) $torneo['id'])) ?>">Ver</a>
                            <?php if ($puedeGestionar): ?>
                                <a class="btn btn--ghost btn--sm" href="<?= e(url('/deportes/torneos/' . (string) $torneo['id'] . '/editar')) ?>">Editar</a>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <p class="section-actions muted">
        <?= e((string) $total) ?> torneo<?= $total === 1 ? '' : 's' ?> registrado<?= $total === 1 ? '' : 's' ?>.
    </p>
<?php endif; ?>

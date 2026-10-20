<?php

declare(strict_types=1);

use App\Core\Icons;

/**
 * Portal público de torneos.
 *
 * @var array<int, array<string, mixed>> $torneos
 * @var array<string, string> $estados
 * @var array<string, string> $modalidades
 */

$claseEstado = static function (string $valor): string {
    return match ($valor) {
        'en_curso'    => 'badge--ok',
        'planificado' => 'badge--info',
        'finalizado'  => 'badge--soft',
        default       => 'badge--soft',
    };
};

$rangoFechas = static function (array $torneo): string {
    $inicio = $torneo['fecha_inicio'] ?? null;
    $fin = $torneo['fecha_fin'] ?? null;

    if ($inicio === null && $fin === null) {
        return 'Fechas por definir';
    }

    if ($inicio !== null && $fin !== null) {
        return fecha_legible($inicio) . ' – ' . fecha_legible($fin);
    }

    return $inicio !== null ? 'Desde ' . fecha_legible($inicio) : 'Hasta ' . fecha_legible($fin);
};
?>

<div class="page-head">
    <div>
        <p class="page-head__eyebrow">Deportes</p>
        <h1>Torneos</h1>
        <p class="page-head__lead">
            Consulte los campeonatos del club, los equipos inscritos, el fixture y la tabla de posiciones.
        </p>
    </div>
</div>

<?php if ($torneos === []): ?>
    <div class="empty-state">
        <span class="empty-state__icon"><?= Icons::render('deportes', 'icon', 26) ?></span>
        <h2>Todavía no hay torneos publicados</h2>
        <p>
            Cuando la institución organice un campeonato, aquí se mostrarán el fixture,
            los resultados y la tabla de posiciones.
        </p>
        <p><a class="btn btn--ghost" href="<?= e(url('/alquiler')) ?>">Ver ambientes y tarifas</a></p>
    </div>
<?php else: ?>
    <div class="cards cards--3">
        <?php foreach ($torneos as $torneo): ?>
            <?php
            $estado = (string) $torneo['estado'];
            $totalEquipos = (int) ($torneo['total_equipos'] ?? 0);
            $totalEncuentros = (int) ($torneo['total_encuentros'] ?? 0);
            ?>
            <article class="card">
                <p class="card__label"><?= e((string) $torneo['deporte_nombre']) ?></p>
                <h2 style="margin:.25rem 0 .4rem;font-size:1.15rem;">
                    <?= e((string) $torneo['nombre']) ?>
                </h2>
                <p class="muted" style="margin:0 0 .6rem;">
                    <?= e((string) ($torneo['categoria'] ?? 'Categoría libre')) ?> ·
                    <?= e($modalidades[(string) $torneo['modalidad']] ?? (string) $torneo['modalidad']) ?>
                </p>

                <span class="badge <?= e($claseEstado($estado)) ?>"><?= e($estados[$estado] ?? $estado) ?></span>

                <ul class="facts">
                    <li><span>Fechas</span><strong><?= e($rangoFechas($torneo)) ?></strong></li>
                    <li><span>Equipos</span><strong><?= e((string) $totalEquipos) ?></strong></li>
                    <li><span>Partidos</span><strong><?= e((string) $totalEncuentros) ?></strong></li>
                </ul>

                <p class="card__actions">
                    <a class="btn btn--primary btn--sm" href="<?= e(url('/torneos/' . (string) $torneo['id'])) ?>">Ver fixture y posiciones</a>
                </p>
            </article>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<?php

declare(strict_types=1);

use App\Core\Icons;

/**
 * Reportes administrativos de caja.
 *
 * @var string $desde
 * @var string $hasta
 * @var array{ingresos: float, egresos: float, saldo: float, cantidad: int} $resumen
 * @var array<int, array<string, mixed>> $categorias
 * @var array<int, array<string, mixed>> $mensual
 */

$ingresos = array_values(array_filter($categorias, static fn (array $fila): bool => (string) $fila['tipo'] === 'ingreso'));
$egresos = array_values(array_filter($categorias, static fn (array $fila): bool => (string) $fila['tipo'] === 'egreso'));

$mes = static function (string $periodo): string {
    $partes = explode('-', $periodo);

    if (count($partes) !== 2) {
        return $periodo;
    }

    $meses = [
        '01' => 'Enero', '02' => 'Febrero', '03' => 'Marzo', '04' => 'Abril',
        '05' => 'Mayo', '06' => 'Junio', '07' => 'Julio', '08' => 'Agosto',
        '09' => 'Setiembre', '10' => 'Octubre', '11' => 'Noviembre', '12' => 'Diciembre',
    ];

    return ($meses[$partes[1]] ?? $partes[1]) . ' ' . $partes[0];
};
?>

<div class="page-head">
    <div>
        <p class="page-head__eyebrow">
            <a href="<?= e(url('/caja')) ?>">Caja</a> · reportes
        </p>
        <h1>Reportes de caja</h1>
        <p class="page-head__lead">
            Resumen del período indicado. Es un reporte **administrativo**: no
            constituye contabilidad tributaria ni comprobantes electrónicos.
        </p>
    </div>
    <div class="page-head__actions">
        <a class="btn btn--ghost" href="<?= e(url('/caja')) ?>">Volver a caja</a>
    </div>
</div>

<form class="form form--filters" method="get" action="<?= e(url('/caja/reportes')) ?>">
    <div class="field">
        <label for="desde">Desde</label>
        <input type="date" id="desde" name="desde" value="<?= e($desde) ?>">
    </div>

    <div class="field">
        <label for="hasta">Hasta</label>
        <input type="date" id="hasta" name="hasta" value="<?= e($hasta) ?>">
    </div>

    <div class="field field--actions">
        <button type="submit" class="btn btn--ghost">Ver reporte</button>
    </div>
</form>

<div class="cards cards--3">
    <div class="card card--stat">
        <p class="card__label">Ingresos</p>
        <p class="card__value"><?= e(dinero($resumen['ingresos'])) ?></p>
        <p class="card__hint"><?= e(fecha_legible($desde)) ?> — <?= e(fecha_legible($hasta)) ?></p>
    </div>

    <div class="card card--stat">
        <p class="card__label">Egresos</p>
        <p class="card__value"><?= e(dinero($resumen['egresos'])) ?></p>
        <p class="card__hint"><?= e((string) $resumen['cantidad']) ?> movimientos</p>
    </div>

    <div class="card card--stat">
        <p class="card__label">Saldo</p>
        <p class="card__value"><?= e(dinero($resumen['saldo'])) ?></p>
        <p class="card__hint">Ingresos menos egresos</p>
    </div>
</div>

<h2 class="section-title">Por categoría</h2>

<?php if ($categorias === []): ?>
    <div class="empty-state">
        <span class="empty-state__icon"><?= Icons::render('caja', 'icon', 26) ?></span>
        <h2>Sin movimientos en el período</h2>
        <p>No hay ingresos ni egresos registrados entre las fechas indicadas.</p>
    </div>
<?php else: ?>
    <div class="cards">
        <?php foreach ([['Ingresos', $ingresos], ['Egresos', $egresos]] as [$titulo, $filas]): ?>
            <div class="card">
                <h3 style="margin:0 0 .75rem;font-size:1rem;"><?= e((string) $titulo) ?></h3>

                <?php if ($filas === []): ?>
                    <p class="muted">Sin movimientos de este tipo.</p>
                <?php else: ?>
                    <div class="table-wrap">
                        <table class="table">
                            <thead>
                            <tr>
                                <th scope="col">Categoría</th>
                                <th scope="col">Operaciones</th>
                                <th scope="col">Total</th>
                            </tr>
                            </thead>
                            <tbody>
                            <?php foreach ($filas as $fila): ?>
                                <tr>
                                    <td><?= e((string) $fila['categoria']) ?></td>
                                    <td><?= e((string) $fila['operaciones']) ?></td>
                                    <td><strong><?= e(dinero((string) $fila['total'])) ?></strong></td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
    </div>

    <h2 class="section-title">Por mes</h2>

    <div class="card">
        <div class="table-wrap">
            <table class="table">
                <thead>
                <tr>
                    <th scope="col">Mes</th>
                    <th scope="col">Ingresos</th>
                    <th scope="col">Egresos</th>
                    <th scope="col">Saldo</th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($mensual as $fila): ?>
                    <tr>
                        <td><?= e($mes((string) $fila['periodo'])) ?></td>
                        <td><?= e(dinero((string) $fila['ingresos'])) ?></td>
                        <td><?= e(dinero((string) $fila['egresos'])) ?></td>
                        <td><strong><?= e(dinero((float) $fila['ingresos'] - (float) $fila['egresos'])) ?></strong></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
<?php endif; ?>

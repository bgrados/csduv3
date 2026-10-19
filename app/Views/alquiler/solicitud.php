<?php

declare(strict_types=1);

/**
 * Formulario público de solicitud de alquiler.
 *
 * Formulario corto y adaptable a pantallas táctiles. No confirma nada: solo
 * registra la solicitud para revisión del personal autorizado.
 *
 * @var array<int, array<string, mixed>> $instalaciones
 * @var array<int, array<string, mixed>> $tiposEvento
 * @var array<string, mixed> $prefill
 * @var int $marca
 */

$valor = static function (string $campo, mixed $porDefecto = '') use ($prefill): string {
    $previo = old($campo, null);

    if ($previo !== null && $previo !== '') {
        return (string) $previo;
    }

    return (string) ($prefill[$campo] ?? $porDefecto);
};
?>

<div class="page-head">
    <div>
        <p class="page-head__eyebrow">Portal de alquileres</p>
        <h1>Solicitar alquiler</h1>
        <p class="page-head__lead">
            Complete los datos del evento. La solicitud queda pendiente de revisión y no
            confirma la reserva por sí sola.
        </p>
    </div>
</div>

<?php if ($instalaciones === []): ?>
    <div class="empty-state">
        <span class="empty-state__icon"><?= \App\Core\Icons::render('alquileres', 'icon', 26) ?></span>
        <h2>Sin ambientes publicados</h2>
        <p>
            El club todavía no publicó ambientes disponibles para alquiler.
            Comuníquese directamente con la institución.
        </p>
    </div>
<?php else: ?>
    <form class="form" method="post" action="<?= e(url('/alquiler/solicitud')) ?>" novalidate>
        <?= csrf_field() ?>
        <input type="hidden" name="_ts" value="<?= e((string) $marca) ?>">

        <div class="honeypot" aria-hidden="true">
            <label for="sitio_web">No completar este campo</label>
            <input type="text" id="sitio_web" name="sitio_web" tabindex="-1" autocomplete="off">
        </div>

        <fieldset class="form__group">
            <legend>Ambiente y fecha</legend>

            <div class="form-grid">
                <div class="field">
                    <label for="instalacion_id">Ambiente <span class="req">*</span></label>
                    <select id="instalacion_id" name="instalacion_id" required>
                        <option value="">Seleccione un ambiente</option>
                        <?php foreach ($instalaciones as $opcion): ?>
                            <option
                                value="<?= e((string) $opcion['id']) ?>"
                                <?= $valor('instalacion_id') === (string) $opcion['id'] ? 'selected' : '' ?>
                            ><?= e((string) $opcion['nombre']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="field">
                    <label for="tipo_evento_id">Tipo de uso</label>
                    <select id="tipo_evento_id" name="tipo_evento_id">
                        <option value="">Sin especificar</option>
                        <?php foreach ($tiposEvento as $tipo): ?>
                            <option
                                value="<?= e((string) $tipo['id']) ?>"
                                <?= $valor('tipo_evento_id') === (string) $tipo['id'] ? 'selected' : '' ?>
                            ><?= e((string) $tipo['nombre']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="field">
                    <label for="fecha_evento">Fecha del evento <span class="req">*</span></label>
                    <input type="date" id="fecha_evento" name="fecha_evento" required value="<?= e($valor('fecha_evento')) ?>">
                </div>

                <div class="field">
                    <label for="cantidad_personas">Cantidad estimada de personas</label>
                    <input type="number" id="cantidad_personas" name="cantidad_personas" min="1" max="1000" inputmode="numeric" value="<?= e($valor('cantidad_personas')) ?>">
                </div>

                <div class="field">
                    <label for="hora_inicio">Hora de inicio <span class="req">*</span></label>
                    <input type="time" id="hora_inicio" name="hora_inicio" required value="<?= e($valor('hora_inicio')) ?>">
                </div>

                <div class="field">
                    <label for="hora_fin">Hora de fin <span class="req">*</span></label>
                    <input type="time" id="hora_fin" name="hora_fin" required value="<?= e($valor('hora_fin')) ?>">
                </div>
            </div>
        </fieldset>

        <fieldset class="form__group">
            <legend>Datos de contacto</legend>

            <div class="form-grid">
                <div class="field">
                    <label for="solicitante_nombre">Nombre y apellidos <span class="req">*</span></label>
                    <input type="text" id="solicitante_nombre" name="solicitante_nombre" maxlength="150" required value="<?= e($valor('solicitante_nombre')) ?>" autocomplete="name">
                </div>

                <div class="field">
                    <label for="solicitante_documento">Documento de identidad</label>
                    <input type="text" id="solicitante_documento" name="solicitante_documento" maxlength="30" value="<?= e($valor('solicitante_documento')) ?>">
                </div>

                <div class="field">
                    <label for="solicitante_telefono">Teléfono o celular <span class="req">*</span></label>
                    <input type="tel" id="solicitante_telefono" name="solicitante_telefono" maxlength="30" required value="<?= e($valor('solicitante_telefono')) ?>" autocomplete="tel" inputmode="tel">
                    <p class="field__hint">Se usa únicamente para responderle sobre esta solicitud.</p>
                </div>

                <div class="field">
                    <label for="solicitante_email">Correo electrónico</label>
                    <input type="email" id="solicitante_email" name="solicitante_email" maxlength="160" value="<?= e($valor('solicitante_email')) ?>" autocomplete="email">
                </div>
            </div>
        </fieldset>

        <fieldset class="form__group">
            <legend>Detalles del evento</legend>

            <div class="field">
                <label for="descripcion">Descripción breve</label>
                <textarea id="descripcion" name="descripcion" rows="4" maxlength="1000" placeholder="Cuéntenos brevemente qué actividad realizará (opcional)"><?= e($valor('descripcion')) ?></textarea>
            </div>

            <div class="check">
                <input type="checkbox" id="acepta_condiciones" name="acepta_condiciones" value="1" required>
                <label for="acepta_condiciones">
                    Confirmo que los datos son correctos y entiendo que esta solicitud
                    <strong>no confirma</strong> el alquiler: el club debe revisarla y comunicarse conmigo.
                </label>
            </div>
        </fieldset>

        <div class="form__actions">
            <button type="submit" class="btn btn--primary">Enviar solicitud</button>
            <a class="btn btn--ghost" href="<?= e(url('/alquiler/disponibilidad')) ?>">Ver disponibilidad</a>
        </div>
    </form>
<?php endif; ?>

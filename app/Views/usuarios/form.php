<?php

declare(strict_types=1);

/**
 * Formulario de alta y edición de usuarios.
 *
 * @var array<string, mixed>|null $usuario
 * @var array<int, array<string, mixed>> $roles
 * @var string $modo  'crear' | 'editar'
 */

$esEdicion = $modo === 'editar' && $usuario !== null;
$propioId = (int) (auth()['id'] ?? 0);
$esPropio = $esEdicion && (int) $usuario['id'] === $propioId;

$valor = static function (string $campo, mixed $porDefecto = '') use ($usuario): string {
    $previo = old($campo, null);

    if ($previo !== null && $previo !== '') {
        return (string) $previo;
    }

    if ($usuario !== null && array_key_exists($campo, $usuario)) {
        return (string) ($usuario[$campo] ?? '');
    }

    return (string) $porDefecto;
};

$accion = $esEdicion ? url('/usuarios/' . (string) $usuario['id']) : url('/usuarios');
?>

<div class="page-head">
    <div>
        <p class="page-head__eyebrow"><a href="<?= e(url('/usuarios')) ?>">Usuarios</a> · <?= $esEdicion ? 'edición' : 'alta' ?></p>
        <h1><?= $esEdicion ? 'Editar usuario' : 'Nuevo usuario' ?></h1>
        <p class="page-head__lead">
            El rol determina a qué módulos puede acceder la persona. Los permisos se aplican
            en el servidor en cada petición.
        </p>
    </div>
</div>

<form class="form" method="post" action="<?= e($accion) ?>">
    <?= csrf_field() ?>

    <fieldset class="form__group">
        <legend>Datos de la persona</legend>

        <div class="form-grid">
            <div class="field">
                <label for="nombres">Nombres <span class="req">*</span></label>
                <input type="text" id="nombres" name="nombres" maxlength="100" required value="<?= e($valor('nombres')) ?>">
            </div>

            <div class="field">
                <label for="apellidos">Apellidos <span class="req">*</span></label>
                <input type="text" id="apellidos" name="apellidos" maxlength="100" required value="<?= e($valor('apellidos')) ?>">
            </div>

            <div class="field">
                <label for="telefono">Teléfono</label>
                <input type="tel" id="telefono" name="telefono" maxlength="30" value="<?= e($valor('telefono')) ?>">
            </div>

            <div class="field">
                <label for="cargo_institucional">Cargo institucional</label>
                <input type="text" id="cargo_institucional" name="cargo_institucional" maxlength="120" value="<?= e($valor('cargo_institucional')) ?>" placeholder="Por ejemplo: Tesorero">
                <p class="field__hint">Es informativo; no otorga permisos por sí solo.</p>
            </div>
        </div>
    </fieldset>

    <fieldset class="form__group">
        <legend>Acceso al sistema</legend>

        <div class="form-grid">
            <div class="field">
                <label for="usuario">Nombre de usuario <span class="req">*</span></label>
                <input type="text" id="usuario" name="usuario" maxlength="60" required value="<?= e($valor('usuario')) ?>" autocomplete="off">
                <p class="field__hint">Solo letras, números, punto, guion y guion bajo.</p>
            </div>

            <div class="field">
                <label for="email">Correo electrónico</label>
                <input type="email" id="email" name="email" maxlength="160" value="<?= e($valor('email')) ?>" autocomplete="off">
            </div>

            <div class="field">
                <label for="rol_id">Rol <span class="req">*</span></label>
                <select id="rol_id" name="rol_id" required>
                    <option value="">Seleccione un rol</option>
                    <?php foreach ($roles as $rol): ?>
                        <option
                            value="<?= e((string) $rol['id']) ?>"
                            <?= $valor('rol_id') === (string) $rol['id'] ? 'selected' : '' ?>
                        ><?= e((string) $rol['nombre']) ?></option>
                    <?php endforeach; ?>
                </select>
                <?php if ($esPropio): ?>
                    <p class="field__hint">No puede cambiar su propio rol.</p>
                <?php endif; ?>
            </div>

            <div class="field">
                <label for="activo">Estado de la cuenta</label>
                <div class="check">
                    <input
                        type="checkbox"
                        id="activo"
                        name="activo"
                        value="1"
                        <?= ($esEdicion ? ((int) $usuario['activo'] === 1) : true) ? 'checked' : '' ?>
                    >
                    <label for="activo">Cuenta habilitada para iniciar sesión</label>
                </div>
                <?php if ($esPropio): ?>
                    <p class="field__hint">No puede desactivar su propia cuenta.</p>
                <?php endif; ?>
            </div>
        </div>
    </fieldset>

    <div class="form__actions">
        <button type="submit" class="btn btn--primary"><?= $esEdicion ? 'Guardar cambios' : 'Crear usuario' ?></button>
        <a class="btn btn--ghost" href="<?= e(url('/usuarios')) ?>">Cancelar</a>
    </div>
</form>

<?php if (!$esEdicion): ?>
    <section class="card">
        <h2>Contraseña inicial</h2>
        <p class="muted">
            La contraseña se asigna después de crear la cuenta. La persona deberá cambiarla
            en su primer ingreso.
        </p>
    </section>
<?php endif; ?>

<?php if ($esEdicion): ?>
    <section class="card">
        <h2>Restablecer contraseña</h2>
        <p class="muted">
            Use esta opción si la persona perdió su contraseña. La contraseña actual no puede
            consultarse porque se guarda cifrada.
        </p>

        <form class="form" method="post" action="<?= e(url('/usuarios/' . (string) $usuario['id'] . '/password')) ?>">
            <?= csrf_field() ?>

            <div class="form-grid">
                <div class="field">
                    <label for="password">Nueva contraseña</label>
                    <input type="password" id="password" name="password" required autocomplete="new-password">
                    <p class="field__hint">Mínimo 10 caracteres, con al menos una letra y un número.</p>
                </div>

                <div class="field">
                    <label for="password_confirmacion">Repita la nueva contraseña</label>
                    <input type="password" id="password_confirmacion" name="password_confirmacion" required autocomplete="new-password">
                </div>
            </div>

            <div class="form__actions">
                <button type="submit" class="btn btn--danger">Restablecer contraseña</button>
            </div>
        </form>
    </section>
<?php endif; ?>

<?php

declare(strict_types=1);

/*
 |--------------------------------------------------------------------------
 | CSDUV3 - Procedimiento temporal para crear el primer administrador
 |--------------------------------------------------------------------------
 |
 | Este archivo se ejecuta ÚNICAMENTE desde la línea de comandos (CLI). Si se
 | abre por el navegador, responde 404 y no hace nada.
 |
 | Uso:
 |     php tools/crear_admin.php
 |     php tools/crear_admin.php --permitir-otro    (para crear un segundo admin)
 |
 | Por qué es temporal:
 |   El club no tiene todavía un administrador con el que crear cuentas desde
 |   la interfaz. Este script resuelve ese único arranque. Cuando ya exista al
 |   menos un administrador activo, ELIMINE este archivo del servidor.
 |
 | Seguridad:
 |   * La contraseña nunca se recibe como argumento (quedaría en el historial
 |     del shell). Siempre se solicita de forma interactiva.
 |   * La contraseña cumple las mismas reglas que en la interfaz web.
 |   * La cuenta se crea con la marca «debe cambiar contraseña»: la primera vez
 |     que ingrese por el navegador deberá cambiarla.
 |   * No se elige un rol distinto de «Administrador del sistema», por lo que
 |     este script no puede usarse para crear cuentas con permisos parciales.
 */

if (PHP_SAPI !== 'cli') {
    if (!headers_sent()) {
        http_response_code(404);
    }

    exit('No disponible.');
}

$appPath = dirname(__DIR__);

require $appPath . '/app/Core/Autoloader.php';

\App\Core\Autoloader::register();
\App\Core\Env::load($appPath . '/.env');
\App\Core\Config::load($appPath . '/config');

require $appPath . '/app/Core/helpers.php';

date_default_timezone_set((string) \App\Core\Config::get('app.timezone', 'America/Lima'));

/**
 * Escribe una línea en la salida estándar.
 */
function informar(string $mensaje = ''): void
{
    fwrite(STDOUT, $mensaje . PHP_EOL);
}

function abortar(string $mensaje): never
{
    fwrite(STDERR, 'Error: ' . $mensaje . PHP_EOL);
    exit(1);
}

/**
 * Lee una línea de la entrada estándar.
 */
function leerLinea(string $etiqueta): string
{
    fwrite(STDOUT, $etiqueta);

    $linea = fgets(STDIN);

    return $linea === false ? '' : trim($linea);
}

/**
 * Lee una contraseña sin mostrarla en pantalla cuando el sistema lo permite.
 */
function leerPassword(string $etiqueta): string
{
    // 1. Linux y macOS: se desactiva el eco de la terminal.
    if (PHP_OS_FAMILY !== 'Windows' && function_exists('shell_exec')) {
        $estado = @shell_exec('stty -g 2>/dev/null');

        if (is_string($estado) && trim($estado) !== '') {
            @shell_exec('stty -echo 2>/dev/null');
            $valor = leerLinea($etiqueta);
            @shell_exec('stty ' . escapeshellarg(trim($estado)) . ' 2>/dev/null');
            informar();

            return trim($valor);
        }
    }

    // 2. Windows: se delega la lectura a PowerShell y se captura la salida.
    if (PHP_OS_FAMILY === 'Windows' && function_exists('shell_exec')) {
        $prompt = str_replace("'", '', $etiqueta);
        $comando = 'powershell -NoProfile -Command "'
            . '$s = Read-Host -Prompt \'' . $prompt . '\' -AsSecureString; '
            . '$b = [Runtime.InteropServices.Marshal]::SecureStringToBSTR($s); '
            . '[Runtime.InteropServices.Marshal]::PtrToStringBSTR($b)"';

        $salida = @shell_exec($comando);

        if (is_string($salida) && trim($salida) !== '') {
            return trim($salida);
        }

        informar('No se pudo leer la contraseña de forma oculta. Se solicitará de forma visible.');
    }

    // 3. Último recurso: entrada visible, advertida de forma explícita.
    informar('ADVERTENCIA: lo que escriba a continuación será visible en la pantalla.');

    return leerLinea($etiqueta);
}

informar();
informar('CSDUV3 · Creación del primer administrador del sistema');
informar('======================================================');
informar();

if (!\App\Core\Database::isAvailable()) {
    abortar(
        'no se pudo conectar a la base de datos. Revise el archivo .env y que el servidor de base '
        . 'de datos esté disponible.'
    );
}

if (!is_file($appPath . '/.env')) {
    informar('Aviso: no se encontró el archivo .env. Se están usando los valores por defecto.');
    informar();
}

if (!\App\Core\Database::tableExists((string) \App\Core\Config::get('database.prefix', 'csduv3_') . 'usuarios')) {
    abortar(
        'la tabla de usuarios no existe. Importe primero database/01_esquema.sql y '
        . 'database/02_datos_iniciales.sql desde phpMyAdmin.'
    );
}

$rolAdmin = \App\Models\Rol::porClave('administrador');

if ($rolAdmin === null) {
    abortar(
        'no se encontró el rol «administrador». Importe database/02_datos_iniciales.sql '
        . 'para cargar los roles del sistema.'
    );
}

$permitirOtro = in_array('--permitir-otro', $argv, true);
$existentes = \App\Models\Usuario::contarAdministradoresActivos();

if ($existentes > 0 && !$permitirOtro) {
    informar('Ya existe al menos un administrador activo (' . $existentes . ').');
    informar('No es necesario ejecutar este script: cree las demás cuentas desde el panel.');
    informar();
    informar('Si de verdad necesita otra cuenta administradora, ejecute:');
    informar('    php tools/crear_admin.php --permitir-otro');
    informar();
    exit(0);
}

$nombres = leerLinea('Nombres: ');
$apellidos = leerLinea('Apellidos: ');
$usuario = leerLinea('Nombre de usuario (solo letras, números, punto, guion y guion bajo): ');
$email = leerLinea('Correo electrónico (opcional, Enter para omitir): ');

if ($nombres === '' || mb_strlen($nombres) < 2) {
    abortar('los nombres son obligatorios.');
}

if ($apellidos === '' || mb_strlen($apellidos) < 2) {
    abortar('los apellidos son obligatorios.');
}

if (preg_match('/^[A-Za-z0-9._-]{3,60}$/', $usuario) !== 1) {
    abortar('el nombre de usuario no es válido.');
}

if (\App\Models\Usuario::findByLogin($usuario) !== null) {
    abortar('ya existe una cuenta con ese nombre de usuario o correo.');
}

if ($email !== '' && (filter_var($email, FILTER_VALIDATE_EMAIL) === false || mb_strlen($email) > 160)) {
    abortar('el correo electrónico no es válido.');
}

informar();
$password = leerPassword('Contraseña (no se mostrará): ');
$confirmacion = leerPassword('Repita la contraseña: ');

if ($password !== $confirmacion) {
    abortar('las contraseñas no coinciden.');
}

$errores = \App\Core\Password::errores($password);

if ($errores !== []) {
    abortar(implode(' ', $errores));
}

try {
    $id = \App\Models\Usuario::crear([
        'nombres'               => $nombres,
        'apellidos'             => $apellidos,
        'usuario'               => $usuario,
        'email'                 => $email,
        'password_hash'         => password_hash($password, PASSWORD_DEFAULT),
        'rol_id'                => (int) $rolAdmin['id'],
        'telefono'              => '',
        'cargo_institucional'   => '',
        'activo'                => 1,
        'debe_cambiar_password' => 1,
    ]);
} catch (\Throwable $exception) {
    abortar('no se pudo crear la cuenta: ' . $exception->getMessage());
}

\App\Core\Audit::log(
    null,
    'crear_primer_admin',
    'usuario',
    $id,
    'Cuenta administradora creada mediante tools/crear_admin.php'
);

informar();
informar('Cuenta administradora creada correctamente.');
informar('  Usuario:  ' . $usuario);
informar('  Ingreso:  ' . (string) \App\Core\Config::get('app.url', '(configure APP_URL)') . '/ingresar');
informar();
informar('Pasos siguientes:');
informar('  1. Ingrese por el navegador y cambie la contraseña cuando el sistema lo solicite.');
informar('  2. Cree el resto de cuentas del personal desde el panel, con el rol de cada persona.');
informar('  3. ELIMINE este archivo (tools/crear_admin.php) del servidor.');
informar();

<?php

declare(strict_types=1);

namespace App\Core;

use App\Models\Usuario;

/**
 * Autenticación y autorización del sistema.
 *
 * La sesión guarda únicamente el identificador del usuario. En cada petición se
 * vuelve a comprobar en la base de datos que la cuenta siga activa y se cargan
 * sus permisos, de modo que deshabilitar un usuario o cambiarle el rol tenga
 * efecto inmediato.
 */
final class Auth
{
    private const SESSION_KEY = 'auth_user_id';

    /** @var array<string, mixed>|null */
    private static ?array $user = null;

    private static bool $resolved = false;

    /** @var array<string, bool>|null */
    private static ?array $permissions = null;

    /**
     * Intenta iniciar sesión.
     *
     * @return array{ok: bool, mensaje: string}
     */
    public static function attempt(string $login, string $password, string $ip, string $userAgent): array
    {
        $login = trim($login);

        if ($login === '' || $password === '') {
            return ['ok' => false, 'mensaje' => 'Ingrese su usuario y contraseña.'];
        }

        $maxIntentos = max(1, (int) Config::get('security.login.max_attempts', 5));
        $minutosBloqueo = max(1, (int) Config::get('security.login.lockout_minutes', 15));

        if (RateLimiter::demasiadosIntentosLogin($ip)) {
            Logger::warning('Demasiados intentos de acceso desde una IP', ['ip' => $ip]);

            return [
                'ok' => false,
                'mensaje' => 'Demasiados intentos fallidos desde esta conexión. Espere unos minutos antes de volver a intentarlo.',
            ];
        }

        $user = Usuario::findByLogin($login);

        // Mensaje genérico: no se revela si el usuario existe o no.
        $generico = ['ok' => false, 'mensaje' => 'Usuario o contraseña incorrectos.'];

        if ($user === null) {
            Usuario::registrarAcceso(null, $login, false, $ip, $userAgent);

            return $generico;
        }

        $id = (int) $user['id'];

        if ((int) $user['activo'] !== 1) {
            Usuario::registrarAcceso($id, $login, false, $ip, $userAgent);
            Logger::warning('Acceso intentado en cuenta deshabilitada', ['usuario_id' => $id]);

            return $generico;
        }

        if (!empty($user['bloqueado_hasta']) && strtotime((string) $user['bloqueado_hasta']) > time()) {
            Usuario::registrarAcceso($id, $login, false, $ip, $userAgent);

            return [
                'ok' => false,
                'mensaje' => 'La cuenta está bloqueada temporalmente por intentos fallidos. Intente nuevamente en unos minutos.',
            ];
        }

        if (!password_verify($password, (string) $user['password_hash'])) {
            Usuario::registrarIntentoFallido($id, $maxIntentos, $minutosBloqueo);
            Usuario::registrarAcceso($id, $login, false, $ip, $userAgent);

            $intentos = (int) $user['intentos_fallidos'] + 1;

            if ($intentos >= $maxIntentos) {
                Logger::warning('Cuenta bloqueada por intentos fallidos', ['usuario_id' => $id]);

                return [
                    'ok' => false,
                    'mensaje' => 'Ha superado el número de intentos permitidos. La cuenta quedó bloqueada temporalmente.',
                ];
            }

            return $generico;
        }

        // Actualiza el hash si el algoritmo por defecto cambió.
        if (password_needs_rehash((string) $user['password_hash'], PASSWORD_DEFAULT)) {
            Usuario::cambiarPassword($id, $password, false);
        }

        Usuario::registrarAccesoExitoso($id);
        Usuario::registrarAcceso($id, $login, true, $ip, $userAgent);

        Session::regenerate(true);
        Session::put(self::SESSION_KEY, $id);

        self::$user = null;
        self::$resolved = false;
        self::$permissions = null;

        Audit::log($id, 'iniciar_sesion', 'usuario', $id, 'Inicio de sesión correcto');

        return ['ok' => true, 'mensaje' => 'Sesión iniciada correctamente.'];
    }

    public static function check(): bool
    {
        return self::user() !== null;
    }

    /** @return array<string, mixed>|null */
    public static function user(): ?array
    {
        if (self::$resolved) {
            return self::$user;
        }

        self::$resolved = true;
        self::$user = null;

        $id = Session::get(self::SESSION_KEY);

        if (!is_int($id) && !(is_string($id) && ctype_digit($id))) {
            return null;
        }

        try {
            $user = Usuario::findConRol((int) $id);
        } catch (\Throwable $exception) {
            Logger::error('No se pudo cargar el usuario de la sesión', ['error' => $exception->getMessage()]);

            return null;
        }

        if ($user === null || (int) $user['activo'] !== 1) {
            Session::forget(self::SESSION_KEY);

            return null;
        }

        self::$user = $user;

        return self::$user;
    }

    public static function id(): ?int
    {
        $user = self::user();

        return $user === null ? null : (int) $user['id'];
    }

    /** @return array<string, mixed>|null */
    public static function role(): ?array
    {
        $user = self::user();

        if ($user === null || $user['rol_id'] === null) {
            return null;
        }

        return [
            'id'    => (int) $user['rol_id'],
            'nombre' => (string) ($user['rol_nombre'] ?? ''),
            'clave' => (string) ($user['rol_clave'] ?? ''),
        ];
    }

    /**
     * Comprueba un permiso concreto (formato modulo.accion).
     */
    public static function can(string $permiso): bool
    {
        $user = self::user();

        if ($user === null || $user['rol_id'] === null) {
            return false;
        }

        if (self::$permissions === null) {
            self::$permissions = Usuario::permisosDeRol((int) $user['rol_id']);
        }

        return isset(self::$permissions[$permiso]);
    }

    /**
     * Comprueba si el usuario tiene al menos uno de los permisos indicados.
     *
     * @param array<int, string> $permisos
     */
    public static function canAny(array $permisos): bool
    {
        foreach ($permisos as $permiso) {
            if (self::can($permiso)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Cierra la sesión y deja un mensaje para la siguiente petición.
     */
    public static function logout(): void
    {
        $id = self::id();

        if ($id !== null) {
            Audit::log($id, 'cerrar_sesion', 'usuario', $id, 'Cierre de sesión');
        }

        Session::destroy();
        Session::start();
        Session::flash('exito', 'Su sesión se cerró correctamente.');

        self::$user = null;
        self::$resolved = false;
        self::$permissions = null;
    }

    /**
     * Invalida la caché en memoria (por ejemplo, tras cambiar el propio rol).
     */
    public static function refresh(): void
    {
        self::$user = null;
        self::$resolved = false;
        self::$permissions = null;
    }

    /**
     * Devuelve el listado de claves de permiso del usuario actual.
     *
     * @return array<int, string>
     */
    public static function permissionKeys(): array
    {
        $user = self::user();

        if ($user === null || $user['rol_id'] === null) {
            return [];
        }

        if (self::$permissions === null) {
            self::$permissions = Usuario::permisosDeRol((int) $user['rol_id']);
        }

        return array_keys(self::$permissions);
    }
}

<?php

declare(strict_types=1);

use App\Core\Config;
use App\Core\Csrf;
use App\Core\Session;
use App\Core\View;

/*
 |--------------------------------------------------------------------------
 | Funciones auxiliares globales
 |--------------------------------------------------------------------------
 | Se mantienen en un único archivo y con nombres cortos para que las vistas
 | sean legibles. Todas las funciones son seguras frente a valores nulos.
 */

if (!function_exists('config')) {
    function config(string $key, mixed $default = null): mixed
    {
        return Config::get($key, $default);
    }
}

if (!function_exists('base_path')) {
    function base_path(string $path = ''): string
    {
        $base = defined('APP_PATH') ? APP_PATH : dirname(__DIR__, 2);

        return $path === '' ? $base : rtrim($base, '/\\') . DIRECTORY_SEPARATOR . ltrim($path, '/\\');
    }
}

if (!function_exists('storage_path')) {
    function storage_path(string $path = ''): string
    {
        $base = (string) config('app.storage_path', base_path('storage'));

        return $path === '' ? $base : rtrim($base, '/\\') . DIRECTORY_SEPARATOR . ltrim($path, '/\\');
    }
}

if (!function_exists('e')) {
    /**
     * Escapa texto para salida HTML y evita XSS.
     */
    function e(mixed $value): string
    {
        if ($value === null) {
            return '';
        }

        if (is_bool($value)) {
            return $value ? '1' : '0';
        }

        return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}

if (!function_exists('url')) {
    function url(string $path = ''): string
    {
        $base = rtrim((string) config('app.url', ''), '/');
        $path = ltrim($path, '/');

        if ($path === '') {
            return $base === '' ? '/' : $base . '/';
        }

        return ($base === '' ? '' : $base) . '/' . $path;
    }
}

if (!function_exists('asset')) {
    function asset(string $path): string
    {
        $file = ltrim($path, '/');
        $base = rtrim((string) config('app.url', ''), '/');
        $version = '';

        $absolute = base_path('public' . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $file));

        if (is_file($absolute)) {
            $version = '?v=' . filemtime($absolute);
        }

        return ($base === '' ? '' : $base) . '/' . $file . $version;
    }
}

if (!function_exists('csrf_token')) {
    function csrf_token(): string
    {
        return Csrf::token();
    }
}

if (!function_exists('csrf_field')) {
    function csrf_field(): string
    {
        return Csrf::field();
    }
}

if (!function_exists('old')) {
    /**
     * Recupera un valor previo de formulario tras un error de validación.
     */
    function old(string $key, mixed $default = ''): mixed
    {
        static $values = null;

        if ($values === null) {
            $stored = Session::flash('_old_input');
            $values = is_array($stored) ? $stored : [];
        }

        return $values[$key] ?? $default;
    }
}

if (!function_exists('remember_old_input')) {
    /**
     * Guarda los datos enviados para repoblarlos después de una redirección.
     *
     * @param array<string, mixed> $data
     */
    function remember_old_input(array $data): void
    {
        unset($data['_csrf_token'], $data['_method']);

        foreach ($data as $key => $value) {
            if (is_string($value) && str_contains(strtolower((string) $key), 'password')) {
                unset($data[$key]);
            }
        }

        Session::flash('_old_input', $data);
    }
}

if (!function_exists('money')) {
    /**
     * Da formato monetario en soles peruanos: S/ 1,234.56
     */
    function money(mixed $value): string
    {
        $amount = is_numeric($value) ? (float) $value : 0.0;
        $symbol = (string) config('app.currency_symbol', 'S/');

        return $symbol . ' ' . number_format($amount, 2, '.', ',');
    }
}

if (!function_exists('fecha')) {
    function fecha(?string $value, string $fallback = '—'): string
    {
        if ($value === null || $value === '') {
            return $fallback;
        }

        $timestamp = strtotime($value);

        if ($timestamp === false) {
            return $fallback;
        }

        return date((string) config('app.date_format', 'd/m/Y'), $timestamp);
    }
}

if (!function_exists('hora')) {
    function hora(?string $value, string $fallback = '—'): string
    {
        if ($value === null || $value === '') {
            return $fallback;
        }

        $timestamp = strtotime($value);

        if ($timestamp === false) {
            return $fallback;
        }

        return date((string) config('app.time_format', 'H:i'), $timestamp);
    }
}

if (!function_exists('fecha_hora')) {
    function fecha_hora(?string $value, string $fallback = '—'): string
    {
        if ($value === null || $value === '') {
            return $fallback;
        }

        $timestamp = strtotime($value);

        if ($timestamp === false) {
            return $fallback;
        }

        return date((string) config('app.datetime_format', 'd/m/Y H:i'), $timestamp);
    }
}

if (!function_exists('is_current')) {
    /**
     * Indica si la ruta actual coincide con el enlace de navegación.
     */
    function is_current(string $path, bool $exact = false): bool
    {
        $current = (string) ($_SERVER['REQUEST_URI'] ?? '/');
        $currentPath = parse_url($current, PHP_URL_PATH);
        $currentPath = is_string($currentPath) ? $currentPath : '/';
        $base = \App\Core\Request::basePath();

        if ($base !== '' && str_starts_with($currentPath, $base)) {
            $currentPath = substr($currentPath, strlen($base));
        }

        $currentPath = '/' . trim($currentPath, '/');
        $target = '/' . trim($path, '/');

        if ($target === '/') {
            return $currentPath === '/';
        }

        return $exact ? $currentPath === $target : str_starts_with($currentPath, $target);
    }
}

if (!function_exists('auth')) {
    /**
     * Usuario autenticado actual, o null.
     *
     * @return array<string, mixed>|null
     */
    function auth(): ?array
    {
        return \App\Core\Auth::user();
    }
}

if (!function_exists('can')) {
    /**
     * Comprueba un permiso del usuario actual.
     *
     * Solo se usa para mostrar u ocultar elementos de la interfaz: la
     * comprobación que protege la operación se hace siempre en el servidor.
     */
    function can(string $permiso): bool
    {
        return \App\Core\Auth::can($permiso);
    }
}

if (!function_exists('usuario_nombre')) {
    /**
     * @param array<string, mixed>|null $usuario
     */
    function usuario_nombre(?array $usuario): string
    {
        if ($usuario === null) {
            return '';
        }

        return trim(((string) ($usuario['nombres'] ?? '')) . ' ' . ((string) ($usuario['apellidos'] ?? '')));
    }
}

if (!function_exists('club')) {
    /**
     * Valor de un parámetro institucional guardado en la base de datos.
     *
     * Si la base de datos no está disponible, devuelve el valor por defecto
     * indicado (normalmente el de la configuración de la aplicación).
     */
    function club(string $clave, string $porDefecto = ''): string
    {
        $valor = \App\Models\Configuracion::cached($clave, '');

        return $valor === '' ? $porDefecto : $valor;
    }
}

if (!function_exists('emergencias')) {
    /**
     * Teléfonos de emergencia del club.
     *
     * Se guardan en la configuración del club como una lista con la etiqueta,
     * el teléfono y una nota. Las filas incompletas se descartan.
     *
     * @return array<int, array{etiqueta: string, telefono: string, nota: string}>
     */
    function emergencias(): array
    {
        $crudo = club('telefonos_emergencia', '');

        if ($crudo === '') {
            return [];
        }

        $datos = json_decode($crudo, true);

        if (!is_array($datos)) {
            return [];
        }

        $lista = [];

        foreach ($datos as $fila) {
            if (!is_array($fila)) {
                continue;
            }

            $etiqueta = trim((string) ($fila['etiqueta'] ?? ''));
            $telefono = trim((string) ($fila['telefono'] ?? ''));

            if ($etiqueta === '' || $telefono === '') {
                continue;
            }

            $lista[] = [
                'etiqueta' => $etiqueta,
                'telefono' => $telefono,
                'nota'     => trim((string) ($fila['nota'] ?? '')),
            ];
        }

        return $lista;
    }
}

if (!function_exists('redes_club')) {
    /**
     * Redes sociales configuradas por el club.
     *
     * Devuelve solo las redes que el club completó y cuyo enlace es válido
     * (http o https). El WhatsApp se arma a partir del número guardado en
     * Contacto, si lo hay.
     *
     * @return array<string, string> clave de la red => enlace
     */
    function redes_club(): array
    {
        $redes = [];

        foreach (\App\Core\Redes::disponibles() as $clave => $datos) {
            if ($clave === 'whatsapp') {
                continue;
            }

            $enlace = trim(club($clave, ''));

            if ($enlace === '') {
                continue;
            }

            // Solo se aceptan enlaces http o https: nada de javascript: ni datos.
            if (!preg_match('#^https?://#i', $enlace)) {
                continue;
            }

            $redes[$clave] = $enlace;
        }

        $whatsapp = preg_replace('/[^0-9+]/', '', club('whatsapp', ''));

        if (is_string($whatsapp) && strlen(preg_replace('/\D/', '', $whatsapp) ?? '') >= 7) {
            $redes['whatsapp'] = 'https://wa.me/' . ltrim($whatsapp, '+');
        }

        return $redes;
    }
}

if (!function_exists('marca_url')) {
    /**
     * URL pública de una imagen de marca (logo, favicon o portada).
     *
     * Si el club no subió ninguna, se usa la imagen estática de la aplicación.
     */
    function marca_url(string $tipo, string $porDefecto = ''): string
    {
        $archivo = club('marca_' . $tipo, '');

        if ($archivo === '') {
            return $porDefecto === '' ? '' : asset($porDefecto);
        }

        return url('/marca/' . $tipo . '?v=' . substr(md5($archivo), 0, 8));
    }
}

if (!function_exists('dinero')) {
    /**
     * Formatea un importe en la moneda configurada.
     */
    function dinero(float|int|string|null $monto): string
    {
        $valor = is_numeric($monto) ? (float) $monto : 0.0;
        $simbolo = (string) config('app.currency_symbol', 'S/');

        return $simbolo . ' ' . number_format($valor, 2, '.', ',');
    }
}

if (!function_exists('fecha_legible')) {
    /**
     * Presenta una fecha de la base de datos en formato dd/mm/aaaa.
     */
    function fecha_legible(?string $fecha, bool $conHora = false): string
    {
        if ($fecha === null || $fecha === '') {
            return '—';
        }

        $marca = strtotime($fecha);

        if ($marca === false) {
            return '—';
        }

        return date($conHora ? 'd/m/Y H:i' : 'd/m/Y', $marca);
    }
}

if (!function_exists('query_url')) {
    /**
     * Construye una URL conservando los filtros actuales de la consulta.
     *
     * @param array<string, scalar|null> $cambios
     */
    function query_url(array $cambios = []): string
    {
        $params = array_merge($_GET, $cambios);

        foreach ($params as $clave => $valor) {
            if ($valor === null || $valor === '') {
                unset($params[$clave]);
            }
        }

        $ruta = parse_url((string) ($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH);
        $ruta = is_string($ruta) && $ruta !== '' ? $ruta : '/';

        return $ruta . ($params === [] ? '' : '?' . http_build_query($params));
    }
}

if (!function_exists('abort')) {
    /**
     * Finaliza la solicitud mostrando una página de error.
     */
    function abort(int $status, string $message = ''): never
    {
        if (!headers_sent()) {
            http_response_code($status);
        }

        $view = 'errors/' . $status;

        try {
            if (View::exists($view)) {
                echo View::render($view, ['status' => $status, 'message' => $message], null);
                exit;
            }
        } catch (\Throwable) {
            // Si la vista de error falla, se continúa con la salida mínima.
        }

        if (!headers_sent()) {
            header('Content-Type: text/html; charset=UTF-8');
        }

        echo '<!DOCTYPE html><html lang="es"><head><meta charset="UTF-8">'
            . '<meta name="viewport" content="width=device-width, initial-scale=1">'
            . '<title>Error ' . $status . '</title></head><body>'
            . '<h1>Error ' . $status . '</h1>'
            . '<p>' . e($message !== '' ? $message : 'No se pudo procesar la solicitud.') . '</p>'
            . '</body></html>';

        exit;
    }
}

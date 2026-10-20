<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Config;
use App\Core\Database;
use App\Core\Logger;
use App\Core\Request;
use App\Core\View;

/**
 * Diagnóstico del sistema.
 *
 * Disponible únicamente cuando APP_ENV=development. En producción devuelve
 * un error 404 para no revelar información técnica.
 */
final class DiagnosticoController
{
    public function index(Request $request): void
    {
        if ((string) Config::get('app.env', 'production') !== 'development') {
            abort(404, 'La página solicitada no existe o fue movida.');
        }

        $extensiones = [
            'pdo_mysql' => 'Conexión a MariaDB/MySQL',
            'mbstring'  => 'Cadenas multibyte (acentos)',
            'openssl'   => 'Cifrado y funciones aleatorias',
            'json'      => 'Intercambio de datos JSON',
            'fileinfo'  => 'Validación de archivos subidos',
            'session'   => 'Sesiones',
            'intl'      => 'Formatos regionales (opcional)',
        ];

        $estadoExtensiones = [];

        foreach ($extensiones as $nombre => $descripcion) {
            $estadoExtensiones[] = [
                'nombre'      => $nombre,
                'descripcion' => $descripcion,
                'cargada'     => extension_loaded($nombre),
                'opcional'    => in_array($nombre, ['intl'], true),
            ];
        }

        $prefijo = (string) Config::get('database.prefix', 'csduv3_');

        $tablasEsperadas = [
            'configuracion', 'roles', 'permisos', 'roles_permisos', 'usuarios',
            'accesos', 'categorias_socio', 'socios', 'socios_bajas',
            'socios_documentos', 'conceptos_pago', 'directivas', 'directiva_cargos',
            'directiva_miembros', 'procesos_electorales', 'listas_electorales',
            'candidatos', 'resultados_electorales', 'tipos_evento', 'instalaciones',
            'tarifas', 'reservas', 'solicitudes_alquiler', 'reserva_pagos',
            'categorias_movimiento',
            'medios_pago', 'periodos', 'movimientos', 'movimientos_auditoria',
            'deportes', 'deporte_puntos', 'torneos', 'torneo_equipos',
            'torneo_participantes', 'jornadas', 'encuentros', 'encuentro_estadisticas',
            'documentos', 'actividades', 'fotos', 'negocios', 'auditoria',
        ];

        $dbConfigurada = (string) Config::get('database.database', '') !== '';
        $dbDisponible = $dbConfigurada && Database::isAvailable();

        $tablas = ['creadas' => 0, 'faltantes' => [], 'total' => count($tablasEsperadas)];

        if ($dbDisponible) {
            foreach ($tablasEsperadas as $tabla) {
                if (Database::tableExists($prefijo . $tabla)) {
                    $tablas['creadas']++;
                } else {
                    $tablas['faltantes'][] = $prefijo . $tabla;
                }
            }
        }

        $rutas = [
            'app'     => base_path(),
            'public'  => PUBLIC_PATH,
            'storage' => storage_path(),
        ];

        $permisosEscritura = [
            'storage/logs'    => $this->escribible(storage_path('logs')),
            'storage/uploads' => $this->escribible(storage_path('uploads')),
            'storage/backups' => $this->escribible(storage_path('backups')),
        ];

        View::display('diagnostico/index', [
            'titulo'            => 'Diagnóstico del sistema',
            'phpVersion'        => PHP_VERSION,
            'phpVersionOk'      => version_compare(PHP_VERSION, '8.3.0', '>='),
            'sapi'              => PHP_SAPI,
            'entorno'           => (string) Config::get('app.env', 'production'),
            'debug'             => (bool) Config::get('app.debug', false),
            'zonaHoraria'       => date_default_timezone_get(),
            'fechaHoraActual'   => date('d/m/Y H:i:s'),
            'extensiones'       => $estadoExtensiones,
            'dbConfigurada'     => $dbConfigurada,
            'dbDisponible'      => $dbDisponible,
            'dbError'           => Database::lastError(),
            'dbVersion'         => $dbDisponible ? Database::serverVersion() : null,
            'tablas'            => $tablas,
            'rutas'             => $rutas,
            'permisosEscritura' => $permisosEscritura,
            'envPresente'       => is_file(base_path('.env')),
            'logPath'           => Logger::path(),
            'https'             => $request->isSecure(),
            'appUrl'            => (string) Config::get('app.url', '(sin definir)'),
            'basePath'          => Request::basePath(),
        ]);
    }

    private function escribible(string $directorio): bool
    {
        if (!is_dir($directorio)) {
            return false;
        }

        $archivo = $directorio . DIRECTORY_SEPARATOR . '.escritura-' . bin2hex(random_bytes(4)) . '.tmp';

        if (@file_put_contents($archivo, 'ok') === false) {
            return false;
        }

        @unlink($archivo);

        return true;
    }
}

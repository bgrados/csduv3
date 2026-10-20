<?php

declare(strict_types=1);

use App\Core\Env;

/**
 * Configuración general de la aplicación.
 *
 * Los valores sensibles se leen del archivo .env (nunca versionado).
 * Este archivo sí se versiona y no debe contener credenciales.
 */
return [
    'name'       => Env::get('APP_NAME', 'Club Social Deportivo Unidad Vecinal N.º 3'),
    'short_name' => Env::get('APP_SHORT_NAME', 'CSDUV3'),
    'env'        => Env::get('APP_ENV', 'production'),
    'debug'      => Env::bool('APP_DEBUG', false),
    'url'        => rtrim((string) Env::get('APP_URL', ''), '/'),
    'timezone'   => Env::get('APP_TIMEZONE', 'America/Lima'),
    'locale'     => Env::get('APP_LOCALE', 'es_PE'),

    // Identidad visual institucional. El logotipo queda configurable desde la
    // tabla csduv3_configuracion; aquí solo se define la ruta pública por defecto.
    'logo'       => 'assets/img/logo.jpg',
    'favicon'    => 'assets/img/favicon.svg',

    // Formatos de presentación (no afectan el almacenamiento en base de datos).
    'date_format'   => 'd/m/Y',
    'time_format'   => 'H:i',
    'datetime_format' => 'd/m/Y H:i',
    'currency'      => 'PEN',
    'currency_symbol' => 'S/',

    // Rutas internas.
    'storage_path' => dirname(__DIR__) . '/storage',
    'views_path'   => dirname(__DIR__) . '/app/Views',
];

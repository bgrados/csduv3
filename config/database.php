<?php

declare(strict_types=1);

use App\Core\Env;

/**
 * Configuración de conexión a la base de datos (MariaDB / MySQL).
 *
 * IMPORTANTE: la contraseña se lee del archivo .env y nunca se escribe aquí.
 */
return [
    'driver'    => 'mysql',
    'host'      => Env::get('DB_HOST', 'localhost'),
    'port'      => (int) Env::get('DB_PORT', '3306'),
    'database'  => Env::get('DB_DATABASE', ''),
    'username'  => Env::get('DB_USERNAME', ''),
    'password'  => Env::get('DB_PASSWORD', ''),
    'charset'   => Env::get('DB_CHARSET', 'utf8mb4'),
    'collation' => Env::get('DB_COLLATION', 'utf8mb4_unicode_ci'),

    // Zona horaria de la sesión MySQL. Se usa el desplazamiento de America/Lima
    // (-05:00, sin horario de verano) porque muchos hostings compartidos no
    // tienen cargadas las tablas de zonas horarias con nombre.
    'timezone_offset' => Env::get('DB_TIMEZONE_OFFSET', '-05:00'),

    // Prefijo de las tablas del sistema.
    'prefix' => 'csduv3_',
];

<?php

declare(strict_types=1);

use App\Core\Env;

/**
 * Configuración de seguridad, sesiones y archivos subidos.
 */
return [
    'app_key' => Env::get('APP_KEY', ''),

    'session' => [
        'name'     => Env::get('SESSION_NAME', 'csduv3_sesion'),
        'lifetime' => (int) Env::get('SESSION_LIFETIME', '7200'),
        'secure'   => Env::bool('SESSION_SECURE', false),
        'http_only' => true,
        'same_site' => 'Lax',
        'path'      => '/',
    ],

    'login' => [
        'max_attempts'     => (int) Env::get('LOGIN_MAX_ATTEMPTS', '5'),
        'lockout_minutes'  => (int) Env::get('LOGIN_LOCKOUT_MINUTES', '15'),
    ],

    'uploads' => [
        'max_size_mb'      => (int) Env::get('UPLOAD_MAX_SIZE_MB', '5'),
        'allowed_images'   => ['jpg', 'jpeg', 'png', 'webp', 'gif'],
        'allowed_documents' => ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'ods', 'odt', 'csv', 'txt'],
        // Tipos MIME aceptados para documentos e imágenes.
        'allowed_mimes'    => [
            'image/jpeg', 'image/png', 'image/webp', 'image/gif',
            'application/pdf',
            'application/msword',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'application/vnd.ms-excel',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'application/vnd.oasis.opendocument.text',
            'application/vnd.oasis.opendocument.spreadsheet',
            'text/plain',
            'text/csv',
        ],
    ],

    'log' => [
        'level' => Env::get('LOG_LEVEL', 'debug'),
    ],
];

-- ============================================================================
--  CSDUV3 - Datos iniciales técnicos
-- ----------------------------------------------------------------------------
--  Archivo:  database/02_datos_iniciales.sql
--
--  Contiene ÚNICAMENTE datos técnicos necesarios para que el sistema funcione:
--    * Roles del sistema y sus permisos (definidos por el requerimiento).
--    * Parámetros de configuración institucional básicos.
--    * Catálogos genéricos editables (medios de pago, categorías de caja,
--      tipos de evento y disciplinas deportivas).
--
--  NO contiene nombres de socios, directivos, candidatos, movimientos
--  financieros ni resultados deportivos: esos datos son reales del club y se
--  registran desde la aplicación.
--
--  Los catálogos genéricos son solo un punto de partida editable desde la
--  propia aplicación (Configuración). No representan decisiones del club.
--
--  Todas las sentencias usan INSERT IGNORE, por lo que el archivo puede
--  ejecutarse más de una vez sin duplicar ni borrar información.
--
--  Orden: ejecutar después de database/01_esquema.sql
-- ============================================================================

SET NAMES utf8mb4;


-- ---------------------------------------------------------------------------
--  1. CONFIGURACIÓN INSTITUCIONAL
-- ---------------------------------------------------------------------------

INSERT IGNORE INTO `csduv3_configuracion` (`clave`, `valor`, `tipo`, `grupo`, `descripcion`) VALUES
    ('nombre_club',        'Club Social Deportivo Unidad Vecinal N.º 3', 'texto',  'identidad',  'Nombre institucional completo'),
    ('siglas',             'CSDUV3',                                    'texto',  'identidad',  'Siglas usadas en la interfaz'),
    ('zona_horaria',       'America/Lima',                              'texto',  'general',    'Zona horaria del sistema'),
    ('moneda',             'PEN',                                       'texto',  'finanzas',   'Moneda de los importes'),
    ('simbolo_moneda',     'S/',                                        'texto',  'finanzas',   'Símbolo usado al mostrar importes'),
    ('formato_fecha',      'd/m/Y',                                     'texto',  'general',    'Formato de fecha de la interfaz'),
    ('logo',               'assets/img/logo.jpg',                       'archivo','identidad',  'Logotipo institucional UV3'),
    ('direccion',          '',                                          'texto',  'contacto',   'Dirección de la sede (completar)'),
    ('telefono',           '',                                          'texto',  'contacto',   'Teléfono institucional (completar)'),
    ('email_institucional','',                                          'texto',  'contacto',   'Correo institucional (completar)'),
    ('anios_mandato',      '4',                                         'numero', 'institucional', 'Duración del mandato de la junta directiva en años (según estatuto)'),
    ('porcentaje_adelanto','50',                                       'numero', 'finanzas',      'Porcentaje de adelanto por defecto al crear una reserva de alquiler');


-- ---------------------------------------------------------------------------
--  2. ROLES DEL SISTEMA
-- ---------------------------------------------------------------------------

INSERT IGNORE INTO `csduv3_roles` (`clave`, `nombre`, `descripcion`, `es_sistema`) VALUES
    ('administrador', 'Administrador del sistema', 'Acceso completo a todos los módulos y a la configuración.', 1),
    ('junta_directiva','Junta directiva',          'Consulta institucional y gestión de directiva, elecciones y documentos.', 1),
    ('tesoreria',     'Tesorería',                 'Registro y control de ingresos, egresos y saldos.', 1),
    ('secretaria',    'Secretaría',                'Gestión de socios, documentos y calendario institucional.', 1),
    ('deportes',      'Responsable de deportes',   'Gestión de disciplinas, torneos, fixture y resultados.', 1),
    ('alquileres',    'Responsable de alquileres', 'Gestión de ambientes, tarifas y reservas.', 1),
    ('consulta',      'Consulta',                  'Acceso de solo lectura a la información autorizada.', 1);


-- ---------------------------------------------------------------------------
--  3. PERMISOS
-- ---------------------------------------------------------------------------

INSERT IGNORE INTO `csduv3_permisos` (`clave`, `modulo`, `descripcion`) VALUES
    ('panel.ver',              'panel',        'Ver el panel principal con indicadores'),
    ('reportes.ver',           'reportes',     'Consultar e imprimir informes'),

    ('socios.ver',             'socios',       'Consultar el padrón de socios'),
    ('socios.crear',           'socios',       'Registrar nuevos socios'),
    ('socios.editar',          'socios',       'Editar la ficha de un socio'),
    ('socios.baja',            'socios',       'Registrar la baja de un socio'),
    ('socios.exportar',        'socios',       'Exportar listados de socios'),
    ('socios.documentos',      'socios',       'Adjuntar y consultar documentos del socio'),

    ('directiva.ver',          'directiva',    'Consultar juntas directivas y su histórico'),
    ('directiva.gestionar',    'directiva',    'Registrar y editar juntas directivas y cargos'),

    ('elecciones.ver',         'elecciones',   'Consultar procesos electorales'),
    ('elecciones.gestionar',   'elecciones',   'Registrar procesos, listas, candidatos y resultados'),

    ('alquileres.ver',         'alquileres',   'Consultar reservas y disponibilidad'),
    ('alquileres.gestionar',   'alquileres',   'Registrar y editar reservas, solicitantes y pagos'),
    ('alquileres.cancelar',    'alquileres',   'Cancelar reservas indicando el motivo'),
    ('alquileres.tarifas',     'alquileres',   'Administrar instalaciones y tarifas'),

    ('caja.ver',               'caja',         'Consultar movimientos de caja'),
    ('caja.registrar',         'caja',         'Registrar ingresos y egresos'),
    ('caja.anular',            'caja',         'Anular movimientos con motivo y trazabilidad'),
    ('caja.reportes',          'caja',         'Consultar reportes financieros por periodo'),

    ('deportes.ver',           'deportes',     'Consultar torneos, fixture y posiciones'),
    ('deportes.gestionar',     'deportes',     'Administrar disciplinas, torneos, equipos y resultados'),

    ('calendario.ver',         'calendario',   'Consultar el calendario institucional'),
    ('calendario.gestionar',   'calendario',   'Registrar y editar actividades del calendario'),

    ('documentos.ver',         'documentos',   'Consultar documentos institucionales'),
    ('documentos.gestionar',   'documentos',   'Registrar, editar y adjuntar documentos'),

    ('usuarios.ver',           'usuarios',     'Consultar usuarios del sistema'),
    ('usuarios.gestionar',     'usuarios',     'Crear y editar usuarios'),
    ('roles.gestionar',        'roles',        'Administrar roles y permisos'),

    ('auditoria.ver',          'auditoria',    'Consultar el registro de auditoría'),

    ('configuracion.gestionar','configuracion','Modificar los parámetros del sistema');


-- ---------------------------------------------------------------------------
--  4. PERMISOS POR ROL
-- ---------------------------------------------------------------------------

-- Administrador del sistema: todos los permisos.
INSERT IGNORE INTO `csduv3_roles_permisos` (`rol_id`, `permiso_id`)
SELECT r.`id`, p.`id`
FROM `csduv3_roles` r
CROSS JOIN `csduv3_permisos` p
WHERE r.`clave` = 'administrador';

-- Junta directiva.
INSERT IGNORE INTO `csduv3_roles_permisos` (`rol_id`, `permiso_id`)
SELECT r.`id`, p.`id`
FROM `csduv3_roles` r
JOIN `csduv3_permisos` p ON p.`clave` IN (
    'panel.ver', 'reportes.ver',
    'socios.ver', 'socios.exportar',
    'directiva.ver', 'directiva.gestionar',
    'elecciones.ver', 'elecciones.gestionar',
    'alquileres.ver',
    'caja.ver', 'caja.reportes',
    'deportes.ver',
    'calendario.ver', 'calendario.gestionar',
    'documentos.ver', 'documentos.gestionar',
    'auditoria.ver'
)
WHERE r.`clave` = 'junta_directiva';

-- Tesorería.
INSERT IGNORE INTO `csduv3_roles_permisos` (`rol_id`, `permiso_id`)
SELECT r.`id`, p.`id`
FROM `csduv3_roles` r
JOIN `csduv3_permisos` p ON p.`clave` IN (
    'panel.ver', 'reportes.ver',
    'socios.ver',
    'alquileres.ver', 'alquileres.gestionar', 'alquileres.cancelar',
    'caja.ver', 'caja.registrar', 'caja.anular', 'caja.reportes',
    'documentos.ver'
)
WHERE r.`clave` = 'tesoreria';

-- Secretaría.
INSERT IGNORE INTO `csduv3_roles_permisos` (`rol_id`, `permiso_id`)
SELECT r.`id`, p.`id`
FROM `csduv3_roles` r
JOIN `csduv3_permisos` p ON p.`clave` IN (
    'panel.ver', 'reportes.ver',
    'socios.ver', 'socios.crear', 'socios.editar', 'socios.baja', 'socios.exportar', 'socios.documentos',
    'directiva.ver', 'elecciones.ver',
    'calendario.ver', 'calendario.gestionar',
    'documentos.ver', 'documentos.gestionar'
)
WHERE r.`clave` = 'secretaria';

-- Responsable de deportes.
INSERT IGNORE INTO `csduv3_roles_permisos` (`rol_id`, `permiso_id`)
SELECT r.`id`, p.`id`
FROM `csduv3_roles` r
JOIN `csduv3_permisos` p ON p.`clave` IN (
    'panel.ver', 'reportes.ver',
    'deportes.ver', 'deportes.gestionar',
    'calendario.ver', 'calendario.gestionar',
    'documentos.ver'
)
WHERE r.`clave` = 'deportes';

-- Responsable de alquileres.
INSERT IGNORE INTO `csduv3_roles_permisos` (`rol_id`, `permiso_id`)
SELECT r.`id`, p.`id`
FROM `csduv3_roles` r
JOIN `csduv3_permisos` p ON p.`clave` IN (
    'panel.ver', 'reportes.ver',
    'alquileres.ver', 'alquileres.gestionar', 'alquileres.cancelar', 'alquileres.tarifas',
    'calendario.ver',
    'caja.ver',
    'documentos.ver'
)
WHERE r.`clave` = 'alquileres';

-- Consulta (solo lectura).
INSERT IGNORE INTO `csduv3_roles_permisos` (`rol_id`, `permiso_id`)
SELECT r.`id`, p.`id`
FROM `csduv3_roles` r
JOIN `csduv3_permisos` p ON p.`clave` IN (
    'panel.ver', 'reportes.ver',
    'socios.ver',
    'directiva.ver',
    'elecciones.ver',
    'alquileres.ver',
    'caja.ver',
    'deportes.ver',
    'calendario.ver',
    'documentos.ver'
)
WHERE r.`clave` = 'consulta';


-- ---------------------------------------------------------------------------
--  5. CATÁLOGOS GENÉRICOS EDITABLES
-- ---------------------------------------------------------------------------

-- Medios de pago (editable desde la aplicación).
INSERT IGNORE INTO `csduv3_medios_pago` (`nombre`, `descripcion`, `orden`) VALUES
    ('Efectivo',              'Pago recibido en efectivo', 1),
    ('Transferencia bancaria','Transferencia o depósito en cuenta institucional', 2);

-- Categorías de caja.
INSERT IGNORE INTO `csduv3_categorias_movimiento` (`nombre`, `tipo`, `descripcion`) VALUES
    ('Alquileres',      'ingreso', 'Ingresos por alquiler de instalaciones'),
    ('Cuotas',          'ingreso', 'Cuotas y aportes de socios'),
    ('Otros ingresos',  'ingreso', 'Otros ingresos institucionales'),
    ('Servicios',       'egreso',  'Servicios básicos y suministros'),
    ('Mantenimiento',   'egreso',  'Mantenimiento de instalaciones y equipos'),
    ('Administración',  'egreso',  'Gastos administrativos'),
    ('Otros egresos',   'egreso',  'Otros egresos institucionales');

-- Tipos de uso de las instalaciones (requerimiento del módulo de alquileres).
INSERT IGNORE INTO `csduv3_tipos_evento` (`nombre`, `requiere_contrato`, `descripcion`) VALUES
    ('Matrimonio',     1, 'Celebración de matrimonio'),
    ('Reunión social', 0, 'Reuniones y actividades sociales'),
    ('Velorio',        0, 'Servicio de velatorio'),
    ('Otro',           0, 'Otros usos autorizados por la institución');

-- Disciplinas deportivas mencionadas en el requerimiento.
-- Las reglas de puntuación se cargan en csduv3_deporte_puntos y son editables.
INSERT IGNORE INTO `csduv3_deportes`
    (`nombre`, `descripcion`, `tipo_puntuacion`, `permite_empate`) VALUES
    ('Fútbol',  'Fútbol de campo o de losa', 'puntos', 1),
    ('Vóley',   'Vóley',                     'sets',   0),
    ('Básquet', 'Básquet',                   'puntos', 0),
    ('Sapo',    'Juego de sapo',             'puntos', 1);

-- Reglas de puntuación por disciplina (editables).
INSERT IGNORE INTO `csduv3_deporte_puntos` (`deporte_id`, `concepto`, `puntos`, `orden`, `descripcion`)
SELECT d.`id`, 'victoria', 3.0, 1, 'Puntos por partido ganado'
FROM `csduv3_deportes` d WHERE d.`nombre` IN ('Fútbol', 'Sapo');

INSERT IGNORE INTO `csduv3_deporte_puntos` (`deporte_id`, `concepto`, `puntos`, `orden`, `descripcion`)
SELECT d.`id`, 'empate', 1.0, 2, 'Puntos por partido empatado'
FROM `csduv3_deportes` d WHERE d.`nombre` IN ('Fútbol', 'Sapo');

INSERT IGNORE INTO `csduv3_deporte_puntos` (`deporte_id`, `concepto`, `puntos`, `orden`, `descripcion`)
SELECT d.`id`, 'derrota', 0.0, 3, 'Puntos por partido perdido'
FROM `csduv3_deportes` d WHERE d.`nombre` IN ('Fútbol', 'Sapo');

INSERT IGNORE INTO `csduv3_deporte_puntos` (`deporte_id`, `concepto`, `puntos`, `orden`, `descripcion`)
SELECT d.`id`, 'victoria', 2.0, 1, 'Puntos por partido ganado'
FROM `csduv3_deportes` d WHERE d.`nombre` IN ('Básquet');

INSERT IGNORE INTO `csduv3_deporte_puntos` (`deporte_id`, `concepto`, `puntos`, `orden`, `descripcion`)
SELECT d.`id`, 'derrota', 1.0, 2, 'Punto de participación por partido perdido'
FROM `csduv3_deportes` d WHERE d.`nombre` IN ('Básquet');

INSERT IGNORE INTO `csduv3_deporte_puntos` (`deporte_id`, `concepto`, `puntos`, `orden`, `descripcion`)
SELECT d.`id`, 'victoria', 3.0, 1, 'Puntos por partido ganado'
FROM `csduv3_deportes` d WHERE d.`nombre` IN ('Vóley');

-- ============================================================================
--  NOTA: no se crean categorías de socio, cuotas, instalaciones, tarifas,
--  cargos de junta directiva, periodos ni personas. Deben registrarse según
--  el estatuto y la realidad del club.
--
--  El primer usuario administrador se crea con el procedimiento temporal
--  descrito en docs/INSTALACION.md (Fase 2).
-- ============================================================================

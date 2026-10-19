-- ============================================================================
--  CSDUV3 - Datos FICTICIOS para desarrollo
-- ----------------------------------------------------------------------------
--  Archivo:  database/03_demo_desarrollo.sql
--
--  ############################################################################
--  #  ATENCIÓN: ESTOS DATOS SON INVENTADOS Y NO PERTENECEN AL CLUB.          #
--  #  Sirven únicamente para probar el sistema en un entorno local.          #
--  #  NO IMPORTAR ESTE ARCHIVO EN EL SERVIDOR DE PRODUCCIÓN.                 #
--  ############################################################################
--
--  Todos los registros están identificados con el prefijo DEMO- para poder
--  distinguirlos y retirarlos con database/04_eliminar_demo.sql
--
--  Orden: ejecutar después de database/02_datos_iniciales.sql
-- ============================================================================

SET NAMES utf8mb4;


-- ---------------------------------------------------------------------------
--  Categorías de socio (ficticias)
-- ---------------------------------------------------------------------------

INSERT IGNORE INTO `csduv3_categorias_socio` (`nombre`, `descripcion`, `edad_minima`, `edad_maxima`, `orden`)
VALUES
    ('DEMO-Activo',  'Categoría ficticia para pruebas', 18, 120, 90),
    ('DEMO-Juvenil', 'Categoría ficticia para pruebas', 12, 17, 91);


-- ---------------------------------------------------------------------------
--  Socios (ficticios)
-- ---------------------------------------------------------------------------

INSERT IGNORE INTO `csduv3_socios`
    (`codigo`, `nombres`, `apellidos`, `tipo_documento`, `numero_documento`, `fecha_nacimiento`,
     `telefono`, `email`, `fecha_ingreso`, `categoria_id`, `estado`, `observaciones`)
SELECT 'DEMO-001', 'Persona', 'De Prueba Uno', 'DNI', '00000001', '1990-01-15',
       '999000001', 'demo.uno@example.invalid', '2024-01-10', c.`id`, 'activo',
       'Registro ficticio de desarrollo'
FROM `csduv3_categorias_socio` c WHERE c.`nombre` = 'DEMO-Activo';

INSERT IGNORE INTO `csduv3_socios`
    (`codigo`, `nombres`, `apellidos`, `tipo_documento`, `numero_documento`, `fecha_nacimiento`,
     `telefono`, `email`, `fecha_ingreso`, `categoria_id`, `estado`, `observaciones`)
SELECT 'DEMO-002', 'Persona', 'De Prueba Dos', 'DNI', '00000002', '1985-06-30',
       '999000002', 'demo.dos@example.invalid', '2023-03-22', c.`id`, 'pendiente',
       'Registro ficticio de desarrollo'
FROM `csduv3_categorias_socio` c WHERE c.`nombre` = 'DEMO-Activo';

INSERT IGNORE INTO `csduv3_socios`
    (`codigo`, `nombres`, `apellidos`, `tipo_documento`, `numero_documento`, `fecha_nacimiento`,
     `telefono`, `email`, `fecha_ingreso`, `categoria_id`, `estado`, `observaciones`)
SELECT 'DEMO-003', 'Persona', 'De Prueba Tres', 'DNI', '00000003', '2008-09-05',
       '999000003', 'demo.tres@example.invalid', '2025-02-01', c.`id`, 'activo',
       'Registro ficticio de desarrollo'
FROM `csduv3_categorias_socio` c WHERE c.`nombre` = 'DEMO-Juvenil';


-- ---------------------------------------------------------------------------
--  Instalaciones y tarifas (ficticias)
-- ---------------------------------------------------------------------------

INSERT IGNORE INTO `csduv3_instalaciones` (`nombre`, `tipo`, `descripcion`, `aforo`, `activo`)
VALUES
    ('DEMO-Salón principal', 'salón', 'Ambiente ficticio para pruebas', 120, 1);

INSERT IGNORE INTO `csduv3_tarifas` (`instalacion_id`, `tipo_evento_id`, `nombre`, `monto`, `moneda`, `activo`)
SELECT i.`id`, t.`id`, 'DEMO-Tarifa social', 250.00, 'PEN', 1
FROM `csduv3_instalaciones` i
JOIN `csduv3_tipos_evento` t ON t.`nombre` = 'Reunión social'
WHERE i.`nombre` = 'DEMO-Salón principal';


-- ---------------------------------------------------------------------------
--  Periodo administrativo (ficticio)
-- ---------------------------------------------------------------------------

INSERT IGNORE INTO `csduv3_periodos` (`nombre`, `fecha_inicio`, `fecha_fin`, `observaciones`)
VALUES ('DEMO-2026', '2026-01-01', '2026-12-31', 'Periodo ficticio de desarrollo');


-- ---------------------------------------------------------------------------
--  Actividad de calendario (ficticia)
-- ---------------------------------------------------------------------------

INSERT IGNORE INTO `csduv3_actividades`
    (`titulo`, `tipo`, `descripcion`, `fecha_inicio`, `lugar`, `estado`)
VALUES
    ('DEMO-Reunión de prueba', 'institucional', 'Actividad ficticia de desarrollo',
     '2026-11-15 19:00:00', 'DEMO-Salón principal', 'programada');

-- ============================================================================
--  FIN DE LOS DATOS FICTICIOS
-- ============================================================================

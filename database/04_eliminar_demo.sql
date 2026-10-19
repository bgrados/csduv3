-- ============================================================================
--  CSDUV3 - Retiro de los datos ficticios de desarrollo
-- ----------------------------------------------------------------------------
--  Archivo:  database/04_eliminar_demo.sql
--
--  Elimina ÚNICAMENTE los registros creados por database/03_demo_desarrollo.sql,
--  identificados por el prefijo DEMO-. No toca datos reales del club.
--
--  Úselo antes de cargar datos reales o antes de desplegar a producción.
-- ============================================================================

SET NAMES utf8mb4;

-- Orden inverso al de creación para respetar las claves foráneas.

DELETE FROM `csduv3_actividades`
WHERE `titulo` LIKE 'DEMO-%';

DELETE FROM `csduv3_periodos`
WHERE `nombre` LIKE 'DEMO-%';

DELETE FROM `csduv3_tarifas`
WHERE `nombre` LIKE 'DEMO-%';

DELETE FROM `csduv3_instalaciones`
WHERE `nombre` LIKE 'DEMO-%';

DELETE FROM `csduv3_socios`
WHERE `codigo` LIKE 'DEMO-%';

DELETE FROM `csduv3_categorias_socio`
WHERE `nombre` LIKE 'DEMO-%';

-- ============================================================================
--  Los registros ficticios fueron retirados.
--  Verifique con: SELECT COUNT(*) FROM csduv3_socios WHERE codigo LIKE 'DEMO-%';
-- ============================================================================

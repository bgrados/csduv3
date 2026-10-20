-- ============================================================================
--  CSDUV3 - Migración 0005
-- ----------------------------------------------------------------------------
--  Archivo:  database/migrations/0005_horarios_que_cruzan_medianoche.sql
--
--  Qué hace
--  --------
--  Permite que una reserva termine al día siguiente. Antes, la restricción
--  `chk_reservas_horario` (hora_fin > hora_inicio) impedía/alquilar un salón
--  de 19:00 a 03:00, que es lo habitual en eventos y bodas.
--
--  Ahora la regla es: si la hora de fin es menor o igual que la de inicio, la
--  reserva termina al día siguiente. La aplicación lo valida y lo muestra
--  como «+1 día».
--
--  ¿Cuándo ejecutarla?
--  -------------------
--    * Aplíquela una vez. Si la restricción no existe, el script no hace nada.
--
--  Seguridad
--  ---------
--    * Solo modifica una restricción; no toca datos.
--    * Es idempotente: puede ejecutarse más de una vez.
-- ============================================================================

SET NAMES utf8mb4;

SET @sql = (
    SELECT IF(
        EXISTS (
            SELECT 1
            FROM information_schema.CHECK_CONSTRAINTS
            WHERE CONSTRAINT_SCHEMA = DATABASE()
              AND CONSTRAINT_NAME = 'chk_reservas_horario'
        ),
        'ALTER TABLE `csduv3_reservas` DROP CONSTRAINT `chk_reservas_horario`',
        'DO 0'
    )
);

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

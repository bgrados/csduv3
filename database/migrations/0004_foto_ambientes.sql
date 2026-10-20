-- ============================================================================
--  CSDUV3 - Migración 0004
-- ----------------------------------------------------------------------------
--  Archivo:  database/migrations/0004_foto_ambientes.sql
--
--  Qué hace
--  --------
--  Añade a los ambientes (instalaciones) una foto, para que el portal pueda
--  mostrarla y el club pueda publicar sus propias instalaciones con fotos.
--
--  ¿Cuándo ejecutarla?
--  -------------------
--    * Si ya aplicó las migraciones 0001 a 0003: ejecute este archivo.
--    * Si NO las ha aplicado, aplique las cuatro en orden; las anteriores no
--      dependen de esta y las siguientes tampoco.
--
--  Seguridad
--  ---------
--    * Solo agrega columnas; no borra ni modifica datos.
--    * Es idempotente: puede ejecutarse más de una vez.
-- ============================================================================

SET NAMES utf8mb4;

ALTER TABLE `csduv3_instalaciones`
    ADD COLUMN IF NOT EXISTS `archivo` VARCHAR(255) NULL
        COMMENT 'Ruta relativa dentro de storage/uploads (nunca pública)' AFTER `aforo`;

ALTER TABLE `csduv3_instalaciones`
    ADD COLUMN IF NOT EXISTS `nombre_original` VARCHAR(200) NULL AFTER `archivo`;

ALTER TABLE `csduv3_instalaciones`
    ADD COLUMN IF NOT EXISTS `tipo_mime` VARCHAR(120) NULL AFTER `nombre_original`;

ALTER TABLE `csduv3_instalaciones`
    ADD COLUMN IF NOT EXISTS `tamano_bytes` INT UNSIGNED NULL AFTER `tipo_mime`;

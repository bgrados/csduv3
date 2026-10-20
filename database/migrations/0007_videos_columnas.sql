-- ============================================================================
--  CSDUV3 - Migración 0007
-- ----------------------------------------------------------------------------
--  Archivo:  database/migrations/0007_videos_columnas.sql
--
--  Qué hace
--  --------
--  Red de seguridad: se asegura de que la tabla de noticias tenga las
--  columnas del video (`video_url` y `video_tipo`).
--
--  La migración 0006 las crea, pero si en algún momento se registró como
--  aplicada sin que las columnas quedaran realmente en la tabla, esta
--  migración las vuelve a agregar. Como usa «ADD COLUMN IF NOT EXISTS», si
--  ya existen no hace nada.
--
--  ¿Cuándo ejecutarla?
--  -------------------
--    * Aplíquela una vez.
--
--  Seguridad
--  ---------
--    * Solo agrega columnas; no toca datos.
--    * Es idempotente: puede ejecutarse más de una vez.
-- ============================================================================

SET NAMES utf8mb4;

ALTER TABLE `csduv3_fotos`
    ADD COLUMN IF NOT EXISTS `video_url` VARCHAR(255) NULL COMMENT 'Enlace al video (YouTube o Facebook)';

ALTER TABLE `csduv3_fotos`
    ADD COLUMN IF NOT EXISTS `video_tipo` VARCHAR(20) NULL COMMENT 'youtube o facebook';

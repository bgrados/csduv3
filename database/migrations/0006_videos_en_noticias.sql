-- ============================================================================
--  CSDUV3 - Migración 0006
-- ----------------------------------------------------------------------------
--  Archivo:  database/migrations/0006_videos_en_noticias.sql
--
--  Qué hace
--  --------
--  Permite que una noticia tenga un video de YouTube o de Facebook, además de
--  (o en lugar de) su foto. Con el video, la portada y la galería pueden
--  mostrar el reproductor del club.
--
--  La sentencia usa «ADD COLUMN IF NOT EXISTS», que MariaDB admite desde la
--  versión 10.0: así el archivo se puede aplicar más de una vez sin fallar.
--
--  ¿Cuándo ejecutarla?
--  -------------------
--    * Aplíquela una vez. Si las columnas ya existen, no hace nada.
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

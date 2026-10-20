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
--  ¿Cuándo ejecutarla?
--  -------------------
--    * Aplíquela una vez. Si las columnas ya existen, el script no hace nada.
--
--  Seguridad
--  ---------
--    * Solo agrega columnas; no toca datos.
--    * Es idempotente: puede ejecutarse más de una vez.
-- ============================================================================

SET NAMES utf8mb4;

SET @sql = (
    SELECT IF(
        EXISTS (
            SELECT 1
            FROM information_schema.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = 'csduv3_fotos'
              AND COLUMN_NAME = 'video_url'
        ),
        'DO 0',
        'ALTER TABLE `csduv3_fotos`
             ADD COLUMN `video_url` VARCHAR(255) NULL COMMENT ''Enlace al video (YouTube o Facebook)'' AFTER `destacado`,
             ADD COLUMN `video_tipo` VARCHAR(20) NULL COMMENT ''youtube o facebook'' AFTER `video_url`'
    )
);

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

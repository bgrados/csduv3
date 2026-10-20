-- ============================================================================
--  CSDUV3 - Migración 0003
-- ----------------------------------------------------------------------------
--  Archivo:  database/migrations/0003_fotos_noticias.sql
--
--  Qué hace
--  --------
--  Convierte la galería de fotos en un módulo de noticias:
--
--    * `resumen`: entradilla que acompaña al titular en la portada.
--    * `destacado`: marca las noticias que aparecen en la portada; el resto
--      queda solo en la galería.
--
--  ¿Cuándo ejecutarla?
--  -------------------
--    * Si ya aplicó la migración 0002: ejecute este archivo.
--    * Si NO ha aplicado la 0002, aplique las dos en orden; la 0002 ya incluye
--      estas columnas y la 0003 no hace nada gracias a `IF NOT EXISTS`.
--
--  Seguridad
--  ---------
--    * Solo agrega columnas; no borra ni modifica datos.
--    * Es idempotente: puede ejecutarse más de una vez.
-- ============================================================================

SET NAMES utf8mb4;

ALTER TABLE `csduv3_fotos`
    ADD COLUMN IF NOT EXISTS `resumen` VARCHAR(300) NULL COMMENT 'Entradilla de la noticia' AFTER `titulo`;

ALTER TABLE `csduv3_fotos`
    ADD COLUMN IF NOT EXISTS `destacado` TINYINT(1) NOT NULL DEFAULT 0
        COMMENT '1 = aparece como noticia en la portada' AFTER `visible`;

-- Índice para la portada: primero las noticias destacadas y más recientes.
ALTER TABLE `csduv3_fotos`
    ADD INDEX IF NOT EXISTS `idx_fotos_portada` (`visible`, `destacado`, `fecha_foto`);

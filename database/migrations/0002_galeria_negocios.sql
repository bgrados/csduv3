-- ============================================================================
--  CSDUV3 - Migración 0002
-- ----------------------------------------------------------------------------
--  Archivo:  database/migrations/0002_galeria_negocios.sql
--
--  Qué hace
--  --------
--  Crea dos tablas nuevas:
--
--    * `csduv3_fotos`: galería institucional (reuniones, campeonatos, junta
--      directiva, socios). Alimenta la sección de la portada que funciona
--      como periódico del club.
--    * `csduv3_negocios`: negocios de la unidad vecinal que el club publica
--      en su portal, con su enlace y datos de contacto.
--
--  También carga el parámetro `porcentaje_adelanto`, que se usa como valor
--  por defecto al calcular el adelanto de una reserva. El club puede cambiar
--  ese valor en la base de datos.
--
--  ¿Cuándo ejecutarla?
--  -------------------
--    * Si instaló el esquema antes de esta migración: ejecute este archivo
--      una vez desde phpMyAdmin o con `php tools/migrar.php`.
--    * Si importa `database/01_esquema.sql` de esta versión o posterior, las
--      tablas ya vienen incluidas y NO necesita ejecutar esta migración.
--
--  Seguridad
--  ---------
--    * Solo crea tablas nuevas (CREATE TABLE IF NOT EXISTS).
--    * No contiene DROP, TRUNCATE, DELETE ni ALTER destructivo.
--    * No modifica ni borra datos existentes.
--    * Puede ejecutarse más de una vez sin efectos secundarios.
-- ============================================================================

SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS `csduv3_fotos` (
    `id`              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `titulo`          VARCHAR(180) NOT NULL COMMENT 'Titular de la noticia',
    `resumen`         VARCHAR(300) NULL COMMENT 'Entradilla de la noticia',
    `descripcion`     VARCHAR(500) NULL,
    `categoria`       VARCHAR(40) NOT NULL DEFAULT 'evento'
                      COMMENT 'reunion, campeonato, directiva, socios, evento, instalacion',
    `archivo`         VARCHAR(255) NULL COMMENT 'Ruta relativa dentro de storage/uploads (nunca pública)',
    `nombre_original` VARCHAR(200) NULL,
    `tipo_mime`       VARCHAR(120) NULL,
    `tamano_bytes`    INT UNSIGNED NULL,
    `fecha_foto`      DATE NULL,
    `visible`         TINYINT(1) NOT NULL DEFAULT 1,
    `destacado`       TINYINT(1) NOT NULL DEFAULT 0 COMMENT '1 = aparece como noticia en la portada',
    `orden`           SMALLINT UNSIGNED NOT NULL DEFAULT 1,
    `subido_por`      BIGINT UNSIGNED NULL,
    `created_at`      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_fotos_visible` (`visible`, `fecha_foto`),
    KEY `idx_fotos_categoria` (`categoria`),
    CONSTRAINT `fk_fotos_usuario`
        FOREIGN KEY (`subido_por`) REFERENCES `csduv3_usuarios` (`id`)
        ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Galería institucional y noticias del club';

CREATE TABLE IF NOT EXISTS `csduv3_negocios` (
    `id`              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `nombre`          VARCHAR(140) NOT NULL,
    `categoria`       VARCHAR(40) NOT NULL DEFAULT 'comercio'
                      COMMENT 'comercio, servicio, profesional, salud, educacion, otro',
    `descripcion`     VARCHAR(600) NULL,
    `telefono`        VARCHAR(30) NULL,
    `whatsapp`        VARCHAR(30) NULL,
    `email`           VARCHAR(160) NULL,
    `web`             VARCHAR(255) NULL COMMENT 'Enlace del negocio',
    `direccion`       VARCHAR(200) NULL,
    `horario`         VARCHAR(200) NULL,
    `archivo`         VARCHAR(255) NULL COMMENT 'Logotipo o foto, dentro de storage/uploads',
    `nombre_original` VARCHAR(200) NULL,
    `tipo_mime`       VARCHAR(120) NULL,
    `tamano_bytes`    INT UNSIGNED NULL,
    `visible`         TINYINT(1) NOT NULL DEFAULT 1,
    `destacado`       TINYINT(1) NOT NULL DEFAULT 0,
    `orden`           SMALLINT UNSIGNED NOT NULL DEFAULT 1,
    `creado_por`      BIGINT UNSIGNED NULL,
    `created_at`      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_negocios_visible` (`visible`, `orden`),
    KEY `idx_negocios_categoria` (`categoria`),
    CONSTRAINT `fk_negocios_usuario`
        FOREIGN KEY (`creado_por`) REFERENCES `csduv3_usuarios` (`id`)
        ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Negocios de la unidad vecinal publicados por el club';

-- Parámetro institucional: porcentaje de adelanto por defecto en las reservas.
INSERT IGNORE INTO `csduv3_configuracion` (`clave`, `valor`, `tipo`, `grupo`, `descripcion`)
VALUES ('porcentaje_adelanto', '50', 'numero', 'finanzas',
        'Porcentaje de adelanto por defecto al crear una reserva de alquiler');

-- ============================================================================
--  CSDUV3 - Migración 0001
-- ----------------------------------------------------------------------------
--  Archivo:  database/migrations/0001_solicitudes_alquiler.sql
--
--  Qué hace
--  --------
--  Crea la tabla `csduv3_solicitudes_alquiler`, que almacena las solicitudes de
--  alquiler enviadas desde el portal público (Fase 2).
--
--  ¿Cuándo ejecutarla?
--  -------------------
--    * Si instaló el esquema de la Fase 1 ANTES de esta migración: ejecute este
--      archivo una vez desde phpMyAdmin.
--    * Si importa `database/01_esquema.sql` de esta versión o posterior, la
--      tabla ya viene incluida y NO necesita ejecutar esta migración.
--
--  Seguridad
--  ---------
--    * Solo crea una tabla nueva (CREATE TABLE IF NOT EXISTS).
--    * No contiene DROP, TRUNCATE, DELETE ni ALTER destructivo.
--    * No modifica ni borra datos existentes.
--    * Puede ejecutarse más de una vez sin efectos secundarios.
--
--  Requisitos: las tablas csduv3_instalaciones, csduv3_tipos_evento y
--  csduv3_usuarios deben existir (se crean en 01_esquema.sql).
-- ============================================================================

SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS `csduv3_solicitudes_alquiler` (
    `id`                    BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `codigo`                VARCHAR(30)  NOT NULL COMMENT 'Código de seguimiento que se muestra al solicitante',
    `instalacion_id`        BIGINT UNSIGNED NOT NULL,
    `tipo_evento_id`        BIGINT UNSIGNED NULL,
    `solicitante_nombre`    VARCHAR(180) NOT NULL,
    `solicitante_documento` VARCHAR(30)  NULL,
    `solicitante_telefono`  VARCHAR(30)  NOT NULL COMMENT 'Dato de contacto para responder la solicitud',
    `solicitante_email`     VARCHAR(160) NULL,
    `fecha_evento`          DATE NOT NULL,
    `hora_inicio`           TIME NOT NULL,
    `hora_fin`              TIME NOT NULL,
    `cantidad_personas`     SMALLINT UNSIGNED NULL,
    `descripcion`           VARCHAR(1000) NULL,
    `acepta_condiciones`    TINYINT(1) NOT NULL DEFAULT 0,
    `estado`                VARCHAR(20) NOT NULL DEFAULT 'pendiente'
                            COMMENT 'pendiente, en_revision, atendida, rechazada',
    `conflicto_detectado`   TINYINT(1) NOT NULL DEFAULT 0
                            COMMENT 'Otra solicitud pendiente se superpone con el mismo horario',
    `nota_interna`          VARCHAR(500) NULL,
    `motivo_rechazo`        VARCHAR(300) NULL,
    `revisado_por`          BIGINT UNSIGNED NULL,
    `revisado_at`           DATETIME NULL,
    `ip`                    VARCHAR(45) NULL COMMENT 'Se usa para limitar envíos repetidos',
    `user_agent`            VARCHAR(255) NULL,
    `created_at`            DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`            DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_solicitudes_codigo` (`codigo`),
    KEY `idx_solicitudes_estado_fecha` (`estado`, `created_at`),
    KEY `idx_solicitudes_agenda` (`instalacion_id`, `fecha_evento`, `hora_inicio`, `hora_fin`),
    KEY `idx_solicitudes_ip` (`ip`, `created_at`),
    CONSTRAINT `fk_solicitudes_instalacion`
        FOREIGN KEY (`instalacion_id`) REFERENCES `csduv3_instalaciones` (`id`)
        ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT `fk_solicitudes_tipo_evento`
        FOREIGN KEY (`tipo_evento_id`) REFERENCES `csduv3_tipos_evento` (`id`)
        ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT `fk_solicitudes_revisor`
        FOREIGN KEY (`revisado_por`) REFERENCES `csduv3_usuarios` (`id`)
        ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT `chk_solicitudes_horario` CHECK (`hora_fin` > `hora_inicio`),
    CONSTRAINT `chk_solicitudes_estado`
        CHECK (`estado` IN ('pendiente', 'en_revision', 'atendida', 'rechazada'))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Solicitudes de alquiler del portal público. NO son reservas confirmadas';

-- ----------------------------------------------------------------------------
--  Verificación posterior
--  ---------------------------------------------------------------------------
--  SELECT COUNT(*) FROM information_schema.tables
--   WHERE table_schema = DATABASE()
--     AND table_name = 'csduv3_solicitudes_alquiler';
--  Resultado esperado: 1
-- ----------------------------------------------------------------------------

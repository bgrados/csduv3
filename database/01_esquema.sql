-- ============================================================================
--  CSDUV3 - Club Social Deportivo Unidad Vecinal N.º 3
--  Esquema inicial de base de datos (MariaDB / MySQL compatible)
-- ----------------------------------------------------------------------------
--  Archivo:      database/01_esquema.sql
--  Motor:        InnoDB
--  Juego:        utf8mb4 / utf8mb4_unicode_ci
--  Prefijo:      csduv3_
--
--  IMPORTANTE
--   * No contiene instrucciones DROP ni DELETE: no borra información existente.
--   * Todas las tablas usan CREATE TABLE IF NOT EXISTS, por lo que puede
--     ejecutarse más de una vez sin destruir datos.
--   * Los cambios posteriores de estructura se registran en database/migrations/
--     (ver docs/BASE_DE_DATOS.md).
--   * Ejecutar desde phpMyAdmin: pestaña "Importar" sobre la base de datos del
--     hosting, o pestaña "SQL" pegando el contenido.
--
--  Orden recomendado de importación:
--     1) database/01_esquema.sql
--     2) database/02_datos_iniciales.sql
--     3) database/03_demo_desarrollo.sql   (solo desarrollo, nunca producción)
-- ============================================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 1;

-- La zona horaria se fija con desplazamiento para no depender de las tablas de
-- zonas horarias del servidor. Si el hosting lo impide, la aplicación también
-- la ajusta al abrir la conexión (config/database.php).
-- SET time_zone = '-05:00';


-- ============================================================================
--  1. CONFIGURACIÓN INSTITUCIONAL
-- ============================================================================

CREATE TABLE IF NOT EXISTS `csduv3_configuracion` (
    `id`          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `clave`       VARCHAR(80)  NOT NULL,
    `valor`       TEXT         NULL,
    `tipo`        VARCHAR(20)  NOT NULL DEFAULT 'texto' COMMENT 'texto, numero, booleano, fecha, archivo',
    `grupo`       VARCHAR(40)  NOT NULL DEFAULT 'general' COMMENT 'general, identidad, contacto, finanzas',
    `descripcion` VARCHAR(200) NULL,
    `created_at`  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_configuracion_clave` (`clave`),
    KEY `idx_configuracion_grupo` (`grupo`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Parámetros configurables del sistema y de la identidad del club';


-- ============================================================================
--  2. SEGURIDAD: ROLES, PERMISOS Y USUARIOS
-- ============================================================================

CREATE TABLE IF NOT EXISTS `csduv3_roles` (
    `id`          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `nombre`      VARCHAR(60)  NOT NULL,
    `clave`       VARCHAR(60)  NOT NULL,
    `descripcion` VARCHAR(200) NULL,
    `es_sistema`  TINYINT(1)   NOT NULL DEFAULT 0 COMMENT '1 = rol base, no eliminable',
    `activo`      TINYINT(1)   NOT NULL DEFAULT 1,
    `created_at`  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_roles_clave` (`clave`),
    UNIQUE KEY `uq_roles_nombre` (`nombre`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Roles del sistema (administrador, tesorería, secretaría, etc.)';

CREATE TABLE IF NOT EXISTS `csduv3_permisos` (
    `id`          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `clave`       VARCHAR(80)  NOT NULL COMMENT 'Formato modulo.accion, por ejemplo socios.crear',
    `modulo`      VARCHAR(40)  NOT NULL,
    `descripcion` VARCHAR(200) NOT NULL,
    `created_at`  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_permisos_clave` (`clave`),
    KEY `idx_permisos_modulo` (`modulo`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Permisos verificables en el servidor';

CREATE TABLE IF NOT EXISTS `csduv3_roles_permisos` (
    `rol_id`     BIGINT UNSIGNED NOT NULL,
    `permiso_id` BIGINT UNSIGNED NOT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`rol_id`, `permiso_id`),
    KEY `idx_roles_permisos_permiso` (`permiso_id`),
    CONSTRAINT `fk_roles_permisos_rol`
        FOREIGN KEY (`rol_id`) REFERENCES `csduv3_roles` (`id`)
        ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `fk_roles_permisos_permiso`
        FOREIGN KEY (`permiso_id`) REFERENCES `csduv3_permisos` (`id`)
        ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Asignación de permisos a cada rol';

CREATE TABLE IF NOT EXISTS `csduv3_usuarios` (
    `id`                  BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `nombres`             VARCHAR(120) NOT NULL,
    `apellidos`           VARCHAR(120) NOT NULL,
    `usuario`             VARCHAR(60)  NOT NULL COMMENT 'Nombre de acceso',
    `email`               VARCHAR(160) NULL,
    `password_hash`       VARCHAR(255) NOT NULL COMMENT 'Generado con password_hash()',
    `rol_id`              BIGINT UNSIGNED NULL,
    `socio_id`            BIGINT UNSIGNED NULL COMMENT 'Si el usuario también es socio',
    `telefono`            VARCHAR(30)  NULL,
    `cargo_institucional` VARCHAR(120) NULL,
    `activo`              TINYINT(1)   NOT NULL DEFAULT 1,
    `debe_cambiar_password` TINYINT(1) NOT NULL DEFAULT 0,
    `ultimo_acceso`       DATETIME     NULL,
    `intentos_fallidos`   SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    `bloqueado_hasta`     DATETIME     NULL,
    `created_at`          DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`          DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_usuarios_usuario` (`usuario`),
    UNIQUE KEY `uq_usuarios_email` (`email`),
    KEY `idx_usuarios_rol` (`rol_id`),
    KEY `idx_usuarios_socio` (`socio_id`),
    KEY `idx_usuarios_activo` (`activo`),
    CONSTRAINT `fk_usuarios_rol`
        FOREIGN KEY (`rol_id`) REFERENCES `csduv3_roles` (`id`)
        ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Usuarios del sistema. Nunca se almacena la contraseña en texto plano';

CREATE TABLE IF NOT EXISTS `csduv3_accesos` (
    `id`               BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `usuario_id`       BIGINT UNSIGNED NULL,
    `usuario_intentado` VARCHAR(60)  NULL,
    `exito`            TINYINT(1)   NOT NULL DEFAULT 0,
    `ip`               VARCHAR(45)  NULL,
    `user_agent`       VARCHAR(255) NULL,
    `created_at`       DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_accesos_usuario_fecha` (`usuario_id`, `created_at`),
    KEY `idx_accesos_ip_fecha` (`ip`, `created_at`),
    CONSTRAINT `fk_accesos_usuario`
        FOREIGN KEY (`usuario_id`) REFERENCES `csduv3_usuarios` (`id`)
        ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Historial de intentos de acceso, para limitar intentos repetidos';


-- ============================================================================
--  3. SOCIOS
-- ============================================================================

CREATE TABLE IF NOT EXISTS `csduv3_categorias_socio` (
    `id`             BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `nombre`         VARCHAR(80) NOT NULL,
    `descripcion`    VARCHAR(200) NULL,
    `edad_minima`    TINYINT UNSIGNED NULL,
    `edad_maxima`    TINYINT UNSIGNED NULL,
    `cuota_sugerida` DECIMAL(12,2) NULL COMMENT 'Referencial; cada cuota se define en conceptos_pago',
    `orden`          SMALLINT UNSIGNED NOT NULL DEFAULT 1,
    `activo`         TINYINT(1) NOT NULL DEFAULT 1,
    `created_at`     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_categorias_socio_nombre` (`nombre`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Categorías de socio configurables por la institución';

CREATE TABLE IF NOT EXISTS `csduv3_socios` (
    `id`               BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `codigo`           VARCHAR(30)  NOT NULL COMMENT 'Código único de socio',
    `nombres`          VARCHAR(120) NOT NULL,
    `apellidos`        VARCHAR(120) NOT NULL,
    `tipo_documento`   VARCHAR(20)  NOT NULL DEFAULT 'DNI' COMMENT 'DNI, CE, PASAPORTE, OTRO',
    `numero_documento` VARCHAR(30)  NULL,
    `fecha_nacimiento` DATE         NULL,
    `sexo`             VARCHAR(20)  NULL COMMENT 'M, F, otro; informativo',
    `estado_civil`     VARCHAR(30)  NULL,
    `direccion`        VARCHAR(200) NULL,
    `distrito`         VARCHAR(80)  NULL,
    `telefono`         VARCHAR(30)  NULL,
    `telefono_alterno` VARCHAR(30)  NULL,
    `email`            VARCHAR(160) NULL,
    `fecha_ingreso`    DATE         NULL,
    `categoria_id`     BIGINT UNSIGNED NULL,
    `estado`           VARCHAR(20)  NOT NULL DEFAULT 'activo'
                       COMMENT 'activo, inactivo, pendiente (regularización), baja',
    `observaciones`    TEXT         NULL,
    `foto`             VARCHAR(255) NULL COMMENT 'Ruta relativa dentro de storage/uploads',
    `created_at`       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `creado_por`       BIGINT UNSIGNED NULL,
    `actualizado_por`  BIGINT UNSIGNED NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_socios_codigo` (`codigo`),
    UNIQUE KEY `uq_socios_documento` (`tipo_documento`, `numero_documento`),
    KEY `idx_socios_apellidos` (`apellidos`, `nombres`),
    KEY `idx_socios_estado` (`estado`),
    KEY `idx_socios_categoria` (`categoria_id`),
    CONSTRAINT `fk_socios_categoria`
        FOREIGN KEY (`categoria_id`) REFERENCES `csduv3_categorias_socio` (`id`)
        ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT `fk_socios_creado_por`
        FOREIGN KEY (`creado_por`) REFERENCES `csduv3_usuarios` (`id`)
        ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT `fk_socios_actualizado_por`
        FOREIGN KEY (`actualizado_por`) REFERENCES `csduv3_usuarios` (`id`)
        ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Registro de socios del club';

CREATE TABLE IF NOT EXISTS `csduv3_socios_bajas` (
    `id`            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `socio_id`      BIGINT UNSIGNED NOT NULL,
    `fecha_baja`    DATE NOT NULL,
    `motivo`        VARCHAR(300) NULL,
    `es_renuncia`   TINYINT(1) NOT NULL DEFAULT 0,
    `registrado_por` BIGINT UNSIGNED NULL,
    `created_at`    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_socios_bajas_socio` (`socio_id`),
    CONSTRAINT `fk_socios_bajas_socio`
        FOREIGN KEY (`socio_id`) REFERENCES `csduv3_socios` (`id`)
        ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT `fk_socios_bajas_usuario`
        FOREIGN KEY (`registrado_por`) REFERENCES `csduv3_usuarios` (`id`)
        ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Bajas de socios: el socio no se elimina, se conserva su historial';

CREATE TABLE IF NOT EXISTS `csduv3_socios_documentos` (
    `id`             BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `socio_id`       BIGINT UNSIGNED NOT NULL,
    `titulo`         VARCHAR(150) NOT NULL,
    `archivo`        VARCHAR(255) NOT NULL COMMENT 'Ruta relativa dentro de storage/uploads',
    `nombre_original` VARCHAR(200) NULL,
    `tipo_mime`      VARCHAR(120) NULL,
    `tamano_bytes`   INT UNSIGNED NULL,
    `subido_por`     BIGINT UNSIGNED NULL,
    `created_at`     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_socios_documentos_socio` (`socio_id`),
    CONSTRAINT `fk_socios_documentos_socio`
        FOREIGN KEY (`socio_id`) REFERENCES `csduv3_socios` (`id`)
        ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `fk_socios_documentos_usuario`
        FOREIGN KEY (`subido_por`) REFERENCES `csduv3_usuarios` (`id`)
        ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Documentos adjuntos a la ficha del socio';


-- ============================================================================
--  4. CONCEPTOS DE PAGO (CONFIGURABLES)
-- ============================================================================

CREATE TABLE IF NOT EXISTS `csduv3_conceptos_pago` (
    `id`             BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `nombre`         VARCHAR(100) NOT NULL,
    `tipo`           VARCHAR(20) NOT NULL DEFAULT 'ingreso' COMMENT 'ingreso',
    `monto_sugerido` DECIMAL(12,2) NULL,
    `periodicidad`   VARCHAR(20) NOT NULL DEFAULT 'unica'
                     COMMENT 'unica, mensual, trimestral, semestral, anual',
    `categoria_movimiento_id` BIGINT UNSIGNED NULL COMMENT 'Categoría de caja asociada',
    `activo`         TINYINT(1) NOT NULL DEFAULT 1,
    `observaciones`  VARCHAR(200) NULL,
    `created_at`     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_conceptos_pago_nombre` (`nombre`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Conceptos de cobro configurables (cuotas, inscripciones, etc.)';


-- ============================================================================
--  5. JUNTA DIRECTIVA Y ELECCIONES
-- ============================================================================

CREATE TABLE IF NOT EXISTS `csduv3_directiva_cargos` (
    `id`          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `nombre`      VARCHAR(80) NOT NULL,
    `descripcion` VARCHAR(200) NULL,
    `orden`       SMALLINT UNSIGNED NOT NULL DEFAULT 1,
    `activo`      TINYINT(1) NOT NULL DEFAULT 1,
    `created_at`  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_directiva_cargos_nombre` (`nombre`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Cargos de la junta directiva, configurables según el estatuto';

CREATE TABLE IF NOT EXISTS `csduv3_directivas` (
    `id`            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `nombre`        VARCHAR(120) NOT NULL COMMENT 'Denominación del periodo o gestión',
    `fecha_inicio`  DATE NOT NULL,
    `fecha_fin`     DATE NULL,
    `estado`        VARCHAR(20) NOT NULL DEFAULT 'vigente' COMMENT 'vigente, concluida, anulada',
    `observaciones` TEXT NULL,
    `created_at`    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_directivas_fechas` (`fecha_inicio`, `fecha_fin`),
    KEY `idx_directivas_estado` (`estado`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Periodos de la junta directiva (se conserva el histórico)';

CREATE TABLE IF NOT EXISTS `csduv3_directiva_miembros` (
    `id`             BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `directiva_id`   BIGINT UNSIGNED NOT NULL,
    `cargo_id`       BIGINT UNSIGNED NOT NULL,
    `socio_id`       BIGINT UNSIGNED NULL COMMENT 'Si el directivo es socio registrado',
    `nombre_completo` VARCHAR(180) NULL COMMENT 'Obligatorio cuando no es socio registrado',
    `documento`      VARCHAR(30) NULL,
    `telefono`       VARCHAR(30) NULL,
    `email`          VARCHAR(160) NULL,
    `fecha_inicio`   DATE NULL,
    `fecha_fin`      DATE NULL,
    `estado`         VARCHAR(20) NOT NULL DEFAULT 'vigente' COMMENT 'vigente, cesado, renuncia',
    `observaciones`  VARCHAR(300) NULL,
    `created_at`     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_directiva_miembros_directiva` (`directiva_id`),
    KEY `idx_directiva_miembros_cargo` (`cargo_id`),
    KEY `idx_directiva_miembros_socio` (`socio_id`),
    CONSTRAINT `fk_directiva_miembros_directiva`
        FOREIGN KEY (`directiva_id`) REFERENCES `csduv3_directivas` (`id`)
        ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `fk_directiva_miembros_cargo`
        FOREIGN KEY (`cargo_id`) REFERENCES `csduv3_directiva_cargos` (`id`)
        ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT `fk_directiva_miembros_socio`
        FOREIGN KEY (`socio_id`) REFERENCES `csduv3_socios` (`id`)
        ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Integrantes de cada junta directiva y el cargo que desempeñan';

CREATE TABLE IF NOT EXISTS `csduv3_procesos_electorales` (
    `id`                  BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `nombre`              VARCHAR(150) NOT NULL,
    `descripcion`         TEXT NULL,
    `fecha_convocatoria`  DATE NULL,
    `fecha_eleccion`      DATE NULL,
    `periodo_inicio`      DATE NULL COMMENT 'Inicio del mandato a elegir',
    `periodo_fin`         DATE NULL COMMENT 'Fin del mandato a elegir',
    `anios_mandato`       TINYINT UNSIGNED NULL COMMENT 'Normalmente 4, según estatuto',
    `estado`              VARCHAR(20) NOT NULL DEFAULT 'planificado'
                          COMMENT 'planificado, convocado, en_proceso, finalizado, anulado',
    `reglamento`          TEXT NULL COMMENT 'Reglas aplicables tomadas del estatuto',
    `observaciones`       TEXT NULL,
    `created_at`          DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`          DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_procesos_electorales_fecha` (`fecha_eleccion`),
    KEY `idx_procesos_electorales_estado` (`estado`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Procesos electorales del club';

CREATE TABLE IF NOT EXISTS `csduv3_listas_electorales` (
    `id`            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `proceso_id`    BIGINT UNSIGNED NOT NULL,
    `nombre`        VARCHAR(120) NOT NULL,
    `lema`          VARCHAR(200) NULL,
    `orden`         SMALLINT UNSIGNED NOT NULL DEFAULT 1 COMMENT 'Orden en la cédula',
    `estado`        VARCHAR(20) NOT NULL DEFAULT 'inscrita' COMMENT 'inscrita, retirada, tachada, ganadora',
    `observaciones` VARCHAR(300) NULL,
    `created_at`    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_listas_proceso_nombre` (`proceso_id`, `nombre`),
    CONSTRAINT `fk_listas_proceso`
        FOREIGN KEY (`proceso_id`) REFERENCES `csduv3_procesos_electorales` (`id`)
        ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Listas o agrupaciones participantes en un proceso electoral';

CREATE TABLE IF NOT EXISTS `csduv3_candidatos` (
    `id`             BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `proceso_id`     BIGINT UNSIGNED NOT NULL,
    `lista_id`       BIGINT UNSIGNED NULL,
    `socio_id`       BIGINT UNSIGNED NULL,
    `nombres`        VARCHAR(120) NOT NULL,
    `apellidos`      VARCHAR(120) NOT NULL,
    `documento`      VARCHAR(30) NULL,
    `cargo_id`       BIGINT UNSIGNED NULL COMMENT 'Cargo al que postula, si aplica',
    `orden`          SMALLINT UNSIGNED NOT NULL DEFAULT 1 COMMENT 'Posición en la lista',
    `es_cabeza_lista` TINYINT(1) NOT NULL DEFAULT 0,
    `observaciones`  VARCHAR(300) NULL,
    `created_at`     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_candidatos_proceso` (`proceso_id`),
    KEY `idx_candidatos_lista` (`lista_id`),
    KEY `idx_candidatos_socio` (`socio_id`),
    CONSTRAINT `fk_candidatos_proceso`
        FOREIGN KEY (`proceso_id`) REFERENCES `csduv3_procesos_electorales` (`id`)
        ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `fk_candidatos_lista`
        FOREIGN KEY (`lista_id`) REFERENCES `csduv3_listas_electorales` (`id`)
        ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT `fk_candidatos_socio`
        FOREIGN KEY (`socio_id`) REFERENCES `csduv3_socios` (`id`)
        ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT `fk_candidatos_cargo`
        FOREIGN KEY (`cargo_id`) REFERENCES `csduv3_directiva_cargos` (`id`)
        ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Candidatos registrados en un proceso electoral';

CREATE TABLE IF NOT EXISTS `csduv3_resultados_electorales` (
    `id`            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `proceso_id`    BIGINT UNSIGNED NOT NULL,
    `lista_id`      BIGINT UNSIGNED NOT NULL,
    `votos`         INT UNSIGNED NOT NULL DEFAULT 0,
    `votos_nulos`   INT UNSIGNED NOT NULL DEFAULT 0,
    `votos_blancos` INT UNSIGNED NOT NULL DEFAULT 0,
    `es_ganadora`   TINYINT(1) NOT NULL DEFAULT 0,
    `registrado_por` BIGINT UNSIGNED NULL,
    `fecha_registro` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `observaciones` TEXT NULL,
    `created_at`    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_resultados_proceso_lista` (`proceso_id`, `lista_id`),
    CONSTRAINT `fk_resultados_proceso`
        FOREIGN KEY (`proceso_id`) REFERENCES `csduv3_procesos_electorales` (`id`)
        ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `fk_resultados_lista`
        FOREIGN KEY (`lista_id`) REFERENCES `csduv3_listas_electorales` (`id`)
        ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `fk_resultados_usuario`
        FOREIGN KEY (`registrado_por`) REFERENCES `csduv3_usuarios` (`id`)
        ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Registro administrativo de resultados. No es un sistema de votación electrónica';


-- ============================================================================
--  6. INSTALACIONES, TARIFAS Y ALQUILERES
-- ============================================================================

CREATE TABLE IF NOT EXISTS `csduv3_tipos_evento` (
    `id`               BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `nombre`           VARCHAR(80) NOT NULL,
    `requiere_contrato` TINYINT(1) NOT NULL DEFAULT 0,
    `descripcion`      VARCHAR(200) NULL,
    `activo`           TINYINT(1) NOT NULL DEFAULT 1,
    `created_at`       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_tipos_evento_nombre` (`nombre`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Tipos de uso autorizado: matrimonio, reunión social, velorio, otros';

CREATE TABLE IF NOT EXISTS `csduv3_instalaciones` (
    `id`          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `nombre`      VARCHAR(120) NOT NULL,
    `tipo`        VARCHAR(40) NOT NULL DEFAULT 'ambiente'
                  COMMENT 'ambiente, losa, cancha, salón, otro',
    `descripcion` VARCHAR(300) NULL,
    `ubicacion`   VARCHAR(150) NULL,
    `aforo`       SMALLINT UNSIGNED NULL,
    `archivo`         VARCHAR(255) NULL,
    `nombre_original` VARCHAR(200) NULL,
    `tipo_mime`       VARCHAR(120) NULL,
    `tamano_bytes`    INT UNSIGNED NULL,
    `requiere_adelanto` TINYINT(1) NOT NULL DEFAULT 0,
    `activo`      TINYINT(1) NOT NULL DEFAULT 1,
    `created_at`  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_instalaciones_nombre` (`nombre`),
    KEY `idx_instalaciones_activo` (`activo`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Ambientes e instalaciones que el club alquila';

CREATE TABLE IF NOT EXISTS `csduv3_tarifas` (
    `id`             BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `instalacion_id` BIGINT UNSIGNED NOT NULL,
    `tipo_evento_id` BIGINT UNSIGNED NULL,
    `nombre`         VARCHAR(120) NOT NULL,
    `monto`          DECIMAL(12,2) NOT NULL,
    `moneda`         CHAR(3) NOT NULL DEFAULT 'PEN',
    `hora_inicio`    TIME NULL COMMENT 'Franja horaria opcional',
    `hora_fin`       TIME NULL,
    `vigente_desde`  DATE NULL,
    `vigente_hasta`  DATE NULL,
    `activo`         TINYINT(1) NOT NULL DEFAULT 1,
    `observaciones`  VARCHAR(300) NULL,
    `created_at`     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_tarifas_instalacion` (`instalacion_id`, `activo`),
    KEY `idx_tarifas_vigencia` (`vigente_desde`, `vigente_hasta`),
    CONSTRAINT `fk_tarifas_instalacion`
        FOREIGN KEY (`instalacion_id`) REFERENCES `csduv3_instalaciones` (`id`)
        ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `fk_tarifas_tipo_evento`
        FOREIGN KEY (`tipo_evento_id`) REFERENCES `csduv3_tipos_evento` (`id`)
        ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT `chk_tarifas_monto` CHECK (`monto` >= 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Tarifas configurables por instalación y tipo de evento';

CREATE TABLE IF NOT EXISTS `csduv3_reservas` (
    `id`                    BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `codigo`                VARCHAR(30) NULL COMMENT 'Código interno de la reserva',
    `instalacion_id`        BIGINT UNSIGNED NOT NULL,
    `tarifa_id`             BIGINT UNSIGNED NULL,
    `tipo_evento_id`        BIGINT UNSIGNED NULL,
    `socio_id`              BIGINT UNSIGNED NULL COMMENT 'Cuando el solicitante es socio',
    `solicitante_nombre`    VARCHAR(180) NOT NULL,
    `solicitante_documento` VARCHAR(30) NULL,
    `solicitante_telefono`  VARCHAR(30) NULL,
    `solicitante_email`     VARCHAR(160) NULL,
    `fecha_evento`          DATE NOT NULL,
    `hora_inicio`           TIME NOT NULL,
    `hora_fin`              TIME NOT NULL,
    `precio_acordado`       DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    `monto_adelanto`        DECIMAL(12,2) NOT NULL DEFAULT 0.00 COMMENT 'Calculado en el servidor',
    `monto_pagado`          DECIMAL(12,2) NOT NULL DEFAULT 0.00 COMMENT 'Calculado en el servidor',
    `estado`                VARCHAR(20) NOT NULL DEFAULT 'pendiente'
                            COMMENT 'pendiente, confirmada, cancelada, finalizada',
    `condiciones`           TEXT NULL,
    `observaciones`         TEXT NULL,
    `motivo_cancelacion`    VARCHAR(300) NULL,
    `cancelada_por`         BIGINT UNSIGNED NULL,
    `cancelada_at`          DATETIME NULL,
    `registrado_por`        BIGINT UNSIGNED NULL,
    `created_at`            DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`            DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_reservas_codigo` (`codigo`),
    KEY `idx_reservas_instalacion_fecha` (`instalacion_id`, `fecha_evento`, `hora_inicio`, `hora_fin`),
    KEY `idx_reservas_fecha` (`fecha_evento`),
    KEY `idx_reservas_estado` (`estado`),
    KEY `idx_reservas_socio` (`socio_id`),
    CONSTRAINT `fk_reservas_instalacion`
        FOREIGN KEY (`instalacion_id`) REFERENCES `csduv3_instalaciones` (`id`)
        ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT `fk_reservas_tarifa`
        FOREIGN KEY (`tarifa_id`) REFERENCES `csduv3_tarifas` (`id`)
        ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT `fk_reservas_tipo_evento`
        FOREIGN KEY (`tipo_evento_id`) REFERENCES `csduv3_tipos_evento` (`id`)
        ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT `fk_reservas_socio`
        FOREIGN KEY (`socio_id`) REFERENCES `csduv3_socios` (`id`)
        ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT `fk_reservas_usuario`
        FOREIGN KEY (`registrado_por`) REFERENCES `csduv3_usuarios` (`id`)
        ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT `fk_reservas_cancela`
        FOREIGN KEY (`cancelada_por`) REFERENCES `csduv3_usuarios` (`id`)
        ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT `chk_reservas_importes` CHECK (`precio_acordado` >= 0 AND `monto_adelanto` >= 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Reservas de instalaciones. El solapamiento se controla en la aplicación
           (SELECT ... FOR UPDATE) además del índice por instalación y fecha';

-- ---------------------------------------------------------------------------
--  Solicitudes enviadas desde el portal público.
--
--  Una solicitud NO es una reserva: queda pendiente de revisión por el personal
--  autorizado y nunca se confirma automáticamente. Cuando el club la acepta,
--  la reserva se registra en csduv3_reservas (módulo de alquileres).
-- ---------------------------------------------------------------------------

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


-- ============================================================================
--  7. CAJA: INGRESOS, EGRESOS Y PAGOS
-- ============================================================================

CREATE TABLE IF NOT EXISTS `csduv3_categorias_movimiento` (
    `id`          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `nombre`      VARCHAR(80) NOT NULL,
    `tipo`        VARCHAR(10) NOT NULL COMMENT 'ingreso, egreso',
    `descripcion` VARCHAR(200) NULL,
    `activo`      TINYINT(1) NOT NULL DEFAULT 1,
    `created_at`  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_categorias_movimiento` (`nombre`, `tipo`),
    KEY `idx_categorias_movimiento_tipo` (`tipo`, `activo`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Categorías de ingreso y egreso, configurables';

CREATE TABLE IF NOT EXISTS `csduv3_medios_pago` (
    `id`          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `nombre`      VARCHAR(60) NOT NULL,
    `descripcion` VARCHAR(200) NULL,
    `activo`      TINYINT(1) NOT NULL DEFAULT 1,
    `orden`       SMALLINT UNSIGNED NOT NULL DEFAULT 1,
    `created_at`  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_medios_pago_nombre` (`nombre`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Medios de pago aceptados (efectivo, transferencia, etc.)';

CREATE TABLE IF NOT EXISTS `csduv3_periodos` (
    `id`           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `nombre`       VARCHAR(60) NOT NULL COMMENT 'Por ejemplo: 2026 o Enero 2026',
    `fecha_inicio` DATE NOT NULL,
    `fecha_fin`    DATE NOT NULL,
    `cerrado`      TINYINT(1) NOT NULL DEFAULT 0 COMMENT '1 = no admite nuevos movimientos',
    `observaciones` VARCHAR(200) NULL,
    `created_at`   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_periodos_nombre` (`nombre`),
    KEY `idx_periodos_fechas` (`fecha_inicio`, `fecha_fin`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Periodos administrativos o contables';

CREATE TABLE IF NOT EXISTS `csduv3_movimientos` (
    `id`                 BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `tipo`               VARCHAR(10) NOT NULL COMMENT 'ingreso, egreso',
    `fecha`              DATE NOT NULL,
    `concepto`           VARCHAR(200) NOT NULL,
    `monto`              DECIMAL(12,2) NOT NULL,
    `categoria_id`       BIGINT UNSIGNED NULL,
    `medio_pago_id`      BIGINT UNSIGNED NULL,
    `periodo_id`         BIGINT UNSIGNED NULL,
    `socio_id`           BIGINT UNSIGNED NULL COMMENT 'Cuando corresponde a un socio',
    `concepto_pago_id`   BIGINT UNSIGNED NULL COMMENT 'Cuando corresponde a una cuota',
    `reserva_id`         BIGINT UNSIGNED NULL COMMENT 'Cuando corresponde a un alquiler',
    `referencia`         VARCHAR(60) NULL COMMENT 'Número de operación o documento',
    `documento_id`       BIGINT UNSIGNED NULL COMMENT 'Sustento registrado en documentos',
    `responsable_id`     BIGINT UNSIGNED NULL COMMENT 'Usuario que registró la operación',
    `observaciones`      TEXT NULL,
    `estado`             VARCHAR(20) NOT NULL DEFAULT 'registrado' COMMENT 'registrado, anulado',
    `anulado_por`        BIGINT UNSIGNED NULL,
    `anulado_at`         DATETIME NULL,
    `motivo_anulacion`   VARCHAR(300) NULL,
    `created_at`         DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`         DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_movimientos_fecha_tipo` (`fecha`, `tipo`),
    KEY `idx_movimientos_categoria` (`categoria_id`),
    KEY `idx_movimientos_periodo` (`periodo_id`),
    KEY `idx_movimientos_socio` (`socio_id`),
    KEY `idx_movimientos_reserva` (`reserva_id`),
    KEY `idx_movimientos_estado` (`estado`),
    CONSTRAINT `fk_movimientos_categoria`
        FOREIGN KEY (`categoria_id`) REFERENCES `csduv3_categorias_movimiento` (`id`)
        ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT `fk_movimientos_medio_pago`
        FOREIGN KEY (`medio_pago_id`) REFERENCES `csduv3_medios_pago` (`id`)
        ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT `fk_movimientos_periodo`
        FOREIGN KEY (`periodo_id`) REFERENCES `csduv3_periodos` (`id`)
        ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT `fk_movimientos_socio`
        FOREIGN KEY (`socio_id`) REFERENCES `csduv3_socios` (`id`)
        ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT `fk_movimientos_concepto_pago`
        FOREIGN KEY (`concepto_pago_id`) REFERENCES `csduv3_conceptos_pago` (`id`)
        ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT `fk_movimientos_reserva`
        FOREIGN KEY (`reserva_id`) REFERENCES `csduv3_reservas` (`id`)
        ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT `fk_movimientos_responsable`
        FOREIGN KEY (`responsable_id`) REFERENCES `csduv3_usuarios` (`id`)
        ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT `fk_movimientos_anulado_por`
        FOREIGN KEY (`anulado_por`) REFERENCES `csduv3_usuarios` (`id`)
        ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT `chk_movimientos_monto` CHECK (`monto` > 0),
    CONSTRAINT `chk_movimientos_tipo` CHECK (`tipo` IN ('ingreso', 'egreso'))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Registro financiero administrativo. No constituye contabilidad tributaria';

CREATE TABLE IF NOT EXISTS `csduv3_reserva_pagos` (
    `id`            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `reserva_id`    BIGINT UNSIGNED NOT NULL,
    `movimiento_id` BIGINT UNSIGNED NULL COMMENT 'Ingreso asociado en caja',
    `tipo`          VARCHAR(20) NOT NULL DEFAULT 'adelanto' COMMENT 'adelanto, saldo, devolucion',
    `fecha`         DATE NOT NULL,
    `monto`         DECIMAL(12,2) NOT NULL,
    `registrado_por` BIGINT UNSIGNED NULL,
    `observaciones` VARCHAR(300) NULL,
    `created_at`    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_reserva_pagos_movimiento` (`movimiento_id`) COMMENT 'Evita duplicar el ingreso',
    KEY `idx_reserva_pagos_reserva` (`reserva_id`),
    CONSTRAINT `fk_reserva_pagos_reserva`
        FOREIGN KEY (`reserva_id`) REFERENCES `csduv3_reservas` (`id`)
        ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `fk_reserva_pagos_movimiento`
        FOREIGN KEY (`movimiento_id`) REFERENCES `csduv3_movimientos` (`id`)
        ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT `fk_reserva_pagos_usuario`
        FOREIGN KEY (`registrado_por`) REFERENCES `csduv3_usuarios` (`id`)
        ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT `chk_reserva_pagos_monto` CHECK (`monto` <> 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Pagos de una reserva, vinculados al movimiento de caja correspondiente';

CREATE TABLE IF NOT EXISTS `csduv3_movimientos_auditoria` (
    `id`            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `movimiento_id` BIGINT UNSIGNED NOT NULL,
    `accion`        VARCHAR(30) NOT NULL COMMENT 'creacion, edicion, anulacion',
    `datos_anteriores` JSON NULL,
    `datos_nuevos`  JSON NULL,
    `usuario_id`    BIGINT UNSIGNED NULL,
    `motivo`        VARCHAR(300) NULL,
    `created_at`    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_mov_auditoria_movimiento` (`movimiento_id`, `created_at`),
    CONSTRAINT `fk_mov_auditoria_movimiento`
        FOREIGN KEY (`movimiento_id`) REFERENCES `csduv3_movimientos` (`id`)
        ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `fk_mov_auditoria_usuario`
        FOREIGN KEY (`usuario_id`) REFERENCES `csduv3_usuarios` (`id`)
        ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Historial de cambios y anulaciones de operaciones financieras';


-- ============================================================================
--  8. DEPORTES Y TORNEOS
-- ============================================================================

CREATE TABLE IF NOT EXISTS `csduv3_deportes` (
    `id`              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `nombre`          VARCHAR(80) NOT NULL,
    `descripcion`     VARCHAR(200) NULL,
    `tipo_puntuacion` VARCHAR(20) NOT NULL DEFAULT 'puntos'
                      COMMENT 'puntos (fútbol, básquet), sets (vóley), tantos',
    `permite_empate`  TINYINT(1) NOT NULL DEFAULT 1,
    `activo`          TINYINT(1) NOT NULL DEFAULT 1,
    `created_at`      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_deportes_nombre` (`nombre`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Disciplinas deportivas. Las reglas de puntuación se definen en
           csduv3_deporte_puntos para no duplicar información';

CREATE TABLE IF NOT EXISTS `csduv3_deporte_puntos` (
    `id`          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `deporte_id`  BIGINT UNSIGNED NOT NULL,
    `concepto`    VARCHAR(40) NOT NULL COMMENT 'victoria, empate, derrota, punto_extra',
    `puntos`      DECIMAL(4,1) NOT NULL DEFAULT 0.0,
    `orden`       SMALLINT UNSIGNED NOT NULL DEFAULT 1 COMMENT 'Criterio de desempate',
    `descripcion` VARCHAR(200) NULL,
    `created_at`  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_deporte_puntos` (`deporte_id`, `concepto`),
    CONSTRAINT `fk_deporte_puntos_deporte`
        FOREIGN KEY (`deporte_id`) REFERENCES `csduv3_deportes` (`id`)
        ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Reglas de puntuación configurables por disciplina y criterios de desempate';

CREATE TABLE IF NOT EXISTS `csduv3_torneos` (
    `id`            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `deporte_id`    BIGINT UNSIGNED NOT NULL,
    `nombre`        VARCHAR(150) NOT NULL,
    `categoria`     VARCHAR(80) NULL COMMENT 'Libre, máster, infantil, etc.',
    `modalidad`     VARCHAR(40) NOT NULL DEFAULT 'todos_contra_todos'
                    COMMENT 'todos_contra_todos, eliminacion_directa, grupos_eliminacion',
    `reglamento`    TEXT NULL,
    `fecha_inicio`  DATE NULL,
    `fecha_fin`     DATE NULL,
    `estado`        VARCHAR(20) NOT NULL DEFAULT 'planificado'
                    COMMENT 'planificado, en_curso, finalizado, anulado',
    `observaciones` TEXT NULL,
    `created_at`    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_torneos_deporte` (`deporte_id`),
    KEY `idx_torneos_estado` (`estado`),
    CONSTRAINT `fk_torneos_deporte`
        FOREIGN KEY (`deporte_id`) REFERENCES `csduv3_deportes` (`id`)
        ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Torneos y campeonatos por disciplina';

CREATE TABLE IF NOT EXISTS `csduv3_torneo_equipos` (
    `id`               BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `torneo_id`        BIGINT UNSIGNED NOT NULL,
    `nombre`           VARCHAR(120) NOT NULL,
    `abreviatura`      VARCHAR(10) NULL,
    `delegado_nombre`  VARCHAR(180) NULL,
    `delegado_telefono` VARCHAR(30) NULL,
    `socio_id`         BIGINT UNSIGNED NULL COMMENT 'Si el equipo representa a un socio',
    `observaciones`    VARCHAR(300) NULL,
    `created_at`       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_torneo_equipos_nombre` (`torneo_id`, `nombre`),
    KEY `idx_torneo_equipos_socio` (`socio_id`),
    CONSTRAINT `fk_torneo_equipos_torneo`
        FOREIGN KEY (`torneo_id`) REFERENCES `csduv3_torneos` (`id`)
        ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `fk_torneo_equipos_socio`
        FOREIGN KEY (`socio_id`) REFERENCES `csduv3_socios` (`id`)
        ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Equipos participantes en un torneo';

CREATE TABLE IF NOT EXISTS `csduv3_torneo_participantes` (
    `id`             BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `torneo_id`      BIGINT UNSIGNED NOT NULL,
    `equipo_id`      BIGINT UNSIGNED NOT NULL,
    `socio_id`       BIGINT UNSIGNED NULL,
    `nombres`        VARCHAR(120) NOT NULL,
    `apellidos`      VARCHAR(120) NULL,
    `documento`      VARCHAR(30) NULL,
    `fecha_nacimiento` DATE NULL,
    `dorsal`         SMALLINT UNSIGNED NULL,
    `posicion`       VARCHAR(40) NULL,
    `es_delegado`    TINYINT(1) NOT NULL DEFAULT 0,
    `observaciones`  VARCHAR(300) NULL,
    `created_at`     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_torneo_participantes_torneo` (`torneo_id`),
    KEY `idx_torneo_participantes_equipo` (`equipo_id`),
    KEY `idx_torneo_participantes_socio` (`socio_id`),
    CONSTRAINT `fk_torneo_participantes_torneo`
        FOREIGN KEY (`torneo_id`) REFERENCES `csduv3_torneos` (`id`)
        ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `fk_torneo_participantes_equipo`
        FOREIGN KEY (`equipo_id`) REFERENCES `csduv3_torneo_equipos` (`id`)
        ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `fk_torneo_participantes_socio`
        FOREIGN KEY (`socio_id`) REFERENCES `csduv3_socios` (`id`)
        ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Jugadores y delegados inscritos por equipo';

CREATE TABLE IF NOT EXISTS `csduv3_jornadas` (
    `id`           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `torneo_id`    BIGINT UNSIGNED NOT NULL,
    `numero`       SMALLINT UNSIGNED NOT NULL,
    `nombre`       VARCHAR(100) NULL,
    `fecha_inicio` DATE NULL,
    `fecha_fin`    DATE NULL,
    `observaciones` VARCHAR(300) NULL,
    `created_at`   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_jornadas_torneo_numero` (`torneo_id`, `numero`),
    CONSTRAINT `fk_jornadas_torneo`
        FOREIGN KEY (`torneo_id`) REFERENCES `csduv3_torneos` (`id`)
        ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Fechas o jornadas de un torneo';

CREATE TABLE IF NOT EXISTS `csduv3_encuentros` (
    `id`                 BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `torneo_id`          BIGINT UNSIGNED NOT NULL,
    `jornada_id`         BIGINT UNSIGNED NULL,
    `equipo_local_id`    BIGINT UNSIGNED NOT NULL,
    `equipo_visitante_id` BIGINT UNSIGNED NOT NULL,
    `instalacion_id`     BIGINT UNSIGNED NULL,
    `fecha`              DATE NOT NULL,
    `hora`               TIME NULL,
    `marcador_local`     SMALLINT UNSIGNED NULL,
    `marcador_visitante` SMALLINT UNSIGNED NULL,
    `sets_local`         TINYINT UNSIGNED NULL COMMENT 'Deportes por sets (vóley)',
    `sets_visitante`     TINYINT UNSIGNED NULL,
    `estado`             VARCHAR(20) NOT NULL DEFAULT 'programado'
                         COMMENT 'programado, jugado, suspendido, cancelado, walkover',
    `observaciones`      VARCHAR(300) NULL,
    `registrado_por`     BIGINT UNSIGNED NULL,
    `created_at`         DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`         DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_encuentros_torneo_fecha` (`torneo_id`, `fecha`),
    KEY `idx_encuentros_jornada` (`jornada_id`),
    KEY `idx_encuentros_equipo_local` (`equipo_local_id`),
    KEY `idx_encuentros_equipo_visitante` (`equipo_visitante_id`),
    CONSTRAINT `fk_encuentros_torneo`
        FOREIGN KEY (`torneo_id`) REFERENCES `csduv3_torneos` (`id`)
        ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `fk_encuentros_jornada`
        FOREIGN KEY (`jornada_id`) REFERENCES `csduv3_jornadas` (`id`)
        ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT `fk_encuentros_local`
        FOREIGN KEY (`equipo_local_id`) REFERENCES `csduv3_torneo_equipos` (`id`)
        ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `fk_encuentros_visitante`
        FOREIGN KEY (`equipo_visitante_id`) REFERENCES `csduv3_torneo_equipos` (`id`)
        ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `fk_encuentros_instalacion`
        FOREIGN KEY (`instalacion_id`) REFERENCES `csduv3_instalaciones` (`id`)
        ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT `fk_encuentros_usuario`
        FOREIGN KEY (`registrado_por`) REFERENCES `csduv3_usuarios` (`id`)
        ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Encuentros programados y sus resultados';

CREATE TABLE IF NOT EXISTS `csduv3_encuentro_estadisticas` (
    `id`             BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `encuentro_id`   BIGINT UNSIGNED NOT NULL,
    `participante_id` BIGINT UNSIGNED NULL,
    `equipo_id`      BIGINT UNSIGNED NULL,
    `tipo`           VARCHAR(40) NOT NULL
                     COMMENT 'gol, punto, tarjeta_amarilla, tarjeta_roja, falta, otro',
    `cantidad`       SMALLINT UNSIGNED NOT NULL DEFAULT 1,
    `minuto`         SMALLINT UNSIGNED NULL,
    `observaciones`  VARCHAR(200) NULL,
    `created_at`     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_encuentro_estadisticas_encuentro` (`encuentro_id`, `tipo`),
    KEY `idx_encuentro_estadisticas_participante` (`participante_id`),
    CONSTRAINT `fk_encuentro_estadisticas_encuentro`
        FOREIGN KEY (`encuentro_id`) REFERENCES `csduv3_encuentros` (`id`)
        ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `fk_encuentro_estadisticas_participante`
        FOREIGN KEY (`participante_id`) REFERENCES `csduv3_torneo_participantes` (`id`)
        ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT `fk_encuentro_estadisticas_equipo`
        FOREIGN KEY (`equipo_id`) REFERENCES `csduv3_torneo_equipos` (`id`)
        ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Goleadores y otras estadísticas por encuentro';


-- ============================================================================
--  9. DOCUMENTOS, CALENDARIO Y AUDITORÍA
-- ============================================================================

CREATE TABLE IF NOT EXISTS `csduv3_documentos` (
    `id`              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `titulo`          VARCHAR(180) NOT NULL,
    `categoria`       VARCHAR(60) NOT NULL DEFAULT 'general'
                      COMMENT 'acta, resolucion, reglamento, contrato, informe, eleccion, socio, general',
    `descripcion`     TEXT NULL,
    `archivo`         VARCHAR(255) NULL COMMENT 'Ruta relativa dentro de storage/uploads (nunca pública)',
    `nombre_original` VARCHAR(200) NULL,
    `tipo_mime`       VARCHAR(120) NULL,
    `tamano_bytes`    INT UNSIGNED NULL,
    `fecha_documento` DATE NULL,
    `es_privado`      TINYINT(1) NOT NULL DEFAULT 1,
    `socio_id`        BIGINT UNSIGNED NULL,
    `entidad_tipo`    VARCHAR(60) NULL COMMENT 'directiva, eleccion, reserva, movimiento, torneo, socio',
    `entidad_id`      BIGINT UNSIGNED NULL,
    `subido_por`      BIGINT UNSIGNED NULL,
    `created_at`      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_documentos_categoria` (`categoria`),
    KEY `idx_documentos_fecha` (`fecha_documento`),
    KEY `idx_documentos_entidad` (`entidad_tipo`, `entidad_id`),
    KEY `idx_documentos_socio` (`socio_id`),
    CONSTRAINT `fk_documentos_socio`
        FOREIGN KEY (`socio_id`) REFERENCES `csduv3_socios` (`id`)
        ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT `fk_documentos_usuario`
        FOREIGN KEY (`subido_por`) REFERENCES `csduv3_usuarios` (`id`)
        ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Documentos institucionales. Los archivos se sirven mediante control de acceso';

CREATE TABLE IF NOT EXISTS `csduv3_actividades` (
    `id`             BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `titulo`         VARCHAR(180) NOT NULL,
    `tipo`           VARCHAR(40) NOT NULL DEFAULT 'institucional'
                     COMMENT 'institucional, directiva, eleccion, deportiva, social, mantenimiento',
    `descripcion`    TEXT NULL,
    `fecha_inicio`   DATETIME NOT NULL,
    `fecha_fin`      DATETIME NULL,
    `todo_el_dia`    TINYINT(1) NOT NULL DEFAULT 0,
    `lugar`          VARCHAR(150) NULL,
    `instalacion_id` BIGINT UNSIGNED NULL,
    `responsable_id` BIGINT UNSIGNED NULL,
    `torneo_id`      BIGINT UNSIGNED NULL,
    `proceso_id`     BIGINT UNSIGNED NULL,
    `estado`         VARCHAR(20) NOT NULL DEFAULT 'programada'
                     COMMENT 'programada, realizada, cancelada, reprogramada',
    `visible`        TINYINT(1) NOT NULL DEFAULT 1,
    `created_at`     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_actividades_fecha` (`fecha_inicio`),
    KEY `idx_actividades_tipo` (`tipo`),
    KEY `idx_actividades_estado` (`estado`),
    CONSTRAINT `fk_actividades_instalacion`
        FOREIGN KEY (`instalacion_id`) REFERENCES `csduv3_instalaciones` (`id`)
        ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT `fk_actividades_responsable`
        FOREIGN KEY (`responsable_id`) REFERENCES `csduv3_usuarios` (`id`)
        ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT `fk_actividades_torneo`
        FOREIGN KEY (`torneo_id`) REFERENCES `csduv3_torneos` (`id`)
        ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT `fk_actividades_proceso`
        FOREIGN KEY (`proceso_id`) REFERENCES `csduv3_procesos_electorales` (`id`)
        ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Calendario institucional: reuniones, elecciones, actividades y eventos';

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
    KEY `idx_fotos_portada` (`visible`, `destacado`, `fecha_foto`),
    CONSTRAINT `fk_fotos_usuario`
        FOREIGN KEY (`subido_por`) REFERENCES `csduv3_usuarios` (`id`)
        ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Galeria institucional y noticias del club';

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

CREATE TABLE IF NOT EXISTS `csduv3_auditoria` (
    `id`            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `usuario_id`    BIGINT UNSIGNED NULL,
    `accion`        VARCHAR(60) NOT NULL COMMENT 'crear, editar, anular, eliminar, iniciar_sesion, etc.',
    `entidad_tipo`  VARCHAR(60) NULL,
    `entidad_id`    BIGINT UNSIGNED NULL,
    `descripcion`   VARCHAR(300) NULL,
    `datos`         JSON NULL,
    `ip`            VARCHAR(45) NULL,
    `user_agent`    VARCHAR(255) NULL,
    `created_at`    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_auditoria_usuario_fecha` (`usuario_id`, `created_at`),
    KEY `idx_auditoria_entidad` (`entidad_tipo`, `entidad_id`),
    KEY `idx_auditoria_accion` (`accion`),
    CONSTRAINT `fk_auditoria_usuario`
        FOREIGN KEY (`usuario_id`) REFERENCES `csduv3_usuarios` (`id`)
        ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Registro de acciones importantes realizadas en el sistema';

-- ============================================================================
--  10. REFERENCIAS DIFERIDAS
-- ----------------------------------------------------------------------------
--  Se añaden al final para respetar el orden de creación de las tablas.
--  Si una restricción ya existe, MariaDB devolverá un aviso (no un error) y el
--  script puede continuar.
-- ============================================================================

-- Categoría de caja asociada a un concepto de pago.
ALTER TABLE `csduv3_conceptos_pago`
    ADD CONSTRAINT `fk_conceptos_pago_categoria`
        FOREIGN KEY (`categoria_movimiento_id`) REFERENCES `csduv3_categorias_movimiento` (`id`)
        ON DELETE SET NULL ON UPDATE CASCADE;

-- Documento de sustento de un movimiento de caja.
ALTER TABLE `csduv3_movimientos`
    ADD CONSTRAINT `fk_movimientos_documento`
        FOREIGN KEY (`documento_id`) REFERENCES `csduv3_documentos` (`id`)
        ON DELETE SET NULL ON UPDATE CASCADE;

-- Usuario del sistema vinculado a un socio (relación opcional).
ALTER TABLE `csduv3_usuarios`
    ADD CONSTRAINT `fk_usuarios_socio`
        FOREIGN KEY (`socio_id`) REFERENCES `csduv3_socios` (`id`)
        ON DELETE SET NULL ON UPDATE CASCADE;

-- ============================================================================
--  FIN DEL ESQUEMA INICIAL
-- ============================================================================

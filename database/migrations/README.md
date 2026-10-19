# Migraciones de base de datos

Las migraciones son cambios **posteriores** a la instalación inicial.

## Reglas del proyecto

1. Solo se permiten cambios **aditivos**: `CREATE TABLE IF NOT EXISTS`, `ADD COLUMN`,
   `ADD INDEX`, `ADD CONSTRAINT`.
2. Están prohibidos `DROP`, `TRUNCATE` y cualquier `DELETE` sobre datos existentes.
3. Cada archivo se ejecuta **una vez**, en orden, desde phpMyAdmin.
4. Todo archivo debe poder ejecutarse más de una vez sin efectos secundarios
   (todos son idempotentes).
5. Ninguna migración debe importar datos reales del club ni datos de ejemplo.

## Archivos

| Orden | Archivo | Qué hace | ¿Necesario si instala desde cero? |
| --- | --- | --- | --- |
| 0001 | `0001_solicitudes_alquiler.sql` | Crea `csduv3_solicitudes_alquiler`, la tabla de solicitudes del portal público (Fase 2). | **No**: ya está incluida en `database/01_esquema.sql`. |

## Cómo saber si debe aplicarla

- Si importó `database/01_esquema.sql` **antes** de la Fase 2, ejecute
  `0001_solicitudes_alquiler.sql` una vez.
- Si importa `database/01_esquema.sql` de la versión actual, la tabla ya existe y
  no debe ejecutar la migración.

## Verificación

Después de aplicar la migración, compruebe que la tabla existe:

```sql
SELECT COUNT(*) FROM information_schema.tables
 WHERE table_schema = DATABASE()
   AND table_name = 'csduv3_solicitudes_alquiler';
```

Resultado esperado: `1`.

También puede abrir `/diagnostico` con `APP_ENV=development`: el conteo de
«Tablas del esquema» debe indicar **40 de 40**.

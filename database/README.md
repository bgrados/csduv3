# Base de datos — archivos SQL

Esta carpeta contiene la definición de la base de datos y sus datos iniciales.
La documentación detallada está en [`../docs/BASE_DE_DATOS.md`](../docs/BASE_DE_DATOS.md).

---

## Orden de ejecución

| Orden | Archivo | Contenido | Obligatorio |
| --- | --- | --- | --- |
| 1 | `01_esquema.sql` | 40 tablas, índices, claves foráneas y restricciones | Sí |
| 2 | `02_datos_iniciales.sql` | Roles, permisos, configuración y catálogos genéricos editables | Sí |
| 3 | `03_demo_desarrollo.sql` | **Datos ficticios** para desarrollo | Solo local |
| 4 | `04_eliminar_demo.sql` | Retira los datos ficticios del paso 3 | Si se usó el paso 3 |
| — | `migrations/` | Cambios de estructura posteriores, numerados | Según la versión |

---

## Reglas

- Todos los archivos usan `CREATE TABLE IF NOT EXISTS` e `INSERT IGNORE`.
- **No contienen `DROP` ni `DELETE` sobre datos reales.**
- Los datos ficticios están siempre identificados con el prefijo `DEMO-`.
- **`03_demo_desarrollo.sql` no debe importarse en producción.**

---

## Cómo importar desde phpMyAdmin

1. Seleccione la base de datos en el panel izquierdo.
2. Pestaña **Importar** → **Seleccionar archivo**.
3. Elija `01_esquema.sql`, cotejamiento `utf8mb4`, y pulse **Continuar**.
4. Repita con `02_datos_iniciales.sql`.
5. Verifique:

   ```sql
   SELECT COUNT(*) FROM information_schema.tables
   WHERE table_schema = DATABASE() AND table_name LIKE 'csduv3_%';
   ```

   Debe devolver `40` (más las tablas que agreguen migraciones posteriores).
   Si devuelve `39`, falta aplicar
   `migrations/0001_solicitudes_alquiler.sql`.

---

## Migraciones

Los cambios posteriores de estructura se guardan en `migrations/` con el formato
`NNNN_descripcion_corta.sql`, se aplican en orden numérico y se documentan en
[`../docs/MANTENIMIENTO.md`](../docs/MANTENIMIENTO.md).

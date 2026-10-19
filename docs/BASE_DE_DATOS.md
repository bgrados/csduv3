# Base de datos

Documentación del esquema de datos del sistema CSDUV3.

- **Motor:** InnoDB
- **Juego de caracteres:** `utf8mb4` / `utf8mb4_unicode_ci`
- **Prefijo de tablas:** `csduv3_`
- **Total de tablas del esquema inicial:** 40

---

## 1. Archivos SQL y orden de ejecución

| Orden | Archivo | Cuándo se usa |
| --- | --- | --- |
| 1 | `01_esquema.sql` | Siempre. Crea las 40 tablas, índices y claves foráneas |
| 2 | `02_datos_iniciales.sql` | Siempre. Roles, permisos, configuración y catálogos genéricos |
| 3 | `03_demo_desarrollo.sql` | **Solo desarrollo.** Datos ficticios identificados con prefijo `DEMO-` |
| 4 | `04_eliminar_demo.sql` | Retira los datos ficticios del paso 3 |
| — | `migrations/` | Cambios de estructura posteriores, numerados y repetibles |

En la Fase 2 se agregó la tabla `csduv3_solicitudes_alquiler` (tabla n.º 40). Si
instaló el esquema de la Fase 1 antes de esta versión, ejecute una vez
`database/migrations/0001_solicitudes_alquiler.sql`.

Todos los archivos usan `CREATE TABLE IF NOT EXISTS` e `INSERT IGNORE`:

- No contienen `DROP` ni `DELETE` sobre datos reales.
- Pueden ejecutarse más de una vez sin destruir información.

**Importante:** `03_demo_desarrollo.sql` contiene datos inventados. Nunca debe
importarse en el servidor de producción.

---

## 2. Grupos de tablas

### Configuración

| Tabla | Contenido |
| --- | --- |
| `csduv3_configuracion` | Parámetros del sistema y de la identidad institucional (nombre, siglas, moneda, logotipo, etc.) |

### Seguridad

| Tabla | Contenido |
| --- | --- |
| `csduv3_roles` | Roles: administrador, junta directiva, tesorería, secretaría, deportes, alquileres, consulta |
| `csduv3_permisos` | Permisos verificables en el servidor, con formato `modulo.accion` |
| `csduv3_roles_permisos` | Asignación de permisos a cada rol |
| `csduv3_usuarios` | Usuarios del sistema. Solo se guarda `password_hash` |
| `csduv3_accesos` | Historial de intentos de acceso, para limitar intentos repetidos |

### Socios

| Tabla | Contenido |
| --- | --- |
| `csduv3_categorias_socio` | Categorías configurables (no se precargan: las define el club) |
| `csduv3_socios` | Ficha del socio con código único y documento único |
| `csduv3_socios_bajas` | Bajas: el socio nunca se elimina, se conserva el historial |
| `csduv3_socios_documentos` | Documentos adjuntos a la ficha |
| `csduv3_conceptos_pago` | Conceptos de cobro configurables (cuotas, inscripciones) |

### Junta directiva y elecciones

| Tabla | Contenido |
| --- | --- |
| `csduv3_directivas` | Periodos de gestión de la junta directiva |
| `csduv3_directiva_cargos` | Cargos configurables según el estatuto |
| `csduv3_directiva_miembros` | Integrantes de cada directiva y su cargo |
| `csduv3_procesos_electorales` | Procesos electorales y periodo de mandato |
| `csduv3_listas_electorales` | Listas participantes |
| `csduv3_candidatos` | Candidatos por lista y cargo |
| `csduv3_resultados_electorales` | Registro **administrativo** de resultados |

### Alquileres

| Tabla | Contenido |
| --- | --- |
| `csduv3_tipos_evento` | Matrimonio, reunión social, velorio, otros |
| `csduv3_instalaciones` | Ambientes e instalaciones |
| `csduv3_tarifas` | Tarifas configurables por instalación y tipo de evento |
| `csduv3_reservas` | Reservas con fecha, horario, importes y estado |
| `csduv3_solicitudes_alquiler` | **Solicitudes del portal público.** No son reservas: quedan pendientes de revisión del personal autorizado |

### Caja

| Tabla | Contenido |
| --- | --- |
| `csduv3_categorias_movimiento` | Categorías de ingreso y egreso |
| `csduv3_medios_pago` | Efectivo, transferencia, etc. |
| `csduv3_periodos` | Periodos administrativos o contables |
| `csduv3_movimientos` | Ingresos y egresos, con anulación trazable |
| `csduv3_reserva_pagos` | Pagos de una reserva vinculados al ingreso en caja |
| `csduv3_movimientos_auditoria` | Historial de cambios y anulaciones de cada movimiento |

### Deportes

| Tabla | Contenido |
| --- | --- |
| `csduv3_deportes` | Disciplinas (fútbol, vóley, básquet, sapo y las que se incorporen) |
| `csduv3_deporte_puntos` | Reglas de puntuación **por disciplina** y criterios de desempate |
| `csduv3_torneos` | Torneos y campeonatos |
| `csduv3_torneo_equipos` | Equipos participantes y delegados |
| `csduv3_torneo_participantes` | Jugadores inscritos por equipo |
| `csduv3_jornadas` | Fechas o jornadas |
| `csduv3_encuentros` | Partidos, programación y resultados |
| `csduv3_encuentro_estadisticas` | Goleadores y otras estadísticas |

### Documentos y calendario

| Tabla | Contenido |
| --- | --- |
| `csduv3_documentos` | Documentos institucionales con control de acceso |
| `csduv3_actividades` | Calendario institucional |
| `csduv3_auditoria` | Registro de acciones importantes |

---

## 3. Relaciones principales

```
csduv3_roles ──< csduv3_usuarios >── csduv3_socios
      │
      └──< csduv3_roles_permisos >── csduv3_permisos

csduv3_categorias_socio ──< csduv3_socios ──< csduv3_socios_bajas
                                       ├──< csduv3_socios_documentos
                                       ├──< csduv3_directiva_miembros
                                       ├──< csduv3_candidatos
                                       ├──< csduv3_movimientos
                                       └──< csduv3_torneo_participantes

csduv3_instalaciones ──< csduv3_tarifas ──< csduv3_reservas ──< csduv3_reserva_pagos
                                  │               │                    │
                                  └── csduv3_tipos_evento              │
                                                                       ▼
csduv3_categorias_movimiento ──< csduv3_movimientos >── csduv3_medios_pago
                                       │  ▲                 csduv3_periodos
                                       │  └──< csduv3_movimientos_auditoria
                                       └── csduv3_documentos

csduv3_deportes ──< csduv3_deporte_puntos
      └──< csduv3_torneos ──< csduv3_jornadas
                   │      ├──< csduv3_torneo_equipos ──< csduv3_torneo_participantes
                   │      └──< csduv3_encuentros ──< csduv3_encuentro_estadisticas
```

---

## 4. Decisiones de diseño

| Decisión | Motivo |
| --- | --- |
| Prefijo `csduv3_` | Identifica las tablas del sistema dentro de la base compartida del hosting |
| `utf8mb4_unicode_ci` | Soporta tildes, la «º» del nombre institucional y caracteres especiales |
| Claves foráneas con `ON DELETE RESTRICT` o `SET NULL` | Evita perder historial; nunca se borra un socio o un movimiento |
| Importes en `DECIMAL(12,2)` | Evita errores de redondeo de los tipos flotantes |
| Estados como `VARCHAR` con valores documentados | Permite agregar estados sin alterar el tipo de la columna |
| Sin columnas derivables | Los saldos y las posiciones se calculan a partir de los movimientos y encuentros |
| `CHECK` en importes y horarios | Refuerza la integridad en el motor, además de la validación en PHP |
| `created_at` / `updated_at` en todas las tablas | Trazabilidad básica de cada registro |

### Control de reservas superpuestas

El esquema aporta el índice
`idx_reservas_instalacion_fecha (instalacion_id, fecha_evento, hora_inicio, hora_fin)`
y, para las solicitudes del portal público, el índice
`idx_solicitudes_agenda (instalacion_id, fecha_evento, hora_inicio, hora_fin)`.

**Estado real en la Fase 2:** la comprobación de solapamiento se hace en el
servidor, con una consulta preparada (`Reserva::haySolapamiento`) antes de crear
la reserva o la solicitud. Esa consulta **no usa transacción ni `FOR UPDATE`**, por
lo que dos envíos exactamente simultáneos podrían pasar la comprobación a la vez.
Cerrar esa condición de carrera con una transacción y `SELECT ... FOR UPDATE`
queda como tarea obligatoria del módulo de alquileres (Fase 4), donde también se
crean las reservas desde el panel.

### Estados de una solicitud de alquiler

| Estado | Significado |
| --- | --- |
| `pendiente` | Recién recibida; nadie la ha revisado |
| `en_revision` | El personal la está verificando |
| `atendida` | El personal respondió al solicitante (no implica reserva) |
| `rechazada` | No se atenderá; se guarda el motivo |

La columna `conflicto_detectado` se marca cuando, al registrarse la solicitud,
existía **otra solicitud pendiente** en el mismo ambiente y horario. El sistema no
rechaza en ese caso: lo señala para que una persona lo revise.

### Evitar ingresos duplicados

`csduv3_reserva_pagos.movimiento_id` es **único**: un movimiento de caja no puede
quedar asociado dos veces a pagos de reservas.

### Anulación de movimientos

Un movimiento financiero no se elimina ni se modifica en silencio: se marca como
`anulado` con usuario, fecha y motivo, y el cambio queda registrado en
`csduv3_movimientos_auditoria`.

---

## 5. Evolución del esquema

Los cambios posteriores se guardan en `database/migrations/` con el formato
`NNNN_descripcion.sql` (por ejemplo `0001_agrega_tabla_xxx.sql`).

Reglas:

1. Una migración no debe eliminar columnas ni tablas con datos sin autorización.
2. Cada migración debe ser repetible o incluir la comprobación correspondiente.
3. Toda migración se documenta en [MANTENIMIENTO.md](MANTENIMIENTO.md).
4. Antes de aplicarla en producción, se toma una copia de seguridad.

---

## 6. Consultas de comprobación útiles

```sql
-- Número de tablas del sistema creadas
SELECT COUNT(*) AS total
FROM information_schema.tables
WHERE table_schema = DATABASE() AND table_name LIKE 'csduv3_%';

-- Verificar que existan los permisos cargados
SELECT COUNT(*) AS permisos FROM csduv3_permisos;

-- Verificar la asignación de permisos por rol
SELECT r.nombre, COUNT(rp.permiso_id) AS permisos
FROM csduv3_roles r
LEFT JOIN csduv3_roles_permisos rp ON rp.rol_id = r.id
GROUP BY r.id, r.nombre
ORDER BY permisos DESC;

-- Comprobar que no queden datos ficticios
SELECT COUNT(*) FROM csduv3_socios WHERE codigo LIKE 'DEMO-%';

-- Verificar el cotejamiento de las tablas
SELECT table_name, table_collation
FROM information_schema.tables
WHERE table_schema = DATABASE() AND table_name LIKE 'csduv3_%'
LIMIT 10;
```

---

## 7. Copias de seguridad

```bash
# Exportar la estructura y los datos
mysqldump -u USUARIO -p BASE_DE_DATOS > csduv3_$(date +%F).sql
```

En hosting compartido sin SSH, use **phpMyAdmin → Exportar** y guarde el archivo
fuera del servidor. Consulte [MANTENIMIENTO.md](MANTENIMIENTO.md).

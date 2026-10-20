# Mantenimiento

Procedimientos de copia de seguridad, actualización y revisión periódica.

---

## 1. Copias de seguridad

### 1.1 Base de datos

**Opción con SSH (si el hosting lo ofrece)**

```bash
mysqldump -u USUARIO -p --single-transaction --routines BASE_DE_DATOS > csduv3_$(date +%F).sql
```

**Opción sin SSH (cPanel / phpMyAdmin)**

1. Abra phpMyAdmin y seleccione la base de datos del proyecto (por ejemplo,
   `TU_BASE_DE_DATOS`).
2. Pestaña **Exportar** → método **Personalizado**.
3. Active: estructura y datos, `DROP TABLE` desactivado si va a importar sobre una
   base existente, codificación `utf8mb4`.
4. Descargue el archivo y guárdelo **fuera del servidor**.

### 1.2 Archivos

Respalde al menos:

| Carpeta | Motivo |
| --- | --- |
| `storage/uploads/` | Documentos y fotografías de socios |
| `storage/backups/` | Copias previas |
| `.env` | Configuración (contiene credenciales: guárdelo cifrado) |

El código fuente no necesita respaldo manual: está en GitHub. **Nunca** incluya
`.env` en el repositorio.

### 1.3 Frecuencia recomendada

| Momento | Acción |
| --- | --- |
| Antes de cada actualización | Copia de base de datos y de `storage/` |
| Semanal | Copia de base de datos completa |
| Mensual | Copia completa y verificación de restauración en un entorno de prueba |

### 1.4 Restauración

1. Cree una base de datos vacía.
2. Importe el archivo `.sql` de la copia.
3. Si es necesario, restaure `storage/uploads/`.
4. Confirme el contenido de `.env`.

---

## 2. Actualizaciones

### 2.1 Preparación

1. Copia de seguridad de la base de datos y de `storage/`.
2. Revise el historial del repositorio antes de aplicar cambios.
3. Anote la versión actual (por ejemplo, en `docs/`).

### 2.2 Aplicación

1. Suba por FTP o Administrador de archivos **solo** los archivos modificados.
2. **No sobrescriba**:
   - `.env`
   - `storage/` y su contenido
   - el archivo `public/.app_path.php`, si existe
3. Aplique las migraciones pendientes de `database/migrations/` en orden
   numérico.
4. Nunca sobrescriba `database/01_esquema.sql` sobre una base con datos: ese
   archivo es para instalaciones nuevas o para crear tablas faltantes.
5. Si cambió archivos estáticos (`app.css`, `app.js`, iconos, manifiesto),
   **aumente la versión de la caché** en `public/sw.js`
   (`var CACHE = 'csduv3-publica-v3';`). Así los navegadores que instalaron la
   aplicación reciben los cambios. El HTML nunca se guarda en caché, por lo que
   las páginas se actualizan solas.

### 2.3 Verificación posterior

1. Abra la portada pública y confirme que se muestra el portal de alquileres.
2. Inicie sesión con un usuario de prueba y confirme que entra al panel.
3. Recorra los módulos modificados.
4. Revise `storage/logs/app-AAAA-MM-DD.log`.

### 2.4 Reversión

Si algo falla:

1. Restaure la copia de seguridad de la base de datos.
2. Vuelva a subir los archivos de la versión anterior.
3. Documente el incidente en este archivo.

---

## 3. Migraciones de base de datos

Formato de nombre: `NNNN_descripcion_corta.sql` (por ejemplo
`0001_agrega_columna_observaciones.sql`).

Reglas:

1. Deben ser repetibles o incluir la comprobación previa.
2. No eliminan columnas ni tablas con información sin autorización expresa.
3. Se documentan en la tabla siguiente.

| N.º | Archivo | Fecha | Descripción |
| --- | --- | --- | --- |
| 0001 | `0001_solicitudes_alquiler.sql` | Fase 2 | Crea `csduv3_solicitudes_alquiler` (tabla n.º 40). No es necesaria en instalaciones nuevas: ya está en `01_esquema.sql` |

---

## 4. Revisión periódica

| Frecuencia | Tarea |
| --- | --- |
| Semanal | Revisar `storage/logs/` en busca de errores repetidos |
| Semanal | Revisar la auditoría ante anulaciones o cambios inusuales |
| Mensual | Revisar usuarios activos y roles asignados |
| Mensual | Verificar espacio en disco del hosting |
| Trimestral | Revisar la seguridad según [SEGURIDAD.md](SEGURIDAD.md) |
| Anual | Revisar parámetros de configuración del club (categorías, tarifas, conceptos) |

---

## 5. Limpieza

- Los archivos de registro pueden crecer con el tiempo. Conserve los últimos 90
  días y elimine los anteriores desde el Administrador de archivos.
- Las copias antiguas en `storage/backups/` deben trasladarse fuera del servidor.
- No borre documentos de socios ni respaldos por iniciativa propia: puede haber
  obligaciones de conservación documental del club.

---

## 6. Contactos y responsabilidades

| Responsabilidad | Persona / Rol |
| --- | --- |
| Acceso a cPanel y base de datos | Administrador del sistema |
| Copias de seguridad | Responsable designado por la junta directiva |
| Actualizaciones del sistema | Responsable técnico |
| Autorización de cambios de estructura | Junta directiva |

> Complete esta tabla con las personas reales del club. El sistema no la
> precarga para no inventar datos institucionales.

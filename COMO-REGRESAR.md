# CSDUV3 · Guía completa y recordatorio

> **Este es el archivo para leer cuando vuelvas al proyecto.**
> Resume qué es la aplicación, dónde está, cómo se despliega, qué falta por
> hacer y qué errores ya se han resuelto (para no repetirlos).

---

## 1. En 30 segundos

| | |
| --- | --- |
| **Qué es** | Portal web del **Club Social Deportivo Unidad Vecinal N.º 3**: gestión interna (panel) + portal público para socios y vecinos. |
| **Dónde está en vivo** | <https://csduv3.benweb.soy.pe> |
| **Repositorio** | <https://github.com/bgrados/csduv3> (rama `main`) |
| **Carpeta en el hosting** | `/home/uclztjki/csduv3_app` |
| **Con qué está hecho** | PHP 8.3 + MariaDB, sin Composer, sin Node, sin Docker. Un solo `git pull` despliega. |
| **Cómo se despliega** | `git push` a `main` → el cron del hosting (cada ~5 min) hace `git pull`, copia la base de datos y aplica las migraciones. |
| **Interfaz** | Español, `America/Lima`, fechas `dd/mm/aaaa`, moneda S/ (soles). |

---

## 2. Los dos portales

### 2.1. Portal público (para socios y vecinos) — `/`

Lo ve **todo el mundo, sin registrarse**:

| Página | Ruta | Qué hay |
| --- | --- | --- |
| Portada | `/` | Revista: foto o video de portada, carrusel de noticias, **sección «Videos del club»**, próximos eventos, agenda deportiva, alquiler de ambientes, **teléfonos de emergencia**, negocios de la unidad, y pie con el contenido de Facebook del club. |
| Ambientes | `/alquiler` | Catálogo de ambientes con fotos, tarifas y horarios. |
| Ficha de ambiente | `/alquiler/ambiente/{id}` | Detalle, tabla de tarifas y **disponibilidad del día**. |
| Reservar | `/alquiler/reservar` | **Reserva confirmada en la web**: se elige ambiente, fecha y hora (admite **de 19:00 a 03:00**, o sea de madrugada), se ven la tarifa, el adelanto y el saldo, y se confirma al instante. |
| Confirmación | `/alquiler/reserva-confirmada` | Comprobante de la reserva con el código. |
| Solicitud | `/alquiler/solicitud` | Consulta alternativa (solicitud, la responde el club). |
| Disponibilidad | `/alquiler/disponibilidad` | Horarios ocupados por ambiente y fecha. |
| Galería | `/galeria` | «El club en imágenes»: noticias con foto **o video**. |
| Torneos | `/torneos` | Fixture, posiciones y agenda deportiva. |
| Negocios | `/negocios` | Directorio de comercios y servicios de la unidad. |

### 2.2. Panel administrativo (para el club) — `/panel`

| Módulo | Ruta | Permiso | Qué hace |
| --- | --- | --- | --- |
| Panel | `/panel` | `panel.ver` | Indicadores y accesos rápidos. |
| Socios | `/socios` | `socios.*` | Padrón, categorías, bajas y documentos por socio. |
| Directiva | `/directiva` | `directiva.*` | Juntas directivas, cargos y su historial. |
| Elecciones | `/elecciones` | `elecciones.*` | Procesos, listas, candidatos, resultados y ganadores. |
| Ambientes y tarifas | `/ambientes` | `alquileres.tarifas` | Ambientes, tarifas por tipo de evento y tipo de cambio. |
| Reservas | `/reservas` | `alquileres.*` | Reserva, pago, cambio de estado y cancelación. |
| Solicitudes | `/solicitudes` | `alquileres.ver` / `.gestionar` | Solicitudes del portal: **convertir en reserva**, atender o rechazar. |
| Caja | `/caja` | `caja.*` | Ingresos, egresos, anulaciones y reportes por periodo. |
| Deportes | `/deportes` | `deportes.*` | Disciplinas, torneos, fixture, posiciones y resultados. |
| Calendario | `/calendario` | `calendario.*` | Agenda institucional. |
| Documentos | `/documentos` | `documentos.*` | Repositorio con archivos adjuntos. |
| Noticias y fotos | `/noticias` | `documentos.*` | Noticias con foto **y/o video de YouTube o Facebook**. |
| Directorio | `/directorio` | `documentos.*` | Negocios de la unidad (con foto, enlace y destacado). |
| Usuarios | `/usuarios` | `usuarios.*` | Cuentas del personal. |
| Roles | `/roles/permisos` | `roles.gestionar` | Matriz de permisos por rol. |
| Auditoría | `/auditoria` | `auditoria.ver` | Rastro de las acciones realizadas. |
| Configuración | `/configuracion` | `configuracion.gestionar` | Identidad, contacto, **redes sociales**, **teléfonos de emergencia**, marca, portada y notificaciones. |
| **Actualizar la base de datos** | `/migraciones` | `configuracion.gestionar` | Aplica las migraciones pendientes con un clic. |

**Roles del sistema:** Administrador (todos los permisos), Junta directiva,
Tesorería, Secretaría y Responsable de deportes. Los permisos **nunca se
crean nuevos** para un módulo: se reutilizan los existentes.

---

## 3. Lo primero que hay que hacer al entrar

1. **Crear la cuenta de administrador** (Terminal del hosting):
   ```
   php8.3 tools/crear_admin.php
   ```
   Después **borrar ese archivo del servidor**. Si ya existe un administrador
   activo: `php8.3 tools/crear_admin.php --permitir-otro`.
2. **Entrar** en `/ingresar` y cambiar la contraseña.
3. **Rotar la contraseña de la base de datos** en cPanel y actualizar el `.env`
   (la anterior quedó expuesta en el historial público de Git).
4. **Poner `APP_DEBUG=false`** en el `.env` de producción (los detalles técnicos
   quedan en `storage/logs/`, pero el club no debe ver errores de base de datos).

---

## 4. Despliegue (cómo se publica un cambio)

```
1. Edito el código en la carpeta local del proyecto
2. php -l (sintaxis) + tools/verificar_estatico.ps1 (verificación estática)
3. git add -A && git commit -m "mensaje en español"
4. git push origin main
5. Espero ~5 minutos: el cron del hosting hace git pull + mysqldump + migrar.php
6. Compruebo en https://csduv3.benweb.soy.pe que todo responde 200 y sin errores
```

El script del servidor es `tools/desplegar-servidor.sh`. Si algo falla, el
detalle queda en `storage/deploy.log` (mira el final del archivo con
`php8.3 tools/ultimos_errores.php 40`).

### Migraciones de base de datos
- **Todo cambio de esquema va en `database/migrations/NNNN_nombre.sql`**, con
  números correlativos (0001, 0002, 0003…). Nunca se edita la base a mano.
- Las migraciones son **idempotentes** (`ADD COLUMN IF NOT EXISTS`, etc.) y
  se registran en la tabla `csduv3_migraciones`.
- El despliegue las aplica solo; si queda alguna pendiente, el panel avisa y
  se puede aplicar con un clic desde **Administración → Actualizar la base de datos**.
- Una migración con `DROP`, `TRUNCATE` o `DELETE` **no se aplica** salvo que se
  ejecute a mano con `--permitir-destructivo`.

---

## 5. Herramientas (`tools/`, solo desde la Terminal)

| Comando | Para qué sirve |
| --- | --- |
| `php8.3 tools/verificar_migraciones.php` | Qué migraciones faltan, qué tablas y columnas hay. |
| `php8.3 tools/migrar.php` | Aplica las migraciones pendientes. |
| `php8.3 tools/ultimos_errores.php 40` | Últimas líneas del registro de la aplicación y del despliegue. |
| `php8.3 tools/diagnostico_reservas.php` | Revisa solicitudes y reservas, **prueba** la conversión y el cambio de estado, y borra lo que prueba. |
| `php8.3 tools/crear_admin.php` | Crea el primer administrador. |
| `php8.3 tools/instalar_demo_fotos.php` | Carga (`--quitar` para borrar) las fotos y negocios de ejemplo. |
| `powershell -File tools/verificar_estatico.ps1` | Verificación estática (rutas, permisos, SQL, marcadores PDO, enlaces). |

---

## 6. Reglas del proyecto (no se rompen)

1. **Idioma:** toda la interfaz en español; identificadores en español.
2. **Fechas y moneda:** `dd/mm/aaaa`, zona `America/Lima`, soles (S/).
3. **Esquema:** solo mediante `database/migrations/`, en orden.
4. **Datos de producción:** no se tocan. Los datos de ejemplo llevan el
   prefijo **`DEMO-`** y viven en `database/03_demo_desarrollo.sql`.
5. **Permisos:** se reutilizan los existentes; no se crean nuevos sin acuerdo.
6. **Seguridad:** consultas preparadas (PDO), CSRF en todos los formularios,
   escapado con `e()`, control de permisos en el servidor.
7. **Migraciones destructivas:** requieren `--permitir-destructivo`.
8. **Nada de framework ni dependencias:** sin Composer, sin npm.

---

## 7. ✅ Lista de tareas pendientes

### Urgente (bloquean el uso real)
- [ ] Crear la cuenta de administrador (`tools/crear_admin.php`) y borrar el script.
- [ ] **Rotar la contraseña de la base de datos** y actualizar el `.env`.
- [ ] Poner `APP_DEBUG=false` en producción.
- [ ] Comprobar el **correo saliente**: Configuración → *Notificaciones* → *Guardar y enviar un correo de prueba*. Si `mail()` está bloqueado, hay que pedir SMTP/sendmail al hosting.

### Importante (revisar con el club)
- [ ] Ejecutar los casos de `docs/PRUEBAS.md` que aún no se han probado en el servidor (`RS-*`, `CX-*`, `EN-*`, `RV-*`, `GA-*`, `MB-*`…).
- [ ] Confirmar que la **migración 0006/0007** quedó aplicada (columnas `video_url` y `video_tipo`).
- [ ] Probar una **reserva de 19:00 a 03:00** (cruce de medianoche).
- [ ] Probar la **conversión de una solicitud en reserva** y que la caja sume el ingreso.
- [ ] Cargar las **fotos reales** de reuniones, championships y junta directiva (y borrar las de ejemplo).
- [ ] Pegar el enlace de **Instagram** cuando exista la cuenta.
- [ ] Revisar los **datos de ejemplo** de negocios (marca `DEMO-`) y cargar los reales.
- [ ] Confirmar los **teléfonos de emergencia** de la zona (Comisaría, Posta Médica…).

### Opcional (mejoras)
- [ ] Programa de backup automático de la base en el hosting.
- [ ] Correos de aviso de vencimiento de socios o deuda de caja.
- [ ] Firma digital o QR en los comprobantes internos.

---

## 8. Problemas que ya se han resuelto (no repetirlos)

| Síntoma | Causa | Solución |
| --- | --- | --- |
| `SQLSTATE[HY093]: Invalid parameter number` | Un marcador con nombre (`:fecha`) repetido en la misma consulta. Con `PDO::ATTR_EMULATE_PREPARES = false` eso es un error. | Se corrigieron `Periodo::porFecha()` y `Reserva::franjasOcupadas()`. La verificación estática ahora lo detecta. |
| `SQLSTATE[42000] ... near 'AND id <> ?'` | En `Reserva::bloquearHorario()` se añadía la cláusula **después** de `FOR UPDATE` (SQL inválido). | El bloqueo va siempre al final. La verificación estática lo detecta. |
| `Unknown column 'video_url'` al publicar una noticia | La migración de las columnas del video no estaba aplicada. | El modelo comprueba si existen y guarda la noticia igual; el panel ofrece aplicarlas con un clic. |
| No se podía cambiar el estado al editar una reserva | El formulario mostraba el estado pero el modelo no lo guardaba. | Ahora se guarda, con el efecto en caja: *confirmada* → ingreso; *pendiente* → reversa; *finalizada* → se mantiene. |
| Al editar una noticia pedía una imagen | La imagen era obligatoria aunque no se subiera ninguna. | Ahora basta con foto **o** video; al editar se conserva la imagen anterior. |
| La solicitud no cambiaba de estado al convertirla | El campo `solicitud_id` se perdía al volver del formulario con errores. | Se conserva con `old()` y la conversión vuelve al formulario con el vínculo intacto. |
| Error 500 en la ficha de un ambiente | Otra consulta con marcador repetido. | Corregida (`Reserva::franjasOcupadas()`). |
| El error de SQL se veía tal cual en la web | `APP_DEBUG=true` en producción. | Poner `false`; el detalle queda en `storage/logs/`. |

---

## 9. Mapa del código (archivos clave)

| Archivo | Para qué |
| --- | --- |
| `app/routes.php` | Todas las rutas (públicas y del panel) y sus permisos. |
| `app/Core/Database.php` | Conexión PDO única ytransactions. |
| `app/Core/Migraciones.php` | Aplica migraciones (lo usan el panel y `tools/migrar.php`). |
| `app/Core/Video.php` | Detecta y reproduce videos de YouTube y Facebook. |
| `app/Core/Redes.php` | Logos y colores de marca de las redes sociales. |
| `app/Core/Uploads.php` | Subida de archivos: valida y convierte a **WebP** (máx. 2000 px, calidad 78). |
| `app/Core/Mailer.php` | Envío de correo. |
| `app/Models/Reserva.php` | Reservas, solapamiento con bloqueo (`FOR UPDATE`), ingresos en caja. |
| `app/Models/Foto.php` | Noticias y fotos (también los videos). |
| `app/Views/layouts/publico.php` | Portal público: cabecera y pie (incluye el bloque de Facebook). |
| `app/Views/layouts/app.php` | Panel: barra lateral, avisos y posición del scroll. |
| `app/Views/home/index.php` | Portada con formato de revista. |
| `app/Views/configuracion/index.php` | Configuración del club (incluye emergencias y redes). |
| `public/assets/css/app.css` | Todo el diseño (paleta verde `#009F39` / amarillo `#FEFE08`). |
| `public/assets/js/app.js` | Carruseles, menú, avisos, PWA y filas de emergencia. |
| `database/01_esquema.sql` | Esquema completo para instalaciones nuevas. |
| `database/migrations/` | Cambios de esquema, en orden. |
| `docs/PRUEBAS.md` | Casos de prueba (`RS-*`, `CX-*`, `EN-*`, `RV-*`, `MB-*`…). |
| [CONTINUAR.md](CONTINUAR.md) | Bitácora detallada del desarrollo. |

---

## 10. Documentación relacionada

| Documento | Contenido |
| --- | --- |
| [docs/PRUEBAS.md](docs/PRUEBAS.md) | Casos de prueba, hechos y pendientes. |
| [docs/INSTALACION.md](docs/INSTALACION.md) | Instalación local. |
| [docs/DESPLIEGUE_BANAHOSTING.md](docs/DESPLIEGUE_BANAHOSTING.md) | Publicación en el hosting. |
| [docs/ARQUITECTURA.md](docs/ARQUITECTURA.md) | Decisiones técnicas. |
| [docs/SEGURIDAD.md](docs/SEGURIDAD.md) | Autenticación, permisos y recomendaciones. |
| [docs/MODULOS.md](docs/MODULOS.md) | Alcance de cada módulo. |
| [docs/DISENO.md](docs/DISENO.md) | Paleta, tipografía y reglas responsive. |
| [docs/PWA.md](docs/PWA.md) | Aplicación instalable y caché. |
| [CONTINUAR.md](CONTINUAR.md) | Bitácora detallada del desarrollo. |

---

## 11. Cómo pedir un cambio

Cuando vuelvas y quieras algo nuevo, basta con decir **qué quieres que pase**
(en palabras normales, sin detalles técnicos). Cada petición se atiende así:

1. Se revisa el código relacionado y se propone el cambio.
2. Se implementa con los estilos del club (verde y amarillo) y sin romper lo
   que ya funciona.
3. Se verifica con `php -l`, la verificación estática y pruebas de render.
4. Se sube a `main` y se comprueba en producción.
5. Si toca la base de datos, se añade una migración y se avisa de que hay que
   aplicarla (o se aplica desde el panel).

---

*Última actualización: la aplicación está completa y desplegada; quedan las
tareas de la sección 7.*

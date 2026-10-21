# CSDUV3 — Sistema de gestión institucional

Aplicación web para la administración del **Club Social Deportivo Unidad Vecinal N.º 3**.

Sistema modular en PHP 8.3 con MariaDB, pensado para ejecutarse en hosting
compartido (BanaHosting, plan Bana-Professional-Deluxe-SSD), sin SSH, sin Docker
y sin dependencias instaladas en el servidor.

- **Dominio previsto:** el subdominio HTTPS que se configure en el hosting
  (consulte [docs/DESPLIEGUE_BANAHOSTING.md](docs/DESPLIEGUE_BANAHOSTING.md))
- **Repositorio:** <https://github.com/bgrados/csduv3>
- **Carpeta local de desarrollo:** el directorio raíz del proyecto (el que contiene
  `app/`, `config/`, `database/`, `docs/`, `public/`, `storage/` y `tools/`)

---

## Estado del proyecto

| Fase | Contenido | Estado |
| --- | --- | --- |
| 1 | Estructura, configuración, PDO, enrutamiento, plantilla base, esquema SQL | **Completada y revisada** |
| 2 | Diseño compartido, autenticación, sesiones, roles y permisos, portal público y PWA | **Completada** |
| 3 | Socios y junta directiva | **Completada** |
| 4 | Instalaciones, reservas, solicitudes, calendario y pagos | **Completada** |
| 5 | Caja, ingresos, egresos e informes | **Completada** |
| 6 | Deportes, torneos, fixture y clasificación | **Completada** |
| 7 | Documentos, noticias con video, negocios, auditoría y elections | **Completada** |
| 8 | Pruebas integrales, documentación y despliegue | **En curso**: la aplicación está desplegada; faltan las pruebas manuales del servidor (ver [docs/PRUEBAS.md](docs/PRUEBAS.md)) |

> La aplicación está **desplegada y en uso**: <https://csduv3.benweb.soy.pe>.
> Lo que queda pendiente son las **pruebas manuales en el servidor** y tres
> tareas de seguridad (crear el administrador, rotar la contraseña de la base
> de datos y poner `APP_DEBUG=false`). Está todo detallado en
> [COMO-REGRESAR.md](COMO-REGRESAR.md).

> **Importante sobre las pruebas.** El código se verifica en cada cambio con
> `php -l`, la verificación estática (`tools/verificar_estatico.ps1`) y pruebas
> de render y de enrutamiento. Aun así, **falta la prueba manual en el
> servidor** con PHP 8.3 y MariaDB: los casos están en
> [docs/PRUEBAS.md](docs/PRUEBAS.md) y el estado de cada uno en
> [COMO-REGRESAR.md](COMO-REGRESAR.md).

---

## Requisitos

**Producción (hosting compartido)**

- PHP 8.3 o superior con las extensiones `pdo_mysql`, `mbstring`, `openssl`, `json`, `fileinfo`, `session`.
- MariaDB 10.11 LTS (recomendada); mínimo 10.2.2 por las restricciones `CHECK` y
  las columnas `JSON`. Con MySQL se requiere 8.0.16 o superior (MySQL 5.7 ignora
  las restricciones `CHECK`).
- Apache con `mod_rewrite` y capacidad de usar archivos `.htaccess`.
- Acceso a phpMyAdmin o a un cliente SQL para importar el esquema.

**Desarrollo local**

- Los mismos requisitos anteriores (XAMPP, Laragon, WAMP o el contenedor que se prefiera).
- No se requiere Composer, Node.js ni procesos en segundo plano.

---

## Estructura del proyecto

```
csduv3/
├── app/
│   ├── Controllers/       Controladores (lógica de cada módulo)
│   ├── Core/              Núcleo: enrutador, PDO, sesiones, CSRF, vistas, validación, Auth
│   ├── Middleware/        Autenticación, invitados, permisos y CSRF
│   ├── Models/            Acceso a datos por entidad (Fases 2 a 7)
│   ├── Views/             Plantillas PHP (panel y portal público)
│   └── routes.php         Definición de rutas
├── config/                Configuración del sistema (sin credenciales)
├── database/              Esquema SQL, datos iniciales, datos de demostración y migraciones
├── docs/                  Documentación técnica y de despliegue
├── public/                Único directorio accesible desde el navegador
│   ├── assets/            CSS, JavaScript e imágenes
│   ├── icons/             Iconos de la aplicación instalable (PNG)
│   ├── .htaccess          Reescritura, cabeceras de seguridad y tipos MIME
│   ├── index.php          Front controller
│   ├── manifest.webmanifest  Manifiesto de la aplicación instalable
│   ├── offline.html       Página mostrada cuando no hay conexión
│   ├── sw.js              Service worker (caché solo de archivos públicos)
│   └── router.php         Enrutador para el servidor embebido de PHP
├── storage/               Registros, archivos subidos y copias de seguridad (privado)
├── tools/                 Scripts de apoyo (no accesibles por web)
├── .env.example           Plantilla de configuración de entorno
└── .gitignore
```

La separación es deliberada: **presentación** (`app/Views`, `public/assets`),
**lógica de negocio** (`app/Controllers`, `app/Middleware`) y **acceso a datos**
(`app/Models`, `app/Core/Database.php`).

---

## Puesta en marcha rápida

1. Copiar `.env.example` como `.env` y completar los datos reales.
2. Importar en phpMyAdmin:
   1. `database/01_esquema.sql`
   2. `database/02_datos_iniciales.sql`
3. Apuntar el DocumentRoot a la carpeta `public/`.
4. Crear la primera cuenta administradora desde la consola:
   `php tools/crear_admin.php` y luego **eliminar ese archivo del servidor**.
5. Abrir la URL del proyecto e ingresar en `/ingresar`.

> Si ya tenía instalada la Fase 1, ejecute además
> `database/migrations/0001_solicitudes_alquiler.sql` una sola vez.

Detalle completo en [docs/INSTALACION.md](docs/INSTALACION.md) y
[docs/DESPLIEGUE_BANAHOSTING.md](docs/DESPLIEGUE_BANAHOSTING.md).

---

## Documentación

> 👉 **Si estás volviendo al proyecto, empieza por [COMO-REGRESAR.md](COMO-REGRESAR.md)**:
> resume la aplicación, el despliegue, las herramientas, las reglas y las tareas
> pendientes.

| Documento | Contenido |
| --- | --- |
| [COMO-REGRESAR.md](COMO-REGRESAR.md) | **Guía general y recordatorio para la próxima visita** |
| [docs/INSTALACION.md](docs/INSTALACION.md) | Instalación local, importación y creación del primer administrador |
| [docs/DESPLIEGUE_BANAHOSTING.md](docs/DESPLIEGUE_BANAHOSTING.md) | Publicación por cPanel, FTP o Administrador de archivos |
| [docs/BASE_DE_DATOS.md](docs/BASE_DE_DATOS.md) | Creación, importación y evolución del esquema |
| [docs/SEGURIDAD.md](docs/SEGURIDAD.md) | Autenticación, permisos, sesiones y recomendaciones |
| [docs/MODULOS.md](docs/MODULOS.md) | Módulos previstos y su alcance |
| [docs/PORTAL_PUBLICO.md](docs/PORTAL_PUBLICO.md) | Portal público de alquileres (uso y reglas) |
| [docs/PWA.md](docs/PWA.md) | Aplicación instalable y política de caché |
| [docs/MANTENIMIENTO.md](docs/MANTENIMIENTO.md) | Copias de seguridad y actualizaciones |
| [docs/ARQUITECTURA.md](docs/ARQUITECTURA.md) | Organización del código y decisiones técnicas |
| [docs/PRUEBAS.md](docs/PRUEBAS.md) | Pruebas realizadas y pruebas pendientes |

---

## Convenciones

- Idioma de la interfaz: **español**.
- Zona horaria: **America/Lima**.
- Fechas: **dd/mm/aaaa**. Moneda: **soles peruanos (S/)**.
- Prefijo de tablas: `csduv3_`.
- Identificadores y nombres de archivos de código en español, coherentes con el dominio del club.

---

## Advertencias

- La identidad visual se aplicó a partir de `logo.jpg`: amarillo `#FEFE08` y
  verde `#009F39`. La paleta, los componentes y las reglas responsive están en
  [docs/DISENO.md](docs/DISENO.md). Conviene que el club entregue el logotipo
  oficial en alta resolución para impresión y formatos grandes.
- La aplicación está **desplegada y funcionando**, pero **faltan las pruebas
  manuales en el servidor** con PHP 8.3 y MariaDB. Hasta ejecutar
  [docs/PRUEBAS.md](docs/PRUEBAS.md) no debe darse por terminada.
- Los comprobantes que genere el sistema son **documentos internos**; no son
  comprobantes electrónicos autorizados por SUNAT.
- El registro de resultados electorales es **administrativo**; no es un sistema de
  votación electrónica certificada.
- El módulo de caja es un registro financiero administrativo, no contabilidad
  tributaria completa.

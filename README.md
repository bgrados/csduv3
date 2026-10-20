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
| 2 | Diseño compartido, autenticación, sesiones, roles y permisos, portal público de alquileres y PWA | **Implementada — pendiente de pruebas en servidor** |
| 3 | Socios y junta directiva | Pendiente |
| 4 | Instalaciones, reservas, calendario y pagos | Pendiente |
| 5 | Caja, ingresos, egresos e informes | Pendiente |
| 6 | Deportes, torneos, fixture y clasificación | Pendiente |
| 7 | Documentos, auditoría y reportes | Pendiente |
| 8 | Pruebas integrales, documentación y despliegue | Pendiente |

> **Importante sobre el estado de la Fase 2.** El código de la Fase 2 está escrito y
> revisado de forma estática, pero **no se ha ejecutado**: en el equipo de desarrollo
> no hay PHP ni MariaDB instalados. Antes de dar la fase por terminada deben
> ejecutarse las pruebas manuales de [docs/PRUEBAS.md](docs/PRUEBAS.md) en un
> servidor con PHP 8.3 y MariaDB.

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

| Documento | Contenido |
| --- | --- |
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
- La Fase 2 está **implementada pero no probada en un servidor** con PHP 8.3 y
  MariaDB. Hasta ejecutar [docs/PRUEBAS.md](docs/PRUEBAS.md) no debe darse por
  terminada.
- Los comprobantes que genere el sistema son **documentos internos**; no son
  comprobantes electrónicos autorizados por SUNAT.
- El registro de resultados electorales es **administrativo**; no es un sistema de
  votación electrónica certificada.
- El módulo de caja es un registro financiero administrativo, no contabilidad
  tributaria completa.

# Aplicación instalable (PWA)

El portal público puede instalarse como aplicación en Android, iPhone/iPad y
computadoras (Chrome, Edge, Safari). Es una mejora de acceso, **no** un mecanismo
para guardar datos privados.

---

## 1. Archivos que la componen

| Archivo | Función |
| --- | --- |
| `public/manifest.webmanifest` | Nombre, colores, iconos, modo `standalone` y accesos directos |
| `public/sw.js` | Service worker: decide qué se guarda en caché |
| `public/offline.html` | Página que se muestra cuando no hay conexión |
| `public/icons/icon-192.png`, `icon-512.png` | Iconos estándar |
| `public/icons/icon-maskable-192.png`, `icon-maskable-512.png` | Iconos adaptables (Android recorta la forma) |
| `public/icons/apple-touch-icon-180.png` | Icono para iPhone/iPad |
| `public/assets/js/app.js` | Registro del service worker y aviso de instalación |
| `tools/generar_iconos.ps1` | Genera los PNG con la identidad UV3 (uso del desarrollador) |

Los iconos usan el amarillo y el verde del logotipo institucional: monograma
**UV3** sobre fondo amarillo. Se regeneran con el script y se reemplazarán por
los iconos definitivos si el club entrega una versión oficial en alta resolución.

---

## 2. Política de caché (regla central)

El service worker aplica tres reglas deliberadamente restrictivas:

1. **Solo estáticos públicos desde caché.** Se precargan al instalar:
   `offline.html`, `assets/css/app.css`, `assets/js/app.js`, el logotipo, el
   favicon y el manifiesto. Los archivos nuevos bajo `assets/` o `icons/` se
   guardan al primer uso.
2. **Las páginas HTML nunca se guardan.** La navegación intenta siempre la red
   primero. Si no hay conexión, se muestra `offline.html`.
3. **El panel y las rutas privadas quedan fuera.** `/panel`, `/perfil`,
   `/usuarios`, `/roles`, `/solicitudes`, `/auditoria`, `/diagnostico`,
   `/ingresar` y `/salir` van siempre a la red y no se almacenan.

Además:

- Las peticiones que **no son GET** (incluido el envío de una solicitud) no pasan
  por el service worker.
- Solo se atienden peticiones del **mismo origen**: nada de terceros.

Consecuencia práctica: **es imposible que el navegador muestre datos de socios,
caja, reservas, sesiones o respuestas del panel desde una copia local.**

---

## 3. Cómo instalarla

**Android (Chrome)**

1. Abra el portal.
2. Use el menú → **Instalar aplicación** o el botón **Instalar** del aviso.
3. Confirme. El icono queda en la pantalla de inicio.

**iPhone/iPad (Safari)**

1. Abra el portal en Safari.
2. Pulse **Compartir** → **Añadir a la pantalla de inicio**.

**Computadora (Chrome/Edge)**

1. Abra el portal.
2. En la barra de direcciones aparece el icono de instalación, o use el menú →
   **Instalar**.

El aviso de instalación que muestra el portal puede descartarse; la decisión se
recuerda en el propio navegador (no en el servidor).

---

## 4. Requisitos

- **HTTPS.** Los service workers solo funcionan en contextos seguros. En `localhost`
  también funcionan, para pruebas.
- El hosting debe servir `manifest.webmanifest` con el tipo
  `application/manifest+json`. `public/.htaccess` ya lo declara y también evita que
  `sw.js` quede almacenado por el navegador o por intermediarios.

Si alguna de estas condiciones falla, el portal sigue funcionando con normalidad:
simplemente no se instala ni usa caché.

---

## 5. Actualizaciones

Cuando cambie algún archivo estático o la forma de trabajar del service worker:

1. Suba los archivos nuevos.
2. **Aumente la versión de la caché** en `public/sw.js`:
   `var CACHE = 'csduv3-publica-v3';`
3. Al abrir el portal, el service worker nuevo se instala, borra las cachés
   antiguas (las que empiezan por `csduv3-`) y toma el control.

Como el HTML nunca se guarda, los contenidos de las páginas se actualizan solos al
recargar.

---

## 6. Qué no hace esta PWA

- **No** permite trabajar sin conexión con datos reales: sin red no hay solicitudes
  ni consultas de disponibilidad.
- **No** recibe notificaciones push ni ejecuta procesos en segundo plano.
- **No** sustituye a una aplicación móvil nativa ni requiere publicarla en tiendas.
- **No** guarda credenciales ni sesiones en el dispositivo.

---

## 7. Pruebas sugeridas

Ejecute las pruebas **PP-1 a PP-6** de [PRUEBAS.md](PRUEBAS.md). Las más
importantes son las de privacidad: comprobar que, tras usar el panel, ninguna
respuesta administrativa aparece en la caché del navegador (DevTools →
Application → Cache Storage).

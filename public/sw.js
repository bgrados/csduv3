/* ==========================================================================
   CSDUV3 - Service Worker del portal público
   --------------------------------------------------------------------------
   Reglas de caché (deliberadamente restrictivas):

   1. Solo se sirve desde caché un listado fijo de archivos estáticos públicos
      (hoja de estilos, script, logo, iconos y la página sin conexión).
   2. Las páginas HTML nunca se guardan en caché. La navegación siempre intenta
      la red primero, de modo que el contenido mostrado es el del servidor.
   3. El área administrativa (/panel, /usuarios, /roles, /solicitudes,
      /auditoria, /ingresar, /perfil) se excluye por completo: toda petición de
      esas rutas va directamente a la red y no se almacena.
   4. Las peticiones que no son GET (por ejemplo, el envío de una solicitud) no
      pasan por el service worker.

   No se cachea información privada de socios, sesiones, datos financieros ni
   respuestas administrativas.
   ========================================================================== */

'use strict';

var CACHE = 'csduv3-publica-v2';

/* Rutas del panel administrativo que nunca se cachean. */
var RUTAS_PRIVADAS = [
    '/panel',
    '/perfil',
    '/usuarios',
    '/roles',
    '/solicitudes',
    '/auditoria',
    '/diagnostico',
    '/ingresar',
    '/salir'
];

/* El prefijo de instalación se deduce del ámbito del service worker. */
function baseDelPortal() {
    try {
        return new URL(self.registration.scope).pathname.replace(/\/$/, '');
    } catch (error) {
        return '';
    }
}

var BASE = baseDelPortal();

function rutaRelativa(pathname) {
    if (BASE !== '' && pathname.indexOf(BASE) === 0) {
        return pathname.slice(BASE.length) || '/';
    }

    return pathname;
}

function esRutaPrivada(pathname) {
    var relativa = rutaRelativa(pathname);

    return RUTAS_PRIVADAS.some(function (ruta) {
        return relativa === ruta || relativa.indexOf(ruta + '/') === 0;
    });
}

function esEstaticoPublico(pathname) {
    var relativa = rutaRelativa(pathname);

    return relativa.indexOf('/assets/') === 0
        || relativa.indexOf('/icons/') === 0
        || relativa === '/manifest.webmanifest';
}

var RECURSOS = [
    './offline.html',
    './assets/css/app.css',
    './assets/js/app.js',
    './assets/img/logo.jpg',
    './assets/img/logo.svg',
    './assets/img/favicon.svg',
    './manifest.webmanifest'
];

self.addEventListener('install', function (evento) {
    evento.waitUntil(
        caches.open(CACHE).then(function (cache) {
            /* Si algún archivo falta, no se interrumpe la instalación. */
            return Promise.all(
                RECURSOS.map(function (recurso) {
                    return cache.add(recurso).catch(function () {
                        return null;
                    });
                })
            );
        }).then(function () {
            return self.skipWaiting();
        })
    );
});

self.addEventListener('activate', function (evento) {
    evento.waitUntil(
        caches.keys().then(function (claves) {
            return Promise.all(
                claves.map(function (clave) {
                    if (clave !== CACHE && clave.indexOf('csduv3-') === 0) {
                        return caches.delete(clave);
                    }

                    return null;
                })
            );
        }).then(function () {
            return self.clients.claim();
        })
    );
});

self.addEventListener('message', function (evento) {
    if (evento.data === 'csduv3:actualizar') {
        self.skipWaiting();
    }
});

self.addEventListener('fetch', function (evento) {
    var peticion = evento.request;

    /* 4. Solo lecturas. */
    if (peticion.method !== 'GET') {
        return;
    }

    var url = new URL(peticion.url);

    /* Solo el mismo origen: nada de terceros. */
    if (url.origin !== self.location.origin) {
        return;
    }

    /* 3. El panel administrativo siempre va a la red, sin caché. */
    if (esRutaPrivada(url.pathname)) {
        return;
    }

    /* 1. Estáticos públicos: primero la caché. */
    if (esEstaticoPublico(url.pathname)) {
        evento.respondWith(desdeCacheOPeticion(peticion));
        return;
    }

    /* 2. Navegación pública: red primero, sin guardar HTML. */
    if (peticion.mode === 'navigate') {
        evento.respondWith(redConRespaldo(peticion));
    }
});

function desdeCacheOPeticion(peticion) {
    return caches.match(peticion).then(function (respuesta) {
        if (respuesta) {
            return respuesta;
        }

        return fetch(peticion).then(function (nueva) {
            if (!nueva || !nueva.ok || nueva.type !== 'basic') {
                return nueva;
            }

            var copia = nueva.clone();

            caches.open(CACHE).then(function (cache) {
                cache.put(peticion, copia);
            });

            return nueva;
        });
    });
}

function redConRespaldo(peticion) {
    return fetch(peticion).catch(function () {
        return caches.match('./offline.html').then(function (respaldo) {
            if (respaldo) {
                return respaldo;
            }

            return new Response(
                'Sin conexión. Vuelva a intentarlo cuando recupere la señal.',
                {
                    status: 503,
                    headers: { 'Content-Type': 'text/plain; charset=utf-8' }
                }
            );
        });
    });
}

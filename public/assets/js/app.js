/* ==========================================================================
   CSDUV3 - Comportamiento del cliente
   Sin dependencias externas. Todo el código funciona con JavaScript moderno
   del navegador y degrada con elegancia si está desactivado.
   ========================================================================== */

(function () {
    'use strict';

    /**
     * Menú lateral en pantallas pequeñas.
     */
    function inicializarMenuLateral() {
        var layout = document.querySelector('[data-sidebar]');

        if (!layout) {
            return;
        }

        var boton = layout.querySelector('[data-sidebar-toggle]');
        var sidebar = layout.querySelector('.sidebar');

        if (!boton || !sidebar) {
            return;
        }

        var backdrop = document.createElement('div');
        backdrop.className = 'layout__backdrop';
        backdrop.hidden = true;
        layout.appendChild(backdrop);

        function abrir() {
            sidebar.classList.add('is-open');
            backdrop.hidden = false;
            boton.setAttribute('aria-expanded', 'true');
        }

        function cerrar() {
            sidebar.classList.remove('is-open');
            backdrop.hidden = true;
            boton.setAttribute('aria-expanded', 'false');
        }

        boton.addEventListener('click', function () {
            if (sidebar.classList.contains('is-open')) {
                cerrar();
            } else {
                abrir();
            }
        });

        backdrop.addEventListener('click', cerrar);

        document.addEventListener('keydown', function (evento) {
            if (evento.key === 'Escape' && sidebar.classList.contains('is-open')) {
                cerrar();
                boton.focus();
            }
        });
    }

    /**
     * Confirmación antes de ejecutar una acción destructiva.
     *
     * Uso: <form data-confirmar="¿Eliminar el registro?">
     */
    function inicializarConfirmaciones() {
        document.querySelectorAll('[data-confirmar]').forEach(function (elemento) {
            elemento.addEventListener('submit', function (evento) {
                var mensaje = elemento.getAttribute('data-confirmar') || '¿Confirma la operación?';

                if (!window.confirm(mensaje)) {
                    evento.preventDefault();
                }
            });
        });
    }

    /**
     * Búsqueda inmediata en tablas marcadas con data-tabla-buscable.
     */
    function inicializarBusquedaEnTablas() {
        document.querySelectorAll('[data-tabla-buscable]').forEach(function (contenedor) {
            var campo = contenedor.querySelector('[data-buscador]');
            var tabla = contenedor.querySelector('table');

            if (!campo || !tabla) {
                return;
            }

            var filas = Array.prototype.slice.call(tabla.tBodies[0] ? tabla.tBodies[0].rows : []);
            var sinResultados = contenedor.querySelector('[data-sin-resultados]');

            function filtrar() {
                var termino = campo.value.trim().toLowerCase();
                var visibles = 0;

                filas.forEach(function (fila) {
                    var coincide = termino === ''
                        || fila.textContent.toLowerCase().indexOf(termino) !== -1;

                    fila.hidden = !coincide;

                    if (coincide) {
                        visibles++;
                    }
                });

                if (sinResultados) {
                    sinResultados.hidden = visibles !== 0;
                }
            }

            campo.addEventListener('input', filtrar);
            filtrar();
        });
    }

    /**
     * Cierre manual de las alertas.
     */
    function inicializarAlertasCerrables() {
        document.querySelectorAll('.alert[data-cerrar]').forEach(function (alerta) {
            var boton = document.createElement('button');
            boton.type = 'button';
            boton.className = 'alert__close';
            boton.setAttribute('aria-label', 'Cerrar mensaje');
            boton.textContent = '×';
            boton.addEventListener('click', function () {
                alerta.remove();
            });
            alerta.appendChild(boton);
        });
    }

    /**
     * Progressive Web App: registro del service worker y del aviso de instalación.
     *
     * El service worker solo sirve desde caché archivos estáticos públicos.
     * El panel administrativo y las páginas HTML nunca se guardan en caché.
     */
    var avisoInstalacion = null;

    function registrarServiceWorker() {
        if (!('serviceWorker' in navigator)) {
            return;
        }

        var enlaceManifest = document.querySelector('link[rel="manifest"]');

        if (!enlaceManifest) {
            return;
        }

        var href = enlaceManifest.getAttribute('href');

        if (!href) {
            return;
        }

        var url = href.replace(/manifest\.webmanifest.*$/, 'sw.js');

        navigator.serviceWorker.register(url).catch(function () {
            /* Sin HTTPS o sin soporte: la aplicación funciona igual, sin caché. */
        });
    }

    function descartadoPorElUsuario() {
        try {
            return window.localStorage.getItem('csduv3-instalacion-descartada') === '1';
        } catch (error) {
            return false;
        }
    }

    function prepararInstalacion() {
        var aviso = document.querySelector('[data-install-banner]');
        var botones = document.querySelectorAll('[data-install-app]');

        window.addEventListener('beforeinstallprompt', function (evento) {
            evento.preventDefault();
            avisoInstalacion = evento;

            if (aviso && !descartadoPorElUsuario()) {
                aviso.hidden = false;
            }
        });

        window.addEventListener('appinstalled', function () {
            avisoInstalacion = null;

            if (aviso) {
                aviso.hidden = true;
            }
        });

        botones.forEach(function (boton) {
            boton.addEventListener('click', function () {
                if (avisoInstalacion) {
                    avisoInstalacion.prompt();
                    avisoInstalacion.userChoice.then(function () {
                        avisoInstalacion = null;

                        if (aviso) {
                            aviso.hidden = true;
                        }
                    });

                    return;
                }

                window.alert('Para instalar la aplicación, abra el menú del navegador y elija «Añadir a la pantalla de inicio» o «Instalar aplicación».');
            });
        });

        var descartar = document.querySelector('[data-install-dismiss]');

        if (descartar && aviso) {
            descartar.addEventListener('click', function () {
                aviso.hidden = true;

                try {
                    window.localStorage.setItem('csduv3-instalacion-descartada', '1');
                } catch (error) {
                    /* Sin almacenamiento local: no es un problema. */
                }
            });
        }
    }

    function inicializarPwa() {
        registrarServiceWorker();
        prepararInstalacion();
    }

    function iniciar() {
        inicializarMenuLateral();
        inicializarConfirmaciones();
        inicializarBusquedaEnTablas();
        inicializarAlertasCerrables();
        inicializarPwa();
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', iniciar);
    } else {
        iniciar();
    }
})();

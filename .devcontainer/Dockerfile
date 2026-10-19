# ============================================================================
#  CSDUV3 - Entorno de PRUEBAS aislado (PHP 8.3 + Apache + MariaDB)
# ----------------------------------------------------------------------------
#  Base: imagen oficial de PHP 8.3 con Apache (mod_php) sobre Debian 12
#  "bookworm". Se usa la imagen oficial (y no la de devcontainers) porque:
#    * trae exactamente PHP 8.3 + Apache + mod_php, sin capas extra;
#    * es determinista y fácil de auditar.
#  Debian 12 incluye MariaDB 10.11 (LTS), que es la versión de referencia
#  para las pruebas del proyecto.
#
#  Este contenedor es DESECHABLE y NO debe apuntar nunca a una base de datos
#  real. Las credenciales de laboratorio las fija .devcontainer/setup.sh.
# ============================================================================
FROM php:8.3-apache-bookworm

# --- Evitar arranques automáticos de servicios ------------------------------
#  En el contenedor no hay systemd. Con este "policy-rc.d" los paquetes no
#  intentan iniciar servicios durante la instalación (lo que puede hacer fallar
#  el postinst). MariaDB lo arranca setup.sh de forma explícita y controlada.
RUN printf '#!/bin/sh\nexit 101\n' > /usr/sbin/policy-rc.d \
    && chmod +x /usr/sbin/policy-rc.d

# --- Paquetes del sistema ---------------------------------------------------
#  mariadb-server / mariadb-client : base de datos local al contenedor
#  init-system-helpers             : aporta /usr/sbin/service
#  libonig-dev, libicu-dev         : necesarias para compilar mbstring e intl
#  curl                            : comprobación local de la web en setup.sh
#  git, unzip, less                : herramientas básicas de trabajo
RUN apt-get update \
    && DEBIAN_FRONTEND=noninteractive apt-get install -y --no-install-recommends \
        mariadb-server \
        mariadb-client \
        init-system-helpers \
        libonig-dev \
        libicu-dev \
        curl \
        git \
        unzip \
        less \
    && rm -rf /var/lib/apt/lists/*

# --- Extensiones de PHP requeridas ------------------------------------------
#  pdo_mysql y mbstring son OBLIGATORIAS: si faltan, la construcción falla.
#  intl es opcional (el código todavía no la usa): si no compila, solo avisa.
RUN set -eux; \
    for ext in pdo_mysql mbstring intl; do \
        if php -m | grep -qi "^${ext}$"; then \
            echo "== ${ext}: ya presente en la imagen"; \
        else \
            docker-php-ext-install "${ext}" \
                || echo "AVISO: no se pudo compilar ${ext} (opcional)"; \
        fi; \
    done; \
    php -m | grep -qi '^pdo_mysql$' || { echo 'ERROR: falta la extension pdo_mysql'; exit 1; }; \
    php -m | grep -qi '^mbstring$'  || { echo 'ERROR: falta la extension mbstring';  exit 1; }

# --- Configuración de PHP para PRUEBAS (nunca para producción) --------------
RUN printf '%s\n' \
        'date.timezone = America/Lima' \
        'display_errors = On' \
        'display_startup_errors = On' \
        'error_reporting = E_ALL' \
        'log_errors = On' \
        'upload_max_filesize = 5M' \
        'post_max_size = 6M' \
        'memory_limit = 256M' \
        'max_execution_time = 60' \
    > /usr/local/etc/php/conf.d/zz-csduv3-pruebas.ini

# --- Apache -----------------------------------------------------------------
#  mod_rewrite: rutas limpias (.htaccess de public/).
#  mod_headers: cabeceras definidas en public/.htaccess (p. ej. sw.js).
#  ServerName  : evita el aviso de nombre de servidor no configurado.
RUN a2enmod rewrite headers \
    && echo 'ServerName localhost' >> /etc/apache2/apache2.conf

# Apache escucha en 8000 (puerto que reenvían Codespaces / Dev Containers).
RUN sed -ri 's/^Listen 80$/Listen 8000/' /etc/apache2/ports.conf

# NOTA: el archivo .devcontainer/apache/000-default.conf NO se copia aquí.
# Contiene el marcador __DOCROOT__ y setup.sh lo sustituye por la ruta real
# del proyecto, de modo que no hay que fijar aquí el nombre del repositorio.

EXPOSE 8000

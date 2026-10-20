# Identidad visual UV3

Este documento fija la paleta, los componentes y las reglas responsive de la
aplicación. La identidad se deriva del logotipo institucional `logo.jpg`
(monograma UV3), muestreado directamente:

| Color del logo | Muestra |
| --- | --- |
| Amarillo institucional | `#FEFE08` |
| Verde institucional | `#009F39` |

> Los colores se tomaron del archivo entregado. No se inventaron colores de
> otras instituciones ni se modificó el logotipo.

---

## 1. Paleta

### 1.1 Marca

| Token CSS | Hex | Uso |
| --- | --- | --- |
| `--brand-yellow` | `#FEFE08` | Acento de marca, enlace activo del menú, CTA del hero, borde superior |
| `--brand-yellow-soft` | `#FFFDE0` | Fondos suaves de íconos y estados vacíos |
| `--brand-green` | `#009F39` | Gráficos, indicadores, borde de tarjetas estadísticas |
| `--brand-green-dark` | `#007A2C` | Botones primarios, enlaces, foco |
| `--brand-green-deep` | `#063B22` | Menú lateral, títulos, pie del portal, texto sobre amarillo |
| `--brand-green-night` | `#042A18` | Gradiente inferior del menú lateral |

### 1.2 Neutros y superficies

| Token CSS | Hex | Uso |
| --- | --- | --- |
| `--color-bg` | `#F5F7F2` | Fondo general con matiz verde |
| `--color-surface` | `#FFFFFF` | Tarjetas, tablas, formularios |
| `--color-surface-soft` | `#FBFDFB` | Campos y bloques secundarios |
| `--color-border` | `#DBE5DC` | Bordes y separadores |
| `--color-border-strong` | `#C3D3C6` | Bordes de campos y botones fantasma |
| `--color-text` | `#18251B` | Texto principal |
| `--color-text-soft` | `#56665A` | Texto secundario |
| `--color-muted` | `#6B7A6E` | Ayudas y metadatos (contraste ≥ 4.5:1) |

### 1.3 Colores semánticos

| Token | Hex | Fondo suave | Uso |
| --- | --- | --- | --- |
| `--color-success` | `#0B7A3B` | `#E4F4EA` | Confirmaciones, estados atendidos |
| `--color-warning` | `#8A5A00` | `#FFF4D6` | Avisos y pendientes |
| `--color-danger` | `#B42318` | `#FDECEA` | Errores y acciones destructivas |
| `--color-info` | `#0B5FA5` | `#E6F0FA` | Información y estados en revisión |

---

## 2. Combinaciones recomendadas

### 2.1 Área administrativa (predeterminada)

- Menú lateral: degradado `#063B22 → #042A18`.
- Enlace activo: fondo `#FEFE08` con texto `#063B22`.
- Barra superior: blanco translúcido con línea superior amarilla.
- Fondo: `#F5F7F2`; tarjetas blancas; texto `#18251B`.
- Acciones primarias: `#007A2C` con texto blanco.

Es la combinación de mayor contraste y la que usa toda la aplicación.

### 2.2 Portal público

- Hero: degradado `#063B22 → #0A522C → #009F39` con un resplandor amarillo.
- Botón principal del hero: amarillo `#FEFE08` con texto `#063B22`.
- Botón secundario: contorno blanco translúcido.
- Pie: `#063B22` con textos `#DCEFE0` y enlaces que pasan a amarillo al pasar el cursor.

### 2.3 Acentos puntuales

- El amarillo se usa **solo** como fondo, borde o gráfico, nunca como texto sobre blanco.
- Para texto sobre amarillo, usar siempre `#063B22` (contraste ≈ 11.7:1).
- Para textos verdes sobre blanco, usar `#007A2C` (contraste ≈ 5.2:1), no `#009F39` (≈ 3.2:1).

---

## 3. Contraste (WCAG 2.1, aproximado)

| Combinación | Contraste | Uso permitido |
| --- | --- | --- |
| `#063B22` sobre `#FEFE08` | 11.7:1 | Texto normal, botones, enlaces activos |
| `#007A2C` sobre `#FFFFFF` | 5.2:1 | Texto normal, enlaces, botones |
| `#063B22` sobre `#FFFFFF` | 13:1 | Títulos y texto principal |
| `#56665A` sobre `#FFFFFF` | 6.1:1 | Texto secundario |
| `#6B7A6E` sobre `#FFFFFF` | 4.5:1 | Ayudas y metadatos |
| `#FEFE08` sobre `#FFFFFF` | 1.1:1 | **Prohibido** para texto |

Los badges y alertas usan fondos suaves con texto oscuro del mismo tono; no se
usa texto amarillo sobre fondos claros.

---

## 4. Tipografía y ritmo

- Fuente: pila del sistema (`system-ui, Segoe UI, Roboto, Arial`). Sin fuentes
  externas: la PWA sigue funcionando sin conexión y sin terceros.
- Tamaño base: 15 px, interlineado 1.55.
- Títulos: `clamp()` en el hero; el resto mantiene una escala compacta.
- Radios: 8 px (controles), 12 px (bloques), 18 px (tarjetas y hero).
- Sombras: verde profundo con baja opacidad, nunca negro puro.
- Transiciones: 160 ms; se anulan con `prefers-reduced-motion`.

---

## 5. Componentes

| Componente | Regla visual |
| --- | --- |
| Botón primario | Verde `#007A2C`, texto blanco, radio 10 px |
| Botón de acento | Amarillo `#FEFE08`, texto `#063B22` (hero y énfasis) |
| Botón fantasma | Blanco, borde verde grisáceo, texto verde |
| Tarjeta | Blanca, radio 18 px, borde `#DBE5DC`, sombra suave |
| Tarjeta estadística | Borde izquierdo verde de 4 px; ámbar si requiere atención |
| Tabla | Cabecera verde claro, filas con hover `#F3FAF4`, scroll horizontal en móvil |
| Badge | Fondo suave + borde del mismo tono + texto oscuro |
| Alerta | Borde izquierdo de 4 px del color semántico |
| Campo | Fondo `#FBFDFB`, foco con anillo verde y borde `#007A2C` |
| Menú lateral | Verde profundo, enlace activo amarillo, logo en mosaico amarillo |
| Pie del portal | Verde profundo con borde superior amarillo |

---

## 6. Responsive

| Ancho | Comportamiento |
| --- | --- |
| ≥ 1200 px | Menú lateral fijo de 268 px; contenido centrado hasta 1440 px |
| 900–1199 px | Menú lateral visible; tarjetas en 2–4 columnas según el ancho; tablas con scroll si no caben |
| 620–899 px | Menú lateral como panel deslizante con fondo oscuro; tablas con scroll |
| < 620 px | Barra superior compacta (perfil y salida como íconos); se oculta el nombre secundario de la marca; botones del hero a ancho completo; tarjetas a una columna |
| < 420 px | Se reducen el padding general, el tamaño de los botones y el de las tarjetas |

En el portal público, la navegación se coloca debajo de la marca y se envuelve
en dos filas por debajo de 760 px para que el acceso del personal siga visible.

Se respetan las áreas seguras de iPhone (`env(safe-area-inset-*)`) en el menú,
el contenido, el pie y el aviso de instalación.

---

## 7. Accesibilidad

- Foco visible: anillo verde de 3 px con desplazamiento.
- Enlaces activos con `aria-current="page"` en el menú administrativo y en la navegación pública.
- El menú móvil se cierra con `Escape`, con el fondo y con el botón de apertura.
- Las animaciones se desactivan con `prefers-reduced-motion`.
- El amarillo nunca se usa como color de texto sobre fondos claros.
- Los tamaños táctiles principales son de 40–44 px.

---

## 8. Archivos de identidad

| Archivo | Función |
| --- | --- |
| `logo.jpg` (raíz) | Archivo original entregado |
| `public/assets/img/logo.jpg` | Logo usado por la aplicación |
| `public/assets/img/logo.svg` | Respaldo vectorial con la misma paleta |
| `public/assets/img/favicon.svg` | Favicon amarillo con monograma verde |
| `public/icons/*.png` | Iconos PWA generados con `tools/generar_iconos.ps1` |
| `public/manifest.webmanifest` | `theme_color` `#063B22`, `background_color` `#F5F7F2` |
| `public/offline.html` | Página sin conexión con la misma identidad |

### Mantenimiento

1. Los colores se cambian solo en las variables `:root` de
   `public/assets/css/app.css`.
2. Si cambia el logo, reemplazar `public/assets/img/logo.jpg` y el respaldo
   `logo.svg`.
3. Regenerar los iconos con:
   `powershell -NoProfile -ExecutionPolicy Bypass -File tools/generar_iconos.ps1`
4. Subir la versión de caché en `public/sw.js` (`CACHE`) cuando cambien los
   estáticos, para que la PWA descargue la nueva hoja de estilos.
5. Actualizar `theme_color` en `manifest.webmanifest`, en los dos layouts y en
   `offline.html` si cambia el color de la barra del navegador.

---

## 9. Estado de validación

La hoja de estilos y las vistas se revisaron de forma estática. La verificación
`tools/verificar_estatico.ps1` reportó **86 archivos PHP con 0 hallazgos** al
excluir `tools/migrar.php` (script de servidor con hallazgos preexistentes y fuera
del alcance de la aplicación); el CSS tiene **282/282 llaves balanceadas**.

También se hicieron **capturas con Edge headless sobre una vista estática de
referencia** que usa la hoja de estilos real:

- **390 px:** panel administrativo y portal público sin desbordamiento horizontal
  de página; la tabla del panel conserva su scroll interno y el menú público se
  reorganiza en dos filas.
- **768 px:** sin desbordamiento horizontal; el panel usa el menú deslizante y el
  portal mantiene las tarjetas en dos columnas.
- **1440 px:** panel y portal a ancho completo, con el menú lateral fijo.

Estas capturas **no sustituyen** la revisión en la aplicación real. Queda
pendiente validar con PHP 8.3 y MariaDB en un navegador de escritorio, en
dispositivos móviles/tabletas físicos, con datos de prueba y con la PWA instalada
(modo sin conexión). Nunca debe hacerse sobre producción.

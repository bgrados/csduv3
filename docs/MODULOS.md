# Módulos del sistema

Descripción funcional de cada módulo, su estado y su alcance. Ningún módulo se
considera terminado solo porque exista su pantalla: se considera terminado
cuando sus operaciones funcionan sobre la base de datos y están probadas.

| Módulo | Fase | Estado |
| --- | --- | --- |
| Panel principal | 2 (base) / 5 (indicadores completos) | **Implementado — pendiente de pruebas en servidor** |
| Socios | 3 | Pendiente |
| Junta directiva y elecciones | 3 | Pendiente |
| Instalaciones, tarifas y reservas | 4 | Pendiente |
| Solicitudes del portal público | 2 | **Implementado — pendiente de pruebas en servidor** |
| Aplicación instalable (PWA) | 2 | **Implementado — pendiente de pruebas en servidor** |
| Calendario institucional | 4 | Pendiente |
| Caja, ingresos y egresos | 5 | Pendiente |
| Informes y documentos | 7 | Pendiente |
| Deportes y torneos | 6 | Pendiente |
| Usuarios, roles y permisos | 2 | **Implementado — pendiente de pruebas en servidor** |
| Auditoría | 7 (consulta en la Fase 2) | **Consulta implementada — pendiente de pruebas en servidor** |

> «Pendiente de pruebas en servidor» significa que el código está escrito y
> revisado de forma estática, pero **no se ha ejecutado** todavía: el equipo de
> desarrollo no tiene PHP ni MariaDB. Ninguna funcionalidad de la Fase 2 debe
> considerarse terminada hasta ejecutar las pruebas de [PRUEBAS.md](PRUEBAS.md).

---

## A. Panel principal

**Objetivo.** Mostrar el estado del club con datos reales de la base de datos.

**Contenido previsto**

- Socios activos.
- Socios pendientes de regularización.
- Alquileres próximos.
- Ingresos y egresos del periodo.
- Saldo financiero.
- Próximos eventos y actividades deportivas.
- Últimos movimientos registrados.

**Reglas**

- Los indicadores se obtienen con consultas a la base de datos; no se muestran
  valores de ejemplo.
- Si no hay información, se muestra cero o un estado vacío con una indicación de
  cómo comenzar.
- El acceso depende del permiso `panel.ver`.

**Estado actual.** La Fase 1 entregó la plantilla, las tarjetas y los estados
vacíos. La Fase 2 conecta los indicadores que ya tienen datos reales
(solicitudes por revisar, reservas próximas, socios activos, saldo del mes y
usuarios activos) **filtrándolos por los permisos del usuario**: quien no puede
ver caja no ve cifras de caja. Los indicadores de módulos aún no implementados
aparecen en cero. Incluye una lista de «tareas recomendadas» que orienta los
primeros pasos del club.

---

## B. Gestión de socios

**Objetivo.** Mantener el padrón de socios y su historial.

**Funciones**

- Registrar, editar y consultar socios.
- Asignar un **código único** de socio.
- Registrar nombres, apellidos, documento de identidad, fecha de nacimiento,
  dirección, teléfonos, correo y fecha de ingreso.
- Registrar categoría, estado y observaciones.
- Consultar el historial de pagos o cuotas cuando el club use ese concepto.
- Adjuntar documentos a la ficha.
- Buscar y filtrar por código, documento, nombre, categoría y estado.
- Registrar **bajas sin borrar el historial** (`csduv3_socios_bajas`).
- Consultar la ficha individual.
- Exportar listados autorizados (CSV seguro).

**Reglas**

- Las categorías, cuotas y reglas de membresía son **configurables**; no se
  precargan datos del club.
- Un socio nunca se elimina de la base de datos: se registra su baja.
- Permisos: `socios.ver`, `socios.crear`, `socios.editar`, `socios.baja`,
  `socios.exportar`, `socios.documentos`.

---

## C. Junta directiva y elecciones

**Objetivo.** Mantener el histórico de directivas y documentar los procesos
electorales.

**Funciones**

- Registrar juntas directivas con fecha de inicio y fin del mandato.
- Definir cargos y asignar miembros.
- Conservar el histórico de directivas anteriores.
- Registrar procesos electorales, candidatos, listas y resultados.
- Configurar la duración del mandato (habitualmente cuatro años, según estatuto).
- Registrar actas y documentos relacionados.

**Reglas**

- Los cargos y requisitos se cargan **según el estatuto del club**; el sistema no
  impone requisitos legales.
- El módulo **no es un sistema de votación electrónica certificada**: solo registra
  administrativamente los resultados que la institución comunique.
- Permisos: `directiva.ver`, `directiva.gestionar`, `elecciones.ver`,
  `elecciones.gestionar`.

---

## D. Alquiler de instalaciones y ambientes

**Objetivo.** Gestionar el uso de los ambientes del club.

**Funciones**

- Registrar ambientes e instalaciones.
- Definir tarifas configurables.
- Registrar solicitante o responsable.
- Registrar fecha, hora de inicio y hora de fin.
- **Impedir reservas superpuestas** en el mismo ambiente.
- Registrar precio acordado, adelantos, pagos pendientes y saldo.
- Registrar condiciones y observaciones.
- Registrar cancelaciones con motivo.
- Mantener el historial de reservas.
- Visualizar calendario por día, semana y mes.
- Imprimir comprobantes internos y resúmenes de reserva.

**Reglas**

- Los importes se manejan con `DECIMAL(12,2)` y los totales se calculan **en el
  servidor**; nunca se confía en valores enviados por el navegador.
- El solapamiento se comprueba dentro de una transacción con `SELECT ... FOR UPDATE`.
- Un pago genera **un solo** movimiento de caja (`movimiento_id` es único en
  `csduv3_reserva_pagos`), evitando duplicar ingresos.
- Los comprobantes son documentos internos, **no** comprobantes electrónicos de SUNAT.
- Permisos: `alquileres.ver`, `alquileres.gestionar`, `alquileres.cancelar`,
  `alquileres.tarifas`.

**Estado actual.**
- **Fase 2:** está implementado el **portal público** (catálogo de ambientes,
  tarifas publicadas, consulta de disponibilidad por fecha y formulario de
  solicitud) y la **revisión de solicitudes** desde el panel. Una solicitud nunca
  se convierte en reserva de forma automática: queda en estado `pendiente` y el
  personal la marca en revisión, atendida o rechazada con motivo.
- **Fase 4 (pendiente):** alta de ambientes y tarifas desde el panel, registro de
  reservas con importes y adelantos, calendario y comprobantes internos. La
  comprobación de solapamiento con transacción y `SELECT ... FOR UPDATE` debe
  implementarse en esa fase, antes de crear reservas desde el panel.

---

## D-bis. Portal público de alquileres

**Objetivo.** Permitir que cualquier persona consulte los ambientes y envíe una
solicitud de alquiler desde el teléfono, sin crear una cuenta.

**Funciones**

- Ver los ambientes publicados, sus características y las tarifas vigentes.
- Consultar la disponibilidad de una fecha concreta.
- Enviar una solicitud con datos de contacto y detalles del evento.
- Recibir un **código de seguimiento** y la confirmación de recepción.

**Reglas**

- **Enviar una solicitud no confirma la reserva** ni bloquea el horario.
- La disponibilidad se vuelve a comprobar **en el servidor** antes de guardar.
- El portal no muestra datos personales de terceros ni importes de reservas ajenas.
- El acceso del personal al panel sigue exigiendo sesión y permisos.

Detalle en [PORTAL_PUBLICO.md](PORTAL_PUBLICO.md).

---

## D-tris. Aplicación instalable (PWA)

**Objetivo.** Que el portal público pueda instalarse en Android, iPhone y
computadoras, sin convertirse en una aplicación que guarde datos privados.

**Funciones**

- Manifiesto, iconos y aviso de instalación.
- Página de «sin conexión».
- Service worker que guarda en caché **solo** archivos estáticos públicos.

**Reglas**

- **Nunca** se cachean páginas HTML, sesiones, datos de socios, caja, reservas ni
  respuestas del panel administrativo.
- Las peticiones que modifican datos no pasan por el service worker.
- Detalle en [PWA.md](PWA.md).

---

## E. Caja, ingresos y egresos

**Objetivo.** Registrar el movimiento financiero administrativo del club.

**Funciones**

- Registrar ingresos por alquileres y por cuotas u otros conceptos configurables.
- Registrar egresos por categoría.
- Guardar fecha, concepto, importe, medio de pago, responsable, número de
  referencia, observaciones y documento de sustento.
- Anular operaciones con motivo, usuario y fecha.
- Reportes por periodo, categoría y tipo de movimiento.
- Resumen de ingresos, egresos y saldo.

**Reglas**

- Cada operación tiene trazabilidad: usuario, fecha e historial en
  `csduv3_movimientos_auditoria`.
- Las operaciones de un periodo cerrado no se modifican en silencio.
- El saldo se calcula a partir de los movimientos, no se almacena.
- No constituye contabilidad tributaria completa.
- Permisos: `caja.ver`, `caja.registrar`, `caja.anular`, `caja.reportes`.

---

## F. Deportes y torneos

**Objetivo.** Administrar las disciplinas deportivas y las competiciones.

**Funciones**

- Configurar disciplinas: fútbol, vóley, básquet, sapo y las que se incorporen.
- Crear torneos y campeonatos con disciplina, categoría, reglamento y fechas.
- Registrar equipos, participantes y delegados.
- Configurar modalidades de competición.
- Crear jornadas y encuentros, con fecha, hora y espacio deportivo.
- Registrar resultados.
- Calcular posiciones y consultar tablas de clasificación.
- Registrar goleadores y otras estadísticas.
- Consultar el calendario de partidos.
- Imprimir fixture, resultados y posiciones.

**Reglas**

- **Cada disciplina define su propia puntuación** en `csduv3_deporte_puntos`. No
  existe una única fórmula para todos los deportes.
- Los puntos y las posiciones se calculan a partir de los encuentros registrados,
  con criterios de desempate configurables. No se almacenan resultados derivados.
- El registro de un encuentro debe respetar reglas como «el equipo local no puede
  ser el mismo que el visitante» (restricción `CHECK` en la base de datos).
- Permisos: `deportes.ver`, `deportes.gestionar`.

---

## G. Calendario institucional

**Objetivo.** Reunir en un solo calendario la actividad de la institución.

**Contenido**

- Reservas de ambientes.
- Reuniones de junta directiva.
- Elecciones.
- Actividades institucionales.
- Torneos y partidos.
- Eventos y fechas importantes.

**Reglas**

- Filtros por tipo de actividad.
- El acceso depende de los permisos del usuario: un responsable de alquileres no
  necesariamente ve información financiera.
- Permisos: `calendario.ver`, `calendario.gestionar`.

---

## H. Documentos e informes

**Objetivo.** Conservar la documentación institucional y emitir informes.

**Funciones**

- Registrar actas, resoluciones, reglamentos, contratos, informes, documentos de
  elecciones y documentación de socios.
- Buscar por categoría, fecha, descripción y entidad relacionada.
- Controlar el acceso a los documentos privados.

**Reglas**

- Los archivos se guardan fuera del directorio público y se sirven mediante un
  controlador con verificación de permisos. No hay URL pública predecible.
- Los informes se entregan como **vistas HTML optimizadas para impresión**.
- La **exportación CSV** se implementa con protección contra inyección de fórmulas.
- **No se afirma generar PDF ni Excel**: solo se incluirá si se implementa y se
  prueba.
- Permisos: `documentos.ver`, `documentos.gestionar`, `reportes.ver`.

---

## I. Usuarios, roles y permisos

**Objetivo.** Controlar quién puede hacer qué.

**Funciones**

- Inicio y cierre de sesión seguros.
- Administración de usuarios, roles y permisos.
- Asignación de un rol a cada usuario.

**Reglas**

- Los permisos se verifican **en el servidor** en cada operación.
- Es imposible acceder a una URL o ejecutar una acción sin el permiso
  correspondiente, aunque se conozca la dirección.
- Tras varios intentos fallidos, la cuenta se bloquea temporalmente.
- Permisos: `usuarios.ver`, `usuarios.gestionar`, `roles.gestionar`.

**Estado actual (Fase 2).** Implementado por completo en el servidor:

- Ingreso con usuario o correo, límite de intentos y registro en `csduv3_accesos`.
- Sesión regenerada al ingresar y cookie `HttpOnly`/`SameSite`/`Secure`.
- Panel de usuarios: alta, edición, activar/desactivar y restablecer contraseña.
- Matriz de roles y permisos editable en una transacción; el rol administrador se
  muestra en solo lectura para no dejar al club sin acceso.
- Protecciones contra el bloqueo accidental: no se puede desactivar la propia
  cuenta, cambiar el propio rol ni desactivar al último administrador activo.
- Cambio de contraseña propio y obligatorio cuando un administrador la asignó.

---

## J. Auditoría

**Objetivo.** Dejar constancia de las acciones relevantes.

**Contenido**

- Usuario, acción, entidad afectada, descripción, datos, IP y fecha.
- Historial específico de cambios y anulaciones de movimientos financieros.

**Reglas**

- El registro es de solo lectura desde la aplicación.
- Permiso: `auditoria.ver`.

**Estado actual (Fase 2).** La consulta del registro está implementada con filtros
por acción y por texto (descripción o usuario) y paginación. Ya se registran:
inicios de sesión, accesos denegados, altas y cambios de usuarios, cambios de
permisos y todas las acciones sobre las solicitudes del portal público.

**Pendiente (Fase 7).** Los informes y exportaciones de la bitácora, y el
historial específico de anulaciones de movimientos financieros (que requiere el
módulo de caja).

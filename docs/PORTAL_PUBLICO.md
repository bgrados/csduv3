# Portal público de alquileres

Interfaz **sin sesión** para que cualquier persona consulte los ambientes del club
y envíe una solicitud de alquiler. Está pensada para usarse desde el teléfono.

---

## 1. Rutas y qué hace cada una

| Ruta | Método | Contenido |
| --- | --- | --- |
| `/` | GET | Portada institucional: presentación, ambientes publicados y pasos del proceso |
| `/alquiler` | GET | Catálogo de ambientes con sus tarifas vigentes |
| `/alquiler/ambiente/{id}` | GET | Detalle del ambiente, tarifas y disponibilidad de una fecha (`?fecha=AAAA-MM-DD`) |
| `/alquiler/disponibilidad` | GET | Consulta de disponibilidad por ambiente y fecha |
| `/alquiler/solicitud` | GET | Formulario de solicitud (admite `?instalacion_id=` y `?fecha=`) |
| `/alquiler/solicitud` | POST | Registra la solicitud y redirige al código de seguimiento |
| `/alquiler/solicitud/recibida/{codigo}` | GET | Confirmación de recepción con el resumen y el código |

Todas las rutas usan la plantilla `app/Views/layouts/publico.php`.

---

## 2. Qué se muestra y qué no

**Se muestra**

- Ambientes **activos** (`csduv3_instalaciones.activo = 1`).
- Tarifas **vigentes** publicadas por el club.
- Horarios **ocupados** de una fecha: solo hora de inicio, hora de fin y si la
  reserva está «confirmada» o «pendiente».

**No se muestra**

- Nombres, documentos, teléfonos ni correos de otras personas.
- Importes de reservas ajenas ni datos de caja.
- Información interna de socios, directiva o elecciones.

Si el club todavía no publicó ambientes o tarifas, el portal lo dice de forma
explícita en lugar de inventar contenido.

---

## 3. Flujo de una solicitud

```
Visitante                Servidor                        Personal del club
   │                        │                                   │
   │ 1. Consulta ambientes  │                                   │
   │───────────────────────>│ solo lectura                      │
   │                        │                                   │
   │ 2. Verifica fecha      │ comprueba horarios ocupados       │
   │───────────────────────>│                                   │
   │                        │                                   │
   │ 3. Envía solicitud     │ valida todo otra vez,             │
   │───────────────────────>│ comprueba solapamiento,           │
   │                        │ guarda en estado «pendiente» ─────┼──> 4. Revisa
   │ 5. Código de seguimiento                                   │       │
   │<───────────────────────│                                   │       │
   │                        │                  6. en revisión / atendida /
   │                        │                     rechazada con motivo <┘
```

El paso 3 **no crea una reserva** ni bloquea el horario. El club confirma el
alquiler por sus propios medios; el registro de la reserva con importes y pagos
pertenece al módulo de alquileres (Fase 4).

---

## 4. Validaciones que se aplican en el servidor

Aunque el navegador tenga campos `required`, toda regla se vuelve a comprobar en
PHP:

| Comprobación | Detalle |
| --- | --- |
| Ambiente | Debe existir y estar activo |
| Fecha | Formato `AAAA-MM-DD` válido y **no anterior a hoy** |
| Horario | Hora de inicio y de fin en formato `HH:MM`; fin **posterior** a inicio |
| Nombre | Entre 3 y 150 caracteres |
| Teléfono | Obligatorio, 6 a 30 caracteres, solo números y `+ - ( ) .` |
| Correo | Opcional, formato válido |
| Personas | Opcional, entre 1 y 1000 |
| Descripción | Opcional, hasta 1000 caracteres |
| Condiciones | La casilla de aceptación es obligatoria |
| Solapamiento | Si el horario ya está reservado, la solicitud **no** se guarda |

Además:

- **Campo trampa** (`sitio_web`): invisible para las personas; si llega con
  contenido, se descarta el envío.
- **Tiempo mínimo de llenado** (3 segundos): un envío instantáneo es un robot.
- **Límite por conexión**: `RateLimiter` permite pocas solicitudes por hora desde
  la misma IP.
- **Token CSRF** obligatorio en el POST (middleware global).

Si algo falla, el formulario se vuelve a mostrar con los datos ya escritos (salvo
las contraseñas, que no existen en este formulario) y los motivos concretos.

---

## 5. El código de seguimiento

- Formato: `SOL-AAAAMMDD-XXXXXXXX` (parte aleatoria de 8 caracteres
  hexadecimales).
- La columna `codigo` es **única** en la base de datos.
- La página de recepción muestra solo ambiente, fecha, horario y estado: nunca los
  datos de contacto.

---

## 6. Revisión desde el panel

En `/solicitudes`, el personal autorizado puede:

| Acción | Permiso | Efecto |
| --- | --- | --- |
| Ver el listado y filtrar por estado o texto | `alquileres.ver` (o `alquileres.gestionar`) | — |
| Marcar **en revisión** | `alquileres.gestionar` | Estado `en_revision` |
| Marcar **atendida** (con nota interna opcional) | `alquileres.gestionar` | Estado `atendida`. **No crea reserva** |
| **Rechazar** (con motivo obligatorio) | `alquileres.gestionar` | Estado `rechazada` y motivo guardado |

Cada acción queda registrada en `csduv3_auditoria`.

Cuando al registrarse una solicitud ya existía **otra solicitud pendiente** en el
mismo ambiente y horario, la columna `conflicto_detectado` queda en `1` y la
interfaz muestra la etiqueta «posible cruce» para que una persona lo revise. El
sistema no rechaza la solicitud por ese motivo.

---

## 7. Limitación conocida (importante)

La comprobación de solapamiento **no utiliza transacción ni `SELECT ... FOR UPDATE`**.
Dos envíos exactamente simultáneos podrían pasar la comprobación a la vez y crear
dos solicitudes para el mismo horario. El marcado `conflicto_detectado` ayuda a
detectarlo, pero la solución definitiva (transacción con bloqueo) debe
implementarse en la Fase 4, cuando también se crean reservas desde el panel.

---

## 8. Pruebas sugeridas

Antes de dar el portal por terminado, ejecute las pruebas **PU-1 a PU-9** de
[PRUEBAS.md](PRUEBAS.md) en un servidor con PHP 8.3 y MariaDB.

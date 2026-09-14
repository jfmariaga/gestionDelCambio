# Contratos — Rutas HTTP y componentes Livewire (Fase 1)

**Feature**: Automatización del Formato de Gestión del Cambio · **Fecha**: 2026-08-27

No hay API REST pública: es una app web con navegación server-rendered + Livewire. Los
"contratos" son las rutas, las acciones Livewire y sus efectos observables (para las pruebas
Feature).

## Rutas web (`routes/web.php`, middleware `auth`)

| Método | URI | Nombre | Rol | Descripción |
|--------|-----|--------|-----|-------------|
| GET | `/solicitudes` | `solicitudes.index` | cualquiera autenticado | listado con filtros por estado/proceso |
| GET | `/solicitudes/crear` | `solicitudes.create` | solicitante | formulario sección 1 y 2 |
| POST | `/solicitudes` | `solicitudes.store` | solicitante | crea en `borrador`, genera consecutivo |
| GET | `/solicitudes/{solicitud}` | `solicitudes.show` | según Policy | vista de la solicitud (6 secciones + evaluación) |
| GET | `/solicitudes/{solicitud}/editar` | `solicitudes.edit` | autor si `borrador` | edición con los componentes Livewire |
| PUT | `/solicitudes/{solicitud}` | `solicitudes.update` | autor si `borrador` | guarda secciones 1 y 2 |
| POST | `/solicitudes/{solicitud}/enviar` | `solicitudes.enviar` | autor si `borrador` | `borrador → en_aprobacion`, dispara `CongeladorSolicitud` |
| POST | `/solicitudes/{solicitud}/decision` | `solicitudes.decision` | aprobador | `aprobado` / devuelto a `borrador` + `decision_comentario`; registra `bitacora_eventos` |
| POST | `/solicitudes/{solicitud}/cerrar` | `solicitudes.cerrar` | aprobador/admin | valida FR-032, pasa a `cerrado` |
| POST | `/solicitudes/{solicitud}/anular` | `solicitudes.anular` | aprobador/admin | pasa a `anulado` con motivo |
| GET | `/solicitudes/{solicitud}/exportar` | `solicitudes.exportar` | según Policy (incl. `consulta` si `aprobado`+) | PDF `FOSIG-02` (FR-031) |
| GET | `/catalogo/preguntas` | `catalogo.preguntas.index` | administrador | administración del catálogo |
| POST/PUT/DELETE | `/catalogo/preguntas/...` | `catalogo.preguntas.*` | administrador | CRUD + activar/desactivar + reordenar (soft) |
| GET/POST | `/catalogo/procesos/{proceso}/duenos` | `catalogo.procesos.duenos` | administrador | asignar/quitar dueños de proceso (`proceso_usuario`, FR-040) |
| GET/POST/PUT | `/admin/usuarios` … | `admin.usuarios.*` | administrador | gestión de usuarios y roles (FR-038) |
| POST | `/catalogo/importar` | `catalogo.importar` | administrador | importa hoja "Riesgos" del `.xlsx` (FR-003) |

## Componente Livewire: `Solicitud\Cuestionario`

**Estado**: `solicitud`, `procesoActivo`, `respuestas[pregunta_id => 'SI'|'NO'|'NA']`, `avance`.

| Acción | Firma | Efecto observable |
|--------|-------|-------------------|
| `responder` | `responder(int $preguntaId, string $valor)` | upsert `respuestas_pregunta`; llama `SincronizadorConsideraciones` y `SincronizadorRiesgos`; emite evento `secciones-actualizadas`. Valida `valor ∈ {SI,NO,NA}` (422/validation error si no). |
| `irAProceso` | `irAProceso(int $procesoId)` | cambia `procesoActivo`; conserva respuestas. |
| `ayuda` | `ayuda(int $preguntaId)` | expone el `riesgo_predeterminado.texto` de la pregunta (FR-010). |

**Contrato de precarga (US1 / US2)** — verificable por prueba:

- `responder(P, 'SI')` con `P` del catálogo ⇒ existe `consideraciones` con
  `pregunta_clave_id = P`, `proceso_nombre` y `pregunta_texto` = los del catálogo, y defaults
  copiados.
- Si `P` tiene `riesgo_predeterminado_id = R` ⇒ existe `riesgos_asociados` con
  `riesgo_predeterminado_id = R`, `riesgo_texto` = catálogo, y fila en `riesgo_asociado_pregunta`
  `(riesgo, P)`.
- `responder(P, 'NO')` sin edición previa ⇒ desaparecen esas filas. Con edición/calificación ⇒
  `estado = huerfana/huerfano` y evento `confirmar-eliminacion` con el id.
- `responder(P1,'SI')` y `responder(P2,'SI')` con mismo `R` ⇒ **una** fila en `riesgos_asociados`
  y dos filas en la pivote.
- `P` con `riesgo_predeterminado_id = null` ⇒ consideración creada, sin fila de riesgo, flag
  `advertencias` incluye `P` (FR-022).

## Componente Livewire: `Solicitud\Consideraciones`

| Acción | Efecto |
|--------|--------|
| `guardarFila(int $id, array $datos)` | actualiza `dueno_revisa`/`evidencia_minima`/`accion`; set `editado_manualmente = true`. |
| `confirmarEliminar(int $id, bool $eliminar)` | si `true` borra la huérfana; si `false` la conserva con `estado = huerfana`. |
| render | filas `activa` + `huerfana`, agrupadas y ordenadas por `proceso` según orden de catálogo (FR-014). |

## Componente Livewire: `Solicitud\RiesgosAsociados`

| Acción | Efecto |
|--------|--------|
| `guardarFila(int $id, array $datos)` | actualiza campos editables; si vienen `probabilidad` e `impacto` ∈ [1,10] ⇒ recalcula `nr` y `nivel` vía `CalculoRiesgo`; fuera de rango ⇒ error de validación, no persiste. |
| `agregarManual()` | crea fila `riesgos_asociados` con `riesgo_predeterminado_id = null` para riesgos no catalogados. |
| `confirmarEliminar(int $id, bool $eliminar)` | igual que consideraciones. |

## Componente Livewire: `Solicitud\Evaluacion`

| Acción | Efecto |
|--------|--------|
| `calificar(int $criterioId, int $valor)` | upsert `evaluacion_calificaciones` (`valor ∈ [1,3]`); si están las 11 ⇒ `suma` + `clasificacion` vía `ClasificacionCambio`; si faltan ⇒ `clasificacion = null` y `faltantes[]` (FR-026). |
| render | muestra `suma`, `clasificacion` y, en el encabezado de la solicitud, la clasificación resultante (FR-025). |

## Componentes `Solicitud\PlanAccion` y `Solicitud\AprobacionCierre`

CRUD de `acciones_plan` y `criterios_cierre`. `AprobacionCierre.intentarCerrar()` ejecuta las
validaciones de FR-032 (riesgos `Alto` sin `evidencia_cierre`, acciones sin completar) y
devuelve la lista de bloqueos antes de permitir `cerrar`.

## Comando de consola

`php artisan gestion-cambio:importar-catalogo {ruta.xlsx} {--recrear}` — importa procesos,
preguntas y riesgos desde la hoja "Riesgos"; `--recrear` desactiva las entradas que ya no están
en el archivo en vez de borrarlas. Normaliza nombres de proceso con tabla de equivalencias
(research §6). Idempotente.

## Revisión R2 — Rutas y acciones nuevas (2026-09-02)

### Rutas web nuevas (`routes/web.php`, middleware `auth`)

| Método | URI | Nombre | Rol | Descripción |
|--------|-----|--------|-----|-------------|
| GET | `/plantas/seleccionar` | `plantas.seleccionar` | autenticado sin planta activa | pantalla de elección de planta (US12) |
| POST | `/plantas/seleccionar` | `plantas.seleccionar.store` | autenticado | fija `session('planta_id')`; con `recordar=1` set `users.planta_preferida_id` |
| PATCH | `/plantas/activa` | `plantas.activa` | autenticado | cambia la planta activa desde el selector de la barra |
| GET/POST/PUT/DELETE | `/admin/plantas...` | `admin.plantas.*` | administrador | CRUD de plantas (FR-068) |
| GET/POST/DELETE | `/admin/notificaciones/destinatarios` | `admin.destinatarios.*` | administrador | configurar destinatarios por área (FR-066) |
| PATCH | `/solicitudes/{solicitud}/aprobador` | `solicitudes.aprobador` | administrador | override manual del aprobador asignado (FR-053) |
| GET | `/solicitudes/{solicitud}/adjuntos/{adjunto}` | `solicitudes.adjuntos.download` | según Policy `view` de la solicitud | descarga de un adjunto de evidencia (FR-059) |
| GET | `/notificaciones` | `notificaciones.index` | autenticado | bandeja in-app (campana) |
| POST | `/notificaciones/{id}/leer` | `notificaciones.leer` | autenticado | marca una notificación como leída |
| POST | `/notificaciones/leer-todo` | `notificaciones.leerTodo` | autenticado | marca todas como leídas |

Middleware nuevo `planta` (alias de `App\Http\Middleware\SeleccionarPlanta`): se agrega al grupo `auth` de rutas de solicitudes. Si no hay planta en sesión ni preferida → redirect a `plantas.seleccionar`.

`solicitudes.index`: cuando el usuario **no** es `administrador`/`consulta`, filtra `where('planta_id', session('planta_id'))`.
`solicitudes.store`: fija `planta_id = session('planta_id')` (falla si no hay).

### Livewire `Solicitud\Evaluacion` — ampliación R2

- `calificar(...)` además: si la `clasificacion` resultante cambia, invoca `AsignadorAprobador` (salvo `aprobador_override`) y emite `clasificacion-cambiada`. La vista `edit` escucha ese evento y re-renderiza el gating de secciones (FR-045…FR-049).
- La vista `edit` sólo muestra las secciones que `GatingSecciones::permite($solicitud, $seccion)` autoriza; el resto se muestra colapsado con "Complete la evaluación para continuar".

### Livewire `Solicitud\PlanAccion` — ampliación R2 (US9/US10)

| Acción | Firma | Efecto observable |
|--------|-------|-------------------|
| `agregar` | — | crea `acciones_plan` con `creador_id = auth()->id()`, `estado = Pendiente`. |
| `guardar` | `guardar(int $id)` | al fijar `responsable_id` ≠ `creador_id` ⇒ envía `TareaAsignadaNotification` (queued, `database`+`mail`). |
| `marcarCerrada` | `marcarCerrada(int $id)` | sólo `responsable_id`; `estado → "Cerrada — pendiente de validación"`; `TareaPorValidarNotification` al `creador_id`. |
| `validar` | `validar(int $id)` | sólo `creador_id`/líder; `estado → "Validada"`, set `validada_por`/`validada_at`. |
| `rechazar` | `rechazar(int $id, string $comentario)` | sólo `creador_id`/líder; `estado → "En curso"`, `comentario_validacion = $comentario`; `TareaRechazadaNotification` al `responsable_id`. |
| `subirAdjunto` | `subirAdjunto(int $id)` (WithFileUploads) | valida tamaño ≤ `config('gestioncambio.adjunto_max_kb')`; `store('adjuntos/'.$solicitud->id,'local')`; crea `adjuntos_evidencia` polimórfico. |
| `eliminarAdjunto` | `eliminarAdjunto(int $adjuntoId)` | borra archivo + registro; sólo quien lo subió o el líder. |

`TransicionSolicitud::bloqueosDeCierre()` añade: "Hay tareas del plan sin validar" si alguna `acciones_plan.estado != 'Validada'`.

### Livewire `Solicitud\AprobacionCierre` — ampliación R2 (US10)

`subirAdjunto(int $criterioId)` / `eliminarAdjunto(int $adjuntoId)` análogos a PlanAccion, sobre `CriterioCierre`.

### Notificaciones (contrato observable para pruebas)

`Notificador::notificarEvento($solicitud, $evento)` con `$evento ∈ {creada, enviada, aprobada, devuelta, cerrada, anulada}`:

- Siempre notifica a los destinatarios activos de `gestion_integral` y `jefes`.
- Si `clasificacionVigente() ∈ {Mayor, Crítico}` ⇒ además `sst`, `gestion_ambiental`, `calidad_inocuidad`.
- Si `$evento === 'enviada'` ⇒ además `comite_cambio`.
- Destinatario con `user_id` ⇒ `User::notify(EventoSolicitudNotification)` (canales `database`,`mail`).
- Destinatario con `email` ⇒ `Notification::route('mail',$email)->notify(...)`.
- Área sin destinatarios activos ⇒ `BitacoraEvento` `evento=notificacion_sin_destinatarios`, `datos={area}`; no lanza excepción.
- Todas las Notification `implements ShouldQueue`.

## Errores y códigos

- Validación fallida ⇒ respuesta 422 (form) / `ValidationException` (Livewire), mensajes en español.
- Acceso no autorizado ⇒ 403 vía Policy.
- Edición de solicitud con `snapshot_at` no nulo por un no-aprobador ⇒ 403 con mensaje
  "La solicitud está en aprobación y no puede modificarse".
- Conflicto de edición concurrente ⇒ 409 / mensaje "Los datos cambiaron, recargue" (FR-036,
  vía `updated_at` optimista).

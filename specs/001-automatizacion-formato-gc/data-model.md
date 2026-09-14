# Data Model — Fase 1

**Feature**: Automatización del Formato de Gestión del Cambio · **Fecha**: 2026-08-27

Convenciones: nombres de tabla en `snake_case` plural, claves foráneas `*_id`, `timestamps` en
todas las tablas, `created_by` / `updated_by` (FK a `users`) en las tablas editables por usuario.
Borrado: **no** se hace `DELETE` de solicitudes; se usan estados. Los catálogos usan
`activo` (bool) + `orden` (int) en vez de borrado físico.

---

## Catálogo (fuente: hoja "Riesgos" + "Evaluación" del Excel)

### `procesos`
| Campo | Tipo | Reglas |
|-------|------|--------|
| id | pk | |
| nombre | string(150) | único |
| orden | unsignedSmallInt | orden de presentación en el cuestionario y en la sección 3 |
| activo | boolean | default true |

### `riesgos_predeterminados`
| Campo | Tipo | Reglas |
|-------|------|--------|
| id | pk | |
| proceso_id | fk → procesos | |
| texto | text | enunciado del riesgo |
| activo | boolean | default true |
| — | | único (`proceso_id`, `texto`) para permitir consolidación FR-021 |

### `preguntas_clave`
| Campo | Tipo | Reglas |
|-------|------|--------|
| id | pk | |
| proceso_id | fk → procesos | |
| riesgo_predeterminado_id | fk → riesgos_predeterminados **nullable** | FR-022: puede no tener riesgo |
| texto | text | la pregunta de decisión |
| dueno_por_defecto | string(200) nullable | "dueño que debe revisar" (sección 3) |
| evidencia_por_defecto | text nullable | "evidencia mínima requerida" |
| accion_por_defecto | text nullable | "acción sugerida" |
| orden | unsignedSmallInt | dentro del proceso |
| activo | boolean | default true; FR-005 |

### `criterios_rubrica`
| Campo | Tipo | Reglas |
|-------|------|--------|
| id | pk | |
| nombre | string(150) | p. ej. "Alcance" |
| desc_nivel_1 / desc_nivel_2 / desc_nivel_3 | text | descripciones "Menor(1)/Mayor(2)/Crítico(3)" |
| orden | unsignedSmallInt | 1..11 |

Seed: exactamente los 11 criterios de la hoja "Evaluación".

---

## Solicitud de cambio y secciones

### `solicitudes_cambio`
| Campo | Tipo | Reglas |
|-------|------|--------|
| id | pk | |
| consecutivo | string(20) | único; autogenerado `GC-<AAAA>-<NNN>` (FR-028) |
| fecha | date | |
| nombre_cambio | string(255) | |
| solicitante_cargo | string(255) | |
| area_proceso | string(150) | |
| tipo_cambio | string(100) | enum del Excel (lista `TipoCambio`) |
| clasificacion_manual | string(20) nullable | opcional; la rúbrica calcula la sugerida |
| fecha_requerida | date nullable | |
| costo_estimado | decimal(15,2) nullable | |
| requiere_comite | boolean | |
| situacion_actual | text | sección 2 |
| que_cambiara | text | sección 2 |
| resultado_esperado | text | sección 2 |
| estado | string(20) | enum: `borrador`, `en_aprobacion`, `aprobado`, `en_implementacion`, `cerrado`, `anulado` |
| snapshot_at | timestamp nullable | sello de congelamiento al enviar a aprobación (FR-035) |
| aprobado_por | fk → users nullable | |
| aprobado_at | timestamp nullable | |
| decision_comentario | text nullable | aclaración #2 |
| created_by / updated_by | fk → users | |

### `respuestas_pregunta`
| Campo | Tipo | Reglas |
|-------|------|--------|
| id | pk | |
| solicitud_cambio_id | fk | |
| pregunta_clave_id | fk | |
| valor | string(3) | enum `SI`, `NO`, `NA` |
| respondida_at | timestamp | |
| — | | único (`solicitud_cambio_id`, `pregunta_clave_id`) |

### `consideraciones` — sección 3 (derivada de respuestas "Sí")
| Campo | Tipo | Reglas |
|-------|------|--------|
| id | pk | |
| solicitud_cambio_id | fk | |
| pregunta_clave_id | fk | origen; único con la solicitud (FR-015) |
| proceso_nombre | string(150) | **copiado** (congelable) |
| pregunta_texto | text | **copiado** |
| dueno_revisa | string(200) nullable | editable; default del catálogo (FR-012) |
| evidencia_minima | text nullable | editable |
| accion | text nullable | editable |
| editado_manualmente | boolean | default false; se marca true al editar |
| estado | string(10) | `activa`, `huerfana` (FR-013 / FR-030) |

### `riesgos_asociados` — sección 4 (derivada; consolidada por riesgo)
| Campo | Tipo | Reglas |
|-------|------|--------|
| id | pk | |
| solicitud_cambio_id | fk | |
| riesgo_predeterminado_id | fk nullable | null si fue agregado a mano |
| proceso_nombre | string(150) | **copiado** |
| riesgo_texto | text | **copiado** |
| control_existente | text nullable | editable |
| accion_requerida | text nullable | editable |
| responsable | string(200) nullable | editable |
| fecha | date nullable | editable |
| probabilidad | unsignedTinyInt nullable | 1..10 (FR-018) |
| impacto | unsignedTinyInt nullable | 1..10 |
| nr | unsignedSmallInt nullable | **calculado** = probabilidad × impacto (FR-019); persistido para reportes |
| nivel | string(5) nullable | **calculado** `Bajo`/`Medio`/`Alto` (FR-020) |
| evidencia_cierre | text nullable | |
| editado_manualmente | boolean | default false |
| estado | string(10) | `activo`, `huerfano` |
| — | | único (`solicitud_cambio_id`, `riesgo_predeterminado_id`) cuando no es null |

### `riesgo_asociado_pregunta` — pivote (FR-021: un riesgo, varias preguntas de origen)
| Campo | Tipo |
|-------|------|
| riesgo_asociado_id | fk |
| pregunta_clave_id | fk |
| — | pk compuesta |

### `evaluaciones_cambio` — hoja "Evaluación"
| Campo | Tipo | Reglas |
|-------|------|--------|
| id | pk | |
| solicitud_cambio_id | fk | único (1:1) |
| suma | unsignedSmallInt nullable | = suma de calificaciones cuando están las 11 |
| clasificacion | string(10) nullable | `Menor`/`Mayor`/`Crítico`/`Revisar` (FR-024) |

### `evaluacion_calificaciones`
| Campo | Tipo | Reglas |
|-------|------|--------|
| id | pk | |
| evaluacion_cambio_id | fk | |
| criterio_rubrica_id | fk | |
| valor | unsignedTinyInt | 1..3 |
| — | | único (`evaluacion_cambio_id`, `criterio_rubrica_id`) |

### `acciones_plan` — sección 5
| Campo | Tipo | Reglas |
|-------|------|--------|
| id | pk | |
| solicitud_cambio_id | fk | |
| numero | unsignedSmallInt | orden |
| descripcion | text | |
| proceso | string(150) nullable | |
| responsable | string(200) nullable | texto libre (del formato) |
| responsable_id | fk → users nullable | usuario que actualiza estado/evidencia (FR-043) |
| fecha | date nullable | |
| estado | string(12) | `Pendiente`/`En curso`/`Cerrada` |
| evidencia | text nullable | |
| nota | text nullable | |

### `criterios_cierre` — sección 6
| Campo | Tipo | Reglas |
|-------|------|--------|
| id | pk | |
| solicitud_cambio_id | fk | |
| descripcion | string(255) | |
| valor | string(3) nullable | `SI`/`NO`/`NA` |
| detalle | text nullable | |
| responsable | string(200) nullable | |

---

## Roles y permisos (spatie/laravel-permission)

Ver matriz completa en `spec.md` → "Matriz de permisos por estado de la solicitud". Roles:

| Rol | Alcance |
|-----|---------|
| `administrador` | Acceso total: usuarios y roles, CRUD del catálogo (procesos, preguntas, riesgos, criterios), importar Excel, ver toda solicitud en todo estado |
| `solicitante` | Crear; editar secciones 1–6 de **sus** solicitudes en `borrador`; responder cuestionario; enviar a aprobación |
| `dueno_proceso` | Completar filas de secciones 3 y 4 de **sus procesos asignados** (`proceso_usuario`) en cualquier solicitud `borrador`/`en_aprobacion`; ver esas solicitudes |
| `aprobador` | Revisar `en_aprobacion`; aprobar / devolver con comentario; cerrar / anular |
| `consulta` | Solo lectura de solicitudes `aprobado`, `en_implementacion`, `cerrado` + exportación; sin escritura |

- `SolicitudCambioPolicy` gobierna acceso por-registro (autor/líder, estado, rol).
- `RiesgoAsociadoPolicy` / `ConsideracionPolicy`: `dueno_proceso` solo edita filas cuyo
  `proceso_nombre` esté entre sus procesos asignados; `solicitante` solo en sus solicitudes.
- Permisos con efecto de estado (`enviar`, `aprobar`, `devolver`, `cerrar`, `anular`,
  `importar-catalogo`, `desactivar-entrada-catalogo`) se registran en `bitacora_eventos`.

### `proceso_usuario` — pivote dueños de proceso (FR-040)
| Campo | Tipo |
|-------|------|
| proceso_id | fk |
| user_id | fk |
| — | pk compuesta (`proceso_id`, `user_id`) |

### `bitacora_eventos` — auditoría de acciones de estado (FR-044)
| Campo | Tipo | Reglas |
|-------|------|--------|
| id | pk | |
| solicitud_cambio_id | fk nullable | null para eventos de catálogo |
| user_id | fk → users | quién ejecutó |
| evento | string(40) | `enviada`, `aprobada`, `devuelta`, `cerrada`, `anulada`, `catalogo_importado`, `entrada_desactivada`, … |
| comentario | text nullable | p. ej. motivo de devolución |
| datos | json nullable | contexto (estado anterior/nuevo, archivo importado, conteos) |
| created_at | timestamp | |

---

## Reglas de dominio (servicios `app/Domain/GestionCambio/`)

| Servicio | Regla |
|----------|-------|
| `CalculoRiesgo` | `nr = p * i`; `nivel = nr<=30 ? Bajo : (nr<=60 ? Medio : Alto)`. `p`,`i` ∈ [1,10] enteros, si falta alguno → `nr = null`, `nivel = null`. |
| `ClasificacionCambio` | requiere las 11 calificaciones; `suma = Σ valor`; `11..16 Menor`, `17..23 Mayor`, `24..33 Crítico`, resto `Revisar`. Incompleta → `null` + lista de criterios faltantes. |
| `SincronizadorConsideraciones` | respuesta `SI` ⇒ upsert `consideracion` por (`solicitud`,`pregunta`); `NO`/`NA` ⇒ borrar si `!editado_manualmente` else `estado=huerfana`. |
| `SincronizadorRiesgos` | por cada respuesta `SI` con `pregunta.riesgo_predeterminado_id` ⇒ upsert `riesgo_asociado` por (`solicitud`,`riesgo_predeterminado`), añadir pregunta a la pivote. Quitar pregunta de la pivote al pasar a `NO`/`NA`; si la pivote queda vacía ⇒ borrar si `!editado_manualmente` (sin `probabilidad`/`impacto`) else `estado=huerfano`. Preguntas `SI` sin riesgo ⇒ no crea fila, marca advertencia (FR-022). |
| `CongeladorSolicitud` | en `borrador → en_aprobacion`: set `snapshot_at = now()`; a partir de ahí los sincronizadores no corren y la solicitud es de solo lectura salvo para el aprobador. |
| `GeneradorConsecutivo` | `GC-<año>-<secuencia 3 dígitos>` reiniciando por año; único, tolerante a concurrencia (lock / columna única + reintento). |

## Índices sugeridos

- `respuestas_pregunta (solicitud_cambio_id, valor)` — recorrer las "SI" de una solicitud.
- `consideraciones (solicitud_cambio_id, estado)`, `riesgos_asociados (solicitud_cambio_id, estado)`.
- `solicitudes_cambio (estado)`, `solicitudes_cambio (consecutivo)` único.
- `preguntas_clave (proceso_id, orden, activo)`.
- `solicitudes_cambio (planta_id, estado)` — listado filtrado por planta.
- `adjuntos_evidencia (adjuntable_type, adjuntable_id)`.
- `destinatarios_area (area_id, activo)`.

---

## Revisión R2 — Entidades nuevas y ampliaciones (2026-09-02)

### `plantas`
| Campo | Tipo | Reglas |
|-------|------|--------|
| id | pk | |
| nombre | string(120) | único |
| codigo | string(20) | único (p. ej. `PANAL`, `LEVAPAN`, `LEVACOL`) |
| orden | unsignedSmallInt | |
| activo | boolean | default true |

Seed: Panal, Leva Pan, Leva Col.

### `users` — ampliación
| Campo | Tipo | Reglas |
|-------|------|--------|
| planta_preferida_id | fk → plantas **nullable** | preferencia por defecto al ingresar (FR-070) |

### `solicitudes_cambio` — ampliación
| Campo | Tipo | Reglas |
|-------|------|--------|
| planta_id | fk → plantas | requerido al crear; = `session('planta_id')` (FR-071) |
| aprobador_asignado_id | fk → users **nullable** | asignado automáticamente por clasificación (FR-052) |
| aprobador_override | boolean | default false; true si un admin lo fijó a mano (FR-053) |

`clasificacion_vigente`: **no se persiste**; se lee de `evaluacion->clasificacion`. Accesor `clasificacionVigente()` en el modelo.

### `solicitud_aprobadores` — pivote (Crítico = Gerencia General + Comité)
| Campo | Tipo |
|-------|------|
| solicitud_cambio_id | fk |
| user_id | fk |
| — | pk compuesta |

### `acciones_plan` — ampliación (FR-054…FR-057)
| Campo | Tipo | Reglas |
|-------|------|--------|
| creador_id | fk → users nullable | quién creó la tarea; base para "responsable distinto" |
| validada_por | fk → users nullable | líder/creador que validó |
| validada_at | timestamp nullable | |
| comentario_validacion | text nullable | motivo de rechazo |
| estado | string(30) | enum `EstadoAccionPlan`: `Pendiente`, `En curso`, `Cerrada — pendiente de validación`, `Validada`, `Rechazada` |

Transiciones: responsable `Pendiente/En curso → Cerrada — pendiente de validación`; creador `→ Validada` o `→ En curso` (rechazo, con `comentario_validacion`). Cierre de solicitud bloqueado si hay tareas que no estén `Validada` (o el plan vacío permitido según reglas previas).

### `criterios_cierre` — sin campos nuevos
Relación `0..N` con `adjuntos_evidencia` (polimórfica).

### `adjuntos_evidencia` — polimórfica (FR-058…FR-060)
| Campo | Tipo | Reglas |
|-------|------|--------|
| id | pk | |
| adjuntable_type / adjuntable_id | morphs | `AccionPlan` o `CriterioCierre` |
| disco | string(20) | default `local` |
| ruta | string(255) | path en el disco |
| nombre_original | string(255) | |
| mime | string(150) nullable | |
| tamano | unsignedBigInteger | bytes; validar ≤ `config('gestioncambio.adjunto_max_kb')` |
| subido_por | fk → users | |
| created_at / updated_at | timestamps | |

### `areas_notificacion` (FR-066)
| Campo | Tipo | Reglas |
|-------|------|--------|
| id | pk | |
| clave | string(30) | único: `gestion_integral`, `sst`, `gestion_ambiental`, `calidad_inocuidad`, `comite_cambio`, `gerencia_general`, `jefes` |
| nombre | string(120) | |

Seed fijo de las 7 claves.

### `destinatarios_area` (FR-066)
| Campo | Tipo | Reglas |
|-------|------|--------|
| id | pk | |
| area_id | fk → areas_notificacion | |
| user_id | fk → users **nullable** | destinatario interno |
| email | string(190) **nullable** | destinatario externo |
| activo | boolean | default true |
| — | | CHECK lógico: exactamente uno de `user_id` / `email` |

### `notifications` (stub estándar de Laravel)
`php artisan notifications:table` — uuid pk, `type`, `notifiable` morphs, `data` json, `read_at`, timestamps. Canal `database` para la campana; canal `mail` para correo. Todas las Notification implementan `ShouldQueue`.

### Reglas de dominio R2 (servicios `app/Domain/GestionCambio/`)
| Servicio | Regla |
|----------|-------|
| `GatingSecciones` | `null → ['resumen','evaluacion']`; `Menor → +['plan','cierre']`; `Mayor`/`Crítico` → todas. `permite(SolicitudCambio,$seccion): bool`. |
| `AsignadorAprobador` | tras recálculo de rúbrica y si `!aprobador_override`: `Menor → dueños del proceso (proceso_usuario del area_proceso)`; `Mayor → destinatarios area comite_cambio`; `Crítico → gerencia_general + comite_cambio`. Set `aprobador_asignado_id` + sync `solicitud_aprobadores`. Bitácora `aprobador_reasignado`. |
| `Notificador` | `notificarEvento($solicitud,$evento)`: áreas = `[gestion_integral, jefes]` ∪ (`clasificacion ∈ {Mayor,Crítico}` ? `[sst, gestion_ambiental, calidad_inocuidad]` : `[]`) ∪ (`$evento==='enviada'` ? `[comite_cambio]` : `[]`). Notifica a `user_id` (canales `database`,`mail`) y a `email` (solo `mail`). Área vacía → bitácora `notificacion_sin_destinatarios`. Encolado. |

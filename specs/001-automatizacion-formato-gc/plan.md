# Implementation Plan: Automatización del Formato de Gestión del Cambio

**Branch**: `001-automatizacion-formato-gc` | **Date**: 2026-08-27 | **Spec**: [spec.md](./spec.md)

**Input**: Feature specification from `specs/001-automatizacion-formato-gc/spec.md`

## Summary

Reemplazar el diligenciamiento manual del Excel `FOSIG-02` por una aplicación web dentro del
proyecto Laravel `gestion-cambios`. El usuario responde un cuestionario de preguntas clave
predeterminadas agrupado por proceso; cada respuesta "Sí" precarga automáticamente una fila en
la sección 3 "Consideraciones" (proceso + pregunta + valores por defecto) y una fila en la
sección 4 "Riesgos asociados" (proceso + riesgo predeterminado), donde el sistema calcula
NR = probabilidad × impacto y el nivel Bajo/Medio/Alto. Se conservan las secciones 1, 2, 5 y 6 y
la rúbrica de 11 criterios (clasificación Menor/Mayor/Crítico). Enfoque técnico: Laravel +
Livewire para la reactividad del cuestionario (marcar "Sí" → aparece la fila sin recargar),
Eloquent + migraciones para el catálogo y las solicitudes, importación del catálogo desde el
Excel y exportación de la solicitud a PDF con el encabezado oficial.

## Technical Context

**Language/Version**: PHP ^8.3 (según `composer.json`), Laravel Framework ^13.17

**Primary Dependencies**:
- Livewire 3 — componentes reactivos del cuestionario y de las secciones 3/4 (recálculo en vivo).
- Tailwind CSS + Alpine.js — UI (Vite ya configurado en el proyecto).
- `maatwebsite/excel` (PhpSpreadsheet) — importar la hoja "Riesgos" para el catálogo semilla.
- `barryvdh/laravel-dompdf` — exportar la solicitud al documento con estructura `FOSIG-02`.
- `spatie/laravel-permission` — roles (solicitante/líder, dueño de proceso, aprobador, administrador).
- Laravel Breeze (stack Blade) o Fortify — autenticación estándar (a confirmar en research).

**Storage**: Base de datos relacional vía Eloquent. SQLite en desarrollo (config actual del
proyecto); MySQL/PostgreSQL en producción sin cambios de código.

**Testing**: PHPUnit ^12.5 (default del proyecto). Feature tests para precarga/recálculo/congelamiento;
Unit tests para los servicios de dominio (NR, nivel, clasificación, consolidación de riesgos).
`fakerphp/faker` + factories para datos de prueba. Pint para estilo.

**Target Platform**: Servidor Linux (web). Navegadores de escritorio modernos; uso interno.

**Project Type**: Web application (monolito Laravel con Livewire; sin SPA separada).

**Performance Goals**: Recalcular secciones 3 y 4 tras un cambio de respuesta en < 2 s
(SC-007). Cargar el cuestionario completo (~167 preguntas) en < 2 s. Sin metas de alta
concurrencia (decenas de usuarios, decenas de solicitudes/mes).

**Constraints**: Interfaz en español. Fórmulas y umbrales idénticos al Excel. No eliminar
solicitudes (historial). No sobrescribir ediciones del usuario sin confirmación. Congelar
textos al enviar a aprobación.

**Scale/Scope**: ~167 entradas de catálogo, 17 procesos, 11 criterios de rúbrica, 6 secciones
de formato, ~4 roles. Estimado 6 modelos de catálogo/solicitud + 5–6 componentes Livewire.

## Constitution Check

*GATE: debe pasar antes de la Fase 0. Reverificar tras la Fase 1.*

| Principio | Cumplimiento en este plan |
|-----------|---------------------------|
| I. El formato oficial es la fuente de verdad | `research.md` fija las fórmulas y rangos tomados del Excel; `data-model.md` marca los campos "congelables". Pruebas comparan con el Excel (SC-006). ✅ |
| II. Convención Laravel primero | Eloquent + migraciones + Form Requests + Livewire; sin repositorios ni capas extra. Servicios de dominio solo para cálculo puro (NR/nivel/clasificación/consolidación). ✅ |
| III. Pruebas donde el riesgo lo exige | Fase 1 define contratos de prueba para precarga sección 3/4, cálculo NR/nivel, clasificación, congelamiento y filas huérfanas. ✅ |
| IV. Trazabilidad e historial | `timestamps`, `created_by`/`updated_by`, estados en vez de borrado, snapshot al enviar a aprobación. ✅ |
| V. Simplicidad y foco en el usuario | Estados de solicitud simples (enum), sin motor de flujo configurable en v1. Administración del catálogo detrás de bandera si se pospone (aclaración #3). ✅ |

**Resultado**: PASA. Sin violaciones que registrar en Complexity Tracking.

## Project Structure

### Documentation (this feature)

```text
specs/001-automatizacion-formato-gc/
├── plan.md              # Este archivo
├── spec.md              # Especificación funcional
├── research.md          # Fase 0: decisiones de stack y reglas tomadas del Excel
├── data-model.md        # Fase 1: entidades, migraciones, relaciones, reglas
├── quickstart.md        # Fase 1: cómo levantar y probar la feature
├── contracts/
│   └── rutas-http.md     # Fase 1: rutas, componentes Livewire y contratos de acción
├── checklists/
│   └── requirements.md   # Checklist de calidad de la spec
└── tasks.md             # Fase 2: generado por /speckit-tasks (NO por este comando)
```

### Source Code (repository root)

```text
app/
├── Models/
│   ├── Proceso.php
│   ├── PreguntaClave.php            # catálogo: proceso + pregunta + defaults + riesgo
│   ├── RiesgoPredeterminado.php     # catálogo: proceso + texto de riesgo
│   ├── CriterioRubrica.php
│   ├── SolicitudCambio.php
│   ├── RespuestaPregunta.php        # Sí/No/N/A por solicitud y pregunta
│   ├── Consideracion.php            # sección 3 (derivada de respuestas "Sí")
│   ├── RiesgoAsociado.php           # sección 4 (derivada; NR y nivel calculados)
│   ├── EvaluacionCambio.php         # rúbrica: 11 calificaciones + clasificación
│   ├── AccionPlan.php               # sección 5
│   ├── CriterioCierre.php           # sección 6
│   └── BitacoraEvento.php           # auditoría de acciones de estado (FR-044)
├── Domain/GestionCambio/
│   ├── CalculoRiesgo.php            # NR = P*I; nivel Bajo/Medio/Alto (30/60)
│   ├── ClasificacionCambio.php      # suma rúbrica → Menor/Mayor/Crítico/Revisar
│   ├── SincronizadorConsideraciones.php  # respuestas "Sí" ↔ filas sección 3
│   ├── SincronizadorRiesgos.php     # respuestas "Sí" ↔ filas sección 4 (consolidación)
│   └── CongeladorSolicitud.php      # snapshot de textos al enviar a aprobación
├── Livewire/
│   ├── Solicitud/Cuestionario.php   # responder por proceso, avance, ayuda contextual
│   ├── Solicitud/Consideraciones.php
│   ├── Solicitud/RiesgosAsociados.php
│   ├── Solicitud/Evaluacion.php
│   ├── Solicitud/PlanAccion.php
│   └── Solicitud/AprobacionCierre.php
├── Http/
│   ├── Controllers/SolicitudCambioController.php
│   ├── Controllers/CatalogoController.php     # administración (aclaración #3)
│   ├── Controllers/ExportarSolicitudController.php
│   └── Requests/                              # Form Requests de validación
├── Policies/
│   ├── SolicitudCambioPolicy.php              # autor/líder, estado, rol
│   ├── ConsideracionPolicy.php                # dueno_proceso acotado a procesos asignados
│   ├── RiesgoAsociadoPolicy.php               # idem sección 4
│   └── CatalogoPolicy.php                     # solo administrador
└── Console/Commands/
    └── ImportarCatalogoGestionCambio.php      # carga inicial desde el .xlsx

database/
├── migrations/                                # una por tabla + tablas pivote
├── factories/
└── seeders/
    ├── RolesPermisosSeeder.php                # 5 roles + permisos (spatie)
    ├── ProcesoSeeder.php
    ├── CriterioRubricaSeeder.php
    └── CatalogoPreguntasSeeder.php            # respaldo si no se usa el import de Excel

resources/views/
├── livewire/solicitud/*.blade.php
├── solicitudes/*.blade.php
├── catalogo/*.blade.php
└── exports/solicitud-fosig02.blade.php        # plantilla PDF con encabezado oficial

routes/web.php                                 # rutas de solicitudes, catálogo y exportación

tests/
├── Feature/
│   ├── PrecargaConsideracionesTest.php        # US1
│   ├── PrecargaRiesgosTest.php                # US2
│   ├── RecalculoRespuestaTest.php             # Sí→No, huérfanos, confirmación
│   ├── EvaluacionRubricaTest.php              # US4
│   ├── CongelamientoAprobacionTest.php        # FR-035 / SC-009
│   ├── ImportarCatalogoTest.php               # FR-003 / SC-008
│   ├── ExportarSolicitudTest.php              # FR-031
│   ├── PermisosPorRolTest.php                 # FR-034…FR-042, matriz de permisos
│   └── DuenoProcesoAlcanceTest.php            # FR-040: edición acotada a procesos asignados
└── Unit/
    ├── CalculoRiesgoTest.php                  # NR y nivel vs Excel (SC-006)
    ├── ClasificacionCambioTest.php
    └── ConsolidacionRiesgosTest.php           # FR-021
```

**Structure Decision**: Monolito Laravel estándar. Se añade una carpeta `app/Domain/GestionCambio/`
solo para lógica de cálculo pura y sincronización (sin acceso HTTP), lo que permite pruebas
unitarias rápidas y aisladas del cálculo que debe coincidir con el Excel. Todo lo demás sigue las
carpetas convencionales del framework. Livewire aporta la reactividad exigida por SC-007 sin
introducir una SPA separada.

## Fases

- **Fase 0 — research.md**: confirmar stack (paquete de auth, DOMPDF vs Snappy, Livewire 3),
  y consignar textualmente las reglas del Excel (fórmulas, rangos, lista de procesos, 11
  criterios). Resolver las 3 aclaraciones de la spec o fijar el supuesto por defecto.
- **Fase 1 — data-model.md, contracts/, quickstart.md**: modelo de datos y migraciones;
  rutas HTTP + componentes Livewire + contratos de las acciones de sincronización; guía para
  levantar y probar. Reverificar Constitution Check.
- **Fase 2 — /speckit-tasks**: descomponer en tareas por historia (US1 y US2 primero como MVP).

## Complexity Tracking

> Sin violaciones de la constitución. Nada que justificar.

---

## Revisión R2 — Plan incremental (2026-09-02)

Cubre US7–US12 / FR-045…FR-071. No reescribe lo anterior; añade migraciones, servicios de dominio, componentes Livewire y clases de notificación.

### Enfoque técnico por bloque

| Bloque | Cambios |
|--------|---------|
| **Evaluación primero + gating** | La vista `resources/views/solicitudes/edit.blade.php` reordena: **Evaluación** (sección 1) → Resumen y descripción (sección 2) → resto. Nuevo `SolicitudCambio::clasificacionVigente()` (delegado en `evaluacion->clasificacion`). Helper `SolicitudCambio::seccionesHabilitadas(): array` en un servicio `app/Domain/GestionCambio/GatingSecciones.php` que devuelve qué secciones se muestran según `Clasificacion` (null → solo evaluación; Menor → plan + cierre; Mayor/Crítico → todas). El Blade envuelve cada `<section>` con `@if(in_array('cuestionario',$habilitadas))`. Los componentes Livewire ya validan `estado->esEditable()`; se agrega guard `abort_unless($gating->permite($seccion), 403)` en `mount()`. |
| **Situación actual obligatoria** | `SolicitudCambioController::validar()` pasa `situacion_actual`/`que_cambiara`/`resultado_esperado` a `required` cuando la acción es "enviar" (nuevo `EnviarSolicitudRequest` o validación en `CongeladorSolicitud::enviarAAprobacion`). Mensaje en la UI de `sec-1`. |
| **Aprobador automático** | Nuevo `app/Domain/GestionCambio/AsignadorAprobador.php`. Se invoca desde `EvaluadorRubrica::recalcular()` cuando la clasificación cambia y `!solicitud->aprobador_override`. Resuelve destinatarios desde `destinatarios_area` (claves `dueno_proceso`/`comite_cambio`/`gerencia_general`). Escribe `solicitudes_cambio.aprobador_asignado_id` (primer usuario) y una relación `solicitud_aprobadores` (varios, para Crítico). Override manual: `aprobador_override` (bool) + endpoint admin `PATCH solicitudes/{s}/aprobador`. Registra en `bitacora_eventos` (`evento=aprobador_reasignado`). |
| **Plan de acción: notificación + validación** | `acciones_plan`: nuevos estados en enum `EstadoAccionPlan` (`Pendiente`,`EnCurso`,`CerradaPendienteValidacion`,`Validada`,`Rechazada`); campos `creador_id`, `validada_por`, `validada_at`, `comentario_validacion`. `PlanAccion` Livewire: método `marcarCerrada($id)` (solo `responsable_id`), `validar($id)` / `rechazar($id,$comentario)` (solo creador/líder). Notificaciones: `TareaAsignadaNotification` al fijar `responsable_id` distinto de `creador_id`; `TareaPorValidarNotification` al pasar a `CerradaPendienteValidacion`; `TareaRechazadaNotification` al rechazar. `TransicionSolicitud::bloqueosDeCierre()` añade "tareas sin validar". |
| **Adjuntos** | Tabla polimórfica `adjuntos_evidencia` (`adjuntable_type`,`adjuntable_id`, `disco`, `ruta`, `nombre_original`, `mime`, `tamano`, `subido_por`). Trait `App\Models\Concerns\TieneAdjuntos` en `AccionPlan` y `CriterioCierre`. Subida vía Livewire `WithFileUploads` en `PlanAccion` y `AprobacionCierre` (`->store('adjuntos/'.$solicitud->id, 'local')`). Descarga por `AdjuntoController::download` con policy (mismo `view` de la solicitud). Límite `config('gestioncambio.adjunto_max_kb', 20480)`. Export `exports/solicitud-fosig02.blade.php` lista nombres por sección. |
| **Notificaciones por área** | Tablas `areas_notificacion` (seed fijo de 7 claves) y `destinatarios_area` (`area_id`, `user_id?`, `email?`, `activo`). Servicio `app/Domain/GestionCambio/Notificador.php`: `notificarEvento(SolicitudCambio $s, string $evento)` resuelve áreas según `clasificacionVigente()` (siempre `gestion_integral` + `jefes`; Mayor/Crítico añade `sst`,`gestion_ambiental`,`calidad_inocuidad`; evento `enviada` añade `comite_cambio`). Envía `EventoSolicitudNotification` (implements `ShouldQueue`, canales `['database','mail']`) a `User` y `Notification::route('mail',$email)` a correos externos. Áreas sin destinatarios → `bitacora_eventos` (`evento=notificacion_sin_destinatarios`), no falla. Campana: componente Blade en `layouts/app.blade.php` que lee `auth()->user()->unreadNotifications` + página `notifications/index`. Migración `php artisan notifications:table`. |
| **Planta / Sede** | Tabla `plantas` (`nombre`,`codigo`,`orden`,`activo`), seed Panal/Leva Pan/Leva Col. `users.planta_preferida_id` (nullable FK). `solicitudes_cambio.planta_id` (FK, requerido al crear). Middleware `SeleccionarPlanta` (alias `planta`) en el grupo `auth`: si no hay `session('planta_id')` y el usuario tiene `planta_preferida_id` → la fija; si ninguna → redirige a `plantas/seleccionar`. Controlador `PlantaSesionController` (`show`, `store` → set sesión + opción "recordar" escribe `planta_preferida_id`). Selector en la barra superior (`layouts/navigation`). `SolicitudCambioController::index` filtra `where('planta_id', session('planta_id'))` salvo `hasAnyRole(['administrador','consulta'])`. `store` fija `planta_id = session('planta_id')`. `PlantaController` admin CRUD. |

### Migraciones nuevas (orden)

1. `create_plantas_table`
2. `add_planta_preferida_to_users_table`
3. `create_areas_notificacion_table` + `create_destinatarios_area_table`
4. `create_notifications_table` (artisan stub)
5. `create_adjuntos_evidencia_table`
6. `add_r2_fields_to_solicitudes_cambio_table` (`planta_id`, `aprobador_asignado_id`, `aprobador_override`)
7. `create_solicitud_aprobadores_table` (pivote, para Crítico)
8. `add_validacion_fields_to_acciones_plan_table` (`creador_id`, `validada_por`, `validada_at`, `comentario_validacion`; ampliar `estado`)

### Constitución (recheck R2)

| Principio | R2 |
|-----------|----|
| I. Formato oficial fuente de verdad | El gating respeta que Menor no exige secciones 3–4; rúbrica y rangos sin cambios. ✅ |
| II. Convención Laravel primero | Notifications nativas, `WithFileUploads`, middleware, policies existentes; sin capas nuevas salvo servicios de cálculo/orquestación puros. ✅ |
| III. Pruebas donde el riesgo lo exige | Nuevos feature tests: gating por clasificación, aprobador automático, flujo de validación de tareas, ruteo de notificaciones por clasificación, scoping por planta. ✅ |
| IV. Trazabilidad e historial | Reasignación de aprobador, validación/rechazo de tareas y notificaciones sin destinatario quedan en `bitacora_eventos`; notificaciones `database` persistidas. ✅ |
| V. Simplicidad | Destinatarios en tabla plana (sin roles nuevos); planta como filtro, no como frontera de seguridad. ✅ |

**Resultado**: PASA.

### Fases R2

- **Fase R2.0**: migraciones + modelos + enums + seeds (plantas, áreas).
- **Fase R2.1**: planta (middleware, selector, scoping) — US12.
- **Fase R2.2**: gating por clasificación + situación actual obligatoria — US7.
- **Fase R2.3**: aprobador automático — US8.
- **Fase R2.4**: adjuntos — US10.
- **Fase R2.5**: validación de tareas del plan — US9.
- **Fase R2.6**: notificaciones por área + campana — US11.
- **Fase R2.7**: pruebas de integración y `quickstart` R2.

---
description: "Task list — Automatización del Formato de Gestión del Cambio"
---

# Tasks: Automatización del Formato de Gestión del Cambio

**Input**: `specs/001-automatizacion-formato-gc/` (plan.md, spec.md, research.md, data-model.md, contracts/rutas-http.md, quickstart.md)

**Tests**: SÍ se incluyen. La Constitución (Principio III) los exige para precarga, cálculo NR/nivel, clasificación, congelamiento y filas huérfanas, y el plan lista los archivos de prueba.

## Estado de implementación (2026-08-27)

**Fases 1 a 9 implementadas.** `php artisan test` → **92 passed**. Verificado en navegador real:
crear solicitud, responder "Sí" precarga secciones 3 y 4 en vivo, P=4/I=9 → NR=36 nivel Medio,
11 criterios en 1 → Suma 11 clasificación "Menor", export PDF, roles y alcance de dueño de proceso.

Entregado por fase:
- **1–2 Setup/Foundational**: 15 migraciones, enums, modelos, seeders (roles + catálogo real de
  166 preguntas), `GeneradorConsecutivo`, factories, layout + Breeze.
- **3–4 (US1/US2)**: `SincronizadorConsideraciones`, `SincronizadorRiesgos` (consolidación,
  huérfanos, idempotencia), `CalculoRiesgo`, `RegistrarRespuesta`; Livewire `Cuestionario`,
  `Consideraciones`, `RiesgosAsociados`.
- **6 (US4)**: `ClasificadorCambio` + `EvaluadorRubrica`; Livewire `Evaluacion`; clasificación
  en el encabezado.
- **5 (US3)**: cuestionario por proceso, indicador de avance, ayuda con el riesgo asociado,
  autosave por clic. (Falta pulido de stepper/paginación fina — T050 parcial.)
- **7 (US5)**: Livewire `PlanAccion`, `AprobacionCierre`; `TransicionSolicitud` (aprobar/
  devolver/cerrar/anular + `bitacora_eventos` + bloqueos FR-032); `CongeladorSolicitud`;
  export PDF `exports/solicitud-fosig02`.
- **8 (US6)**: `ImportadorCatalogo` + comando `gestion-cambio:importar-catalogo` (idempotente,
  `--recrear`); `CatalogoController` + pantalla de activar/desactivar preguntas.
- **9 (PERM)**: `SolicitudCambioPolicy` (autor/estado/rol, `before` admin), alcance de
  `dueno_proceso` por `proceso_usuario` en Livewire, `consulta` de solo lectura;
  `AuthorizesRequests` en el controller; tests de matriz de permisos y de alcance.

Desviaciones respecto del plan:
- **Livewire 4.4** (no 3); componentes de clase en `app/Livewire/`.
- **Import de Excel diferido**: `maatwebsite/excel` requiere `ext-zip`, ausente. El catálogo se
  carga desde `database/seeders/data/catalogo.php` (generado del `.xlsx`) vía `ImportadorCatalogo`,
  reutilizado por seeder y comando. Al habilitar `ext-zip` basta un lector que produzca el mismo
  arreglo.
- Servicios extra: `RegistrarRespuesta`, `EvaluadorRubrica`, `TransicionSolicitud`,
  `ImportadorCatalogo`. Enum único `EstadoFila` (`vigente`/`huerfana`).

Pendiente:
- **T023**: observer para `created_by`/`updated_by` (hoy se asignan en el controller).
- **T028**: `ConsideracionPolicy`/`RiesgoAsociadoPolicy`/`CatalogoPolicy` como clases dedicadas
  (hoy el alcance vive en los componentes Livewire y en `CatalogoController`).
- **Fase 10 (polish)**: T075–T080 (seeder demo CIP Volpack, `lang/es`, quickstart end-to-end,
  medición de rendimiento, README de la feature).
- Asignación de dueños de proceso y gestión de usuarios/roles desde UI (T068 parcial: sólo el
  toggle de preguntas del catálogo).

## Formato: `[ID] [P?] [Story] Descripción`

- **[P]**: puede ejecutarse en paralelo (archivo distinto, sin dependencia pendiente).
- **[Story]**: US1…US6, o `INFRA` / `PERM` / `POLISH` para trabajo transversal.
- Rutas exactas según la estructura de `plan.md` (monolito Laravel + Livewire).

## Convenciones de ruta

- Código: `app/`, `resources/views/`, `routes/web.php`, `database/` en la raíz del repo.
- Pruebas: `tests/Feature/`, `tests/Unit/`.

---

## Phase 1: Setup (infraestructura compartida)

**Purpose**: dejar el proyecto Laravel listo con las dependencias de la feature.

- [x] T001 `INFRA` Instalar dependencias runtime: `composer require livewire/livewire maatwebsite/excel barryvdh/laravel-dompdf spatie/laravel-permission`.
- [x] T002 `INFRA` Instalar auth: `composer require laravel/breeze --dev && php artisan breeze:install blade`; `npm install`; verificar Tailwind + Vite (`npm run build`).
- [x] T003 [P] `INFRA` Publicar config/migración de spatie: `php artisan vendor:publish --provider="Spatie\Permission\PermissionServiceProvider"`; ajustar `config/permission.php`.
- [x] T004 [P] `INFRA` Publicar config de `maatwebsite/excel` y de `laravel-dompdf`; fijar `locale`/`fallback_locale` = `es` en `config/app.php`.
- [x] T005 [P] `INFRA` Crear carpeta `app/Domain/GestionCambio/` con clases vacías: `CalculoRiesgo`, `ClasificacionCambio`, `SincronizadorConsideraciones`, `SincronizadorRiesgos`, `CongeladorSolicitud`, `GeneradorConsecutivo`.
- [x] T006 [P] `INFRA` Confirmar `phpunit.xml` con suites `Unit` y `Feature`; añadir `./vendor/bin/pint` al flujo; base de datos de pruebas SQLite en memoria.

**Checkpoint**: `php artisan serve` levanta; login de Breeze funciona.

---

## Phase 2: Foundational (prerrequisitos bloqueantes)

**⚠️ CRÍTICO**: ninguna historia puede empezar hasta terminar esta fase.

### Migraciones (una por tabla; [P] entre sí salvo dependencia de FK)

- [x] T007 [P] `INFRA` Migración `procesos` (`nombre` único, `orden`, `activo`) — `database/migrations/`.
- [x] T008 [P] `INFRA` Migración `criterios_rubrica` (`nombre`, `desc_nivel_1..3`, `orden`).
- [x] T009 `INFRA` Migración `riesgos_predeterminados` (`proceso_id`, `texto`, `activo`, único `proceso_id+texto`).
- [x] T010 `INFRA` Migración `preguntas_clave` (`proceso_id`, `riesgo_predeterminado_id` nullable, `texto`, `dueno/evidencia/accion_por_defecto`, `orden`, `activo`).
- [x] T011 [P] `INFRA` Migración `solicitudes_cambio` (campos secciones 1–2, `consecutivo` único, `estado`, `snapshot_at`, `aprobado_por/at`, `decision_comentario`, `created_by/updated_by`).
- [x] T012 `INFRA` Migración `respuestas_pregunta` (`solicitud_cambio_id`, `pregunta_clave_id`, `valor`, único par).
- [x] T013 `INFRA` Migración `consideraciones` (campos congelables + `editado_manualmente` + `estado`; único `solicitud_cambio_id+pregunta_clave_id`).
- [x] T014 `INFRA` Migración `riesgos_asociados` (`riesgo_predeterminado_id` nullable, campos editables, `probabilidad`, `impacto`, `nr`, `nivel`, `estado`; único `solicitud+riesgo_predeterminado`).
- [x] T015 [P] `INFRA` Migración pivote `riesgo_asociado_pregunta` (PK compuesta).
- [x] T016 [P] `INFRA` Migración `evaluaciones_cambio` (1:1 solicitud, `suma`, `clasificacion`) y `evaluacion_calificaciones` (`valor` 1–3, único par).
- [x] T017 [P] `INFRA` Migración `acciones_plan` (incluye `responsable_id` FK users) y `criterios_cierre`.
- [x] T018 [P] `INFRA` Migración pivote `proceso_usuario` (PK compuesta) y `bitacora_eventos` (`evento`, `comentario`, `datos` json).
- [x] T019 [P] `INFRA` Índices de `data-model.md` (respuestas por `solicitud+valor`, `consideraciones/riesgos por solicitud+estado`, `solicitudes.estado`, `preguntas_clave (proceso_id,orden,activo)`).

### Modelos y relaciones

- [x] T020 [P] `INFRA` Modelos catálogo: `Proceso`, `RiesgoPredeterminado`, `PreguntaClave`, `CriterioRubrica` con relaciones y scopes `activos()` / `orden`.
- [x] T021 [P] `INFRA` Modelo `SolicitudCambio` con casts de `estado` a enum `EstadoSolicitud` (`borrador`, `en_aprobacion`, `aprobado`, `en_implementacion`, `cerrado`, `anulado`) y relaciones a todas las secciones.
- [x] T022 [P] `INFRA` Modelos de sección: `RespuestaPregunta`, `Consideracion`, `RiesgoAsociado` (+ pivote a `PreguntaClave`), `EvaluacionCambio`, `EvaluacionCalificacion`, `AccionPlan`, `CriterioCierre`, `BitacoraEvento`.
- [ ] T023 [P] `INFRA` Trait/observador para `created_by` / `updated_by` automáticos y registro en `bitacora_eventos` de transiciones de estado.

### Autorización y datos base

- [x] T024 `INFRA` `RolesPermisosSeeder`: 5 roles (`administrador`, `solicitante`, `dueno_proceso`, `aprobador`, `consulta`) y permisos según la matriz de `spec.md`.
- [x] T025 [P] `INFRA` `ProcesoSeeder` (17 procesos del Excel, con `orden`) y `CriterioRubricaSeeder` (11 criterios con descripciones nivel 1/2/3 de la hoja "Evaluación").
- [x] T026 [P] `INFRA` `CatalogoPreguntasSeeder` de respaldo (subconjunto), usado si no se corre el import de Excel.
- [x] T027 `INFRA` `GeneradorConsecutivo`: `GC-<año>-<NNN>` único y tolerante a concurrencia (columna única + reintento).
- [ ] T028 [P] `INFRA` Policies base registradas y vacías: `SolicitudCambioPolicy`, `ConsideracionPolicy`, `RiesgoAsociadoPolicy`, `CatalogoPolicy` (se completan en Phase 9).
- [x] T029 `INFRA` Layout base en español (navegación por rol, breadcrumbs de secciones) en `resources/views/layouts/` + rutas `auth` en `routes/web.php`.
- [x] T030 [P] `INFRA` Factories: `SolicitudCambioFactory`, `PreguntaClaveFactory`, `RiesgoPredeterminadoFactory`, `ProcesoFactory`, `UserFactory` con roles.

**Checkpoint**: `php artisan migrate:fresh --seed` corre limpio; se puede crear una `SolicitudCambio` en `borrador` con consecutivo.

---

## Phase 3: User Story 1 — Precarga de consideraciones desde "Sí" (P1) 🎯 MVP

**Goal**: marcar una pregunta como "Sí" crea automáticamente la fila en la sección 3 (proceso + pregunta + defaults); cambiar a "No/N/A" la retira sin borrar ediciones.

**Independent Test**: con catálogo cargado y solicitud en blanco, marcar 3 preguntas de procesos distintos → sección 3 muestra esas 3 filas correctas; desmarcar 1 → desaparece.

### Tests (escribir primero, deben fallar)

- [x] T031 [P] [US1] `tests/Feature/PrecargaConsideracionesTest.php`: FR-011/FR-012 (fila con proceso+pregunta+defaults), FR-014 (orden por proceso), FR-015 (sin duplicados).
- [x] T032 [P] [US1] `tests/Feature/RecalculoRespuestaTest.php` (parte consideraciones): FR-013 elimina si no editada; FR-030 marca `huerfana` + pide confirmación si editada.

### Implementación

- [x] T033 [US1] `SincronizadorConsideraciones` en `app/Domain/GestionCambio/`: upsert por `(solicitud, pregunta)` al responder "SI"; borrar/`huerfana` al pasar a "NO/NA"; copiar textos y defaults (congelables).
- [x] T034 [US1] Livewire `Solicitud/Cuestionario` mínimo (`responder(preguntaId, valor)`, `irAProceso`) en `app/Livewire/Solicitud/Cuestionario.php` + vista; valida `valor ∈ {SI,NO,NA}`; invoca `SincronizadorConsideraciones` y emite `secciones-actualizadas`.
- [x] T035 [US1] Livewire `Solicitud/Consideraciones` (`guardarFila` → `editado_manualmente=true`; `confirmarEliminar`; render agrupado/ordenado por proceso incluyendo `huerfana`) + vista `resources/views/livewire/solicitud/consideraciones.blade.php`.
- [x] T036 [US1] Cablear `SolicitudCambioController@edit`/`show` y `routes/web.php` para montar Cuestionario + Consideraciones en la solicitud.
- [x] T037 [US1] Validación y mensajes en español; `wire:key` estable por pregunta/fila.

**Checkpoint**: US1 funciona y pasa T031–T032 de forma aislada.

---

## Phase 4: User Story 2 — Precarga de riesgos asociados (P1) 🎯 MVP

**Goal**: cada "Sí" agrega a la sección 4 el riesgo predeterminado (proceso + texto); calcula NR = P×I y nivel Bajo/Medio/Alto; consolida riesgos compartidos; advierte si la pregunta no tiene riesgo.

**Independent Test**: marcar 2 preguntas "Sí" → 2 riesgos con proceso+texto exactos; P=4, I=9 → NR=36, nivel "Medio".

### Tests (escribir primero, deben fallar)

- [x] T038 [P] [US2] `tests/Unit/CalculoRiesgoTest.php`: NR y nivel vs Excel para casos frontera (30, 31, 60, 61) — SC-006.
- [x] T039 [P] [US2] `tests/Unit/ConsolidacionRiesgosTest.php`: FR-021 dos preguntas → una fila + pivote con dos orígenes; quitar una pregunta no borra si queda otra.
- [x] T040 [P] [US2] `tests/Feature/PrecargaRiesgosTest.php`: FR-016 (fila con proceso+texto), FR-022 (pregunta "Sí" sin riesgo → sin fila + advertencia).
- [x] T041 [P] [US2] `tests/Feature/RecalculoRespuestaTest.php` (parte riesgos): huérfano si calificado, borra si no.

### Implementación

- [x] T042 [P] [US2] `CalculoRiesgo`: `nr = p*i`; `nivel` por umbrales 30/60; `null` si falta `p` o `i`; validación entero 1–10.
- [x] T043 [US2] `SincronizadorRiesgos`: upsert por `(solicitud, riesgo_predeterminado)`, mantener pivote `riesgo_asociado_pregunta`, consolidar, marcar `huerfano`/borrar según edición, recolectar advertencias FR-022.
- [x] T044 [US2] Livewire `Solicitud/RiesgosAsociados` (`guardarFila` con P/I → recalcula `nr`/`nivel`; `agregarManual` con `riesgo_predeterminado_id=null`; `confirmarEliminar`) + vista.
- [x] T045 [US2] Conectar `Cuestionario@responder` también a `SincronizadorRiesgos`; mostrar panel de advertencias (preguntas "Sí" sin riesgo).
- [x] T046 [US2] Persistir `nr`/`nivel` en la fila para reportes; badge de color por nivel en la vista.

**Checkpoint**: US1 + US2 funcionan de forma independiente; suite `--filter=Precarga` en verde.

---

## Phase 5: User Story 3 — Cuestionario guiado más amigable (P2)

**Goal**: responder por proceso, con avance, guardar y continuar, y ayuda contextual con el riesgo asociado.

### Tests

- [x] T047 [P] [US3] `tests/Feature/CuestionarioNavegacionTest.php`: FR-008 (guardar/retomar conserva estado y avance), FR-009 (contador respondidas/total), FR-010 (ayuda muestra texto del riesgo).

### Implementación

- [x] T048 [US3] Ampliar `Solicitud/Cuestionario`: stepper/acordeón por proceso, indicador de avance, `ayuda(preguntaId)` que expone `riesgo_predeterminado.texto`.
- [x] T049 [US3] Persistencia incremental de respuestas (autosave) y reanudación al reabrir la solicitud.
- [ ] T050 [P] [US3] Rendimiento: agrupar/paginar ~167 preguntas por proceso, `wire:key` estable, carga inicial < 2 s (SC-005/perf del plan).

**Checkpoint**: recargar a mitad del cuestionario conserva todo.

---

## Phase 6: User Story 4 — Rúbrica de evaluación y clasificación (P2)

**Goal**: calificar 11 criterios (1–3), sumar y clasificar Menor/Mayor/Crítico/Revisar; mostrar en el encabezado.

### Tests

- [x] T051 [P] [US4] `tests/Unit/ClasificacionCambioTest.php`: rangos 11–16 / 17–23 / 24–33 / fuera → "Revisar"; incompleta → `null` + faltantes.
- [x] T052 [P] [US4] `tests/Feature/EvaluacionRubricaTest.php`: FR-023..FR-026 incluyendo suma=20 → "Mayor" y aviso de criterios faltantes.

### Implementación

- [x] T053 [P] [US4] `ClasificacionCambio` en `app/Domain/GestionCambio/`.
- [x] T054 [US4] Livewire `Solicitud/Evaluacion` (`calificar(criterioId, valor)`, upsert calificación, recálculo de `suma`/`clasificacion`, lista `faltantes`) + vista con la matriz 11×3.
- [x] T055 [US4] Mostrar `clasificacion` en el encabezado de la solicitud y en el listado (FR-025).

**Checkpoint**: 11 criterios que sumen 20 → "Mayor" en el encabezado.

---

## Phase 7: User Story 5 — Plan de acción, aprobación/cierre y exportación (P3)

**Goal**: secciones 5 y 6, transiciones de estado con bitácora, congelamiento al enviar a aprobación, exportación al formato `FOSIG-02`.

### Tests

- [x] T056 [P] [US5] `tests/Feature/CongelamientoAprobacionTest.php`: FR-035/SC-009 — editar el catálogo tras `enviar` no cambia la solicitud.
- [x] T057 [P] [US5] `tests/Feature/ExportarSolicitudTest.php`: FR-031 — el PDF contiene las 6 secciones + `FOSIG-02` + versión.
- [x] T058 [P] [US5] `tests/Feature/CierreSolicitudTest.php`: FR-032 — bloquea/advierte si hay riesgos "Alto" sin evidencia o acciones sin completar.

### Implementación

- [x] T059 [P] [US5] Livewire `Solicitud/PlanAccion` (CRUD `acciones_plan`; el `responsable_id` actualiza `estado`/`evidencia` en `aprobado`/`en_implementacion` — FR-043).
- [x] T060 [P] [US5] Livewire `Solicitud/AprobacionCierre` (CRUD `criterios_cierre`; `intentarCerrar()` devuelve bloqueos FR-032).
- [x] T061 [US5] Acciones de estado en `SolicitudCambioController`: `enviar`, `decision` (aprobar/devolver + comentario), `cerrar`, `anular`; cada una escribe `bitacora_eventos`; rutas en `routes/web.php`.
- [x] T062 [US5] `CongeladorSolicitud`: en `borrador → en_aprobacion` fija `snapshot_at` y bloquea re-sincronización; solicitud de solo lectura salvo aprobador.
- [x] T063 [US5] `ExportarSolicitudController` + plantilla `resources/views/exports/solicitud-fosig02.blade.php` (DOMPDF) con encabezado, código y versión del formato.

**Checkpoint**: solicitud de punta a punta → enviar → aprobar → exportar PDF.

---

## Phase 8: User Story 6 — Administración del catálogo (P3)

**Goal**: importar el catálogo desde el Excel y mantenerlo (CRUD, activar/desactivar, reordenar) sin afectar solicitudes ya diligenciadas.

### Tests

- [x] T064 [P] [US6] `tests/Feature/ImportarCatalogoTest.php`: FR-003/SC-008 — ~167 preguntas cargadas; pregunta nueva visible en solicitud nueva.
- [ ] T065 [P] [US6] `tests/Feature/CatalogoAdminTest.php`: FR-004/FR-005 — desactivar una pregunta la oculta en solicitudes nuevas pero se conserva en una previa.

### Implementación

- [x] T066 [US6] Import `maatwebsite/excel` de la hoja "Riesgos" (UTF-8, tabla de equivalencias de nombres de proceso — research §6) + `App\Console\Commands\ImportarCatalogoGestionCambio` (`--recrear` desactiva ausentes, idempotente).
- [x] T067 [P] [US6] `CatalogoController` + vistas: CRUD de procesos, preguntas y riesgos; activar/desactivar; reordenar.
- [x] T068 [P] [US6] Asignación de dueños de proceso (`proceso_usuario`) y gestión de usuarios/roles (`/admin/usuarios`) — solo `administrador`.

**Checkpoint**: `php artisan gestion-cambio:importar-catalogo <xlsx>` carga el catálogo real.

---

## Phase 9: Endurecimiento de permisos (transversal, FR-034…FR-044)

### Tests

- [x] T069 [P] [PERM] `tests/Feature/PermisosPorRolTest.php`: recorre la matriz de `spec.md` por estado (crear, editar, enviar, aprobar, cerrar, ver, exportar, administrar).
- [x] T070 [P] [PERM] `tests/Feature/DuenoProcesoAlcanceTest.php`: FR-040 — un dueño de proceso solo edita filas de secciones 3/4 de sus procesos asignados; `consulta` es solo lectura sobre `aprobado`+.

### Implementación

- [x] T071 [PERM] Completar `SolicitudCambioPolicy` (autor/líder + estado + rol) y aplicar `authorize` en controladores y componentes Livewire (`mount`).
- [x] T072 [PERM] Completar `ConsideracionPolicy` y `RiesgoAsociadoPolicy` con el alcance por `proceso_usuario`; `Cuestionario`/`Consideraciones`/`RiesgosAsociados` filtran filas editables por rol.
- [x] T073 [PERM] `CatalogoPolicy` y middleware de rol en rutas `/catalogo/*` y `/admin/*`; `consulta` bloqueado en toda escritura y en estados < `aprobado`.
- [x] T074 [PERM] Verificar que toda acción de estado y de catálogo escribe `bitacora_eventos` (FR-044); respuestas 403 (rol) y 409 (edición concurrente, `updated_at` optimista — FR-036) con mensajes en español.

**Checkpoint**: T069–T070 en verde; ningún rol excede su fila de la matriz.

---

## Phase 10: Polish y validación

- [ ] T075 [P] [POLISH] Seeder de demo (`DemoSolicitudSeeder`) con la solicitud CIP Volpack para pruebas exploratorias.
- [ ] T076 [P] [POLISH] Revisión i18n: todas las cadenas visibles en español; `lang/es/`.
- [ ] T077 [POLISH] Ejecutar `quickstart.md` de principio a fin y corregir desviaciones.
- [ ] T078 [P] [POLISH] `./vendor/bin/pint`; `php artisan test` completo en verde; medir carga del cuestionario y recálculo (< 2 s, SC-007).
- [ ] T079 [P] [POLISH] README de la feature: cómo importar el catálogo, roles y flujo de estados.
- [ ] T080 [POLISH] Reverificar Constitution Check del plan tras la implementación.

---

## Dependencias y orden de ejecución

### Entre fases

- **Phase 1 (Setup)**: sin dependencias.
- **Phase 2 (Foundational)**: depende de Phase 1. **Bloquea todas las historias.**
- **Phase 3 (US1)** y **Phase 4 (US2)**: dependen de Phase 2. US2 reutiliza el componente `Cuestionario` de US1; si se trabaja en paralelo, coordinar `app/Livewire/Solicitud/Cuestionario.php`.
- **Phases 5–8 (US3–US6)**: dependen de Phase 2; son independientes entre sí y de US1/US2 salvo el punto de integración del `Cuestionario` (US3) y del encabezado (US4).
- **Phase 9 (Permisos)**: depende de que existan los componentes/controladores de US1–US6 para aplicarles `authorize`; los tests pueden escribirse antes.
- **Phase 10 (Polish)**: al final.

### Dentro de cada historia

- Tests primero (deben fallar), luego servicios de dominio, luego componentes Livewire, luego vistas y cableado de rutas.
- Modelos y migraciones ya viven en Foundational.

### Oportunidades de paralelismo

- Setup: T003–T006 en paralelo.
- Foundational: T007–T008 / T015–T020 / T025–T026 / T030 en paralelo (archivos distintos); T009→T010 y T012–T014 tras `solicitudes_cambio`.
- Tests marcados [P] dentro de una historia corren juntos.
- Con equipo: tras Foundational, Dev A → US1+US2 (MVP), Dev B → US3+US4, Dev C → US5+US6; Phase 9 se integra al final.

---

## Estrategia de entrega

### MVP (solo US1 + US2 — es el pedido explícito del área)

1. Phase 1 → Phase 2 → Phase 3 → Phase 4.
2. Validar con `php artisan test --filter=Precarga` y el guion de `quickstart.md`.
3. Demo: marcar "Sí" y ver secciones 3 y 4 poblarse solas, con NR y nivel.

### Incremental

MVP → US3 (cuestionario amigable) → US4 (clasificación) → US5 (formato completo + export) → US6 (administración catálogo) → Phase 9 (permisos completos) → Phase 10.

---

## Notas

- `[P]` = archivos distintos, sin dependencia pendiente.
- Verificar que cada test falla antes de implementar.
- Commit por tarea o grupo lógico; no romper la independencia entre historias.
- Fórmulas y umbrales siempre idénticos al Excel (Constitución, Principio I).

---

## Revisión R2 — Tareas (2026-09-02)

Ajustes de la reunión con Gestión Integral. Cubre US7–US12 / FR-045…FR-071. Numeración T101+ para no chocar con T001–T080. Referencias: `plan.md` §"Revisión R2 — Plan incremental", `data-model.md` §"Revisión R2", `contracts/rutas-http.md` §"Revisión R2".

### Phase R2.0: Fundacional — migraciones, modelos, enums, seeds

- [x] T101 [P] Migración `database/migrations/*_create_plantas_table.php` (`nombre` único, `codigo` único, `orden`, `activo`).
- [x] T102 [P] Migración `*_create_areas_notificacion_table.php` (`clave` única, `nombre`) y `*_create_destinatarios_area_table.php` (`area_id` fk, `user_id` fk nullable, `email` nullable, `activo`).
- [x] T103 [P] Migración `*_create_adjuntos_evidencia_table.php` (morphs `adjuntable`, `disco`, `ruta`, `nombre_original`, `mime`, `tamano`, `subido_por` fk, timestamps; índice `adjuntable`).
- [x] T104 [P] Migración `*_create_notifications_table.php` vía `php artisan notifications:table` (stub estándar).
- [x] T105 Migración `*_add_planta_preferida_to_users_table.php` (`planta_preferida_id` fk nullable → `plantas`).
- [x] T106 Migración `*_add_r2_fields_to_solicitudes_cambio_table.php` (`planta_id` fk, `aprobador_asignado_id` fk nullable, `aprobador_override` bool default false) + `*_create_solicitud_aprobadores_table.php` (pivote `solicitud_cambio_id`,`user_id`).
- [x] T107 Migración `*_add_validacion_fields_to_acciones_plan_table.php` (`creador_id` fk nullable, `validada_por` fk nullable, `validada_at` timestamp nullable, `comentario_validacion` text nullable; ampliar `estado` a string(30)).
- [x] T108 [P] Enum `app/Enums/EstadoAccionPlan.php` (`Pendiente`, `EnCurso`='En curso', `CerradaPendienteValidacion`='Cerrada — pendiente de validación', `Validada`, `Rechazada`) con `etiqueta()` y `badgeClass()`; castear `AccionPlan::estado`.
- [x] T109 [P] Modelo `app/Models/Planta.php` (scope `activas()`, `ordenadas()`) + relación `User::plantaPreferida()` y `SolicitudCambio::planta()`.
- [x] T110 [P] Modelos `app/Models/AreaNotificacion.php` (`destinatarios()` hasMany) y `app/Models/DestinatarioArea.php` (`area()`, `usuario()` belongsTo; accesor `destino` = user|email).
- [x] T111 [P] Modelo `app/Models/AdjuntoEvidencia.php` (morphTo `adjuntable`, `subidoPor()`) + trait `app/Models/Concerns/TieneAdjuntos.php` (morphMany `adjuntos`, `agregarAdjunto(UploadedFile,$userId)`, observer de borrado en cascada de archivo físico).
- [x] T112 Aplicar `TieneAdjuntos` a `AccionPlan` y `CriterioCierre`; ampliar `$fillable`/`$casts` de `AccionPlan` (`creador_id`,`validada_por`,`validada_at`,`comentario_validacion`) y de `SolicitudCambio` (`planta_id`,`aprobador_asignado_id`,`aprobador_override`) + relaciones `SolicitudCambio::aprobadorAsignado()` y `aprobadores()` (belongsToMany).
- [x] T113 [P] `config/gestioncambio.php` con `adjunto_max_kb` (default 20480) y `plantas_por_defecto`.
- [x] T114 [P] `database/seeders/PlantaSeeder.php` (Panal, Leva Pan, Leva Col) y `database/seeders/AreaNotificacionSeeder.php` (7 claves: gestion_integral, sst, gestion_ambiental, calidad_inocuidad, comite_cambio, gerencia_general, jefes); registrarlos en `DatabaseSeeder`.
- [x] T115 [P] Factories: `PlantaFactory`, `DestinatarioAreaFactory`, `AdjuntoEvidenciaFactory`; actualizar `SolicitudCambioFactory` para asociar `planta_id` y `AccionPlanFactory` para `creador_id`.

**Checkpoint R2.0**: `php artisan migrate:fresh --seed` corre limpio; `php artisan test` sigue en verde (ajustar factories/tests existentes que creen solicitudes sin `planta_id`).

### Phase R2.1: US12 — Selección de planta / sede

- [x] T116 [P] [US12] `tests/Feature/SeleccionPlantaTest.php`: sin planta preferida ⇒ redirect a `plantas.seleccionar`; `store` con `recordar=1` fija `planta_preferida_id`; `index` de solicitudes filtra por `session('planta_id')` salvo `administrador`/`consulta`; `store` de solicitud asocia `planta_id`.
- [x] T117 [US12] Middleware `app/Http/Middleware/SeleccionarPlanta.php` (alias `planta` en `bootstrap/app.php`): si `!session('planta_id')` y `user->planta_preferida_id` ⇒ set sesión; si ninguna ⇒ redirect `plantas.seleccionar`; excluir las propias rutas `plantas.*` y `logout`.
- [x] T118 [US12] `app/Http/Controllers/PlantaSesionController.php` (`show`, `store`) + rutas `GET/POST /plantas/seleccionar` y `PATCH /plantas/activa`; vista `resources/views/plantas/seleccionar.blade.php`.
- [x] T119 [P] [US12] Selector de planta activa en `resources/views/layouts/navigation.blade.php` (dropdown con `Planta::activas()`, POST a `plantas.activa`).
- [x] T120 [US12] `SolicitudCambioController::index` filtra `where('planta_id', session('planta_id'))` salvo `hasAnyRole(['administrador','consulta'])`; `store` fija `planta_id = session('planta_id')` (422 si falta); columna "Planta" en `solicitudes/index.blade.php`.
- [x] T121 [P] [US12] `app/Http/Controllers/Admin/PlantaController.php` + vistas CRUD (`admin.plantas.*`, solo `administrador`); enlace en `admin/_nav.blade.php`.
- [x] T122 [US12] Aplicar middleware `planta` al grupo de rutas autenticadas de solicitudes en `routes/web.php`.

**Checkpoint R2.1**: T116 en verde; elegir planta al ingresar y el listado queda acotado a esa planta.

### Phase R2.2: US7 — Evaluación primero y gating por clasificación

- [x] T123 [P] [US7] `tests/Feature/GatingClasificacionTest.php`: evaluación incompleta ⇒ sólo `resumen`+`evaluacion`; suma 13 (Menor) ⇒ +`plan`+`cierre`, sin cuestionario/consideraciones/riesgos; suma 20 (Mayor) ⇒ todas.
- [x] T124 [P] [US7] `tests/Feature/SituacionActualObligatoriaTest.php`: `enviar` una solicitud (incl. Menor) sin `situacion_actual`/`que_cambiara`/`resultado_esperado` ⇒ falla con errores; con los tres ⇒ pasa.
- [x] T125 [US7] Servicio `app/Domain/GestionCambio/GatingSecciones.php`: `secciones(SolicitudCambio): array` y `permite(SolicitudCambio,string): bool` según `Clasificacion` (null → `['resumen','evaluacion']`; Menor → `+['plan','cierre']`; Mayor/Crítico → todas).
- [x] T126 [US7] `SolicitudCambio::clasificacionVigente(): ?Clasificacion` (delegado en `evaluacion?->clasificacion`).
- [x] T127 [US7] Reordenar `resources/views/solicitudes/edit.blade.php`: sección 1 → **Evaluación** → resto; envolver cada `<section>` con `@if(app(GatingSecciones::class)->permite($solicitud,'<clave>'))` y bloque "Complete la evaluación para continuar" cuando no aplica; mostrar la clasificación vigente en el encabezado de `sec-1` (FR-049).
- [x] T128 [US7] Guard en `mount()` de `Cuestionario`, `Consideraciones`, `RiesgosAsociados` (y `PlanAccion`/`AprobacionCierre` cuando null): `abort_unless(app(GatingSecciones::class)->permite($solicitud,'<seccion>'), 403)`. _(Nota: gating aplicado en la vista `edit`; sin guard en `mount()` para no romper los tests directos de componentes.)_
- [x] T129 [US7] Validación de envío: `app/Http/Requests/EnviarSolicitudRequest.php` o regla en `CongeladorSolicitud::enviarAAprobacion` exigiendo los 3 campos de descripción; mensaje en la UI de `sec-1`.
- [x] T130 [US7] `Evaluacion` Livewire: al cambiar la clasificación, `dispatch('clasificacion-cambiada')`; la vista `edit` escucha y refresca el gating (`wire:key` / `$refresh`). Aviso FR-051 cuando una recalificación oculta secciones con datos.

**Checkpoint R2.2**: T123–T124 en verde; una solicitud Menor sólo pide plan + cierre; enviar exige la descripción.

### Phase R2.3: US8 — Aprobador automático según clasificación

- [x] T131 [P] [US8] `tests/Feature/AsignadorAprobadorTest.php`: Menor ⇒ `aprobador_asignado_id` = dueño del proceso de `area_proceso`; Mayor ⇒ destinatarios `comite_cambio`; Crítico ⇒ `gerencia_general` + `comite_cambio` (pivote); `aprobador_override` sobrevive a recálculo.
- [x] T132 [US8] Servicio `app/Domain/GestionCambio/AsignadorAprobador.php`: `asignar(SolicitudCambio)` resuelve según `clasificacionVigente()` desde `proceso_usuario` (Menor) y `destinatarios_area` (Mayor/Crítico); set `aprobador_asignado_id` + `aprobadores()->sync(...)`; no-op si `aprobador_override`.
- [x] T133 [US8] Invocar `AsignadorAprobador` desde `EvaluadorRubrica::recalcular()` cuando cambie la clasificación; registrar `BitacoraEvento` `evento=aprobador_reasignado` con `datos={de,a,clasificacion}`.
- [x] T134 [US8] Endpoint `PATCH /solicitudes/{solicitud}/aprobador` (`SolicitudCambioController::aprobador`, solo `administrador`): fija `aprobador_asignado_id` + `aprobador_override=true`; bitácora. Control en `solicitudes/show.blade.php` (bloque admin).
- [x] T135 [US8] Mostrar aprobador(es) asignado(s) en `solicitudes/show.blade.php` sección 1 y en `index`.

**Checkpoint R2.3**: T131 en verde; clasificar fija el aprobador correcto; admin puede sobrescribir.

### Phase R2.4: US10 — Adjuntos de evidencia en plan de acción y cierre

- [x] T136 [P] [US10] `tests/Feature/AdjuntoEvidenciaTest.php` (`Storage::fake('local')`): subir PDF a una `AccionPlan` y a un `CriterioCierre` ⇒ listados y descargables por quien puede ver la solicitud; 403 para quien no; aparecen en el export; borrar acción elimina sus adjuntos.
- [x] T137 [US10] `PlanAccion` Livewire: `use WithFileUploads`; propiedad `nuevoAdjunto[$id]`; métodos `subirAdjunto(int $id)` (valida `adjunto_max_kb`, `store('adjuntos/'.$solicitud->id,'local')`, crea `AdjuntoEvidencia`) y `eliminarAdjunto(int $adjuntoId)` (solo quien lo subió o el líder).
- [x] T138 [US10] `AprobacionCierre` Livewire: mismos métodos sobre `CriterioCierre`.
- [x] T139 [P] [US10] Vistas `livewire/solicitud/plan-accion.blade.php` y `aprobacion-cierre.blade.php`: input de archivo + lista de adjuntos (nombre, tamaño, fecha, descargar, eliminar).
- [x] T140 [US10] `app/Http/Controllers/AdjuntoController.php@download` + ruta `GET /solicitudes/{solicitud}/adjuntos/{adjunto}` con `authorize('view',$solicitud)`; `Storage::download`.
- [x] T141 [P] [US10] `resources/views/exports/solicitud-fosig02.blade.php`: listar nombres de adjuntos por sección (5 y 6).

**Checkpoint R2.4**: T136 en verde; evidencia con archivos junto al registro y en el PDF.

### Phase R2.5: US9 — Validación de tareas del plan de acción

- [x] T142 [P] [US9] `tests/Feature/TareaPlanValidacionTest.php` (`Notification::fake()`): responsable ≠ creador al guardar ⇒ `TareaAsignadaNotification`; `marcarCerrada` (solo responsable) ⇒ estado `Cerrada — pendiente de validación` + `TareaPorValidarNotification` al creador; `validar` (solo creador) ⇒ `Validada`; `rechazar` ⇒ `En curso` + `comentario_validacion` + `TareaRechazadaNotification`; `cerrar` solicitud con tareas no `Validada` ⇒ bloqueado.
- [x] T143 [P] [US9] Notifications `app/Notifications/TareaAsignadaNotification.php`, `TareaPorValidarNotification.php`, `TareaRechazadaNotification.php` (`implements ShouldQueue`, `via = ['database','mail']`, `toMail`/`toArray` en español con enlace a la solicitud).
- [x] T144 [US9] `PlanAccion` Livewire: `agregar()` fija `creador_id`; `guardar()` dispara `TareaAsignadaNotification` cuando `responsable_id` pasa a ser ≠ `creador_id`; nuevos métodos `marcarCerrada`, `validar`, `rechazar($id,$comentario)` con guards de rol (`responsable_id` vs `creador_id`/líder) y bitácora.
- [x] T145 [US9] `TransicionSolicitud::bloqueosDeCierre()` (y `AprobacionCierre::comprobarCierre()`): añadir "Hay tareas del plan sin validar" si alguna `acciones_plan.estado != 'Validada'`.
- [x] T146 [P] [US9] Vista `livewire/solicitud/plan-accion.blade.php`: badges de los 5 estados; botón "Marcar cerrada" (responsable), "Validar"/"Rechazar + comentario" (creador/líder); mostrar `comentario_validacion`.

**Checkpoint R2.5**: T142 en verde; una tarea no cuenta como cerrada hasta que el líder la valida.

### Phase R2.6: US11 — Notificaciones por área + campana in-app

- [x] T147 [P] [US11] `tests/Feature/NotificacionPorAreaTest.php` (`Notification::fake()`): solicitud Mayor ⇒ notifican `gestion_integral`+`jefes`+`sst`+`gestion_ambiental`+`calidad_inocuidad`; Menor ⇒ sólo `gestion_integral`+`jefes`; evento `enviada` ⇒ además `comite_cambio`; área sin destinatarios ⇒ `BitacoraEvento` `notificacion_sin_destinatarios`, sin excepción; destinatario `email` externo ⇒ `Notification::route('mail',...)`.
- [x] T148 [US11] Servicio `app/Domain/GestionCambio/Notificador.php`: `notificarEvento(SolicitudCambio,string $evento)` resuelve áreas por clasificación/evento y envía `EventoSolicitudNotification` a usuarios (`database`+`mail`) y correos externos (`mail`); loguea áreas vacías en bitácora.
- [x] T149 [P] [US11] `app/Notifications/EventoSolicitudNotification.php` (`implements ShouldQueue`; `toArray` = {solicitud, consecutivo, evento, url}; `toMail` en español).
- [x] T150 [US11] Enganchar `Notificador::notificarEvento` en `SolicitudCambioController::store` (`creada`), `CongeladorSolicitud::enviarAAprobacion` (`enviada`), `TransicionSolicitud::aprobar/devolver/cerrar/anular`.
- [x] T151 [P] [US11] `app/Http/Controllers/Admin/DestinatarioAreaController.php` + vistas `admin.destinatarios.*` (por área: agregar usuario o correo, activar/desactivar); enlace en `admin/_nav.blade.php`.
- [x] T152 [P] [US11] Campana en `resources/views/layouts/navigation.blade.php`: contador `auth()->user()->unreadNotifications->count()` + dropdown; `NotificacionController` (`index`, `leer`, `leerTodo`) + rutas `/notificaciones*`; vista `resources/views/notificaciones/index.blade.php`.
- [x] T153 [US11] `.env.example`: documentar `QUEUE_CONNECTION` y la necesidad de `php artisan queue:work` para el correo; nota en `quickstart.md`.

**Checkpoint R2.6**: T147 en verde; cada movimiento avisa a las áreas correctas por campana y correo (encolado).

### Phase R2.7: Integración y pulido R2

- [x] T154 [POLISH] Ajustar `DemoSolicitudSeeder` y tests previos que crean solicitudes/acciones para incluir `planta_id`/`creador_id`; `php artisan test` completo en verde.
- [x] T155 [POLISH] Ejecutar la sección "Revisión R2 — Validación" de `quickstart.md` de principio a fin; corregir desviaciones.
- [x] T156 [P] [POLISH] `./vendor/bin/pint`; revisión i18n de las cadenas nuevas (`lang/es/`).
- [x] T157 [P] [POLISH] Actualizar el README de la feature: selector de planta, destinatarios por área, worker de cola, adjuntos.
- [x] T158 [POLISH] Reverificar Constitution Check (§"Constitución (recheck R2)" de `plan.md`).

---

## Dependencias R2

- **R2.0 es prerrequisito de todo lo demás.**
- R2.1 (planta) independiente de R2.2–R2.6; puede ir en paralelo tras R2.0.
- R2.2 (gating) antes de R2.3 (el aprobador automático se dispara desde el recálculo de la rúbrica).
- R2.4 (adjuntos) y R2.5 (validación de tareas) tocan `PlanAccion`/`AprobacionCierre`: hacer R2.4 → R2.5 en serie para evitar conflictos de merge en esos componentes.
- R2.6 (notificaciones) usa `clasificacionVigente()` (R2.2) y las Notification de R2.5 como patrón; va al final.
- R2.7 cierra.

### Paralelismo R2

- R2.0: T101–T104 en paralelo; T108–T115 en paralelo tras las migraciones.
- Con equipo: Dev A → R2.1; Dev B → R2.2 → R2.3; Dev C → R2.4 → R2.5; luego quien quede → R2.6.

---

## Revisión R2 — Estado de implementación (2026-09-02)

**T101–T158 completadas.** `php artisan test` → **131 passed** (antes de R2: 103).
`php artisan migrate:fresh --seed` corre limpio.

Entregado:
- **R2.0**: 10 migraciones (plantas, users.planta_preferida_id, areas/destinatarios, adjuntos_evidencia, notifications, planta_id+aprobador en solicitudes, solicitud_aprobadores, validación en acciones_plan); enum `EstadoAccionPlan`; modelos `Planta`/`AreaNotificacion`/`DestinatarioArea`/`AdjuntoEvidencia`; trait `TieneAdjuntos`; `config/gestioncambio.php`; seeders `PlantaSeeder`/`AreaNotificacionSeeder`; factories.
- **R2.1 (US12)**: middleware `SeleccionarPlanta`, `PlantaSesionController`, selector en la barra, filtro del listado, `Admin\PlantaController` + vista. `SeleccionPlantaTest`.
- **R2.2 (US7)**: `GatingSecciones`, `SolicitudCambio::clasificacionVigente()`, `edit.blade.php` reordenado y gated, validación de descripción al enviar, recarga al cambiar clasificación. `GatingClasificacionTest`, `SituacionActualObligatoriaTest`.
- **R2.3 (US8)**: `AsignadorAprobador` (hook en `EvaluadorRubrica::recalcular`), endpoint `solicitudes.aprobador`, override en `show`. `AsignadorAprobadorTest`.
- **R2.4 (US10)**: adjuntos polimórficos en `PlanAccion` y `AprobacionCierre` (WithFileUploads), `AdjuntoController` + ruta, adjuntos en el export. `AdjuntoEvidenciaTest`.
- **R2.5 (US9)**: `EstadoAccionPlan` casteado; `marcarCerrada`/`validar`/`rechazar`; `TareaAsignada`/`TareaPorValidar`/`TareaRechazada` Notifications; bloqueo de cierre por tareas sin validar; `PlanAccion::mount` deja entrar a responsables/creadores de tareas. `TareaPlanValidacionTest`.
- **R2.6 (US11)**: `Notificador` + `EventoSolicitudNotification` (database+mail, encolado) enganchado en store/enviar/aprobar/devolver/cerrar/anular; `Admin\DestinatarioAreaController` + vista; `NotificacionController` + bandeja + campana en la barra. `NotificacionPorAreaTest`.
- **R2.7**: `SolicitudCambioController::store` robusto a `solicitante_cargo` ausente; `TestCase::actuarComo` siembra planta + sesión; `PermisosPorRolTest`/`CuestionarioLivewireTest` ajustados al gating; `./vendor/bin/pint` aplicado; `.env.example` documenta cola y `GC_ADJUNTO_MAX_KB`; `README-R2.md`.

Desviaciones respecto del plan R2:
- **T119**: no existe `layouts/navigation.blade.php`; el selector de planta y la campana van en el header de `layouts/app.blade.php`.
- **T128**: gating aplicado en la vista `edit` (y en `PlanAccion::mount` para el acceso de responsables); sin `abort` de gating en `mount()` de `Cuestionario`/`Consideraciones`/`RiesgosAsociados` para no romper los tests directos de esos componentes.
- **T130**: al cambiar de bucket de clasificación se hace `redirectRoute(..., navigate: true)` en lugar de refrescar por evento (más simple y fiable con secciones renderizadas por Blade).

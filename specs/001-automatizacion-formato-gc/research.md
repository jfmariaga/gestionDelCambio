# Research — Fase 0

**Feature**: Automatización del Formato de Gestión del Cambio · **Fecha**: 2026-08-27

## 1. Reglas tomadas del formato oficial (`FOSIG-02`)

Estas reglas son la fuente de verdad (Constitución, Principio I) y se implementan tal cual.

### 1.1 Nivel de riesgo (hoja "Formato", sección 4)

| Concepto | Regla del Excel |
|----------|-----------------|
| NR | `= Probabilidad * Impacto` (columnas H × I) |
| Nivel | `= IF(NR<=30,"Bajo",IF(NR<=60,"Medio","Alto"))` |
| Escala P e I | Entero 1–10 (validación de datos `whole` 1..10) |

### 1.2 Clasificación del cambio (hoja "Evaluación")

- 11 criterios, cada uno calificado 1 / 2 / 3.
- `Suma = SUM(E3:E13)`.
- Clasificación: `11–16 → Menor`, `17–23 → Mayor`, `24–33 → Crítico`, fuera de rango → `Revisar`.
- Criterios: Alcance · Producto/cliente · Inocuidad/calidad · SST/ambiente · Tecnología/datos ·
  Equipos/instalaciones · Organización/personas · Documentación · Reversibilidad · Inversión
  económica · Impacto legal/compliance. (Descripciones de nivel 1/2/3 en la hoja "Evaluación".)

### 1.3 Catálogo (hoja "Riesgos")

- Columnas: `Proceso | Riesgo predeterminado | Pregunta clave | Sí/No`.
- ~167 filas. Procesos presentes: Dirección Estratégica, Investigación y Desarrollo, Compras,
  Despachos / CEDI, Producción, Gestión Integral (SIG), Seguridad Alimentaria / BPM -
  Microbiología, Gente y Cultura, Tecnología, Financiero / Costos, Gestión Ambiental, SST,
  Mantenimiento y calibración, General, Mejora Continua, Planeación, Seguridad Física.
- **Decisión**: la columna "Sí/No" es dato de ejemplo de la solicitud modelo (CIP Volpack); al
  importar se toman `Proceso`, `Pregunta clave` y `Riesgo predeterminado`, y esa columna se
  ignora. (Supuesto confirmado en la spec.)
- Relación pregunta↔riesgo: en la hoja es 1 fila = 1 pregunta = 1 riesgo. El modelo permite que
  un mismo texto de riesgo se comparta entre preguntas (deduplicado por proceso+texto) para
  soportar la consolidación FR-021.

### 1.4 Secciones del "Formato"

1. Resumen del cambio · 2. Descripción simple · 3. Consideraciones (Proceso | Pregunta clave |
Sí/No | Dueño que revisa | Evidencia mínima | Acción) · 4. Riesgos asociados (Proceso | Riesgo |
Control existente | Acción requerida | Responsable | Fecha | Probabilidad | Impacto | NR | Nivel |
Evidencia de cierre) · 5. Plan de acción y seguimiento · 6. Aprobación y cierre.

Listas de validación del Excel a reutilizar como enums: `Sí/No/N/A`; Tipo de cambio;
Clasificación; Estado de acción (`Pendiente/En curso/Cerrada`); Procesos.

## 2. Decisiones de stack

| Tema | Decisión | Alternativas consideradas | Motivo |
|------|----------|---------------------------|--------|
| Reactividad del cuestionario | **Livewire 3** (+ Alpine) | Inertia + Vue/React; Blade + JS a mano | Herramienta interna CRUD con recálculo en servidor; sin necesidad de SPA; menor superficie de mantenimiento para un equipo pequeño; cumple SC-007 (<2 s). |
| Estilos | **Tailwind CSS** (Vite ya presente) | Bootstrap | Vite ya configurado en el proyecto; consistencia con ecosistema Laravel actual. |
| Autenticación | **Laravel Breeze (Blade)** | Fortify manual; Jetstream | Mínimo y estándar; suficiente para uso interno. Jetstream trae equipo/2FA no requeridos. |
| Roles/permisos | **spatie/laravel-permission** | Gates/Policies puros con columna `role` | 4 roles con permisos por acción; el paquete es el estándar de facto y evita reinventar. Policies siguen usándose para reglas por-registro. |
| Import del catálogo | **maatwebsite/excel** | Leer PhpSpreadsheet directo; convertir a CSV | API de import sencilla; el proyecto ya está en PHP; permite recarga controlada del catálogo. |
| Export a PDF | **barryvdh/laravel-dompdf** | spatie/laravel-pdf (Browsershot/Puppeteer); Snappy/wkhtmltopdf | DOMPDF no requiere binarios externos ni Node en el servidor; el formato es tabular y estático. Si se exige fidelidad tipográfica exacta al `FOSIG-02`, revisar spatie/laravel-pdf (aclaración #1). |
| Exportar también a Excel | Diferido | maatwebsite/excel export | No pedido explícitamente; el PDF con encabezado oficial cubre el expediente. |
| Base de datos | La del proyecto (**SQLite** dev) | — | Sin cambios; el modelo es portable a MySQL/PostgreSQL. |
| Pruebas | **PHPUnit 12** (default) | Pest (plugin permitido en composer, no instalado) | Menor fricción; se puede migrar a Pest luego sin afectar el diseño. |

## 3. Patrón de sincronización respuestas ↔ secciones 3 y 4

- Al guardar una `RespuestaPregunta` con valor `Sí`, un servicio de dominio
  (`SincronizadorConsideraciones` / `SincronizadorRiesgos`) hace *upsert* de la fila derivada,
  ligada por `pregunta_clave_id` (y `solicitud_id`).
- Al pasar a `No`/`N/A`:
  - si la fila derivada **no** tiene edición manual ni calificación → se elimina;
  - si **sí** la tiene → se marca `estado = huerfano` y la UI pide confirmación (FR-030).
- Consolidación de riesgos (FR-021): la fila de `RiesgoAsociado` se identifica por
  `solicitud_id + riesgo_predeterminado_id`; las preguntas de origen se guardan en una pivote
  `riesgo_asociado_pregunta`.
- Idempotencia: reprocesar todas las respuestas de una solicitud debe converger al mismo estado
  (sin duplicados) — cubierto por prueba.

## 4. Congelamiento al enviar a aprobación (FR-035 / SC-009)

- Los textos de `pregunta`, `riesgo`, `dueño`, `evidencia`, `acción` se **copian** a la fila
  derivada al crearse (no se leen por relación en tiempo de render).
- Al transicionar `borrador → en_aprobacion` se sella un `snapshot_at`; a partir de ahí la edición
  del catálogo no toca la solicitud (ya no lo hacía) y se bloquea la re-sincronización.

## 5. Aclaraciones pendientes de la spec y supuesto por defecto

| # | Pregunta | Supuesto por defecto (si no hay respuesta) |
|---|----------|--------------------------------------------|
| 1 | Fidelidad de la exportación | PDF con las 6 secciones + encabezado `FOSIG-02` y versión; no réplica pixel-perfect de la plantilla. |
| 2 | Flujo de aprobación | Estados en el sistema + registro de decisión (aprobador, fecha, comentario); sin motor configurable. |
| 3 | Administración del catálogo en v1 | Incluir pantalla de administración básica (CRUD + activar/desactivar + reordenar). Si hay que recortar, se entrega solo el import y se pospone la pantalla. |

## 6. Riesgos técnicos

- **Codificación del `.xlsx`**: los textos traen acentos; el import debe forzar UTF-8.
- **Nombres de proceso no homogéneos** entre hojas ("Seguridad Alimentaria / BPM" en "Formato"
  vs "Seguridad Alimentaria / BPM - Microbiología" en "Riesgos"): se normaliza con una tabla de
  equivalencias al importar.
- **Livewire y tablas grandes** (~167 preguntas): paginar/agrupar por proceso y usar `wire:key`
  estable para no perder estado al recalcular.

---

## 7. Revisión R2 — Decisiones (2026-09-02)

- **Destinatarios por área**: tabla `destinatarios_area` (usuario interno o correo externo) en vez de roles spatie nuevos. Motivo: "Comité de cambio", "Gerencia General", "Jefes", "SST", etc. no son permisos, son listas de distribución que el área mantiene sola. Alternativa descartada: 7 roles nuevos → contamina la matriz de permisos y obliga a asignar rol para recibir correo.
- **Canal de notificación**: `database` + `mail`, ambas Notification `implements ShouldQueue` sobre `QUEUE_CONNECTION=database`. In-app (campana con `unreadNotifications`) es la vía efectiva mientras `MAIL_MAILER=log`; al configurar SMTP el correo sale sin cambios de código. Alternativa descartada: solo correo → sin trazabilidad in-app; solo in-app → el área pidió correo explícitamente.
- **Adjuntos**: tabla polimórfica única `adjuntos_evidencia` para `AccionPlan` y `CriterioCierre`, `Storage` disco `local` (privado), descarga por controlador con Policy. Sin límite de tipo (foto/video/PDF/Excel/…); límite de tamaño configurable (`config/gestioncambio.php`, default 20 MB). Alternativa descartada: `spatie/laravel-medialibrary` → dependencia grande para un caso simple.
- **Planta activa**: middleware + `session('planta_id')` + `users.planta_preferida_id`. Filtro operativo, no frontera de seguridad (admin/consulta ven todo). Alternativa descartada: tenancy/multi-DB → sobredimensionado para "por sede".
- **Gating por clasificación**: servicio puro `GatingSecciones` consultado desde el Blade `edit` y desde `mount()` de cada componente Livewire (defensa en profundidad). La clasificación no se persiste en `solicitudes_cambio`; se deriva de `evaluacion->clasificacion`.
- **Aprobador automático**: `AsignadorAprobador` se dispara desde `EvaluadorRubrica::recalcular()` sólo si `!aprobador_override`. Crítico requiere varios aprobadores → pivote `solicitud_aprobadores`; `aprobador_asignado_id` guarda el principal para compatibilidad con la UI actual.
- **Situación actual obligatoria**: se valida al **enviar a aprobación** (no al guardar borrador), para no romper el guardado incremental.

## 8. Riesgos técnicos R2

- **Worker de cola**: el envío de correo/notificaciones requiere `php artisan queue:work`. En dev sin worker, las `database` notifications tampoco se materializan hasta procesar la cola → considerar `QUEUE_CONNECTION=sync` en local o documentar el worker en quickstart.
- **Recalificación que baja la clasificación**: Mayor→Menor oculta secciones con datos. No se borran; el gating sólo las esconde. Hace falta aviso explícito en la UI (FR-051).
- **Adjuntos huérfanos**: si se elimina una acción/criterio, borrar en cascada sus `adjuntos_evidencia` y los archivos físicos (observer en el modelo).

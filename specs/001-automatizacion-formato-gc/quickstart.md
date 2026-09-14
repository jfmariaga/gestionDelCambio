# Quickstart — Fase 1

**Feature**: Automatización del Formato de Gestión del Cambio

## Requisitos

- PHP ^8.3 y Composer (ya presentes en el entorno).
- Node + npm (Vite ya está configurado en el proyecto).
- El archivo Excel de referencia del catálogo (hoja "Riesgos"), p. ej.
  `Ejemplo_Gestion_Cambio_CIP_Volpack 6.xlsx`.

## Puesta en marcha (entorno de desarrollo)

```bash
composer install
cp .env.example .env   # si aún no existe
php artisan key:generate
touch database/database.sqlite   # el proyecto usa SQLite en dev
php artisan migrate
php artisan db:seed              # procesos + 11 criterios de rúbrica (+ catálogo de respaldo)
npm install && npm run build     # o: npm run dev
php artisan serve
```

Dependencias que agrega esta feature (se instalan durante la implementación):

```bash
composer require livewire/livewire maatwebsite/excel barryvdh/laravel-dompdf spatie/laravel-permission
composer require laravel/breeze --dev && php artisan breeze:install blade
```

## Cargar el catálogo desde el Excel

```bash
php artisan gestion-cambio:importar-catalogo "ruta/al/Ejemplo_Gestion_Cambio_CIP_Volpack 6.xlsx"
```

Debe crear ~167 preguntas clave repartidas en los 17 procesos, cada una con su riesgo
predeterminado (cuando la hoja lo define).

## Probar el flujo principal (manual)

1. Entrar como usuario con rol `solicitante`.
2. `Solicitudes → Crear`: llenar sección 1 (el consecutivo `GC-AAAA-NNN` se genera solo) y
   sección 2. Guardar.
3. Pestaña **Cuestionario**: elegir un proceso (p. ej. *Producción*) y marcar una pregunta como
   **Sí**.
   - Verificar que en la pestaña **Consideraciones** aparece esa pregunta con su proceso y sus
     valores por defecto.
   - Verificar que en **Riesgos asociados** aparece el riesgo predeterminado de esa pregunta.
4. En **Riesgos asociados**, poner Probabilidad = 4 e Impacto = 9 → debe mostrar `NR = 36` y
   nivel **Medio**.
5. Volver al **Cuestionario** y cambiar esa pregunta a **No**:
   - Si no se editó la fila → desaparece de ambas secciones.
   - Si se calificó el riesgo → pide confirmación antes de eliminar.
6. Pestaña **Evaluación**: calificar los 11 criterios; con suma 20 la clasificación debe ser
   **Mayor**.
7. **Enviar a aprobación**: la solicitud queda de solo lectura; editar el catálogo no cambia sus
   textos.
8. **Exportar**: se descarga el PDF con las 6 secciones y el encabezado `FOSIG-02`.

## Pruebas automatizadas

```bash
php artisan test --testsuite=Feature --filter=Precarga
php artisan test tests/Unit/CalculoRiesgoTest.php
php artisan test            # suite completa
./vendor/bin/pint --test    # estilo
```

Casos mínimos que deben pasar (ver `contracts/rutas-http.md`):

- `PrecargaConsideracionesTest` — "Sí" ⇒ fila en sección 3 con proceso + pregunta correctos.
- `PrecargaRiesgosTest` — "Sí" ⇒ riesgo asociado con proceso + texto; consolidación cuando dos
  preguntas comparten riesgo.
- `RecalculoRespuestaTest` — "Sí"→"No" elimina o marca huérfano según edición previa.
- `CalculoRiesgoTest` / `ClasificacionCambioTest` — resultados idénticos al Excel (SC-006).
- `CongelamientoAprobacionTest` — editar el catálogo tras enviar no altera la solicitud (SC-009).
- `ImportarCatalogoTest` — ~167 preguntas cargadas y una nueva visible en solicitud (SC-008).

## Siguiente paso

Ejecutar `/speckit-tasks` para generar `tasks.md` a partir de este plan (empezar por US1 y US2,
que son el MVP).

---

## Revisión R2 — Validación (2026-09-02)

Prerrequisitos extra: `php artisan migrate` (nuevas tablas), `php artisan db:seed --class=PlantaSeeder`, `php artisan db:seed --class=AreaNotificacionSeeder`, y un worker `php artisan queue:work` (o `QUEUE_CONNECTION=sync` en `.env` local).

Casos mínimos R2 (ver `contracts/rutas-http.md` §Revisión R2):

- `SeleccionPlantaTest` — sin planta preferida ⇒ redirect a `plantas.seleccionar`; tras elegir, el listado sólo muestra solicitudes de esa planta; `store` asocia `planta_id`.
- `GatingClasificacionTest` — evaluación incompleta ⇒ sólo resumen + evaluación visibles; suma 13 (Menor) ⇒ sólo +plan +cierre; suma 20 (Mayor) ⇒ todas.
- `SituacionActualObligatoriaTest` — solicitud Menor sin situación actual/qué cambiará/resultado ⇒ `enviar` falla con errores de validación.
- `AsignadorAprobadorTest` — Menor ⇒ aprobador = dueño del proceso; Mayor ⇒ destinatarios de `comite_cambio`; Crítico ⇒ + `gerencia_general`; override manual sobrevive a recálculo.
- `TareaPlanValidacionTest` — responsable ≠ creador ⇒ `TareaAsignadaNotification`; responsable marca Cerrada ⇒ estado "Cerrada — pendiente de validación" + `TareaPorValidarNotification`; líder valida ⇒ "Validada"; cerrar solicitud con tareas sin validar ⇒ bloqueado.
- `AdjuntoEvidenciaTest` — subir PDF a una acción y a un criterio ⇒ descargable por quien puede ver la solicitud; aparece en el export.
- `NotificacionPorAreaTest` — solicitud Mayor ⇒ notifican gestión integral + SST + ambiental + calidad + jefes; Menor ⇒ sólo gestión integral + jefes; área sin destinatarios ⇒ bitácora, sin excepción.

Usar `Notification::fake()` y `Storage::fake('local')` en los tests R2.

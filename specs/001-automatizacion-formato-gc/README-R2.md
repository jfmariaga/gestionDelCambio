# Revisión R2 — Guía de operación (2026-09-02)

Ajustes de la reunión con Gestión Integral. Implementados sobre la feature 001.
`php artisan test` → **131 passed**.

## Qué cambió

| # | Cambio | Dónde |
|---|--------|-------|
| US7 | **Crear** una solicitud pide solo lo mínimo (nombre, fecha, área opcional); al continuar se abre la solicitud con la **evaluación como sección 1** (antes que Resumen y descripción). La clasificación (Menor/Mayor/Crítico) habilita el resto del formulario y asigna el aprobador: **Menor** → solo Plan de acción + Aprobación y cierre; **Mayor/Crítico** → todas las secciones. Situación actual / qué cambiará / resultado esperado son obligatorios para enviar a aprobación (incluso en Menor). | `solicitudes/create.blade.php` (mínimo), `GatingSecciones`, `solicitudes/edit.blade.php`, `SolicitudCambioController::enviar` |
| US8 | **Aprobador automático** por clasificación: Menor → dueño del proceso; Mayor → Comité de cambio; Crítico → Gerencia General + Comité. Un admin puede sustituirlo (`PATCH /solicitudes/{id}/aprobador`), y el override sobrevive a recálculos. | `AsignadorAprobador`, `EvaluadorRubrica::recalcular` |
| US9 | **Validación de tareas del plan**: al asignar un responsable distinto del creador se le notifica; el responsable marca *Cerrada* → *Cerrada — pendiente de validación* → el líder **valida** o **rechaza con comentario**. No se cierra la solicitud con tareas sin validar. | `PlanAccion` (`marcarCerrada`/`validar`/`rechazar`), `EstadoAccionPlan`, `TransicionSolicitud::bloqueosDeCierre` |
| US10 | **Adjuntos** (cualquier formato) en cada tarea del plan y cada criterio de cierre: **hasta 10 por elemento, uno a la vez**; descarga con permiso de la solicitud; aparecen en el PDF. Límite de tamaño `GC_ADJUNTO_MAX_KB` (20 MB). | `adjuntos_evidencia`, `TieneAdjuntos`, `AdjuntoController`, `PlanAccion::MAX_ADJUNTOS` |
| US11 | **Notificaciones por área** (in-app + correo, encoladas): siempre Gestión Integral + Jefes; Mayor/Crítico añade SST + Ambiental + Calidad; al enviar añade Comité. Área sin destinatarios → queda en bitácora, no falla. Campana en la barra superior. | `Notificador`, `EventoSolicitudNotification`, `admin/destinatarios`, `notificaciones/index` |
| US12 | **Selector de planta/sede** al ingresar (Panal, Leva Pan, Leva Col), con preferencia por usuario. Cada solicitud queda marcada con la planta; el listado se filtra por la planta activa (admin/consulta ven todo). | middleware `planta`, `PlantaSesionController`, `Admin\PlantaController` |

## Puesta en marcha

```sh
php artisan migrate            # 10 migraciones nuevas
php artisan db:seed --class=PlantaSeeder
php artisan db:seed --class=AreaNotificacionSeeder
php artisan queue:work         # necesario para el envío real de correo
```

En desarrollo sin worker, poner `QUEUE_CONNECTION=sync` en `.env` para que las notificaciones se entreguen de inmediato. Mientras `MAIL_MAILER=log`, el correo queda en `storage/logs`; la campana in-app funciona igual.

## Configuración

- **Destinatarios por área**: Administración → *Destinatarios*. Cada área acepta usuarios del sistema y/o correos externos.
- **Plantas**: Administración → *Plantas* (agregar / renombrar / desactivar sin afectar solicitudes existentes).
- **Adjuntos**: `config/gestioncambio.php` (`adjunto_max_kb`, `adjunto_disco`).

## Constitución (recheck R2) — PASA

Notifications nativas encoladas, `WithFileUploads`, middleware y policies existentes; sin capas
nuevas salvo servicios de cálculo/orquestación puros (`GatingSecciones`, `AsignadorAprobador`,
`Notificador`). Reasignación de aprobador, validación/rechazo de tareas y áreas sin destinatario
quedan en `bitacora_eventos`. Planta es filtro operativo, no frontera de seguridad.

# gestion-cambios Constitution

## Core Principles

### I. El formato oficial es la fuente de verdad
Toda regla de negocio (procesos, preguntas clave, riesgos, fórmulas NR, rangos de clasificación,
secciones del formato) proviene del formato oficial `FOSIG-02` y de sus hojas de catálogo. El
software no inventa ni "mejora" cálculos: los reproduce. Cualquier divergencia respecto del Excel
vigente se documenta y se aprueba con el área dueña del formato antes de implementarse.

### II. Convención Laravel primero
Se usa el stack y las convenciones del proyecto Laravel existente (estructura estándar, Eloquent,
migraciones, validación con Form Requests, colas si aplica). No se introducen patrones ni
abstracciones (repositorios, capas hexagonales, DDD táctico) salvo que un problema concreto lo
justifique y quede registrado en Complexity Tracking del plan.

### III. Pruebas donde el riesgo lo exige (NO NEGOCIABLE)
Son obligatorias las pruebas automatizadas de: precarga de la sección 3 al responder "Sí",
precarga y consolidación de riesgos en la sección 4, cálculo de NR y nivel, clasificación de la
rúbrica, congelamiento de textos al enviar a aprobación y manejo de filas huérfanas. Estas
pruebas se escriben junto con la funcionalidad, no después del cierre.

### IV. Trazabilidad e historial
Ninguna solicitud de cambio se elimina. Se registran autor y fechas de creación/modificación.
Los textos de consideraciones y riesgos de una solicitud enviada a aprobación quedan
congelados y son auditables.

### V. Simplicidad y foco en el usuario
El objetivo es que el formato sea más fácil de usar que el Excel. Se prioriza el MVP (US1 y US2)
y se evita construir motores configurables (flujos de aprobación, reglas dinámicas) mientras un
modelo de estados simple resuelva la necesidad.

## Restricciones técnicas

- Aplicación web dentro del repositorio Laravel `gestion-cambios`; PHP y Laravel en las versiones
  ya fijadas por `composer.json`.
- Interfaz en español.
- Persistencia mediante la base de datos configurada en el proyecto (SQLite en desarrollo).
- El catálogo inicial se importa desde la hoja "Riesgos" del Excel vigente.
- Sin dependencias de servicios externos para operar el flujo principal.

## Flujo de trabajo

- Se sigue el ciclo de Spec Kit: `spec.md` → `plan.md` → `tasks.md` → implementación.
- Cada historia de usuario P1/P2 se entrega como incremento demostrable e independientemente
  testeable.
- Revisión de código verifica: fidelidad al formato oficial, cobertura de las pruebas del
  Principio III y ausencia de complejidad no justificada.

## Governance

Esta constitución prevalece sobre preferencias individuales. Las enmiendas se documentan en este
archivo con fecha y quedan reflejadas en la versión. El plan de cada feature incluye un
"Constitution Check" que debe pasar antes de generar tareas.

**Version**: 1.0.0 | **Ratified**: 2026-08-27 | **Last Amended**: 2026-08-27

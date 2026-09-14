# Feature Specification: Automatización del Formato de Gestión del Cambio

**Feature Branch**: `001-automatizacion-formato-gc`

**Created**: 2026-08-27

**Status**: Draft

**Input**: User description: "Automatización formato. En reunión técnica el día de hoy se presentó el nuevo formato de gestión del cambio, sin embargo se solicitó la automatización de este documento con el fin de que sea más amigable y fácil de utilizar para el usuario. Se requiere que: las preguntas predeterminadas al ser seleccionadas como SÍ se carguen automáticamente en la hoja principal, relacionando proceso y pregunta clave; así mismo, en la parte de evaluación de riesgos se relacione el proceso y el riesgo asociado a la pregunta clave."

## Contexto

Hoy la Gestión del Cambio se diligencia en un libro de Excel (`FOSIG-02`) con tres hojas:

- **Formato** — la solicitud de cambio propiamente dicha, con seis secciones: (1) Resumen del cambio, (2) Descripción simple, (3) Consideraciones por proceso, (4) Riesgos asociados, (5) Plan de acción y seguimiento, (6) Aprobación y cierre.
- **Riesgos** — un catálogo de ~167 preguntas clave predeterminadas. Cada fila relaciona un **proceso**, una **pregunta clave** y el **riesgo predeterminado** que esa pregunta busca detectar.
- **Evaluación** — una rúbrica de clasificación con 11 criterios calificados de 1 a 3; la suma ubica el cambio en *Menor* (11–16), *Mayor* (17–23) o *Crítico* (24–33).

El usuario que registra un cambio debe leer el catálogo completo, decidir qué preguntas aplican, copiar manualmente a la sección 3 del Formato las que marca "Sí" y transcribir a mano los riesgos correspondientes en la sección 4. El proceso es lento, propenso a omisiones y produce resultados distintos entre turnos y entre personas.

## User Scenarios & Testing *(mandatory)*

### User Story 1 - Precarga de consideraciones desde las preguntas marcadas "Sí" (Priority: P1)

El líder del cambio abre una solicitud nueva, recorre el cuestionario de preguntas clave predeterminadas agrupado por proceso y marca **Sí / No / N/A** en cada una. Al marcar una pregunta como **Sí**, esa pregunta aparece automáticamente en la sección 3 "Consideraciones" del formato, con su proceso, su pregunta clave y los valores predeterminados de dueño que debe revisar, evidencia mínima y acción sugerida. Si vuelve a marcarla como No o N/A, la consideración se retira de la sección 3.

**Why this priority**: Es el núcleo de la solicitud del área. Sin esta automatización el formato sigue siendo un ejercicio manual de copiar y pegar. Entrega valor por sí sola aunque no exista nada más.

**Independent Test**: Con solo el catálogo cargado y una solicitud en blanco, marcar tres preguntas de procesos distintos como "Sí" y verificar que la sección 3 muestra exactamente esas tres filas con proceso y pregunta correctos; desmarcar una y verificar que desaparece.

**Acceptance Scenarios**:

1. **Given** una solicitud de cambio nueva y el catálogo de preguntas disponible, **When** el usuario marca una pregunta clave como "Sí", **Then** la sección 3 "Consideraciones" incluye una fila con el proceso y la pregunta clave de esa entrada del catálogo.
2. **Given** una pregunta ya marcada "Sí" y visible en la sección 3, **When** el usuario la cambia a "No" o "N/A", **Then** la fila correspondiente se elimina de la sección 3 sin afectar las demás.
3. **Given** varias preguntas "Sí" del mismo proceso, **When** se genera la sección 3, **Then** las consideraciones aparecen agrupadas y ordenadas por proceso siguiendo el orden del catálogo.
4. **Given** una consideración precargada, **When** el usuario ajusta el dueño, la evidencia o la acción en la sección 3, **Then** el cambio se conserva y no se sobrescribe al recalcular otras filas.

---

### User Story 2 - Precarga de riesgos asociados a cada pregunta "Sí" (Priority: P1)

Para cada pregunta clave marcada "Sí", el sistema agrega a la sección 4 "Riesgos asociados" el **riesgo predeterminado** que el catálogo asocia a esa pregunta, junto con su proceso. Cada riesgo precargado queda listo para que el usuario complete control existente, acción requerida, responsable, fecha, probabilidad (1–10) e impacto (1–10). El sistema calcula automáticamente el Nivel de Riesgo (NR = probabilidad × impacto) y el nivel cualitativo (Bajo ≤ 30, Medio ≤ 60, Alto > 60).

**Why this priority**: Es la segunda mitad explícita de la solicitud. Garantiza que ningún riesgo asociado a una consideración aplicable quede sin evaluar y elimina la transcripción manual.

**Independent Test**: Marcar dos preguntas "Sí" y confirmar que la sección 4 contiene dos riesgos con el texto de riesgo y el proceso exactos del catálogo; ingresar probabilidad e impacto en una fila y verificar NR y nivel calculados.

**Acceptance Scenarios**:

1. **Given** una pregunta clave marcada "Sí", **When** se actualiza la sección 4, **Then** aparece una fila de riesgo con el proceso y el texto del riesgo predeterminado asociado a esa pregunta en el catálogo.
2. **Given** una fila de riesgo precargada, **When** el usuario ingresa probabilidad e impacto, **Then** el sistema muestra NR = probabilidad × impacto y el nivel "Bajo", "Medio" o "Alto" según los umbrales 30 y 60.
3. **Given** una pregunta "Sí" que luego se cambia a "No", **When** se actualiza la sección 4, **Then** el riesgo precargado por esa pregunta se retira, salvo que el usuario ya lo haya calificado o editado, en cuyo caso se marca como "huérfano" y se solicita confirmación antes de eliminarlo.
4. **Given** dos preguntas "Sí" distintas que comparten el mismo riesgo predeterminado, **When** se actualiza la sección 4, **Then** el riesgo aparece una sola vez y referencia ambas preguntas de origen.

---

### User Story 3 - Cuestionario guiado por proceso más amigable que el Excel (Priority: P2)

El usuario responde el cuestionario en una interfaz por pasos o por secciones plegables, un proceso a la vez, con indicador de avance (preguntas respondidas / total), posibilidad de guardar y continuar después, y ayuda contextual con la definición del riesgo que cada pregunta busca detectar.

**Why this priority**: Es el objetivo de "más amigable y fácil de utilizar". Mejora la adopción, pero las historias 1 y 2 ya entregan el valor funcional aunque la interfaz sea mínima.

**Independent Test**: Responder parcialmente el cuestionario, cerrar sesión, volver a entrar y comprobar que las respuestas y el porcentaje de avance se conservan.

**Acceptance Scenarios**:

1. **Given** un cuestionario con preguntas de varios procesos, **When** el usuario navega, **Then** puede ver y responder un proceso a la vez y consultar cuántas preguntas le faltan.
2. **Given** respuestas parciales, **When** el usuario guarda y sale, **Then** al regresar encuentra el mismo estado.
3. **Given** una pregunta clave, **When** el usuario abre la ayuda contextual, **Then** ve el enunciado del riesgo predeterminado asociado.

---

### User Story 4 - Rúbrica de evaluación y clasificación del cambio (Priority: P2)

El usuario califica los 11 criterios de la rúbrica (Alcance, Producto/cliente, Inocuidad/calidad, SST/ambiente, Tecnología/datos, Equipos/instalaciones, Organización/personas, Documentación, Reversibilidad, Inversión económica, Impacto legal/compliance) en escala 1–3. El sistema suma y clasifica el cambio como *Menor*, *Mayor* o *Crítico*, y muestra la clasificación en el resumen de la solicitud.

**Why this priority**: Complementa el formato oficial y da contexto de severidad a las secciones 3 y 4, pero es independiente de la mecánica de precarga.

**Independent Test**: Calificar los 11 criterios con valores que sumen 20 y verificar que la clasificación resultante sea "Mayor".

**Acceptance Scenarios**:

1. **Given** los 11 criterios calificados, **When** se calcula la evaluación, **Then** el sistema muestra la suma y la clasificación según los rangos 11–16 / 17–23 / 24–33.
2. **Given** una evaluación incompleta, **When** el usuario intenta cerrar la sección, **Then** el sistema indica qué criterios faltan y no muestra clasificación.

---

### User Story 5 - Plan de acción, aprobación/cierre y exportación del formato (Priority: P3)

El usuario registra las acciones de la sección 5 (descripción, proceso responsable, responsable, fecha, estado, evidencia, nota) y diligencia los criterios de la sección 6 (Sí / No / N/A, detalle, responsable). Puede exportar la solicitud completa a un documento con la estructura y el encabezado del formato oficial `FOSIG-02` para adjuntar al expediente del cambio.

**Why this priority**: Necesario para reemplazar por completo el Excel, pero no forma parte del pedido central de automatización; puede llegar después del MVP.

**Independent Test**: Completar una solicitud de punta a punta y exportarla; verificar que el documento contiene las seis secciones con los datos ingresados.

**Acceptance Scenarios**:

1. **Given** una solicitud con acciones en la sección 5, **When** el usuario cambia el estado de una acción a "En curso" o "Cerrada", **Then** el estado se refleja en el seguimiento de la solicitud.
2. **Given** una solicitud completa, **When** el usuario exporta, **Then** obtiene un documento con las seis secciones, el código `FOSIG-02` y la versión del formato.

---

### User Story 6 - Administración del catálogo de preguntas y riesgos (Priority: P3)

Un usuario administrador mantiene el catálogo: agrega, edita, desactiva o reordena preguntas clave, edita el riesgo predeterminado asociado y los valores por defecto de dueño, evidencia y acción. Los cambios en el catálogo aplican a las solicitudes nuevas y no alteran retroactivamente las solicitudes ya diligenciadas.

**Why this priority**: Da autonomía al área para mantener el instrumento sin intervención técnica, pero el MVP puede operar con el catálogo cargado una sola vez desde el Excel.

**Independent Test**: Desactivar una pregunta del catálogo y verificar que ya no aparece en solicitudes nuevas pero sigue visible en una solicitud creada antes del cambio.

**Acceptance Scenarios**:

1. **Given** una pregunta nueva agregada al catálogo, **When** se crea una solicitud, **Then** la pregunta aparece en el cuestionario dentro de su proceso.
2. **Given** una solicitud ya creada, **When** el administrador edita el texto de un riesgo en el catálogo, **Then** la solicitud existente conserva el texto que tenía al momento de precargarse.

---

---

## Revisión R2 — Ajustes de reunión con Gestión Integral (2026-09-02)

Reunión con Julian Pardo (Gestión Integral). Los siguientes cambios ajustan el flujo del formato ya construido; no reemplazan las historias P1–P3, las refinan.

### User Story 7 - La evaluación va primero y define qué se diligencia (Priority: P1)

El líder del cambio, al abrir una solicitud, lo primero que hace —después del resumen y la descripción simple— es la **rúbrica de evaluación** (11 criterios, 1–3). El sistema suma y clasifica automáticamente el cambio en **Menor**, **Mayor** o **Crítico** y muestra esa clasificación arriba, en la sección "Resumen y descripción". La clasificación determina el resto del formulario:

- **Menor**: el sistema habilita únicamente **Plan de acción** y **Aprobación y cierre**. No se solicitan cuestionario, consideraciones ni riesgos. El líder gestiona el cambio directamente.
- **Mayor** o **Crítico**: el sistema habilita todas las secciones (cuestionario, consideraciones, riesgos, plan de acción, aprobación y cierre).

En todos los casos —incluido Menor— es obligatorio registrar **situación actual**, **qué cambiará** y **resultado esperado** antes de continuar.

**Why this priority**: Es el orden real del proceso del área. Sin la clasificación calculada primero, el usuario no sabe qué debe llenar y termina diligenciando secciones que no aplican.

**Independent Test**: Crear una solicitud, calificar los 11 criterios con suma 13 (Menor) y verificar que solo aparecen Plan de acción y Aprobación y cierre, con la clasificación "Menor" visible en el resumen; repetir con suma 20 (Mayor) y verificar que aparecen todas las secciones.

**Acceptance Scenarios**:

1. **Given** una solicitud nueva sin evaluación, **When** el usuario abre el formulario, **Then** ve el resumen, la descripción simple y la rúbrica de evaluación, y las secciones de cuestionario/consideraciones/riesgos/plan/cierre están bloqueadas con el mensaje "Complete la evaluación para continuar".
2. **Given** los 11 criterios calificados con resultado "Menor", **When** el sistema recalcula, **Then** se habilitan solo Plan de acción y Aprobación y cierre y la clasificación "Menor" aparece en el encabezado del resumen.
3. **Given** una evaluación con resultado "Mayor" o "Crítico", **When** el sistema recalcula, **Then** se habilitan todas las secciones.
4. **Given** una solicitud "Menor" sin situación actual / qué cambiará / resultado esperado, **When** el usuario intenta enviarla a aprobación, **Then** el sistema lo impide y señala los campos faltantes.
5. **Given** una solicitud "Mayor" ya con cuestionario y riesgos diligenciados, **When** una recalificación la baja a "Menor", **Then** el sistema advierte que las consideraciones y riesgos quedarán ocultos (no se borran) antes de aplicar el cambio.

---

### User Story 8 - Aprobador asignado automáticamente según la clasificación (Priority: P1)

Al fijarse la clasificación, el sistema asigna automáticamente el aprobador de la solicitud:

- **Menor**: el **líder / dueño del proceso**.
- **Mayor**: el **Comité de cambio**.
- **Crítico**: **Gerencia General** + **Comité de cambio**.

Un administrador puede sustituir manualmente el aprobador asignado si el caso lo requiere.

**Why this priority**: Define quién cierra el cambio; hoy se elige a mano y se presta a error.

**Independent Test**: Clasificar una solicitud como "Mayor" y verificar que el aprobador asignado corresponde a los destinatarios del Comité de cambio configurados; cambiar a "Crítico" y verificar que se agregan los de Gerencia General.

**Acceptance Scenarios**:

1. **Given** una solicitud recién clasificada como "Menor", **When** se consulta el aprobador asignado, **Then** es el dueño del proceso del área de la solicitud.
2. **Given** una solicitud clasificada como "Crítico", **When** se consulta el aprobador, **Then** incluye a Gerencia General y al Comité de cambio.
3. **Given** un aprobador asignado automáticamente, **When** un administrador lo cambia, **Then** se conserva la elección manual y queda registrada en la bitácora.
4. **Given** una solicitud cuya clasificación cambia de "Mayor" a "Crítico" antes de enviarse, **When** se recalcula, **Then** el aprobador asignado se actualiza salvo que haya override manual.

---

### User Story 9 - Tareas del plan de acción con responsable, notificación y validación (Priority: P2)

El líder registra en el plan de acción una o varias tareas (descripción, proceso, responsable, fecha, estado, evidencia). Cuando el **responsable** de una tarea es distinto de quien la creó, esa persona **recibe una notificación** con el detalle de su tarea. El responsable ejecuta, adjunta evidencia y marca la tarea como **Cerrada**; en ese momento la tarea vuelve al **líder/creador** para que **valide** si quedó correcta antes del cierre definitivo del plan.

**Why this priority**: Da seguimiento real a las acciones y evita que se den por cerradas sin verificación.

**Independent Test**: Crear una tarea con responsable distinto al creador, verificar que el responsable la ve en su bandeja y recibe notificación; el responsable la marca "Cerrada" con evidencia; verificar que el líder recibe una tarea de validación y que la tarea no cuenta como cerrada hasta que el líder la valida.

**Acceptance Scenarios**:

1. **Given** una tarea nueva con responsable distinto al creador, **When** se guarda, **Then** el responsable recibe notificación in-app y por correo con la descripción, el proceso y la fecha.
2. **Given** una tarea "En curso" asignada a un responsable, **When** el responsable la marca "Cerrada" y adjunta evidencia, **Then** su estado pasa a "Cerrada — pendiente de validación" y el líder recibe una notificación para validar.
3. **Given** una tarea "Cerrada — pendiente de validación", **When** el líder la valida, **Then** queda "Validada"; **When** el líder la rechaza con comentario, **Then** vuelve a "En curso" y el responsable es notificado.
4. **Given** un plan con tareas sin validar, **When** se intenta cerrar la solicitud, **Then** el sistema lo advierte y no permite el cierre.

---

### User Story 10 - Adjuntos de archivo en plan de acción y en aprobación/cierre (Priority: P2)

En cada tarea del plan de acción y en cada criterio de aprobación y cierre, además del texto de evidencia, el usuario puede **adjuntar archivos** (foto, video, PDF, Excel, Word, cualquier formato). Los adjuntos quedan disponibles para consulta y se incluyen como anexos al exportar la solicitud.

**Why this priority**: Evita los "soportes aparte" (cuadernos, carpetas) y deja la evidencia junto al registro.

**Independent Test**: Adjuntar un PDF y una imagen a una tarea; verificar que se listan, se pueden descargar y aparecen referenciados en la exportación.

**Acceptance Scenarios**:

1. **Given** una tarea del plan, **When** el usuario adjunta uno o más archivos, **Then** se listan con nombre, tamaño y fecha, y se pueden descargar.
2. **Given** un criterio de la sección 6, **When** el usuario adjunta un archivo, **Then** queda ligado a ese criterio.
3. **Given** una solicitud con adjuntos, **When** se exporta, **Then** el documento referencia los adjuntos (nombre y sección de origen).
4. **Given** un adjunto cargado, **When** la solicitud ya está cerrada, **Then** el adjunto sigue disponible en solo lectura.

---

### User Story 11 - Notificaciones por área según la clasificación (Priority: P2)

Cada movimiento relevante de una solicitud (creación, envío a aprobación, decisión, cierre) genera notificaciones a las áreas que correspondan:

- **Siempre**: **Gestión Integral** (para no perder la trazabilidad de lo que pasa en la aplicación).
- **Menor**: solo Gestión Integral.
- **Mayor** o **Crítico**: Gestión Integral + **SST** + **Gestión Ambiental** + **Calidad e Inocuidad**.
- Al enviarse a comité: además, los destinatarios del **Comité de cambio** que apliquen al tipo de cambio (producción, mantenimiento, etc.).
- **Jefes**: se notifica a los jefes para trazabilidad de quién registró y en qué va.

Los destinatarios de cada área se administran en una **pantalla de configuración** (por área: usuarios y/o correos). Las notificaciones se entregan **in-app** (bandeja/campana) y por **correo**.

**Why this priority**: El área necesita enterarse de todos los cambios sin depender de que alguien les avise.

**Independent Test**: Configurar destinatarios de las cuatro áreas; crear una solicitud "Mayor" y verificar que las cuatro áreas reciben notificación in-app y correo; crear una "Menor" y verificar que solo Gestión Integral recibe.

**Acceptance Scenarios**:

1. **Given** destinatarios configurados para Gestión Integral, **When** se crea cualquier solicitud, **Then** Gestión Integral recibe notificación in-app y correo.
2. **Given** una solicitud "Mayor", **When** se envía a aprobación, **Then** Gestión Integral, SST, Gestión Ambiental y Calidad e Inocuidad reciben la notificación.
3. **Given** una solicitud "Menor", **When** cambia de estado, **Then** solo Gestión Integral es notificada.
4. **Given** un área sin destinatarios configurados, **When** ocurre un evento que la involucra, **Then** el sistema registra la omisión en la bitácora y no falla el flujo.
5. **Given** el correo saliente no disponible, **When** se genera una notificación, **Then** la notificación in-app se entrega igual y el correo se reintenta.

---

### User Story 12 - Selección de planta / sede al ingresar (Priority: P2)

Al iniciar sesión, el usuario **escoge la planta** en la que va a trabajar (Panal, Leva Pan, Leva Col; la lista es ampliable). La planta activa se guarda en la sesión y como **preferencia por defecto** del usuario para próximos ingresos. Toda solicitud que cree queda **marcada con esa planta**, y el listado de solicitudes se **filtra por la planta activa**. El usuario puede cambiar de planta sin cerrar sesión.

**Why this priority**: La aplicación es corporativa y hay sedes que pueden no implementarla (p. ej. Tuluá); separar por planta evita mezclar datos.

**Independent Test**: Ingresar y elegir "Panal", crear una solicitud y verificar que queda en Panal; cambiar a "Leva Pan" y verificar que el listado ya no muestra la solicitud de Panal y que las nuevas quedan en Leva Pan.

**Acceptance Scenarios**:

1. **Given** un usuario que inicia sesión, **When** no tiene planta preferida, **Then** el sistema le pide elegir una planta antes de mostrar el tablero.
2. **Given** un usuario con planta preferida, **When** inicia sesión, **Then** entra directo con esa planta activa y puede cambiarla desde la barra superior.
3. **Given** una planta activa, **When** el usuario crea una solicitud, **Then** la solicitud queda asociada a esa planta.
4. **Given** una planta activa, **When** el usuario abre el listado de solicitudes, **Then** solo ve las de esa planta (salvo el rol de administración/consulta global, que puede ver todas).
5. **Given** un administrador, **When** gestiona el catálogo de plantas, **Then** puede agregar, renombrar o desactivar plantas sin afectar solicitudes ya asociadas.

---

### Edge Cases

- **Catálogo sin preguntas para un proceso**: el proceso no aparece en el cuestionario; la sección 3 no muestra ese proceso.
- **Pregunta "Sí" sin riesgo predeterminado definido**: se crea la consideración en la sección 3 pero no se agrega fila en la sección 4; el sistema advierte que la pregunta no tiene riesgo asociado en el catálogo.
- **Usuario edita una consideración o un riesgo precargado y luego cambia la respuesta a "No"**: el sistema no borra silenciosamente trabajo del usuario; marca la fila como huérfana y pide confirmación explícita para eliminarla o conservarla.
- **Respuesta cambiada muchas veces (Sí→No→Sí)**: al volver a "Sí" se restauran las filas; si existían ediciones previas conservadas como huérfanas, se ofrecen para reutilizar en lugar de duplicar.
- **Dos preguntas del mismo proceso con el mismo riesgo**: el riesgo se consolida en una sola fila de la sección 4 con referencia a ambas preguntas.
- **Solicitud enviada a aprobación mientras se edita el catálogo**: la solicitud queda congelada con los textos vigentes al momento de enviarse.
- **Evaluación de riesgo con probabilidad o impacto fuera de 1–10**: el sistema rechaza el valor y no calcula NR.
- **Cierre de la solicitud con riesgos "Alto" sin evidencia de cierre**: el sistema advierte y exige justificación o evidencia antes de permitir el cierre.
- **Solicitud sin ninguna pregunta marcada "Sí"**: se permite continuar, pero el sistema advierte que no se registraron consideraciones ni riesgos.
- **Concurrencia**: dos personas editando la misma solicitud; la segunda escritura advierte que los datos cambiaron y evita sobrescribir a ciegas.

## Requirements *(mandatory)*

### Functional Requirements

#### Catálogo de preguntas y riesgos

- **FR-001**: El sistema MUST mantener un catálogo de preguntas clave predeterminadas donde cada entrada relaciona un proceso, el texto de la pregunta clave y el riesgo predeterminado asociado.
- **FR-002**: Cada entrada del catálogo MUST poder incluir valores por defecto de "dueño que debe revisar", "evidencia mínima requerida" y "acción sugerida" para la sección 3.
- **FR-003**: El sistema MUST permitir cargar el catálogo inicial a partir del contenido de la hoja "Riesgos" del formato Excel vigente (~167 entradas).
- **FR-004**: El sistema MUST permitir a un administrador agregar, editar, desactivar y reordenar entradas del catálogo.
- **FR-005**: Las entradas desactivadas MUST dejar de ofrecerse en solicitudes nuevas sin desaparecer de las solicitudes que ya las usaron.

#### Cuestionario y respuestas

- **FR-006**: El sistema MUST presentar las preguntas del catálogo activas agrupadas por proceso al diligenciar una solicitud.
- **FR-007**: Los usuarios MUST poder responder cada pregunta como "Sí", "No" o "N/A".
- **FR-008**: El sistema MUST guardar respuestas parciales y permitir retomar la solicitud más tarde con el mismo estado y avance.
- **FR-009**: El sistema MUST mostrar el avance del cuestionario (preguntas respondidas frente al total aplicable).
- **FR-010**: El sistema MUST ofrecer ayuda contextual por pregunta que muestre el enunciado del riesgo predeterminado asociado.

#### Precarga de la sección 3 "Consideraciones"

- **FR-011**: Al marcar una pregunta como "Sí", el sistema MUST crear en la sección 3 una consideración con el proceso y la pregunta clave de esa entrada del catálogo.
- **FR-012**: El sistema MUST poblar la consideración con los valores por defecto de dueño, evidencia y acción del catálogo, dejándolos editables por el usuario.
- **FR-013**: Al cambiar una respuesta de "Sí" a "No" o "N/A", el sistema MUST retirar la consideración correspondiente, preservando las ediciones del usuario según FR-030.
- **FR-014**: El sistema MUST agrupar y ordenar las consideraciones de la sección 3 por proceso siguiendo el orden definido en el catálogo.
- **FR-015**: El sistema MUST evitar consideraciones duplicadas para la misma pregunta dentro de una solicitud.

#### Precarga de la sección 4 "Riesgos asociados"

- **FR-016**: Para cada pregunta marcada "Sí", el sistema MUST agregar a la sección 4 el riesgo predeterminado asociado a esa pregunta, con su proceso.
- **FR-017**: El sistema MUST dejar editables los campos control existente, acción requerida, responsable, fecha y evidencia de cierre de cada riesgo precargado.
- **FR-018**: El sistema MUST permitir calificar probabilidad e impacto en escala entera de 1 a 10 y rechazar valores fuera de ese rango.
- **FR-019**: El sistema MUST calcular el Nivel de Riesgo como NR = probabilidad × impacto.
- **FR-020**: El sistema MUST derivar el nivel cualitativo: "Bajo" si NR ≤ 30, "Medio" si 30 < NR ≤ 60, "Alto" si NR > 60.
- **FR-021**: Cuando dos o más preguntas "Sí" comparten el mismo riesgo predeterminado, el sistema MUST consolidarlo en una sola fila que referencie todas las preguntas de origen.
- **FR-022**: Si una pregunta "Sí" no tiene riesgo predeterminado en el catálogo, el sistema MUST crear la consideración pero no la fila de riesgo, y MUST advertirlo.

#### Evaluación y clasificación del cambio

- **FR-023**: El sistema MUST presentar la rúbrica de 11 criterios con sus tres niveles (1, 2, 3) y sus descripciones.
- **FR-024**: El sistema MUST sumar las calificaciones y clasificar el cambio como "Menor" (11–16), "Mayor" (17–23) o "Crítico" (24–33), e indicar "Revisar" fuera de esos rangos.
- **FR-025**: El sistema MUST mostrar la clasificación resultante en el resumen de la solicitud.
- **FR-026**: El sistema MUST indicar qué criterios faltan cuando la evaluación está incompleta y no mostrar clasificación hasta completarla.

#### Formato, plan de acción y cierre

- **FR-027**: El sistema MUST capturar los campos de la sección 1 (fecha, consecutivo, nombre del cambio, solicitante/cargo, área/proceso, tipo de cambio, clasificación, fecha requerida, costo estimado, ¿requiere comité?) y la sección 2 (situación actual, qué cambiará, resultado esperado).
- **FR-028**: El sistema MUST generar el consecutivo de la solicitud de forma automática y única (formato tipo `GC-AAAA-NNN`).
- **FR-029**: El sistema MUST permitir registrar acciones de la sección 5 con descripción, proceso, responsable, fecha, estado y evidencia, y criterios de la sección 6 con Sí/No/N/A, detalle y responsable.
- **FR-030**: Antes de eliminar una consideración o un riesgo que el usuario editó o calificó, el sistema MUST pedir confirmación y ofrecer conservarlo como registro independiente.
- **FR-031**: El sistema MUST permitir exportar la solicitud completa a un documento con la estructura y el encabezado del formato oficial (código y versión).
- **FR-032**: El sistema MUST advertir, al intentar cerrar la solicitud, si existen riesgos de nivel "Alto" sin evidencia de cierre o acciones de la sección 5 sin completar.

#### Trazabilidad y concurrencia

- **FR-033**: El sistema MUST registrar autor y fecha de creación y de última modificación de cada solicitud.
- **FR-035**: El sistema MUST congelar los textos de consideraciones y riesgos de una solicitud en el momento en que se envía a aprobación, de modo que cambios posteriores en el catálogo no la alteren.
- **FR-036**: El sistema MUST evitar que dos ediciones simultáneas de la misma solicitud se sobrescriban sin advertencia.
- **FR-037**: El sistema MUST conservar el histórico de solicitudes (no se eliminan; se marcan como cerradas o anuladas).

#### Roles y permisos

- **FR-034**: El acceso al sistema MUST requerir autenticación y estar gobernado por roles. Roles definidos: **Administrador**, **Solicitante / Líder del cambio**, **Dueño de proceso** (revisor), **Aprobador** (Comité de cambios) y **Consulta / Auditoría** (solo lectura). Un usuario puede tener más de un rol.
- **FR-038**: El **Administrador** MUST tener acceso total: gestión de usuarios y asignación de roles, administración del catálogo (procesos, preguntas, riesgos, criterios de rúbrica), importación desde Excel, y visualización de todas las solicitudes en cualquier estado.
- **FR-039**: El **Solicitante / Líder del cambio** MUST poder crear solicitudes, editarlas mientras están en `borrador`, responder el cuestionario, ajustar secciones 1–6 de sus solicitudes y enviarlas a aprobación. No puede editar solicitudes de las que no es autor ni líder.
- **FR-040**: Cada **proceso** MUST poder tener uno o más **Dueños de proceso** asignados. Un Dueño de proceso MUST poder ver y completar las consideraciones (sección 3) y los riesgos asociados (sección 4) correspondientes a los procesos que tiene asignados, en cualquier solicitud en estado `borrador` o `en_aprobacion`, aunque no sea su autor; no puede modificar otras secciones ni las filas de procesos que no le corresponden.
- **FR-041**: El **Aprobador** MUST poder revisar solicitudes en `en_aprobacion`, registrar la decisión (aprobar o devolver a `borrador`) con comentario y responsable, y —junto con el Administrador— cerrar o anular solicitudes. Ningún otro rol puede aprobar.
- **FR-042**: El rol **Consulta / Auditoría** MUST tener acceso de solo lectura a las solicitudes `aprobado`, `en_implementacion` y `cerrado`, incluidas sus evidencias y su exportación; no puede crear ni editar nada.
- **FR-043**: El sistema MUST permitir asignar el responsable de cada acción del plan (sección 5) a un usuario; ese usuario MUST poder actualizar el estado y la evidencia de sus acciones mientras la solicitud esté `aprobado` o `en_implementacion`, sin necesidad de otro rol.
- **FR-044**: Toda acción con efecto de estado (enviar a aprobación, aprobar, devolver, cerrar, anular, importar catálogo, desactivar entrada de catálogo) MUST quedar registrada con usuario, fecha y, cuando aplique, comentario.

#### Matriz de permisos por estado de la solicitud

| Capacidad | Administrador | Solicitante/Líder | Dueño de proceso | Aprobador | Consulta |
|---|---|---|---|---|---|
| Crear solicitud | ✔ | ✔ | — | — | — |
| Editar secciones 1–2, cuestionario, plan (`borrador`) | ✔ | ✔ (propias) | — | — | — |
| Completar secciones 3 y 4 (`borrador` / `en_aprobacion`) | ✔ | ✔ (propias) | ✔ (solo sus procesos) | — | — |
| Enviar a aprobación | ✔ | ✔ (propias) | — | — | — |
| Aprobar / devolver | ✔ | — | — | ✔ | — |
| Actualizar acciones asignadas (sección 5) | ✔ | ✔ (propias) | ✔ (si es responsable) | ✔ (si es responsable) | — |
| Cerrar / anular | ✔ | — | — | ✔ | — |
| Ver solicitud en `borrador` | ✔ | ✔ (propias) | ✔ (con filas de sus procesos) | — | — |
| Ver solicitud `aprobado` … `cerrado` + exportar | ✔ | ✔ (propias) | ✔ (sus procesos) | ✔ | ✔ |
| Administrar catálogo e importar Excel | ✔ | — | — | — | — |
| Gestionar usuarios y roles | ✔ | — | — | — | — |

#### Revisión R2 — Evaluación primero, notificaciones, adjuntos y planta

##### Evaluación primero y gating por clasificación

- **FR-045**: El sistema MUST presentar la rúbrica de evaluación como **primera sección** del formulario, antes del resumen y la descripción simple y antes del cuestionario, las consideraciones y los riesgos.
- **FR-046**: El sistema MUST mantener bloqueadas (solo lectura, con aviso "Complete la evaluación para continuar") las secciones de cuestionario, consideraciones, riesgos, plan de acción y aprobación/cierre mientras la evaluación no esté completa y clasificada.
- **FR-047**: Cuando la clasificación resultante es **Menor**, el sistema MUST habilitar únicamente Plan de acción y Aprobación y cierre, y MUST NOT solicitar cuestionario, consideraciones ni riesgos.
- **FR-048**: Cuando la clasificación resultante es **Mayor** o **Crítico**, el sistema MUST habilitar todas las secciones.
- **FR-049**: El sistema MUST mostrar la clasificación vigente (Menor / Mayor / Crítico / Revisar) en el encabezado de la sección "Resumen y descripción".
- **FR-050**: El sistema MUST exigir situación actual, qué cambiará y resultado esperado en toda solicitud —incluidas las **Menor**— y MUST impedir el envío a aprobación si faltan.
- **FR-051**: Si una recalificación cambia la clasificación de forma que oculta secciones ya diligenciadas (p. ej. de Mayor a Menor), el sistema MUST advertir antes de aplicar y MUST conservar (ocultos, no borrados) los datos de cuestionario, consideraciones y riesgos por si vuelve a subir la clasificación.

##### Asignación automática del aprobador

- **FR-052**: Al fijarse o cambiar la clasificación, el sistema MUST asignar el aprobador: **Menor** → dueño del proceso del área de la solicitud; **Mayor** → destinatarios del Comité de cambio; **Crítico** → destinatarios de Gerencia General + Comité de cambio.
- **FR-053**: El sistema MUST permitir a un administrador sustituir manualmente el aprobador asignado; el override MUST registrarse en la bitácora y MUST NOT ser revertido por recálculos posteriores de la clasificación.

##### Plan de acción: notificación y validación de tareas

- **FR-054**: El sistema MUST notificar (in-app + correo) al responsable de una tarea del plan cuando ese responsable es distinto de quien la creó, incluyendo descripción, proceso y fecha.
- **FR-055**: El responsable de una tarea MUST poder cambiarla a "Cerrada", momento en que la tarea pasa a "Cerrada — pendiente de validación" y el líder/creador MUST ser notificado para validarla.
- **FR-056**: El líder/creador MUST poder **validar** (queda "Validada") o **rechazar con comentario** (vuelve a "En curso", se notifica al responsable) una tarea "Cerrada — pendiente de validación".
- **FR-057**: El sistema MUST impedir el cierre de la solicitud si existen tareas del plan sin validar, y MUST advertirlo.

##### Adjuntos de evidencia

- **FR-058**: El sistema MUST permitir adjuntar archivos (imagen, video, PDF, hoja de cálculo, documento, u otro) a cada tarea del plan de acción y a cada criterio de aprobación y cierre, además del texto de evidencia. Se cargan **de a uno** y se admiten **hasta 10 por tarea o criterio**.
- **FR-059**: El sistema MUST listar cada adjunto con nombre, tamaño y fecha, permitir su descarga, y conservarlo disponible en solo lectura cuando la solicitud esté cerrada o anulada.
- **FR-060**: El sistema MUST referenciar los adjuntos (nombre y sección de origen) en la exportación del formato.

##### Notificaciones por área

- **FR-061**: El sistema MUST entregar las notificaciones por dos canales: in-app (bandeja/campana) y correo electrónico, y MUST procesarlas de forma encolada sin bloquear la acción del usuario.
- **FR-062**: El sistema MUST notificar a **Gestión Integral** en todo evento relevante de cualquier solicitud (creación, envío a aprobación, decisión, cierre, anulación), independientemente de la clasificación.
- **FR-063**: Para solicitudes **Mayor** o **Crítico**, el sistema MUST notificar además a **SST**, **Gestión Ambiental** y **Calidad e Inocuidad**.
- **FR-064**: Al enviarse una solicitud al Comité de cambio, el sistema MUST notificar a los destinatarios del Comité que apliquen al tipo de cambio.
- **FR-065**: El sistema MUST notificar a los jefes (destinatarios configurados como "Jefes") de los eventos de estado, para trazabilidad.
- **FR-066**: El sistema MUST ofrecer una pantalla de administración para configurar los destinatarios de cada área (Gestión Integral, SST, Gestión Ambiental, Calidad e Inocuidad, Comité de cambio, Gerencia General, Jefes), admitiendo usuarios del sistema y/o direcciones de correo externas.
- **FR-067**: Si un área involucrada no tiene destinatarios configurados, el sistema MUST registrar la omisión en la bitácora y MUST continuar el flujo sin error.

##### Planta / Sede

- **FR-068**: El sistema MUST mantener un catálogo de plantas/sedes (inicialmente Panal, Leva Pan, Leva Col), administrable (agregar, renombrar, desactivar) sin afectar solicitudes ya asociadas.
- **FR-069**: Al iniciar sesión, si el usuario no tiene planta preferida, el sistema MUST solicitarle elegir una antes de mostrar el tablero; si la tiene, MUST entrar con esa planta activa.
- **FR-070**: El sistema MUST guardar la planta activa en la sesión y como preferencia por defecto del usuario, y MUST permitir cambiarla sin cerrar sesión.
- **FR-071**: El sistema MUST asociar cada solicitud creada a la planta activa y MUST filtrar el listado de solicitudes por la planta activa, salvo para roles con visibilidad global (Administrador, Consulta/Auditoría).

### Key Entities *(include if feature involves data)*

- **Proceso**: unidad organizacional evaluada en la gestión del cambio (p. ej. Producción, SST, Tecnología, Compras). Atributos: nombre, orden de presentación, estado activo.
- **Pregunta clave del catálogo**: entrada predeterminada que relaciona un proceso con una pregunta de decisión. Atributos: proceso, texto de la pregunta, dueño por defecto, evidencia mínima por defecto, acción sugerida por defecto, orden, estado activo. Relación: 0..1 riesgo predeterminado.
- **Riesgo predeterminado del catálogo**: enunciado del riesgo que una pregunta clave busca detectar. Atributos: proceso, texto del riesgo. Relación: asociado a una o varias preguntas clave.
- **Solicitud de cambio**: instancia del formato. Atributos: consecutivo, fecha, nombre del cambio, solicitante/cargo, área/proceso, tipo de cambio, clasificación, fecha requerida, costo estimado, ¿requiere comité?, situación actual, qué cambiará, resultado esperado, estado (borrador / en aprobación / aprobado / en implementación / cerrado / anulado), autor, fechas de auditoría.
- **Respuesta a pregunta clave**: respuesta del usuario a una pregunta del catálogo dentro de una solicitud. Atributos: solicitud, pregunta clave, valor (Sí / No / N/A), fecha de respuesta.
- **Consideración (sección 3)**: fila derivada de una respuesta "Sí". Atributos: solicitud, pregunta clave de origen, proceso, texto de la pregunta (congelable), dueño que revisa, evidencia mínima, acción, indicador de edición manual, estado (activa / huérfana).
- **Riesgo asociado (sección 4)**: fila de evaluación derivada de una o varias respuestas "Sí". Atributos: solicitud, preguntas clave de origen, proceso, texto del riesgo (congelable), control existente, acción requerida, responsable, fecha, probabilidad (1–10), impacto (1–10), NR calculado, nivel calculado, evidencia de cierre, estado (activo / huérfano).
- **Evaluación del cambio**: calificación de la rúbrica. Atributos: solicitud, calificación por cada uno de los 11 criterios (1–3), suma, clasificación (Menor / Mayor / Crítico / Revisar).
- **Criterio de rúbrica**: definición de un criterio de evaluación. Atributos: nombre, descripción del nivel 1, nivel 2 y nivel 3, orden.
- **Acción del plan (sección 5)**: Atributos: solicitud, número, descripción, proceso, responsable, fecha, estado (Pendiente / En curso / Cerrada), evidencia, nota.
- **Criterio de cierre (sección 6)**: Atributos: solicitud, descripción, valor (Sí / No / N/A), detalle/evidencia, responsable.
- **Usuario / Rol**: persona autenticada con uno o más roles (administrador, solicitante/líder, dueño de proceso, aprobador, consulta/auditoría).
- **Asignación de dueño de proceso**: relación entre un usuario con rol "dueño de proceso" y uno o más procesos, que determina qué filas de las secciones 3 y 4 puede completar en cualquier solicitud.

#### Revisión R2

- **Planta / Sede**: unidad física donde opera el usuario (Panal, Leva Pan, Leva Col, …). Atributos: nombre, código, orden, estado activo. Relación: 1..N solicitudes.
- **Preferencia de planta del usuario**: planta por defecto que el usuario ve seleccionada al ingresar. Atributos: usuario, planta.
- **Área de notificación**: destino configurable de avisos (Gestión Integral, SST, Gestión Ambiental, Calidad e Inocuidad, Comité de cambio, Gerencia General, Jefes). Atributos: clave del área, nombre.
- **Destinatario de área**: usuario del sistema o correo externo ligado a un Área de notificación. Atributos: área, usuario (opcional), correo (opcional), estado activo.
- **Notificación**: aviso generado por un evento de solicitud. Atributos: destinatario, solicitud, tipo de evento, canal (in-app / correo), estado (pendiente / enviada / leída), fecha.
- **Adjunto de evidencia**: archivo ligado a una acción del plan (sección 5) o a un criterio de cierre (sección 6). Atributos: origen (acción/criterio), nombre original, tamaño, tipo, ruta de almacenamiento, subido por, fecha.
- **Acción del plan (sección 5)** — *ampliada*: se agregan los estados "Cerrada — pendiente de validación", "Validada" y "Rechazada"; y los campos responsable_id, creador_id, comentario de validación, validada por, validada en. Relación: 0..N adjuntos de evidencia.
- **Criterio de cierre (sección 6)** — *ampliada*: relación 0..N adjuntos de evidencia.
- **Solicitud de cambio** — *ampliada*: se agregan planta, clasificación vigente derivada de la evaluación, aprobador asignado y bandera de override manual del aprobador.
- **Evaluación del cambio** — *sin cambios de datos*: pasa a ser prerrequisito de gating; su clasificación habilita/oculta secciones y dispara la asignación del aprobador.

## Success Criteria *(mandatory)*

### Measurable Outcomes

- **SC-001**: El 100 % de las preguntas clave marcadas "Sí" aparecen como consideración en la sección 3 con el proceso y la pregunta correctos, sin intervención manual de copiado.
- **SC-002**: El 100 % de las preguntas "Sí" con riesgo predeterminado en el catálogo generan la fila correspondiente en la sección 4, con proceso y texto de riesgo correctos.
- **SC-003**: Registrar una solicitud de cambio completa (secciones 1 a 6) toma en promedio un 50 % menos de tiempo que diligenciar el Excel equivalente, medido con al menos 5 usuarios reales.
- **SC-004**: En una prueba con 10 solicitudes, la cantidad de consideraciones o riesgos omitidos respecto de las respuestas "Sí" es cero.
- **SC-005**: Un usuario nuevo completa su primera solicitud sin asistencia en menos de 20 minutos.
- **SC-006**: El nivel de riesgo (NR y clasificación Bajo/Medio/Alto) y la clasificación del cambio (Menor/Mayor/Crítico) coinciden con el cálculo del formato Excel en el 100 % de los casos de prueba.
- **SC-007**: Cambiar una respuesta de "Sí" a "No" retira la consideración y el riesgo asociados en menos de 2 segundos y nunca elimina sin confirmación datos editados por el usuario.
- **SC-008**: El catálogo inicial queda cargado con las ~167 preguntas y sus riesgos, y un administrador puede agregar una pregunta nueva y verla en una solicitud sin apoyo del área técnica.
- **SC-009**: Una solicitud enviada a aprobación no cambia su contenido aunque después se edite el catálogo (verificable comparando el documento antes y después).

## Assumptions

- La automatización se implementa como aplicación web dentro del proyecto Laravel existente `gestion-cambios`; el Excel deja de ser la herramienta de diligenciamiento y pasa a ser, a lo sumo, un formato de exportación.
- La columna "Sí/No" de la hoja "Riesgos" del Excel corresponde a datos de ejemplo de la solicitud modelo (CIP Volpack) y no a una respuesta por defecto del catálogo; al importar el catálogo se toman proceso, pregunta y riesgo, y esa columna se ignora.
- Los umbrales y fórmulas se conservan tal cual el Excel: NR = probabilidad × impacto; nivel Bajo ≤ 30, Medio ≤ 60, Alto > 60; clasificación del cambio 11–16 Menor, 17–23 Mayor, 24–33 Crítico.
- La lista de procesos y la rúbrica de 11 criterios se toman del Excel vigente y podrán ajustarse luego desde administración.
- La autenticación y la gestión de usuarios se apoyan en el mecanismo estándar del proyecto Laravel. Los 5 roles (Administrador, Solicitante/Líder, Dueño de proceso, Aprobador, Consulta/Auditoría) y la matriz de permisos por estado quedan definidos en FR-034…FR-044.
- El rol "Dueño de proceso" trabaja acotado a los procesos que tiene asignados; la asignación proceso↔usuario la gestiona el Administrador. Si en la organización el mismo cargo es a la vez solicitante y dueño de proceso, se le otorgan ambos roles.
- El rol "Consulta / Auditoría" cubre auditoría interna, de cliente y de certificación (contexto BPM/HACCP/FSSC); es estrictamente de solo lectura sobre solicitudes ya aprobadas o cerradas.
- El flujo de aprobación (quién aprueba, en qué orden, notificaciones) se modela de forma básica (estados de la solicitud); un motor de flujo de aprobación configurable está fuera del alcance de la v1.
- Idioma de la interfaz: español.
- La exportación al formato oficial `FOSIG-02` (sección 5 y 6 incluidas) es deseable para la v1 pero puede entregarse inmediatamente después del MVP sin bloquear las historias P1.
- Volumen esperado: decenas de solicitudes por mes y unas pocas decenas de usuarios; no hay requisitos de alta concurrencia.

## Assumptions — Revisión R2

- El aprobador "Comité de cambio", "Gerencia General", etc. se resuelve a través de la tabla de **Destinatarios de área**; no se crean roles nuevos de spatie para ello.
- Los canales de notificación son in-app (persistidas en base de datos, con campana en la barra superior) y correo. El correo se envía por el `mailer` configurado del proyecto; mientras sea `log`, las notificaciones in-app son la vía efectiva y el correo queda registrado.
- Las notificaciones se procesan por la cola del proyecto (`queue` database); requieren un worker en ejecución para el envío de correo.
- Los adjuntos se guardan en el `filesystem` del proyecto (disco `local`/`public` según configuración); sin límite de tipo, con límite de tamaño configurable (por defecto 20 MB).
- "Jefes" se maneja como un área de notificación más dentro de la pantalla de destinatarios.
- La planta activa es un filtro operativo, no un límite de seguridad: Administrador y Consulta/Auditoría ven todas las plantas.
- El catálogo inicial de plantas es Panal, Leva Pan y Leva Col; Tuluá se agrega solo si decide implementar.

## Áreas de clarificación pendientes

1. **Fidelidad de la exportación**: ¿la v1 debe producir un documento idéntico al `FOSIG-02` (mismo formato/plantilla para auditoría) o basta un PDF/planilla con las seis secciones y el encabezado del código y versión?
2. **Alcance de la administración del catálogo en v1**: ¿se requiere edición del catálogo por administrador desde el inicio, o es aceptable cargarlo una vez desde el Excel y posponer la pantalla de administración?
3. **Comité por tipo de cambio (FR-064)**: ¿la correspondencia "tipo de cambio → destinatarios de comité" (producción, mantenimiento, …) se configura en la misma pantalla de destinatarios con un campo de tipo, o basta un único grupo "Comité de cambio" para la v1?

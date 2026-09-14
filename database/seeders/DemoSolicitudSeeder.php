<?php

namespace Database\Seeders;

use App\Domain\GestionCambio\CalculoRiesgo;
use App\Domain\GestionCambio\EvaluadorRubrica;
use App\Domain\GestionCambio\GeneradorConsecutivo;
use App\Domain\GestionCambio\RegistrarRespuesta;
use App\Domain\GestionCambio\SincronizadorPlanRiesgos;
use App\Enums\EstadoSolicitud;
use App\Enums\TipoCambio;
use App\Enums\ValorRespuesta;
use App\Models\CriterioRubrica;
use App\Models\PreguntaClave;
use App\Models\Proceso;
use App\Models\SolicitudCambio;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Recrea la solicitud de ejemplo con la que se partió: "Implementación del sistema CIP en
 * Volpack 1 y Volpack 2" (Ejemplo_Gestion_Cambio_CIP_Volpack). Responde "Sí" a un conjunto de
 * preguntas clave para que se precarguen las secciones 3 y 4, califica los riesgos, la rúbrica,
 * el plan de acción y los criterios de cierre. Queda en estado borrador para poder explorarla.
 */
class DemoSolicitudSeeder extends Seeder
{
    /** Procesos del catálogo y cuántas de sus primeras preguntas se responden "Sí". */
    private const RESPUESTAS_SI = [
        'Dirección Estratégica' => 2,
        'Producción' => 4,
        'Seguridad Alimentaria / BPM - Microbiología' => 4,
        'SST' => 4,
        'Gestión Ambiental' => 3,
        'Mantenimiento y calibración' => 4,
        'Tecnología' => 3,
        'Gente y Cultura' => 3,
        'Gestión Integral (SIG)' => 2,
        'Compras' => 3,
        'Planeación' => 2,
        'Financiero / Costos' => 2,
    ];

    public function run(): void
    {
        $responsable = User::where('email', 'solicitante@example.com')->first()
            ?? User::factory()->create(['name' => 'Solicitante Demo', 'email' => 'solicitante@example.com']);

        if (! $responsable->hasRole('solicitante')) {
            $responsable->assignRole('solicitante');
        }

        $aprobador = User::where('email', 'aprobador@example.com')->first();

        // Idempotencia: si ya existe la de ejemplo, se recrea desde cero.
        SolicitudCambio::where('nombre_cambio', 'like', '%CIP en Volpack%')->get()
            ->each(fn (SolicitudCambio $s) => $s->delete());

        $solicitud = SolicitudCambio::create([
            'consecutivo' => GeneradorConsecutivo::siguiente(2026),
            'fecha' => '2026-08-26',
            'nombre_cambio' => 'Implementación del sistema CIP en Volpack 1 y Volpack 2',
            'solicitante_cargo' => 'Jefatura de Producción / Líder del cambio',
            'area_proceso' => 'Producción',
            'tipo_cambio' => TipoCambio::Permanente->value,
            'clasificacion_manual' => 'Mayor',
            'fecha_requerida' => '2026-11-30',
            'costo_estimado' => 120_000_000,
            'requiere_comite' => true,
            'aprobador_id' => $aprobador?->id,
            'situacion_actual' => 'La limpieza de los equipos Volpack 1 y 2 se ejecuta con intervención manual y desmontajes parciales. Los parámetros, tiempos y evidencias pueden variar entre turnos, lo que aumenta tiempos improductivos, exposición del personal y riesgo de limpieza insuficiente.',
            'que_cambiara' => 'Instalar, integrar y validar un sistema CIP automatizado para Volpack 1 y 2, con circuitos, tanque(s), bombas, válvulas, instrumentación, recetas, alarmas, enjuague, dosificación y registro de variables críticas. Se actualizarán métodos de limpieza, mantenimiento, SST, inocuidad y capacitación.',
            'resultado_esperado' => 'Sistema liberado tras FAT/SAT, calificación y validación sanitaria. Criterios: cobertura de limpieza aprobada, ausencia de residuos según límites definidos, parámetros trazables, 100 % de operadores/mantenimiento competentes, cero fugas y reducción esperada >=20 % del tiempo de limpieza sin afectar inocuidad, calidad o disponibilidad.',
            'estado' => EstadoSolicitud::Solicitado,
            'created_by' => $responsable->id,
            'updated_by' => $responsable->id,
        ]);

        $this->responderCuestionario($solicitud);
        $this->calificarRiesgos($solicitud);
        $this->calificarRubrica($solicitud);
        $this->planDeAccion($solicitud);
        $this->criteriosDeCierre($solicitud);

        $this->command?->info("Solicitud de ejemplo creada: {$solicitud->consecutivo}");
    }

    private function responderCuestionario(SolicitudCambio $solicitud): void
    {
        $registrar = app(RegistrarRespuesta::class);

        foreach (self::RESPUESTAS_SI as $nombreProceso => $cuantas) {
            $proceso = Proceso::where('nombre', $nombreProceso)->first();

            if (! $proceso) {
                continue;
            }

            $proceso->preguntasClave()
                ->where('activo', true)
                ->orderBy('orden')->orderBy('id')
                ->take($cuantas)->get()
                ->each(fn (PreguntaClave $p) => $registrar($solicitud, $p, ValorRespuesta::Si));
        }
    }

    private function calificarRiesgos(SolicitudCambio $solicitud): void
    {
        // Mezcla de niveles Bajo / Medio / Alto usando la escala nominal (1,3,5,7,10).
        $pares = [[3, 3], [3, 10], [3, 7], [5, 7], [10, 7], [1, 10], [5, 5], [3, 10], [7, 10], [3, 5], [5, 7], [1, 7]];
        $i = 0;

        foreach ($solicitud->riesgosAsociados()->orderBy('id')->get() as $fila) {
            [$p, $imp] = $pares[$i % count($pares)];
            $i++;

            $calc = CalculoRiesgo::evaluar($p, $imp);

            $fila->update([
                'control_existente' => 'Método manual e inspección preoperacional.',
                'accion_requerida' => 'Definir, validar y documentar el control asociado al cambio.',
                'responsable' => 'Líder de proceso',
                'fecha' => '2026-11-15',
                'probabilidad' => $p,
                'impacto' => $imp,
                'nr' => $calc['nr'],
                'nivel' => $calc['nivel'],
                'editado_manualmente' => true,
            ]);
        }

        app(SincronizadorPlanRiesgos::class)->sincronizar($solicitud->fresh());
    }

    private function calificarRubrica(SolicitudCambio $solicitud): void
    {
        $evaluador = app(EvaluadorRubrica::class);
        // Suma 20 -> clasificación "Mayor".
        $valores = [2, 1, 2, 2, 2, 2, 2, 1, 2, 3, 1];

        CriterioRubrica::orderBy('orden')->get()->values()
            ->each(function (CriterioRubrica $criterio, int $idx) use ($evaluador, $solicitud, $valores) {
                $evaluador->calificar($solicitud, $criterio->id, $valores[$idx] ?? 2);
            });
    }

    private function planDeAccion(SolicitudCambio $solicitud): void
    {
        $acciones = [
            ['Aprobar alcance técnico, URS, presupuesto y gobierno del cambio.', 'Dirección / Ingeniería', 'Gerente de Operaciones', '2026-09-10', 'En curso'],
            ['Homologar proveedor y verificar materiales, químicos y repuestos aptos.', 'Compras / Calidad', 'Jefe de Compras', '2026-09-25', 'Pendiente'],
            ['Realizar ingeniería de detalle y análisis de servicios/capacidad.', 'Mantenimiento', 'Jefe de Mantenimiento', '2026-10-05', 'Pendiente'],
            ['Actualizar matrices SST, ambiental, inocuidad y riesgos del proceso.', 'SIG', 'Jefe SIG', '2026-10-10', 'Pendiente'],
            ['Ejecutar FAT y cerrar desviaciones del fabricante.', 'Ingeniería / Calidad', 'Líder de Proyecto', '2026-10-20', 'Pendiente'],
            ['Validar recetas CIP, enjuague, residuos y microbiología.', 'Calidad / Inocuidad', 'Líder de Inocuidad', '2026-11-18', 'Pendiente'],
        ];

        foreach ($acciones as $n => [$desc, $proceso, $resp, $fecha, $estado]) {
            $solicitud->accionesPlan()->create([
                'numero' => $n + 1,
                'descripcion' => $desc,
                'proceso' => $proceso,
                'responsable' => $resp,
                'fecha' => $fecha,
                'estado' => $estado,
            ]);
        }
    }

    private function criteriosDeCierre(SolicitudCambio $solicitud): void
    {
        $criterios = [
            ['Alcance, presupuesto y recursos aprobados', 'NO', 'Pendiente de acta del comité de cambios.', 'Gerente de Operaciones'],
            ['Instalación, FAT/SAT y seguridades conformes', 'NO', 'Pendiente de ejecución y cierre de punch list.', 'Líder de Proyecto'],
            ['Validación sanitaria y de calidad aprobada', 'NO', 'Requiere informe de validación y liberación formal.', 'Líder de Inocuidad'],
            ['Documentos, matrices, mantenimiento y calibración actualizados', 'NO', 'Pendiente de publicación y carga en sistemas.', 'Jefe SIG / Mantenimiento'],
            ['Personal capacitado y competente', 'NO', 'Pendiente lograr 100 % de cobertura y competencia.', 'Jefe de Producción'],
            ['Contingencia y respaldos probados', 'NO', 'Pendiente prueba de reversión/restauración.', 'Automatización / Planeación'],
            ['Eficacia verificada durante 8 semanas', 'NA', 'No aplica aún; se evaluará después de estabilización.', 'Mejora Continua'],
        ];

        foreach ($criterios as [$desc, $valor, $detalle, $resp]) {
            $solicitud->criteriosCierre()->create([
                'descripcion' => $desc,
                'valor' => $valor,
                'detalle' => $detalle,
                'responsable' => $resp,
            ]);
        }
    }
}

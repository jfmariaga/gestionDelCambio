<?php

namespace Database\Seeders;

use App\Domain\GestionCambio\CalculoRiesgo;
use App\Domain\GestionCambio\CongeladorSolicitud;
use App\Domain\GestionCambio\EvaluadorRubrica;
use App\Domain\GestionCambio\GeneradorConsecutivo;
use App\Domain\GestionCambio\RegistrarRespuesta;
use App\Domain\GestionCambio\SincronizadorPlanRiesgos;
use App\Domain\GestionCambio\TransicionSolicitud;
use App\Enums\EstadoAccionPlan;
use App\Enums\EstadoSolicitud;
use App\Enums\TipoCambio;
use App\Enums\ValorRespuesta;
use App\Models\AprobacionSolicitud;
use App\Models\CriterioRubrica;
use App\Models\PreguntaClave;
use App\Models\Proceso;
use App\Models\SolicitudCambio;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Http\UploadedFile;

/**
 * UN caso 100% completo y CERRADO, con adjuntos reales, para poder ver cómo se ven el resumen
 * (show.blade.php) y el PDF exportado (exports/solicitud-fosig02.blade.php) cuando todo el
 * formato está diligenciado: cuestionario respondido, riesgos calificados (incluye Medio/Alto
 * con su acción de plan sincronizada), rúbrica de evaluación, plan de acción 100% validado con
 * evidencias adjuntas, criterios de cierre con evidencias adjuntas, las dos compuertas de
 * aprobación superadas y la solicitud cerrada.
 *
 * Ejecutar con:  php artisan db:seed --class=CasoCompletoSeeder
 *
 * Es idempotente: borra el caso anterior (mismo nombre_cambio) y lo vuelve a crear.
 */
class CasoCompletoSeeder extends Seeder
{
    private const NOMBRE_CAMBIO = 'Actualización del sistema de pasteurización HTST — Planta Panal';

    /** Procesos del catálogo y cuántas de sus primeras preguntas clave se responden "Sí". */
    private const RESPUESTAS_SI = [
        'Producción' => 4,
        'Seguridad Alimentaria / BPM - Microbiología' => 4,
        'SST' => 3,
        'Gestión Ambiental' => 2,
        'Mantenimiento y calibración' => 4,
        'Tecnología' => 2,
        'Gestión Integral (SIG)' => 2,
        'Compras' => 2,
    ];

    public function run(): void
    {
        SolicitudCambio::where('nombre_cambio', self::NOMBRE_CAMBIO)->get()
            ->each(fn (SolicitudCambio $s) => $s->delete());

        $lider = User::where('email', 'solicitante@example.com')->first()
            ?? User::factory()->create(['name' => 'Solicitante Demo', 'email' => 'solicitante@example.com']);
        if (! $lider->hasRole('solicitante')) {
            $lider->assignRole('solicitante');
        }

        $aprobador = User::where('email', 'aprobador@example.com')->first()
            ?? User::factory()->create(['name' => 'Aprobador Demo', 'email' => 'aprobador@example.com']);
        if (! $aprobador->hasRole('aprobador')) {
            $aprobador->assignRole('aprobador');
        }

        if (Proceso::count() === 0 || CriterioRubrica::count() < 11) {
            $this->command?->warn('Ejecuta primero CatalogoSeeder (procesos, preguntas clave y rúbrica) antes de CasoCompletoSeeder.');

            return;
        }

        $solicitud = SolicitudCambio::create([
            'consecutivo' => GeneradorConsecutivo::siguiente(),
            'fecha' => now()->subMonths(3)->toDateString(),
            'nombre_cambio' => self::NOMBRE_CAMBIO,
            'solicitante_cargo' => 'Jefatura de Producción / Líder del cambio',
            'area_proceso' => 'Producción',
            'tipo_cambio' => TipoCambio::Permanente->value,
            'fecha_requerida' => now()->subMonth()->toDateString(),
            'costo_estimado' => 85_000_000,
            'requiere_comite' => true,
            'situacion_actual' => 'El pasteurizador HTST actual opera con controlador analógico, sin registro electrónico de temperatura/tiempo y con paradas frecuentes por desajuste de válvulas de desvío de flujo, lo que genera reprocesos y riesgo de subprocesamiento.',
            'que_cambiara' => 'Reemplazo del controlador por un PLC con registro electrónico trazable (temperatura, tiempo de retención, válvula de desvío), integración con el sistema de trazabilidad y recalibración de instrumentación crítica.',
            'resultado_esperado' => 'Pasteurización validada con registro electrónico 100% trazable, cero desviaciones de temperatura/tiempo no registradas, reducción de paradas no programadas y personal capacitado en el nuevo HMI.',
            'estado' => EstadoSolicitud::Solicitado,
            'created_by' => $lider->id,
            'updated_by' => $lider->id,
        ]);

        $this->responderCuestionario($solicitud);
        $this->calificarRiesgos($solicitud);
        $this->calificarRubrica($solicitud); // suma 22 -> clasificación "Mayor"
        $this->planDeAccionCompletoConAdjuntos($solicitud, $lider, $aprobador);
        $this->criteriosDeCierreCompletosConAdjuntos($solicitud, $lider, $aprobador);

        $this->llevarACerrado($solicitud, $lider, $aprobador);

        $this->command?->info("Caso completo creado y cerrado: {$solicitud->fresh()->consecutivo} — {$solicitud->nombre_cambio}");
        $this->command?->info('Ver resumen:  '.route('solicitudes.show', $solicitud));
        $this->command?->info('Exportar PDF: '.route('solicitudes.exportar', $solicitud));
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
        // Mezcla deliberada de niveles Bajo / Medio / Alto (NR = probabilidad × impacto).
        $pares = [[3, 7], [7, 10], [10, 7], [5, 5], [3, 3], [5, 7], [1, 7], [10, 10], [3, 5], [7, 7]];
        $i = 0;

        foreach ($solicitud->riesgosAsociados()->orderBy('id')->get() as $fila) {
            [$p, $imp] = $pares[$i % count($pares)];
            $i++;

            $calc = CalculoRiesgo::evaluar($p, $imp);

            $fila->update([
                'control_existente' => 'Inspección visual y registro manual en bitácora de planta.',
                'accion_requerida' => 'Definir, validar y documentar el control digital asociado al cambio.',
                'responsable' => 'Líder de proceso',
                'fecha' => now()->subMonths(2)->toDateString(),
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
        $criterios = CriterioRubrica::orderBy('orden')->pluck('id');

        // Reparte una suma de 22 entre los 11 criterios (base 1 c/u) -> clasificación "Mayor".
        $suma = 22;
        $extra = $suma - $criterios->count();

        foreach ($criterios as $id) {
            $valor = 1;
            if ($extra > 0) {
                $sube = min(2, $extra);
                $valor += $sube;
                $extra -= $sube;
            }
            $evaluador->calificar($solicitud, $id, $valor);
        }
    }

    /** Todas las acciones (manuales + las auto-sincronizadas de riesgos Medio/Alto) quedan Validadas, con adjuntos. */
    private function planDeAccionCompletoConAdjuntos(SolicitudCambio $solicitud, User $lider, User $aprobador): void
    {
        $manuales = [
            ['Aprobar alcance técnico, URS, presupuesto y gobierno del cambio.', 'Dirección / Ingeniería', 'Gerente de Operaciones'],
            ['Homologar proveedor del PLC y verificar instrumentación certificada.', 'Compras / Calidad', 'Jefe de Compras'],
            ['Ejecutar FAT del controlador y cerrar desviaciones del fabricante.', 'Ingeniería / Calidad', 'Líder de Proyecto'],
            ['Recalibrar instrumentación crítica (temperatura, caudal, válvula de desvío).', 'Mantenimiento y calibración', 'Jefe de Mantenimiento'],
            ['Validar el registro electrónico de temperatura/tiempo (SAT) y trazabilidad.', 'Calidad / Inocuidad', 'Líder de Inocuidad'],
            ['Capacitar a operadores en el nuevo HMI y actualizar procedimientos.', 'Producción', 'Jefe de Producción'],
        ];

        $siguienteNumero = ($solicitud->accionesPlan()->max('numero') ?? 0) + 1;

        foreach ($manuales as $n => [$desc, $proceso, $respNombre]) {
            $solicitud->accionesPlan()->create([
                'numero' => $siguienteNumero + $n,
                'descripcion' => $desc,
                'proceso' => $proceso,
                'responsable' => $respNombre,
                'responsable_id' => $lider->id,
                'creador_id' => $lider->id,
                'fecha' => now()->subMonth()->toDateString(),
                'estado' => EstadoAccionPlan::Pendiente->value,
            ]);
        }

        // Validar TODAS las acciones (manuales + las que sincronizó el riesgo Medio/Alto).
        $acciones = $solicitud->accionesPlan()->orderBy('numero')->get();

        foreach ($acciones as $i => $accion) {
            $accion->update([
                'responsable_id' => $accion->responsable_id ?? $lider->id,
                'responsable' => $accion->responsable ?: ($accion->responsableUsuario?->name ?? $lider->name),
                'fecha' => $accion->fecha ?? now()->subMonth()->toDateString(),
                'estado' => EstadoAccionPlan::Validada->value,
                'validada_por' => $aprobador->id,
                'validada_at' => now()->subWeeks(2),
                'evidencia' => 'Evidencia documental adjunta y verificada en sitio.',
            ]);

            // Adjuntos reales (simulados) en un par de acciones representativas.
            if ($i === 3) {
                $accion->agregarAdjunto(
                    UploadedFile::fake()->create('certificado_calibracion_instrumentos.pdf', 180, 'application/pdf'),
                    $lider->id,
                    config('gestioncambio.adjunto_disco')
                );
            }
            if ($i === 4) {
                $accion->agregarAdjunto(
                    UploadedFile::fake()->create('informe_validacion_SAT.pdf', 240, 'application/pdf'),
                    $aprobador->id,
                    config('gestioncambio.adjunto_disco')
                );
                $accion->agregarAdjunto(
                    UploadedFile::fake()->image('registro_temperatura_grafico.jpg', 800, 600),
                    $aprobador->id,
                    config('gestioncambio.adjunto_disco')
                );
            }
        }
    }

    private function criteriosDeCierreCompletosConAdjuntos(SolicitudCambio $solicitud, User $lider, User $aprobador): void
    {
        $criterios = [
            ['Alcance, presupuesto y recursos aprobados', 'SI', 'Acta del comité de cambios del 12/07.', 'Gerente de Operaciones'],
            ['Instalación, FAT/SAT y seguridades conformes', 'SI', 'FAT y SAT cerrados sin desviaciones abiertas.', 'Líder de Proyecto'],
            ['Validación sanitaria y de calidad aprobada', 'SI', 'Informe de validación firmado por Calidad.', 'Líder de Inocuidad'],
            ['Documentos, matrices, mantenimiento y calibración actualizados', 'SI', 'Procedimientos y matrices publicados en el SIG.', 'Jefe SIG / Mantenimiento'],
            ['Personal capacitado y competente', 'SI', '100% de operadores evaluados como competentes.', 'Jefe de Producción'],
            ['Contingencia y respaldos probados', 'SI', 'Prueba de reversión ejecutada sin hallazgos.', 'Automatización / Planeación'],
            ['Eficacia verificada durante 8 semanas', 'SI', 'Cero desviaciones de temperatura/tiempo en 8 semanas de seguimiento.', 'Mejora Continua'],
        ];

        foreach ($criterios as $n => [$desc, $valor, $detalle, $respNombre]) {
            $criterio = $solicitud->criteriosCierre()->create([
                'descripcion' => $desc,
                'valor' => $valor,
                'detalle' => $detalle,
                'responsable' => $respNombre,
                'responsable_id' => $lider->id,
            ]);

            if ($n === 0) {
                $criterio->agregarAdjunto(
                    UploadedFile::fake()->create('acta_comite_de_cambios.pdf', 90, 'application/pdf'),
                    $lider->id,
                    config('gestioncambio.adjunto_disco')
                );
            }
            if ($n === 4) {
                $criterio->agregarAdjunto(
                    UploadedFile::fake()->create('listado_asistencia_capacitacion.pdf', 60, 'application/pdf'),
                    $lider->id,
                    config('gestioncambio.adjunto_disco')
                );
            }
        }
    }

    /** Empuja la solicitud por las dos compuertas de aprobación hasta "Cerrado". */
    private function llevarACerrado(SolicitudCambio $solicitud, User $lider, User $aprobador): void
    {
        $transicion = app(TransicionSolicitud::class);

        // Compuerta 1 (inicial): enviar a evaluación y aprobar.
        app(CongeladorSolicitud::class)->enviarAAprobacion($solicitud, $lider->id);
        $this->aprobarTodaLaEtapa($solicitud, $aprobador, AprobacionSolicitud::ETAPA_INICIAL);
        $solicitud->forceFill(['estado' => EstadoSolicitud::Aprobado])->save();

        // Implementación: ya dejamos el plan 100% validado antes de llegar aquí.
        $transicion->iniciarImplementacion($solicitud, $lider->id);
        $transicion->marcarImplementadoSiCorresponde($solicitud->fresh(), $lider->id);

        // Compuerta 2 (cierre): enviar a verificación, aprobar y cerrar.
        $transicion->enviarAVerificacion($solicitud->fresh(), $lider->id);
        $this->aprobarTodaLaEtapa($solicitud->fresh(), $aprobador, AprobacionSolicitud::ETAPA_CIERRE);

        $bloqueos = $transicion->cerrar($solicitud->fresh(), $aprobador->id);

        if ($bloqueos !== []) {
            $this->command?->warn('No se pudo cerrar automáticamente: '.implode(' ', $bloqueos));
        }
    }

    /**
     * Aprueba la etapa completa sin depender de la resolución automática de aprobadores
     * (áreas de notificación / dueños de proceso pueden no estar configurados en el catálogo
     * de demo): asegura una fila para $aprobador y aprueba cualquier otra fila pendiente.
     */
    private function aprobarTodaLaEtapa(SolicitudCambio $solicitud, User $aprobador, string $etapa): void
    {
        AprobacionSolicitud::updateOrCreate(
            ['solicitud_cambio_id' => $solicitud->id, 'user_id' => $aprobador->id, 'etapa' => $etapa],
            ['decision' => AprobacionSolicitud::APROBADO, 'decidido_at' => now()],
        );

        AprobacionSolicitud::where('solicitud_cambio_id', $solicitud->id)
            ->where('etapa', $etapa)
            ->update(['decision' => AprobacionSolicitud::APROBADO, 'decidido_at' => now()]);
    }
}

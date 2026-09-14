<?php

namespace Database\Seeders;

use App\Domain\GestionCambio\EvaluadorRubrica;
use App\Enums\EstadoAccionPlan;
use App\Enums\EstadoSolicitud;
use App\Enums\TipoCambio;
use App\Models\CriterioRubrica;
use App\Models\Proceso;
use App\Models\SolicitudCambio;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Datos de ejemplo para ENSAYAR el panel de métricas del administrador (/dashboard): variedad
 * de líderes de cambio, responsables de acciones, procesos, tipos de cambio, clasificaciones y
 * estados, más un plan de acción con acciones vencidas, próximas a vencer, en proceso y
 * validadas repartidas en los últimos meses (para la tabla por responsable y la evolución
 * mensual). Así se pueden probar las 9 tarjetas, su detalle al hacer clic, y cada filtro.
 *
 * Ejecutar con:  php artisan db:seed --class=DashboardDemoSeeder
 *
 * Es idempotente: primero borra las solicitudes previas marcadas con el prefijo "[DEMO]" y las
 * vuelve a crear, así que se puede correr varias veces sin acumular datos duplicados.
 */
class DashboardDemoSeeder extends Seeder
{
    private const MARCA = '[DEMO] ';

    /** Nombre => rol de los usuarios usados como líderes del cambio / responsables de acciones. */
    private const USUARIOS = [
        'Camila Rojas' => 'solicitante',
        'Andrés Gómez' => 'solicitante',
        'Laura Méndez' => 'solicitante',
        'Julián Ortiz' => 'dueno_proceso',
        'Sofía Herrera' => 'dueno_proceso',
        'Mateo Restrepo' => 'aprobador',
    ];

    /**
     * Una fila por solicitud demo: [título, estado, suma de la rúbrica (define la
     * clasificación), tipo de cambio, meses de desfase respecto a hoy (negativo = pasado)].
     */
    private const SOLICITUDES = [
        ['Automatización de línea de empaque', EstadoSolicitud::Solicitado, 20, TipoCambio::Permanente, 0],
        ['Cambio temporal de proveedor de insumo crítico', EstadoSolicitud::Solicitado, 13, TipoCambio::Temporal, 0],
        ['Parada de emergencia por falla de caldera', EstadoSolicitud::Solicitado, 28, TipoCambio::Emergente, -1],
        ['Actualización de receta de producto estrella', EstadoSolicitud::EnEvaluacion, 18, TipoCambio::Permanente, -1],
        ['Piloto de nuevo empaque biodegradable', EstadoSolicitud::EnEvaluacion, 15, TipoCambio::PilotoPrueba, -2],
        ['Modernización del sistema CIP', EstadoSolicitud::Aprobado, 22, TipoCambio::Permanente, -2],
        ['Reversión de cambio en dosificación', EstadoSolicitud::Aprobado, 14, TipoCambio::Reversion, -3],
        ['Instalación de nueva báscula de recepción', EstadoSolicitud::EnImplementacion, 19, TipoCambio::Permanente, -3],
        ['Cambio urgente de proveedor de empaque', EstadoSolicitud::EnImplementacion, 26, TipoCambio::Emergente, -4],
        ['Actualización de software de trazabilidad', EstadoSolicitud::Implementado, 17, TipoCambio::Permanente, -4],
        ['Ajuste de parámetros de pasteurización', EstadoSolicitud::EnVerificacion, 21, TipoCambio::Temporal, -5],
        ['Cambio de layout de bodega de materia prima', EstadoSolicitud::Cerrado, 13, TipoCambio::Permanente, -5],
        ['Migración de sistema de gestión documental', EstadoSolicitud::Cerrado, 24, TipoCambio::Permanente, -6],
        ['Cambio de turno piloto en Volpack 2', EstadoSolicitud::Cancelado, 16, TipoCambio::Temporal, -2],
    ];

    /** @var list<User> */
    private array $usuarios = [];

    /** @var list<string> */
    private array $procesos = [];

    public function run(): void
    {
        SolicitudCambio::where('nombre_cambio', 'like', self::MARCA.'%')->get()
            ->each(fn (SolicitudCambio $s) => $s->delete());

        $this->usuarios = $this->crearUsuarios();
        $this->procesos = Proceso::activos()->ordenados()->pluck('nombre')->all();

        if ($this->procesos === [] || CriterioRubrica::count() < 11) {
            $this->command?->warn('Ejecuta primero el CatalogoSeeder (catálogo de procesos y rúbrica de evaluación) antes de DashboardDemoSeeder.');

            return;
        }

        $evaluador = app(EvaluadorRubrica::class);
        $criterios = CriterioRubrica::orderBy('orden')->pluck('id');

        foreach (self::SOLICITUDES as $i => [$titulo, $estado, $suma, $tipo, $mesesOffset]) {
            $lider = $this->usuarios[$i % count($this->usuarios)];
            $proceso = $this->procesos[$i % count($this->procesos)];
            $fecha = now()->addMonths($mesesOffset)->startOfMonth()->addDays(5 + $i);

            $solicitud = SolicitudCambio::factory()->create([
                'nombre_cambio' => self::MARCA.$titulo,
                'area_proceso' => $proceso,
                'tipo_cambio' => $tipo->value,
                'fecha' => $fecha->toDateString(),
                'fecha_requerida' => $fecha->copy()->addMonths(2)->toDateString(),
                'estado' => $estado->value,
                'created_by' => $lider->id,
                'updated_by' => $lider->id,
            ]);

            $this->calificarConSuma($evaluador, $solicitud, $criterios, $suma);
            $this->crearPlanDeAccion($solicitud, $i);
        }

        $this->command?->info('Datos de prueba del panel de métricas creados: '.count(self::SOLICITUDES).' solicitudes "[DEMO]" con su plan de acción.');
    }

    /** @return list<User> */
    private function crearUsuarios(): array
    {
        return collect(self::USUARIOS)
            ->map(function (string $rol, string $nombre) {
                $email = Str::slug($nombre).'@demo.test';
                $user = User::where('email', $email)->first()
                    ?? User::factory()->create(['name' => $nombre, 'email' => $email]);

                if (! $user->hasRole($rol)) {
                    $user->assignRole($rol);
                }

                return $user;
            })
            ->values()
            ->all();
    }

    /** Reparte $suma entre los criterios (base 1 c/u) para obtener la clasificación deseada. */
    private function calificarConSuma(EvaluadorRubrica $evaluador, SolicitudCambio $solicitud, Collection $criterioIds, int $suma): void
    {
        $extra = $suma - $criterioIds->count();

        foreach ($criterioIds as $id) {
            $valor = 1;
            if ($extra > 0) {
                $sube = min(2, $extra);
                $valor += $sube;
                $extra -= $sube;
            }
            $evaluador->calificar($solicitud, $id, $valor);
        }
    }

    /**
     * 4 acciones por solicitud: una ya validada (mes atrás, para la evolución mensual), una
     * vencida, una próxima a vencer y una futura sin vencer todavía.
     */
    private function crearPlanDeAccion(SolicitudCambio $solicitud, int $i): void
    {
        $diasProximoVencimiento = (int) config('gestioncambio.dias_proximo_vencimiento', 7);
        $hoy = now();

        $definiciones = [
            ['Definir y documentar el control asociado', EstadoAccionPlan::Validada, -30, true],
            ['Actualizar procedimiento operativo', $i % 2 === 0 ? EstadoAccionPlan::EnCurso : EstadoAccionPlan::Pendiente, -(2 + $i % 6), false],
            ['Verificar cumplimiento con el responsable del proceso', EstadoAccionPlan::Pendiente, 1 + $i % max(1, $diasProximoVencimiento), false],
            ['Capacitar al equipo involucrado', EstadoAccionPlan::CerradaPendienteValidacion, 20 + $i, false],
        ];

        foreach ($definiciones as $n => [$descripcion, $estado, $offsetDias, $validada]) {
            $responsable = $this->usuarios[($i + $n) % count($this->usuarios)];
            $fecha = $hoy->copy()->addDays($offsetDias);
            $creadaEl = $fecha->copy()->subDays(25);

            $accion = $solicitud->accionesPlan()->create([
                'numero' => $n + 1,
                'descripcion' => $descripcion.' — '.$solicitud->area_proceso,
                'proceso' => $solicitud->area_proceso,
                'responsable_id' => $responsable->id,
                'responsable' => $responsable->name,
                'creador_id' => $solicitud->created_by,
                'fecha' => $fecha->toDateString(),
                'estado' => $estado->value,
                'validada_por' => $validada ? $responsable->id : null,
                'validada_at' => $validada ? $fecha->copy()->addDays(5)->min($hoy) : null,
            ]);

            // created_at no es "fillable"; se ajusta aparte para que la evolución mensual
            // (acciones "programadas" por mes de creación) tenga datos en varios meses.
            $accion->forceFill(['created_at' => $creadaEl])->save();
        }
    }
}

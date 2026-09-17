<?php

namespace App\Livewire\Solicitud;

use App\Domain\GestionCambio\RegistrarRespuesta;
use App\Domain\GestionCambio\SincronizadorRiesgos;
use App\Enums\ValorRespuesta;
use App\Models\PreguntaClave;
use App\Models\Proceso;
use App\Models\SolicitudCambio;
use Livewire\Attributes\Computed;
use Livewire\Component;

class Cuestionario extends Component
{
    public SolicitudCambio $solicitud;

    /** @var array<int,string> pregunta_id => 'SI'|'NO'|'NA' */
    public array $respuestas = [];

    public ?int $procesoActivo = null;

    public function mount(SolicitudCambio $solicitud): void
    {
        // Poder ver la solicitud (p. ej. un responsable de tarea del plan) O poder completar
        // secciones (p. ej. un dueño de proceso sin filas asignadas todavía) alcanza para
        // montar la página; responder() exige el permiso real de escritura.
        $user = auth()->user();
        abort_unless($user->can('view', $solicitud) || $user->can('completarSecciones', $solicitud), 403);
        $this->solicitud = $solicitud;
        $this->respuestas = $solicitud->respuestas()
            ->pluck('valor', 'pregunta_clave_id')
            ->map(fn ($v) => $v instanceof ValorRespuesta ? $v->value : $v)
            ->all();

        $this->procesoActivo = $this->procesos->first()?->id;
    }

    #[Computed]
    public function procesos()
    {
        return Proceso::activos()->ordenados()
            ->whereHas('preguntasClave', fn ($q) => $q->activas())
            ->withCount(['preguntasClave as preguntas_total' => fn ($q) => $q->activas()])
            ->get();
    }

    #[Computed]
    public function preguntasDelProceso()
    {
        if (! $this->procesoActivo) {
            return collect();
        }

        return PreguntaClave::activas()
            ->with('riesgoPredeterminado')
            ->where('proceso_id', $this->procesoActivo)
            ->orderBy('orden')->orderBy('id')
            ->get();
    }

    #[Computed]
    public function avance(): array
    {
        $total = PreguntaClave::activas()->count();
        $respondidas = collect($this->respuestas)->filter()->count();

        return ['respondidas' => $respondidas, 'total' => $total];
    }

    /**
     * Preguntas respondidas por proceso: [proceso_id => count].
     *
     * @return array<int,int>
     */
    #[Computed]
    public function respondidasPorProceso(): array
    {
        if ($this->respuestas === []) {
            return [];
        }

        return PreguntaClave::activas()
            ->whereIn('id', array_keys(array_filter($this->respuestas)))
            ->selectRaw('proceso_id, count(*) as total')
            ->groupBy('proceso_id')
            ->pluck('total', 'proceso_id')
            ->all();
    }

    #[Computed]
    public function advertenciasSinRiesgo()
    {
        return app(SincronizadorRiesgos::class)->preguntasSiSinRiesgo($this->solicitud->fresh());
    }

    public function irAProceso(int $procesoId): void
    {
        $this->procesoActivo = $procesoId;
    }

    public function responder(int $preguntaId, string $valor): void
    {
        $this->authorize('completarSecciones', $this->solicitud);

        $enum = ValorRespuesta::tryFrom($valor);
        abort_if($enum === null, 422, 'Valor de respuesta inválido.');

        if (! $this->solicitud->estado->esEditable() || $this->solicitud->estaCongelada()) {
            $this->dispatch('solicitud-bloqueada');
            $this->dispatch('toast', icon: 'error', title: 'La solicitud está bloqueada y no admite cambios.');

            return;
        }

        $completoAntes = $this->solicitud->cuestionarioCompleto();

        $pregunta = PreguntaClave::findOrFail($preguntaId);
        app(RegistrarRespuesta::class)($this->solicitud, $pregunta, $enum);

        $this->respuestas[$preguntaId] = $valor;

        unset($this->avance, $this->advertenciasSinRiesgo, $this->respondidasPorProceso);
        $this->dispatch('secciones-actualizadas');
        $this->dispatch('toast', icon: 'success', title: 'Respuesta guardada.');

        // FR-045…FR-049: al llegar al 100%, recargar para habilitar riesgos/plan/cierre sin que
        // el usuario tenga que refrescar la página a mano (el gating de secciones se evalúa una
        // sola vez al renderizar edit.blade.php).
        if (! $completoAntes && $this->solicitud->fresh()->cuestionarioCompleto()) {
            $this->redirectRoute('solicitudes.edit', $this->solicitud, navigate: true);
        }
    }

    public function render()
    {
        return view('livewire.solicitud.cuestionario');
    }
}

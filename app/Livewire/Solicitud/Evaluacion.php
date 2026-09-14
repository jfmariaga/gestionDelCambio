<?php

namespace App\Livewire\Solicitud;

use App\Domain\GestionCambio\EvaluadorRubrica;
use App\Models\CriterioRubrica;
use App\Models\SolicitudCambio;
use Livewire\Attributes\Computed;
use Livewire\Component;

class Evaluacion extends Component
{
    public SolicitudCambio $solicitud;

    /** @var array<int,int> criterio_id => 1..3 */
    public array $calificaciones = [];

    public function mount(SolicitudCambio $solicitud): void
    {
        $this->authorize('view', $solicitud);
        $this->solicitud = $solicitud;
        $evaluacion = $solicitud->evaluacion;

        if ($evaluacion) {
            $this->calificaciones = $evaluacion->calificaciones()
                ->pluck('valor', 'criterio_rubrica_id')->all();
        }
    }

    #[Computed]
    public function criterios()
    {
        return CriterioRubrica::ordenados()->get();
    }

    #[Computed]
    public function resultado(): array
    {
        $evaluacion = $this->solicitud->evaluacion;

        if (! $evaluacion) {
            return ['completa' => false, 'suma' => null, 'clasificacion' => null, 'faltan' => $this->criterios->count()];
        }

        return app(EvaluadorRubrica::class)->recalcular($evaluacion);
    }

    public function calificar(int $criterioId, int $valor): void
    {
        if (! $this->solicitud->estado->esEditable() || $this->solicitud->estaCongelada()) {
            return;
        }

        $clasificacionAntes = $this->solicitud->evaluacion?->clasificacion;

        app(EvaluadorRubrica::class)->calificar($this->solicitud, $criterioId, $valor);
        $this->calificaciones[$criterioId] = $valor;

        unset($this->resultado);
        $this->dispatch('evaluacion-actualizada');
        $this->dispatch('toast', icon: 'success', title: 'Calificación guardada.');

        // FR-045…FR-049: si cambió la clasificación, recargar para re-evaluar el gating de
        // secciones y reflejar el aprobador asignado (US7 / US8).
        $clasificacionAhora = $this->solicitud->refresh()->evaluacion?->clasificacion;

        if ($clasificacionAntes !== $clasificacionAhora) {
            $this->dispatch('clasificacion-cambiada');
            $this->redirectRoute('solicitudes.edit', $this->solicitud, navigate: true);
        }
    }

    public function render()
    {
        return view('livewire.solicitud.evaluacion');
    }
}

<?php

namespace App\Livewire\Solicitud;

use App\Domain\GestionCambio\TransicionSolicitud;
use App\Enums\EstadoSolicitud;
use App\Livewire\Concerns\ConListaDeUsuarios;
use App\Models\SolicitudCambio;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

/**
 * Seguimiento y cierre, simplificado a una nota de cierre única: el líder la redacta y, al
 * enviarla, se siembran y notifican los aprobadores de la compuerta 2 (misma resolución de
 * siempre: áreas SIG/SST/Ambiental/Calidad + dueños del proceso + Gerencia si es Crítico). Los
 * bloqueos automáticos (tareas del plan sin validar, riesgos Medio/Alto sin acción) se
 * mantienen y se revisan aparte con "Comprobar requisitos de cierre".
 */
class AprobacionCierre extends Component
{
    use ConListaDeUsuarios;

    public SolicitudCambio $solicitud;

    public string $notaCierre = '';

    /** @var array<int,string> */
    public array $bloqueos = [];

    /** Si ya se corrió comprobarCierre() al menos una vez, para distinguir "sin revisar" de "sin bloqueos". */
    public bool $comprobado = false;

    /** @var array<int,string> bloqueos devueltos por el último intento de "Enviar a cierre". */
    public array $bloqueosEnvio = [];

    public function mount(SolicitudCambio $solicitud): void
    {
        $this->authorize('view', $solicitud);
        $this->solicitud = $solicitud;
        $this->notaCierre = (string) $solicitud->nota_cierre;
    }

    private function esLider(): bool
    {
        return Auth::id() === $this->solicitud->created_by || Auth::user()->hasRole('administrador');
    }

    public function guardar(): void
    {
        abort_unless($this->esLider(), 403);

        $this->solicitud->update(['nota_cierre' => $this->notaCierre]);
        $this->dispatch('toast', icon: 'success', title: 'Nota de cierre guardada.');
    }

    /**
     * Guarda la nota y envía la solicitud a verificación: siembra y notifica a los responsables
     * implicados (compuerta 2) para que aprueben el cierre.
     */
    public function enviarACierre(): void
    {
        $this->authorize('gestionarImplementacion', $this->solicitud);

        $this->solicitud->update(['nota_cierre' => $this->notaCierre]);

        $this->bloqueosEnvio = app(TransicionSolicitud::class)->enviarAVerificacion($this->solicitud, Auth::id());

        if ($this->bloqueosEnvio !== []) {
            $this->dispatch('toast', icon: 'warning', title: $this->bloqueosEnvio[0]);

            return;
        }

        $this->solicitud->refresh();
        $this->dispatch('toast', icon: 'success', title: 'Enviado a verificación. Se notificó a los responsables implicados.');
    }

    public function comprobarCierre(): void
    {
        $this->bloqueos = app(TransicionSolicitud::class)->bloqueosDeCierre($this->solicitud);
        $this->comprobado = true;

        if ($this->bloqueos === []) {
            $this->dispatch('toast', icon: 'success', title: 'Sin bloqueos: la solicitud está lista para cerrarse.');
        } else {
            $this->dispatch('toast', icon: 'warning', title: 'Hay '.count($this->bloqueos).' requisito(s) pendiente(s) de cierre.');
        }
    }

    public function render()
    {
        return view('livewire.solicitud.aprobacion-cierre', [
            'esLider' => $this->esLider(),
            'puedeEnviar' => $this->solicitud->estado === EstadoSolicitud::Implementado,
        ]);
    }
}

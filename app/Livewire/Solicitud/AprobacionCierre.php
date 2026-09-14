<?php

namespace App\Livewire\Solicitud;

use App\Domain\GestionCambio\TransicionSolicitud;
use App\Livewire\Concerns\ConListaDeUsuarios;
use App\Models\AdjuntoEvidencia;
use App\Models\CriterioCierre;
use App\Models\SolicitudCambio;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Component;
use Livewire\WithFileUploads;

class AprobacionCierre extends Component
{
    use ConListaDeUsuarios, WithFileUploads;

    /** @var array<int,UploadedFile|null> */
    public array $nuevoAdjunto = [];

    public SolicitudCambio $solicitud;

    /** @var array<int,array<string,mixed>> */
    public array $edicion = [];

    /** @var array<int,string> */
    public array $bloqueos = [];

    public function mount(SolicitudCambio $solicitud): void
    {
        $this->authorize('view', $solicitud);
        $this->solicitud = $solicitud;
        $this->sincronizarEdicion();
    }

    #[Computed]
    public function criterios()
    {
        return $this->solicitud->criteriosCierre()
            ->with(['responsableUsuario:id,name', 'adjuntos.subidoPor:id,name'])
            ->orderBy('id')->get();
    }

    /**
     * Vuelca los valores actuales en $edicion para que `wire:model` siempre tenga un valor
     * y no se pierdan los campos que el usuario no tocó al guardar.
     */
    private function sincronizarEdicion(?int $soloId = null): void
    {
        foreach ($this->criterios as $criterio) {
            if ($soloId !== null && $criterio->id !== $soloId) {
                continue;
            }

            $this->edicion[$criterio->id] = [
                'descripcion' => $criterio->descripcion,
                'valor' => $criterio->valor,
                'detalle' => $criterio->detalle,
                'responsable_id' => $criterio->responsable_id,
            ];
        }
    }

    private function esLider(): bool
    {
        return Auth::id() === $this->solicitud->created_by || Auth::user()->hasRole('administrador');
    }

    /** Máximo de adjuntos por criterio de cierre (US10). Se suben de a uno. */
    public const MAX_ADJUNTOS = 10;

    public function subirAdjunto(int $id): void
    {
        $criterio = $this->solicitud->criteriosCierre()->findOrFail($id);
        $archivo = $this->nuevoAdjunto[$id] ?? null;
        abort_unless($archivo, 422, 'Seleccione un archivo.');

        if ($criterio->adjuntos()->count() >= self::MAX_ADJUNTOS) {
            $this->addError("nuevoAdjunto.$id", 'Máximo '.self::MAX_ADJUNTOS.' adjuntos por criterio.');

            return;
        }

        $this->validate([
            "nuevoAdjunto.$id" => ['file', 'max:'.config('gestioncambio.adjunto_max_kb')],
        ]);

        $criterio->agregarAdjunto($archivo, Auth::id(), config('gestioncambio.adjunto_disco'));

        unset($this->nuevoAdjunto[$id], $this->criterios);
        $this->dispatch('toast', icon: 'success', title: 'Adjunto subido.');
    }

    public function eliminarAdjunto(int $adjuntoId): void
    {
        $adjunto = AdjuntoEvidencia::whereKey($adjuntoId)
            ->where('adjuntable_type', (new CriterioCierre)->getMorphClass())
            ->firstOrFail();

        abort_unless($adjunto->subido_por === Auth::id() || $this->esLider(), 403);

        $adjunto->delete();
        unset($this->criterios);
        $this->dispatch('toast', icon: 'success', title: 'Adjunto eliminado.');
    }

    public function agregar(): void
    {
        CriterioCierre::create([
            'solicitud_cambio_id' => $this->solicitud->id,
            'descripcion' => 'Nuevo criterio de cierre',
        ]);
        unset($this->criterios);
        $this->sincronizarEdicion();
        $this->dispatch('toast', icon: 'success', title: 'Criterio agregado.');
    }

    public function guardar(int $id): void
    {
        $criterio = $this->solicitud->criteriosCierre()->findOrFail($id);
        $datos = $this->edicion[$id] ?? [];

        $responsableId = ($datos['responsable_id'] ?? $criterio->responsable_id) ?: null;
        $responsableNombre = $responsableId
            ? (User::whereKey($responsableId)->value('name') ?? $criterio->responsable)
            : ($datos['responsable'] ?? $criterio->responsable);

        $criterio->update([
            'descripcion' => $datos['descripcion'] ?? $criterio->descripcion,
            'valor' => ($datos['valor'] ?? $criterio->valor) ?: null,
            'detalle' => $datos['detalle'] ?? $criterio->detalle,
            'responsable_id' => $responsableId,
            'responsable' => $responsableNombre,
        ]);
        unset($this->criterios);
        $this->sincronizarEdicion($id);
        $this->dispatch('toast', icon: 'success', title: 'Criterio guardado.');
    }

    public function eliminar(int $id): void
    {
        $this->solicitud->criteriosCierre()->whereKey($id)->get()->each->delete();
        unset($this->criterios, $this->edicion[$id]);
        $this->dispatch('toast', icon: 'success', title: 'Criterio eliminado.');
    }

    public function comprobarCierre(): void
    {
        $this->bloqueos = app(TransicionSolicitud::class)->bloqueosDeCierre($this->solicitud);
    }

    public function render()
    {
        return view('livewire.solicitud.aprobacion-cierre');
    }
}

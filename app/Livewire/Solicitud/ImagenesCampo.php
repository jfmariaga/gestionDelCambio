<?php

namespace App\Livewire\Solicitud;

use App\Models\AdjuntoEvidencia;
use App\Models\SolicitudCambio;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Component;
use Livewire\WithFileUploads;

class ImagenesCampo extends Component
{
    use WithFileUploads;

    public const MAX_IMAGENES = 5;

    public SolicitudCambio $solicitud;

    public string $campo;

    public ?UploadedFile $nuevaImagen = null;

    public function mount(SolicitudCambio $solicitud, string $campo): void
    {
        $this->solicitud = $solicitud;
        $this->campo = $campo;
    }

    #[Computed]
    public function imagenes()
    {
        return $this->solicitud->adjuntos()
            ->where('campo', $this->campo)
            ->with('subidoPor:id,name')
            ->get();
    }

    public function subir(): void
    {
        $this->authorize('update', $this->solicitud);

        if ($this->imagenes->count() >= self::MAX_IMAGENES) {
            $this->addError('nuevaImagen', 'Máximo '.self::MAX_IMAGENES.' imágenes por campo.');

            return;
        }

        $this->validate([
            'nuevaImagen' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:'.config('gestioncambio.adjunto_max_kb')],
        ]);

        $this->solicitud->agregarAdjunto($this->nuevaImagen, Auth::id(), config('gestioncambio.adjunto_disco'), $this->campo);

        unset($this->nuevaImagen);
        unset($this->imagenes);
        $this->dispatch('imagen-subida');
    }

    public function eliminar(int $adjuntoId): void
    {
        $this->authorize('update', $this->solicitud);

        $adjunto = AdjuntoEvidencia::whereKey($adjuntoId)
            ->where('adjuntable_type', $this->solicitud->getMorphClass())
            ->where('adjuntable_id', $this->solicitud->id)
            ->where('campo', $this->campo)
            ->firstOrFail();

        $adjunto->delete();

        unset($this->imagenes);
    }

    public function render()
    {
        return view('livewire.solicitud.imagenes-campo');
    }
}

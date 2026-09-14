<?php

namespace App\Models\Concerns;

use App\Models\AdjuntoEvidencia;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Http\UploadedFile;

/**
 * Da a un modelo (AccionPlan, CriterioCierre) la capacidad de guardar adjuntos de evidencia
 * (fotos, video, PDF, Excel, …) en el disco `local`, con borrado en cascada. Revisión R2 / US10.
 */
trait TieneAdjuntos
{
    public static function bootTieneAdjuntos(): void
    {
        static::deleting(function ($modelo) {
            $modelo->adjuntos()->get()->each->delete();
        });
    }

    public function adjuntos(): MorphMany
    {
        return $this->morphMany(AdjuntoEvidencia::class, 'adjuntable')->latest();
    }

    public function agregarAdjunto(UploadedFile $archivo, int $usuarioId, string $disco = 'local'): AdjuntoEvidencia
    {
        $solicitudId = $this->solicitud_cambio_id ?? $this->solicitud?->id ?? 'sin-solicitud';
        $ruta = $archivo->store("adjuntos/{$solicitudId}", $disco);

        return $this->adjuntos()->create([
            'disco' => $disco,
            'ruta' => $ruta,
            'nombre_original' => $archivo->getClientOriginalName(),
            'mime' => $archivo->getClientMimeType(),
            'tamano' => $archivo->getSize(),
            'subido_por' => $usuarioId,
        ]);
    }
}

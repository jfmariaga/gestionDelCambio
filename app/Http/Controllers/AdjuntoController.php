<?php

namespace App\Http\Controllers;

use App\Models\AdjuntoEvidencia;
use App\Models\SolicitudCambio;
use Illuminate\Support\Facades\Storage;

class AdjuntoController extends Controller
{
    public function download(SolicitudCambio $solicitud, AdjuntoEvidencia $adjunto)
    {
        $this->autorizarYResolver($solicitud, $adjunto);

        return Storage::disk($adjunto->disco)->download($adjunto->ruta, $adjunto->nombre_original);
    }

    public function inline(SolicitudCambio $solicitud, AdjuntoEvidencia $adjunto)
    {
        $this->autorizarYResolver($solicitud, $adjunto);

        return Storage::disk($adjunto->disco)->response($adjunto->ruta, $adjunto->nombre_original);
    }

    private function autorizarYResolver(SolicitudCambio $solicitud, AdjuntoEvidencia $adjunto): void
    {
        $this->authorize('view', $solicitud);

        // El adjunto debe pertenecer a una acción, criterio o a la propia solicitud.
        $pertenece = $adjunto->adjuntable
            && $adjunto->adjuntable->solicitudCambioId() === $solicitud->id;

        abort_unless($pertenece, 404);
    }
}

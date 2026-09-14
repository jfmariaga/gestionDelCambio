<?php

namespace App\Http\Controllers;

use App\Models\AdjuntoEvidencia;
use App\Models\SolicitudCambio;
use Illuminate\Support\Facades\Storage;

class AdjuntoController extends Controller
{
    public function download(SolicitudCambio $solicitud, AdjuntoEvidencia $adjunto)
    {
        $this->authorize('view', $solicitud);

        // El adjunto debe pertenecer a una acción o criterio de esta solicitud.
        $pertenece = $adjunto->adjuntable
            && ($adjunto->adjuntable->solicitud_cambio_id ?? null) === $solicitud->id;

        abort_unless($pertenece, 404);

        return Storage::disk($adjunto->disco)->download($adjunto->ruta, $adjunto->nombre_original);
    }
}

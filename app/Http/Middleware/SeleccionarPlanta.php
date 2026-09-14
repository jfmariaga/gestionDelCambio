<?php

namespace App\Http\Middleware;

use App\Models\Planta;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Garantiza que haya una planta / sede activa en la sesión antes de operar con solicitudes
 * (Revisión R2 / US12). Si el usuario tiene planta preferida, la fija automáticamente; si no,
 * lo envía a elegir una.
 */
class SeleccionarPlanta
{
    public function handle(Request $request, Closure $next): Response
    {
        $usuario = $request->user();

        if ($usuario && ! $request->session()->has('planta_id')) {
            $preferida = $usuario->planta_preferida_id;

            if ($preferida && Planta::whereKey($preferida)->where('activo', true)->exists()) {
                $request->session()->put('planta_id', $preferida);
            } else {
                return redirect()->route('plantas.seleccionar');
            }
        }

        return $next($request);
    }
}

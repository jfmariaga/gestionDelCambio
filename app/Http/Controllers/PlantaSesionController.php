<?php

namespace App\Http\Controllers;

use App\Models\Planta;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

/**
 * Selección de la planta / sede activa (Revisión R2 / US12). La planta vive en la sesión y,
 * opcionalmente, como preferencia por defecto del usuario.
 */
class PlantaSesionController extends Controller
{
    public function show()
    {
        $plantas = Planta::activas()->ordenadas()->get();

        return view('plantas.seleccionar', compact('plantas'));
    }

    public function store(Request $request)
    {
        $datos = $request->validate([
            'planta_id' => ['required', Rule::exists('plantas', 'id')->where('activo', true)],
            'recordar' => ['boolean'],
        ]);

        $request->session()->put('planta_id', (int) $datos['planta_id']);

        if ($request->boolean('recordar')) {
            $usuario = Auth::user();
            $usuario->planta_preferida_id = (int) $datos['planta_id'];
            $usuario->save();
        }

        return redirect()->intended(route('solicitudes.index'))
            ->with('status', 'Planta activa: '.Planta::find($datos['planta_id'])->nombre);
    }

    /** Cambio rápido de planta desde la barra superior. */
    public function cambiar(Request $request)
    {
        $datos = $request->validate([
            'planta_id' => ['required', Rule::exists('plantas', 'id')->where('activo', true)],
        ]);

        $request->session()->put('planta_id', (int) $datos['planta_id']);

        return back()->with('status', 'Planta activa: '.Planta::find($datos['planta_id'])->nombre);
    }
}

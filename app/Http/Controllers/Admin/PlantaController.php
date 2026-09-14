<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Planta;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class PlantaController extends Controller
{
    public function index()
    {
        $plantas = Planta::ordenadas()->withCount('solicitudes')->get();

        return view('admin.plantas.index', compact('plantas'));
    }

    public function store(Request $request)
    {
        Planta::create($this->validar($request, null));

        return back()->with('status', 'Planta creada.');
    }

    public function update(Request $request, Planta $planta)
    {
        $planta->update($this->validar($request, $planta));

        return back()->with('status', 'Planta actualizada.');
    }

    public function destroy(Planta $planta)
    {
        if ($planta->solicitudes()->exists()) {
            return back()->with('error', 'No se puede eliminar: la planta tiene solicitudes. Desactívela.');
        }

        $planta->delete();

        return back()->with('status', 'Planta eliminada.');
    }

    private function validar(Request $request, ?Planta $planta): array
    {
        $datos = $request->validate([
            'nombre' => ['required', 'string', 'max:120', Rule::unique('plantas', 'nombre')->ignore($planta?->id)],
            'codigo' => ['required', 'string', 'max:20', Rule::unique('plantas', 'codigo')->ignore($planta?->id)],
            'orden' => ['required', 'integer', 'min:0'],
        ]);
        $datos['codigo'] = strtoupper($datos['codigo']);
        $datos['activo'] = $request->boolean('activo');

        return $datos;
    }
}

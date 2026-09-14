<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Proceso;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ProcesoController extends Controller
{
    public function index()
    {
        $procesos = Proceso::withCount(['preguntasClave', 'riesgosPredeterminados'])
            ->ordenados()->get();

        return view('admin.catalogo.procesos', compact('procesos'));
    }

    public function store(Request $request)
    {
        $datos = $this->validar($request, null);
        Proceso::create($datos);

        return back()->with('status', 'Proceso creado.');
    }

    public function update(Request $request, Proceso $proceso)
    {
        $proceso->update($this->validar($request, $proceso));

        return back()->with('status', 'Proceso actualizado.');
    }

    public function destroy(Proceso $proceso)
    {
        if ($proceso->preguntasClave()->exists() || $proceso->riesgosPredeterminados()->exists()) {
            return back()->with('error', 'No se puede eliminar: el proceso tiene preguntas o riesgos. Desactívelo.');
        }

        $proceso->delete();

        return back()->with('status', 'Proceso eliminado.');
    }

    private function validar(Request $request, ?Proceso $proceso): array
    {
        $datos = $request->validate([
            'nombre' => ['required', 'string', 'max:150', Rule::unique('procesos', 'nombre')->ignore($proceso?->id)],
            'orden' => ['required', 'integer', 'min:0'],
        ]);
        $datos['activo'] = $request->boolean('activo');

        return $datos;
    }
}

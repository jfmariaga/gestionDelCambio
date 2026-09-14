<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Proceso;
use App\Models\RiesgoPredeterminado;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class RiesgoPredeterminadoController extends Controller
{
    public function index()
    {
        $procesos = Proceso::ordenados()
            ->with(['riesgosPredeterminados' => fn ($q) => $q->orderBy('texto')])
            ->get();

        return view('admin.catalogo.riesgos', compact('procesos'));
    }

    public function store(Request $request)
    {
        RiesgoPredeterminado::create($this->validar($request, null));

        return back()->with('status', 'Riesgo predeterminado creado.');
    }

    public function update(Request $request, RiesgoPredeterminado $riesgo)
    {
        $riesgo->update($this->validar($request, $riesgo));

        return back()->with('status', 'Riesgo actualizado.');
    }

    public function destroy(RiesgoPredeterminado $riesgo)
    {
        if ($riesgo->preguntasClave()->exists()) {
            return back()->with('error', 'No se puede eliminar: hay preguntas clave que usan este riesgo.');
        }

        $riesgo->delete();

        return back()->with('status', 'Riesgo eliminado.');
    }

    private function validar(Request $request, ?RiesgoPredeterminado $riesgo): array
    {
        $datos = $request->validate([
            'proceso_id' => ['required', 'integer', 'exists:procesos,id'],
            'texto' => [
                'required', 'string', 'max:500',
                Rule::unique('riesgos_predeterminados', 'texto')
                    ->where(fn ($q) => $q->where('proceso_id', $request->integer('proceso_id')))
                    ->ignore($riesgo?->id),
            ],
        ]);
        $datos['activo'] = $request->boolean('activo', true);

        return $datos;
    }
}

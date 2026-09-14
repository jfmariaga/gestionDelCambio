<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\PreguntaClave;
use App\Models\Proceso;
use App\Models\RespuestaPregunta;
use App\Models\RiesgoPredeterminado;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class PreguntaClaveController extends Controller
{
    public function index(Request $request)
    {
        $procesoId = $request->integer('proceso');

        $procesos = Proceso::ordenados()->get();
        $preguntas = PreguntaClave::with('proceso', 'riesgoPredeterminado')
            ->when($procesoId, fn ($q) => $q->where('proceso_id', $procesoId))
            ->orderBy('proceso_id')->orderBy('orden')
            ->paginate(30)->withQueryString();

        $riesgosPorProceso = RiesgoPredeterminado::orderBy('texto')->get()->groupBy('proceso_id');

        return view('admin.catalogo.preguntas', compact('procesos', 'preguntas', 'riesgosPorProceso', 'procesoId'));
    }

    public function store(Request $request)
    {
        PreguntaClave::create($this->validar($request, null));

        return back()->with('status', 'Pregunta clave creada.');
    }

    public function update(Request $request, PreguntaClave $pregunta)
    {
        $pregunta->update($this->validar($request, $pregunta));

        return back()->with('status', 'Pregunta actualizada.');
    }

    public function destroy(PreguntaClave $pregunta)
    {
        if (RespuestaPregunta::where('pregunta_clave_id', $pregunta->id)->exists()) {
            return back()->with('error', 'No se puede eliminar: la pregunta ya fue respondida en solicitudes. Desactívela.');
        }

        $pregunta->delete();

        return back()->with('status', 'Pregunta eliminada.');
    }

    private function validar(Request $request, ?PreguntaClave $pregunta): array
    {
        $datos = $request->validate([
            'proceso_id' => ['required', 'integer', 'exists:procesos,id'],
            'texto' => [
                'required', 'string', 'max:500',
                Rule::unique('preguntas_clave', 'texto')
                    ->where(fn ($q) => $q->where('proceso_id', $request->integer('proceso_id')))
                    ->ignore($pregunta?->id),
            ],
            'riesgo_predeterminado_id' => ['nullable', 'integer', 'exists:riesgos_predeterminados,id'],
            'dueno_por_defecto' => ['nullable', 'string', 'max:200'],
            'evidencia_por_defecto' => ['nullable', 'string'],
            'accion_por_defecto' => ['nullable', 'string'],
            'orden' => ['required', 'integer', 'min:0'],
        ]);
        $datos['activo'] = $request->boolean('activo', true);

        return $datos;
    }
}

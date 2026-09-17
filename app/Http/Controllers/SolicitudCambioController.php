<?php

namespace App\Http\Controllers;

use App\Domain\GestionCambio\AsignadorAprobador;
use App\Domain\GestionCambio\CongeladorSolicitud;
use App\Domain\GestionCambio\GeneradorConsecutivo;
use App\Domain\GestionCambio\Notificador;
use App\Domain\GestionCambio\TransicionSolicitud;
use App\Enums\EstadoSolicitud;
use App\Enums\TipoCambio;
use App\Models\AccionPlan;
use App\Models\BitacoraEvento;
use App\Models\SolicitudCambio;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use RuntimeException;

class SolicitudCambioController extends Controller
{
    public function index()
    {
        $this->authorize('viewAny', SolicitudCambio::class);

        $usuario = Auth::user();
        $vistaGlobal = $usuario->hasAnyRole(['administrador', 'consulta']);

        $solicitudes = SolicitudCambio::query()
            ->when(! $vistaGlobal && session('planta_id'), fn ($q) => $q->where('planta_id', session('planta_id')))
            ->when(! $vistaGlobal, fn ($q) => $q->where(fn ($q) => $this->soloVisiblesPara($q, $usuario)))
            ->with('planta')
            ->latest()
            ->paginate(20);

        return view('solicitudes.index', compact('solicitudes', 'vistaGlobal'));
    }

    /**
     * Roles aditivos: une lo que cada rol del usuario puede ver, con el mismo criterio que
     * SolicitudCambioPolicy::view() (administrador/consulta ya salieron por $vistaGlobal).
     */
    private function soloVisiblesPara(Builder $query, $usuario): void
    {
        if ($usuario->hasRole('solicitante')) {
            $query->orWhere('created_by', $usuario->id);
        }

        if ($usuario->hasRole('dueno_proceso')) {
            $nombres = $usuario->procesos()->pluck('nombre');
            $query->orWhereIn('area_proceso', $nombres)
                ->orWhereHas('riesgosAsociados', fn ($r) => $r->whereIn('proceso_nombre', $nombres));
        }

        if ($usuario->hasRole('aprobador')) {
            $query->orWhere('estado', '!=', EstadoSolicitud::Solicitado)
                ->orWhere('created_by', $usuario->id);
        }
    }

    public function create()
    {
        $this->authorize('create', SolicitudCambio::class);

        return view('solicitudes.create', [
            'proximoConsecutivo' => GeneradorConsecutivo::proximo(),
        ]);
    }

    public function store(Request $request)
    {
        $this->authorize('create', SolicitudCambio::class);

        $datos = $this->validar($request, estricto: false);

        $plantaId = session('planta_id');
        abort_if(! $plantaId, 422, 'Seleccione una planta antes de crear la solicitud.');

        $solicitud = SolicitudCambio::create([
            ...$datos,
            'consecutivo' => GeneradorConsecutivo::siguiente(),
            'planta_id' => $plantaId,
            'estado' => EstadoSolicitud::Solicitado,
            'solicitante_cargo' => ($datos['solicitante_cargo'] ?? null) ?: Auth::user()->name,
            'created_by' => Auth::id(),
            'updated_by' => Auth::id(),
        ]);

        app(Notificador::class)->notificarEvento($solicitud, 'creada');

        return redirect()
            ->route('solicitudes.edit', $solicitud)
            ->with('status', "Solicitud {$solicitud->consecutivo} creada.");
    }

    public function show(SolicitudCambio $solicitud)
    {
        $this->authorize('view', $solicitud);

        return view('solicitudes.show', compact('solicitud'));
    }

    public function edit(SolicitudCambio $solicitud)
    {
        $this->authorize('view', $solicitud);
        abort_unless($solicitud->estado->admiteGestionDePlanYCierre(), 403, 'La solicitud ya no admite cambios.');

        return view('solicitudes.edit', compact('solicitud'));
    }

    /**
     * Vista mínima con UNA sola tarea del plan de acción, sin el resto del formulario: a donde
     * enlazan las notificaciones de tarea para que quien no es solicitante/dueño/admin solo vea
     * (y solo pueda operar) la acción que le corresponde.
     */
    public function tarea(SolicitudCambio $solicitud, AccionPlan $accion)
    {
        abort_unless($accion->solicitud_cambio_id === $solicitud->id, 404);
        $this->authorize('view', $solicitud);

        return view('solicitudes.tarea', compact('solicitud', 'accion'));
    }

    public function update(Request $request, SolicitudCambio $solicitud)
    {
        $this->authorize('update', $solicitud);

        $solicitud->update([
            ...$this->validar($request, estricto: true),
            'updated_by' => Auth::id(),
        ]);

        // Adelanto del conjunto de aprobadores de la compuerta inicial (Fase 3).
        app(AsignadorAprobador::class)->sincronizar($solicitud->fresh());

        return back()->with('status', 'Solicitud actualizada.');
    }

    public function enviar(SolicitudCambio $solicitud, CongeladorSolicitud $congelador)
    {
        $this->authorize('enviar', $solicitud);

        // FR-050: la descripción simple es obligatoria en toda solicitud, incluidas las Menores.
        $faltantes = collect([
            'situacion_actual' => 'Situación actual',
            'que_cambiara' => 'Qué cambiará',
            'resultado_esperado' => 'Resultado esperado',
        ])->reject(fn ($_, $campo) => filled($solicitud->{$campo}));

        if ($faltantes->isNotEmpty()) {
            return back()->withErrors(
                $faltantes->mapWithKeys(fn ($label, $campo) => [$campo => "«{$label}» es obligatorio para enviar a aprobación."])->all()
            );
        }

        $congelador->enviarAAprobacion($solicitud, Auth::id());

        return redirect()
            ->route('solicitudes.show', $solicitud)
            ->with('status', 'Solicitud enviada a evaluación.');
    }

    public function decision(Request $request, SolicitudCambio $solicitud, TransicionSolicitud $transicion)
    {
        $datos = $request->validate([
            'accion' => ['required', 'in:aprobar,devolver'],
            'comentario' => ['nullable', 'string', 'max:2000'],
        ]);

        if ($datos['accion'] === 'devolver') {
            $request->validate(['comentario' => ['required', 'string', 'max:2000']]);
        }

        if ($solicitud->estado === EstadoSolicitud::EnEvaluacion) {
            $this->authorize('decidir', $solicitud);

            try {
                $transicion->registrarDecisionInicial($solicitud, Auth::user(), $datos['accion'], $datos['comentario'] ?? null);
            } catch (RuntimeException $e) {
                return redirect()->route('solicitudes.show', $solicitud)->with('error', $e->getMessage());
            }

            $mensaje = $solicitud->fresh()->estado === EstadoSolicitud::Aprobado
                ? 'Solicitud aprobada.'
                : ($datos['accion'] === 'devolver' ? 'Solicitud devuelta.' : 'Aprobación registrada. Faltan otros aprobadores.');

            return redirect()->route('solicitudes.show', $solicitud)->with('status', $mensaje);
        }

        if ($solicitud->estado === EstadoSolicitud::EnVerificacion) {
            $this->authorize('decidirCierre', $solicitud);

            try {
                $bloqueos = $transicion->registrarDecisionCierre($solicitud, Auth::user(), $datos['accion'], $datos['comentario'] ?? null);
            } catch (RuntimeException $e) {
                return redirect()->route('solicitudes.show', $solicitud)->with('error', $e->getMessage());
            }

            if ($bloqueos !== []) {
                return redirect()->route('solicitudes.show', $solicitud)->with('errores_cierre', $bloqueos);
            }

            $mensaje = $solicitud->fresh()->estado === EstadoSolicitud::Cerrado
                ? 'Solicitud cerrada.'
                : ($datos['accion'] === 'devolver' ? 'Devuelta a implementación.' : 'Aprobación de cierre registrada. Faltan otros aprobadores.');

            return redirect()->route('solicitudes.show', $solicitud)->with('status', $mensaje);
        }

        abort(422, 'La solicitud no está en una etapa que admita decisión.');
    }

    public function implementar(SolicitudCambio $solicitud, TransicionSolicitud $transicion)
    {
        $this->authorize('gestionarImplementacion', $solicitud);
        $transicion->iniciarImplementacion($solicitud, Auth::id());

        return redirect()->route('solicitudes.show', $solicitud)->with('status', 'Implementación iniciada.');
    }

    public function marcarImplementado(SolicitudCambio $solicitud, TransicionSolicitud $transicion)
    {
        $this->authorize('gestionarImplementacion', $solicitud);

        $bloqueos = $transicion->marcarImplementado($solicitud, Auth::id());

        if ($bloqueos !== []) {
            return redirect()->route('solicitudes.show', $solicitud)->with('errores_implementacion', $bloqueos);
        }

        return redirect()->route('solicitudes.show', $solicitud)->with('status', 'Implementación marcada como completa. Ya puede continuar a seguimiento y cierre.');
    }

    public function enviarVerificacion(SolicitudCambio $solicitud, TransicionSolicitud $transicion)
    {
        $this->authorize('gestionarImplementacion', $solicitud);
        $bloqueos = $transicion->enviarAVerificacion($solicitud, Auth::id());

        if ($bloqueos !== []) {
            return redirect()->route('solicitudes.show', $solicitud)->with('errores_verificacion', $bloqueos);
        }

        return redirect()->route('solicitudes.show', $solicitud)->with('status', 'Solicitud enviada a seguimiento y cierre.');
    }

    public function cerrar(SolicitudCambio $solicitud, TransicionSolicitud $transicion)
    {
        $this->authorize('cerrar', $solicitud);

        $bloqueos = $transicion->cerrar($solicitud, Auth::id());

        if ($bloqueos !== []) {
            return redirect()->route('solicitudes.show', $solicitud)
                ->with('errores_cierre', $bloqueos);
        }

        return redirect()->route('solicitudes.show', $solicitud)->with('status', 'Solicitud cerrada.');
    }

    public function anular(Request $request, SolicitudCambio $solicitud, TransicionSolicitud $transicion)
    {
        $this->authorize('cerrar', $solicitud);

        $datos = $request->validate(['comentario' => ['required', 'string', 'max:2000']]);
        $transicion->cancelar($solicitud, Auth::id(), $datos['comentario']);

        return redirect()->route('solicitudes.show', $solicitud)->with('status', 'Solicitud anulada.');
    }

    public function aprobador(Request $request, SolicitudCambio $solicitud)
    {
        abort_unless($request->user()->hasRole('administrador'), 403);

        $datos = $request->validate([
            'aprobador_asignado_id' => ['required', 'integer', Rule::exists('users', 'id')],
        ]);

        $anterior = $solicitud->aprobador_asignado_id;

        $solicitud->forceFill([
            'aprobador_asignado_id' => $datos['aprobador_asignado_id'],
            'aprobador_override' => true,
        ])->save();
        $solicitud->aprobadores()->sync([$datos['aprobador_asignado_id']]);

        BitacoraEvento::create([
            'solicitud_cambio_id' => $solicitud->id,
            'user_id' => Auth::id(),
            'evento' => 'aprobador_reasignado',
            'comentario' => 'Override manual del administrador.',
            'datos' => ['de' => $anterior, 'a' => $datos['aprobador_asignado_id']],
        ]);

        return back()->with('status', 'Aprobador actualizado.');
    }

    public function exportar(SolicitudCambio $solicitud)
    {
        $this->authorize('exportar', $solicitud);

        $solicitud->load([
            'responsable', 'riesgosAsociados', 'evaluacion.calificaciones.criterio',
            'accionesPlan.adjuntos', 'accionesPlan.riesgoAsociado', 'criteriosCierre.adjuntos',
        ]);

        $pdf = Pdf::loadView('exports.solicitud-fosig02', compact('solicitud'));

        return $pdf->download("{$solicitud->consecutivo}.pdf");
    }

    private function validar(Request $request, bool $estricto): array
    {
        if ($request->has('costo_estimado')) {
            $request->merge([
                'costo_estimado' => preg_replace('/\D/', '', (string) $request->input('costo_estimado')) ?: null,
            ]);
        }

        $obligatorio = $estricto ? ['required'] : ['nullable'];

        return $request->validate([
            'fecha' => ['required', 'date'],
            'nombre_cambio' => ['required', 'string', 'max:255'],
            'solicitante_cargo' => ['nullable', 'string', 'max:255'],
            'area_proceso' => [...$obligatorio, 'string', 'max:150', Rule::exists('procesos', 'nombre')],
            'tipo_cambio' => [...$obligatorio, Rule::enum(TipoCambio::class)],
            'fecha_requerida' => [...$obligatorio, 'date'],
            'costo_estimado' => ['nullable', 'numeric', 'min:0'],
            'requiere_comite' => ['boolean'],
            'situacion_actual' => [...$obligatorio, 'string'],
            'que_cambiara' => [...$obligatorio, 'string'],
            'resultado_esperado' => [...$obligatorio, 'string'],
        ]);
    }
}

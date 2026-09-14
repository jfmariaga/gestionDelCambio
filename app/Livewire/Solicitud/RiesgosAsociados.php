<?php

namespace App\Livewire\Solicitud;

use App\Domain\GestionCambio\CalculoRiesgo;
use App\Domain\GestionCambio\SincronizadorPlanRiesgos;
use App\Enums\EstadoFila;
use App\Enums\Impacto;
use App\Enums\Probabilidad;
use App\Livewire\Concerns\ConListaDeUsuarios;
use App\Models\RiesgoAsociado;
use App\Models\SolicitudCambio;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Component;

class RiesgosAsociados extends Component
{
    use ConListaDeUsuarios;

    public SolicitudCambio $solicitud;

    /** @var array<int,array<string,mixed>> */
    public array $edicion = [];

    public function mount(SolicitudCambio $solicitud): void
    {
        $this->authorize('completarSecciones', $solicitud);
        $this->solicitud = $solicitud;
    }

    private function autorizarProceso(string $procesoNombre): void
    {
        $user = Auth::user();
        $ok = $user->hasRole('administrador')
            || ($this->solicitud->created_by === $user->id && $user->hasRole('solicitante'))
            || $user->esDuenoDeProceso($procesoNombre);

        abort_unless($ok, 403, 'No puede editar filas de este proceso.');
    }

    #[On('secciones-actualizadas')]
    public function refrescar(): void
    {
        unset($this->filas);
    }

    #[Computed]
    public function filas()
    {
        return $this->solicitud->riesgosAsociados()
            ->with(['preguntas', 'responsableUsuario:id,name'])
            ->orderBy('proceso_nombre')
            ->orderBy('id')
            ->get();
    }

    public function guardarFila(int $id): void
    {
        $fila = $this->solicitud->riesgosAsociados()->findOrFail($id);
        $this->autorizarProceso($fila->proceso_nombre);
        $datos = $this->edicion[$id] ?? [];

        $probabilidad = $this->normalizarEntero($datos['probabilidad'] ?? $fila->probabilidad);
        $impacto = $this->normalizarEntero($datos['impacto'] ?? $fila->impacto);

        foreach ([
            'probabilidad' => [$probabilidad, Probabilidad::valores()],
            'impacto' => [$impacto, Impacto::valores()],
        ] as $campo => [$valor, $permitidos]) {
            if ($valor !== null && ! in_array($valor, $permitidos, true)) {
                throw ValidationException::withMessages([
                    "edicion.$id.$campo" => "Seleccione un valor válido de $campo.",
                ]);
            }
        }

        ['nr' => $nr, 'nivel' => $nivel] = CalculoRiesgo::evaluar($probabilidad, $impacto);

        $responsableId = $this->normalizarEntero($datos['responsable_id'] ?? $fila->responsable_id);
        $responsableNombre = $responsableId
            ? (User::whereKey($responsableId)->value('name') ?? $fila->responsable)
            : ($datos['responsable'] ?? $fila->responsable);

        $fila->update([
            'control_existente' => $datos['control_existente'] ?? $fila->control_existente,
            'accion_requerida' => $datos['accion_requerida'] ?? $fila->accion_requerida,
            'responsable_id' => $responsableId,
            'responsable' => $responsableNombre,
            'fecha' => $datos['fecha'] ?? $fila->fecha,
            'probabilidad' => $probabilidad,
            'impacto' => $impacto,
            'nr' => $nr,
            'nivel' => $nivel,
            'editado_manualmente' => true,
        ]);

        // Solo los riesgos Medio/Alto arrastran una acción al plan (Fase 7).
        app(SincronizadorPlanRiesgos::class)->sincronizar($this->solicitud->fresh());

        unset($this->filas);
        $this->dispatch('riesgo-guardado', id: $id);
        $this->dispatch('toast', icon: 'success', title: 'Riesgo actualizado.');
    }

    public function agregarManual(): void
    {
        RiesgoAsociado::create([
            'solicitud_cambio_id' => $this->solicitud->id,
            'riesgo_predeterminado_id' => null,
            'proceso_nombre' => '',
            'riesgo_texto' => '',
            'editado_manualmente' => true,
            'estado' => EstadoFila::Vigente,
        ]);

        unset($this->filas);
        $this->dispatch('toast', icon: 'success', title: 'Riesgo agregado.');
    }

    public function confirmarEliminar(int $id, bool $eliminar): void
    {
        $fila = $this->solicitud->riesgosAsociados()->findOrFail($id);

        if ($fila->estado !== EstadoFila::Huerfana && $fila->riesgo_predeterminado_id !== null) {
            return; // solo se eliminan manualmente las filas huérfanas o las agregadas a mano.
        }

        if ($eliminar) {
            $fila->preguntas()->detach();
            $fila->delete();
            app(SincronizadorPlanRiesgos::class)->sincronizar($this->solicitud->fresh());

            unset($this->filas);
            $this->dispatch('toast', icon: 'success', title: 'Riesgo eliminado.');

            return;
        }

        unset($this->filas);
    }

    private function normalizarEntero(mixed $valor): ?int
    {
        if ($valor === null || $valor === '') {
            return null;
        }

        return (int) $valor;
    }

    public function render()
    {
        return view('livewire.solicitud.riesgos-asociados');
    }
}

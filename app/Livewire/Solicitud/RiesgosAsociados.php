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
        // Poder ver la solicitud (p. ej. un responsable de tarea del plan) O poder completar
        // secciones (p. ej. un dueño de proceso sin filas asignadas todavía) alcanza para
        // montar la página; cada acción de escritura exige su propio permiso.
        $user = Auth::user();
        abort_unless($user->can('view', $solicitud) || $user->can('completarSecciones', $solicitud), 403);
        $this->solicitud = $solicitud;
        $this->sincronizarEdicion();
    }

    /**
     * Vuelca los valores actuales de las filas en $edicion para que `wire:model` siempre tenga
     * un valor de partida (si no, Livewire los trata como vacíos y los pisa en el próximo
     * render, aunque la fila ya tenga datos guardados).
     */
    private function sincronizarEdicion(?int $soloId = null): void
    {
        foreach ($this->filas as $fila) {
            if ($soloId !== null && $fila->id !== $soloId) {
                continue;
            }

            $this->edicion[$fila->id] = [
                'proceso_nombre' => $fila->proceso_nombre,
                'riesgo_texto' => $fila->riesgo_texto,
                'control_existente' => $fila->control_existente,
                'accion_requerida' => $fila->accion_requerida,
                'responsable_id' => $fila->responsable_id,
                'fecha' => optional($fila->fecha)->toDateString(),
                'probabilidad' => $fila->probabilidad,
                'impacto' => $fila->impacto,
            ];
        }
    }

    private function autorizarProceso(string $procesoNombre): void
    {
        $user = Auth::user();
        $ok = $user->hasRole('administrador')
            || ($this->solicitud->created_by === $user->id && $user->hasRole('solicitante'))
            || $user->esDuenoDeProceso($procesoNombre);

        abort_unless($ok, 403, 'No puede editar filas de este proceso.');
    }

    /** Los riesgos solo se editan mientras la solicitud está en borrador/evaluación y sin congelar. */
    private function bloqueada(): bool
    {
        if (! $this->solicitud->estado->esEditable() || $this->solicitud->estaCongelada()) {
            $this->dispatch('solicitud-bloqueada');
            $this->dispatch('toast', icon: 'error', title: 'La solicitud está bloqueada y no admite cambios.');

            return true;
        }

        return false;
    }

    #[On('secciones-actualizadas')]
    public function refrescar(): void
    {
        unset($this->filas);
        $this->sincronizarEdicion();
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
        if ($this->bloqueada()) {
            return;
        }

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
        $this->sincronizarEdicion($id);
        $this->dispatch('riesgo-guardado', id: $id);
        // Para que el Plan de acción refleje la tarea sincronizada sin recargar la página.
        $this->dispatch('secciones-actualizadas');
        $this->dispatch('toast', icon: 'success', title: 'Riesgo actualizado.');
    }

    public function agregarManual(): void
    {
        $this->authorize('completarSecciones', $this->solicitud);

        if ($this->bloqueada()) {
            return;
        }

        RiesgoAsociado::create([
            'solicitud_cambio_id' => $this->solicitud->id,
            'riesgo_predeterminado_id' => null,
            'proceso_nombre' => '',
            'riesgo_texto' => '',
            'editado_manualmente' => true,
            'estado' => EstadoFila::Vigente,
        ]);

        unset($this->filas);
        $this->sincronizarEdicion();
        $this->dispatch('toast', icon: 'success', title: 'Riesgo agregado.');
    }

    public function confirmarEliminar(int $id, bool $eliminar): void
    {
        $this->authorize('completarSecciones', $this->solicitud);

        if ($this->bloqueada()) {
            return;
        }

        $fila = $this->solicitud->riesgosAsociados()->findOrFail($id);

        if ($fila->estado !== EstadoFila::Huerfana && $fila->riesgo_predeterminado_id !== null) {
            return; // solo se eliminan manualmente las filas huérfanas o las agregadas a mano.
        }

        if ($eliminar) {
            $fila->preguntas()->detach();
            $fila->delete();
            app(SincronizadorPlanRiesgos::class)->sincronizar($this->solicitud->fresh());

            unset($this->filas, $this->edicion[$id]);
            $this->dispatch('secciones-actualizadas');
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

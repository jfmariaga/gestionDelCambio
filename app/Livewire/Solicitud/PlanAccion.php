<?php

namespace App\Livewire\Solicitud;

use App\Domain\GestionCambio\TransicionSolicitud;
use App\Enums\EstadoAccionPlan;
use App\Enums\EstadoSolicitud;
use App\Livewire\Concerns\ConListaDeUsuarios;
use App\Models\AccionPlan;
use App\Models\AdjuntoEvidencia;
use App\Models\SolicitudCambio;
use App\Models\User;
use App\Notifications\TareaAsignadaNotification;
use App\Notifications\TareaPorValidarNotification;
use App\Notifications\TareaRechazadaNotification;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Component;
use Livewire\WithFileUploads;

class PlanAccion extends Component
{
    use ConListaDeUsuarios, WithFileUploads;

    public SolicitudCambio $solicitud;

    /** Modo "foco": solo esta tarea es visible/operable (link directo desde una notificación). */
    public ?int $soloAccionId = null;

    /** @var array<int,array<string,mixed>> */
    public array $edicion = [];

    /** @var array<int,string> comentario de rechazo por acción */
    public array $rechazo = [];

    /** @var array<int,UploadedFile|null> */
    public array $nuevoAdjunto = [];

    public function mount(SolicitudCambio $solicitud, ?int $soloAccionId = null): void
    {
        $this->solicitud = $solicitud;
        $this->soloAccionId = $soloAccionId;

        // FR-043 / FR-055: quien es responsable o creador de alguna tarea del plan puede
        // operar en esta sección aunque no tenga otro rol sobre la solicitud.
        $participa = $solicitud->accionesPlan()
            ->where(fn ($q) => $q->where('responsable_id', Auth::id())->orWhere('creador_id', Auth::id()))
            ->exists();

        if (! $participa) {
            $this->authorize('view', $solicitud);
        }

        $this->sincronizarEdicion();
    }

    /** En modo foco, ninguna acción de escritura puede tocar otra tarea distinta a la enlazada. */
    private function fueraDeFoco(int $id): bool
    {
        return $this->soloAccionId !== null && $id !== $this->soloAccionId;
    }

    /** Un riesgo recién calificado Medio/Alto sincroniza una tarea aquí; refleja el cambio sin recargar. */
    #[On('secciones-actualizadas')]
    public function refrescar(): void
    {
        unset($this->acciones);
        $this->sincronizarEdicion();
    }

    #[Computed]
    public function acciones()
    {
        return $this->solicitud->accionesPlan()
            ->with(['responsableUsuario:id,name', 'creador:id,name', 'adjuntos.subidoPor:id,name', 'riesgoAsociado'])
            ->when($this->soloAccionId, fn ($q) => $q->whereKey($this->soloAccionId))
            ->get();
    }

    /**
     * Vuelca los valores actuales de las acciones en $edicion para que `wire:model` siempre
     * tenga un valor y no se "pierdan" los campos que el usuario no tocó al guardar.
     */
    private function sincronizarEdicion(?int $soloId = null): void
    {
        foreach ($this->acciones as $accion) {
            if ($soloId !== null && $accion->id !== $soloId) {
                continue;
            }

            $this->edicion[$accion->id] = [
                'descripcion' => $accion->descripcion,
                'proceso' => $accion->proceso,
                'responsable_id' => $accion->responsable_id,
                'fecha' => optional($accion->fecha)->toDateString(),
                'estado' => $accion->estado->value,
                'evidencia' => $accion->evidencia,
                'nota' => $accion->nota,
            ];
        }
    }

    private function esLider(): bool
    {
        $user = Auth::user();

        return $user->id === $this->solicitud->created_by || $user->hasRole('administrador');
    }

    private function esResponsable(AccionPlan $accion): bool
    {
        return Auth::id() === $accion->responsable_id;
    }

    public function agregar(): void
    {
        abort_if($this->soloAccionId !== null, 403);
        abort_unless($this->solicitud->estado === EstadoSolicitud::Solicitado, 403);

        AccionPlan::create([
            'solicitud_cambio_id' => $this->solicitud->id,
            'numero' => ($this->solicitud->accionesPlan()->max('numero') ?? 0) + 1,
            'descripcion' => 'Nueva acción',
            'estado' => EstadoAccionPlan::Pendiente->value,
            'creador_id' => Auth::id(),
        ]);

        unset($this->acciones);
        $this->sincronizarEdicion();
        $this->dispatch('toast', icon: 'success', title: 'Acción agregada.');
    }

    public function guardar(int $id): void
    {
        abort_if($this->fueraDeFoco($id), 403);
        $accion = $this->solicitud->accionesPlan()->findOrFail($id);
        abort_unless($this->esResponsable($accion) || $this->esLider(), 403);
        $datos = $this->edicion[$id] ?? [];

        $responsableAntes = $accion->responsable_id;

        // Descripción/proceso/responsable/fecha son del líder (o admin): en borrador puede
        // reorganizar el plan. El responsable de la tarea (FR-055) solo toca estado/evidencia/nota,
        // sin importar el estado de la solicitud.
        if ($this->solicitud->estado === EstadoSolicitud::Solicitado && $this->esLider()) {
            $responsableId = ($datos['responsable_id'] ?? $accion->responsable_id) ?: null;
            $responsableNombre = $responsableId
                ? (User::whereKey($responsableId)->value('name') ?? $accion->responsable)
                : ($datos['responsable'] ?? $accion->responsable);

            $fecha = $datos['fecha'] ?? optional($accion->fecha)->toDateString();

            $accion->fill([
                'descripcion' => $datos['descripcion'] ?? $accion->descripcion,
                'proceso' => $datos['proceso'] ?? $accion->proceso,
                'responsable_id' => $responsableId,
                'responsable' => $responsableNombre,
                'fecha' => filled($fecha) ? $fecha : null,
            ]);
        }

        // El cambio libre de estado (Pendiente/En curso) solo aplica antes de entrar al flujo
        // de validación (quién puede llegar hasta aquí ya se validó arriba).
        if (isset($datos['estado']) && in_array($datos['estado'], [EstadoAccionPlan::Pendiente->value, EstadoAccionPlan::EnCurso->value], true)) {
            $accion->estado = $datos['estado'];
        }

        $accion->fill([
            'evidencia' => $datos['evidencia'] ?? $accion->evidencia,
            'nota' => $datos['nota'] ?? $accion->nota,
        ])->save();

        // FR-054: notificar al responsable si es distinto de quien creó la tarea.
        if ($accion->responsable_id
            && $accion->responsable_id !== $responsableAntes
            && $accion->responsable_id !== $accion->creador_id) {
            $accion->responsableUsuario?->notify(new TareaAsignadaNotification($accion));
        }

        unset($this->acciones);
        $this->sincronizarEdicion($id);
        $this->dispatch('toast', icon: 'success', title: 'Acción guardada.');
    }

    /** El responsable marca su tarea como cerrada: pasa a "pendiente de validación" (FR-055). */
    public function marcarCerrada(int $id): void
    {
        abort_if($this->fueraDeFoco($id), 403);
        $accion = $this->solicitud->accionesPlan()->findOrFail($id);
        abort_unless(Auth::id() === $accion->responsable_id || Auth::user()->hasRole('administrador'), 403);

        $accion->update(['estado' => EstadoAccionPlan::CerradaPendienteValidacion->value]);

        $lider = $accion->creador ?? $this->solicitud->responsable;
        $lider?->notify(new TareaPorValidarNotification($accion));

        unset($this->acciones);
        $this->sincronizarEdicion($id);
        $this->dispatch('toast', icon: 'success', title: 'Tarea marcada como cerrada, pendiente de validación.');
    }

    /** El líder valida la tarea cerrada (FR-056). */
    public function validar(int $id): void
    {
        abort_if($this->fueraDeFoco($id), 403);
        abort_unless($this->esLider(), 403);
        $accion = $this->solicitud->accionesPlan()->findOrFail($id);

        $accion->update([
            'estado' => EstadoAccionPlan::Validada->value,
            'validada_por' => Auth::id(),
            'validada_at' => now(),
            'comentario_validacion' => null,
        ]);

        // Si todas las acciones quedaron validadas, la solicitud pasa a "Implementado".
        app(TransicionSolicitud::class)
            ->marcarImplementadoSiCorresponde($this->solicitud->fresh(), Auth::id());

        unset($this->acciones);
        $this->sincronizarEdicion($id);
        $this->dispatch('toast', icon: 'success', title: 'Tarea aprobada.');
    }

    /** El líder rechaza la tarea: vuelve a "En curso" y se notifica al responsable (FR-056). */
    public function rechazar(int $id): void
    {
        abort_if($this->fueraDeFoco($id), 403);
        abort_unless($this->esLider(), 403);
        $accion = $this->solicitud->accionesPlan()->findOrFail($id);
        $motivo = trim($this->rechazo[$id] ?? '');
        abort_if($motivo === '', 422, 'Indique el motivo del rechazo.');

        $accion->update([
            'estado' => EstadoAccionPlan::EnCurso->value,
            'comentario_validacion' => $motivo,
        ]);

        $accion->responsableUsuario?->notify(new TareaRechazadaNotification($accion, $motivo));

        $this->rechazo[$id] = '';
        unset($this->acciones);
        $this->sincronizarEdicion($id);
        $this->dispatch('toast', icon: 'warning', title: 'Tarea rechazada.');
    }

    /** Máximo de adjuntos por tarea del plan (US10). Se suben de a uno. */
    public const MAX_ADJUNTOS = 10;

    public function subirAdjunto(int $id): void
    {
        abort_if($this->fueraDeFoco($id), 403);
        $accion = $this->solicitud->accionesPlan()->findOrFail($id);
        abort_unless($this->esResponsable($accion) || $this->esLider(), 403);
        $archivo = $this->nuevoAdjunto[$id] ?? null;
        abort_unless($archivo, 422, 'Seleccione un archivo.');

        if ($accion->adjuntos()->count() >= self::MAX_ADJUNTOS) {
            $this->addError("nuevoAdjunto.$id", 'Máximo '.self::MAX_ADJUNTOS.' adjuntos por acción.');

            return;
        }

        $this->validate([
            "nuevoAdjunto.$id" => ['file', 'max:'.config('gestioncambio.adjunto_max_kb')],
        ]);

        $accion->agregarAdjunto($archivo, Auth::id(), config('gestioncambio.adjunto_disco'));

        unset($this->nuevoAdjunto[$id], $this->acciones);
        $this->dispatch('toast', icon: 'success', title: 'Adjunto subido.');
    }

    public function eliminarAdjunto(int $adjuntoId): void
    {
        $adjunto = AdjuntoEvidencia::whereKey($adjuntoId)
            ->where('adjuntable_type', (new AccionPlan)->getMorphClass())
            ->firstOrFail();

        abort_if($this->fueraDeFoco($adjunto->adjuntable_id), 403);
        abort_unless(
            $adjunto->subido_por === Auth::id() || $this->esLider(),
            403,
        );

        $adjunto->delete();
        unset($this->acciones);
        $this->dispatch('toast', icon: 'success', title: 'Adjunto eliminado.');
    }

    public function eliminar(int $id): void
    {
        abort_if($this->soloAccionId !== null, 403);
        abort_unless($this->solicitud->estado === EstadoSolicitud::Solicitado, 403);
        $this->solicitud->accionesPlan()->whereKey($id)->get()->each->delete();
        unset($this->acciones, $this->edicion[$id]);
        $this->dispatch('toast', icon: 'success', title: 'Acción eliminada.');
    }

    public function render()
    {
        return view('livewire.solicitud.plan-accion');
    }
}

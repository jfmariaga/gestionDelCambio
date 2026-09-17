<div class="space-y-3">
    @php($editable = $solicitud->estado === \App\Enums\EstadoSolicitud::Solicitado)
    @php($esLider = auth()->id() === $solicitud->created_by || auth()->user()->hasRole('administrador'))
    @php($puedeReorganizar = $editable && $esLider)
    @php($maxKb = config('gestioncambio.adjunto_max_kb'))

    @forelse ($this->acciones as $accion)
        @php($esResponsable = auth()->id() === $accion->responsable_id)
        @php($puedeOperar = $esResponsable || $esLider)
        <div wire:key="acc-{{ $accion->id }}" class="rounded-lg border border-ink-200 p-4">
            <div class="mb-2 flex flex-wrap items-center gap-2">
                <span class="flex h-6 w-6 flex-none items-center justify-center rounded-md bg-ink-100 text-xs font-bold text-ink-500">{{ $accion->numero }}</span>
                <span class="badge {{ $accion->estado->badgeClass() }}">{{ $accion->estado->etiqueta() }}</span>
                @if ($accion->riesgoAsociado)
                    <span class="badge {{ $accion->riesgoAsociado->nivel?->colorBadge() ?? 'badge-gray' }}">Riesgo {{ $accion->riesgoAsociado->nivel?->value }}{{ $accion->riesgoAsociado->nr ? ' · NR '.$accion->riesgoAsociado->nr : '' }}</span>
                @else
                    <span class="badge badge-gray">Manual</span>
                @endif
                @if ($accion->responsableUsuario)<span class="text-xs text-ink-400">Responsable: {{ $accion->responsableUsuario->name }}</span>@endif
            </div>
            @if ($accion->riesgoAsociado)
                <p class="mb-2 rounded-md border border-ink-200 bg-ink-50 px-3 py-1.5 text-xs text-ink-600">
                    <span class="font-semibold text-ink-500">Riesgo asociado:</span> {{ $accion->riesgoAsociado->riesgo_texto }}
                </p>
            @endif

            <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                <div class="sm:col-span-2 lg:col-span-4"><label class="field-label">Descripción</label><input type="text" class="input input-sm" @disabled(!$puedeReorganizar) wire:model="edicion.{{ $accion->id }}.descripcion"></div>
                <div><label class="field-label">Proceso</label><input type="text" class="input input-sm" @disabled(!$puedeReorganizar) wire:model="edicion.{{ $accion->id }}.proceso"></div>
                <div>
                    <label class="field-label">Responsable</label>
                    <x-user-select :users="$this->usuarios" :model="'edicion.'.$accion->id.'.responsable_id'" :current="$accion->responsable_id" :disabled="! $puedeReorganizar" class="w-full" />
                </div>
                <div><label class="field-label">Fecha</label><input type="date" class="input input-sm" @disabled(!$puedeReorganizar) wire:model="edicion.{{ $accion->id }}.fecha"></div>
                @if (in_array($accion->estado, [\App\Enums\EstadoAccionPlan::Pendiente, \App\Enums\EstadoAccionPlan::EnCurso], true))
                    <div>
                        <label class="field-label">Estado</label>
                        <select class="select input-sm" @disabled(!$puedeOperar) wire:model="edicion.{{ $accion->id }}.estado">
                            @foreach (['Pendiente', 'En curso'] as $e)
                                <option value="{{ $e }}">{{ $e }}</option>
                            @endforeach
                        </select>
                    </div>
                @endif
                <div class="sm:col-span-2 lg:col-span-4"><label class="field-label">Evidencia</label><input type="text" class="input input-sm" @disabled(!$puedeOperar) wire:model="edicion.{{ $accion->id }}.evidencia"></div>
            </div>

            @if ($accion->comentario_validacion && $accion->estado === \App\Enums\EstadoAccionPlan::EnCurso)
                <p class="mt-2 rounded-md border border-amber-200 bg-amber-50 px-3 py-2 text-xs text-amber-700">Devuelta por el líder: {{ $accion->comentario_validacion }}</p>
            @endif

            {{-- Adjuntos de evidencia (US10) — hasta 10 por acción, se suben de a uno --}}
            <div class="mt-3 rounded-lg bg-ink-50 p-3">
                <div class="mb-1.5 text-xs font-semibold text-ink-500">Adjuntos ({{ $accion->adjuntos->count() }}/{{ \App\Livewire\Solicitud\PlanAccion::MAX_ADJUNTOS }})</div>
                @forelse ($accion->adjuntos as $adj)
                    <div wire:key="adj-{{ $adj->id }}" class="flex items-center justify-between gap-2 py-1 text-xs">
                        <a href="{{ route('solicitudes.adjuntos.download', [$solicitud, $adj]) }}" class="font-medium text-brand-700 hover:text-brand-800">{{ $adj->nombre_original }}</a>
                        <span class="text-ink-400">{{ $adj->tamanoLegible() }} · {{ $adj->created_at->format('d/m/Y') }} · {{ $adj->subidoPor?->name }}</span>
                        @if ($adj->subido_por === auth()->id() || $esLider)
                            <button type="button" class="text-rose-600 hover:text-rose-700" wire:click="eliminarAdjunto({{ $adj->id }})">Quitar</button>
                        @endif
                    </div>
                @empty
                    <p class="text-xs text-ink-400">Sin adjuntos.</p>
                @endforelse
                @if (! $puedeOperar)
                    {{-- Sin acción: solo el responsable o el líder pueden adjuntar evidencia. --}}
                @elseif ($accion->adjuntos->count() < \App\Livewire\Solicitud\PlanAccion::MAX_ADJUNTOS)
                    <div class="mt-2 flex items-center gap-2">
                        <input type="file" class="text-xs" wire:key="file-acc-{{ $accion->id }}-{{ $accion->adjuntos->count() }}" wire:model="nuevoAdjunto.{{ $accion->id }}">
                        <button type="button" class="btn btn-secondary btn-xs" wire:click="subirAdjunto({{ $accion->id }})" wire:loading.attr="disabled">Subir</button>
                        <span class="text-[11px] text-ink-400">1 archivo a la vez · máx. {{ number_format($maxKb / 1024, 0) }} MB</span>
                    </div>
                @else
                    <p class="mt-2 text-[11px] text-ink-400">Límite de {{ \App\Livewire\Solicitud\PlanAccion::MAX_ADJUNTOS }} adjuntos alcanzado.</p>
                @endif
                @error('nuevoAdjunto.'.$accion->id)<p class="mt-1 text-[11px] text-rose-600">{{ $message }}</p>@enderror
            </div>

            <div class="mt-3 flex flex-wrap justify-end gap-2">
                @if ($editable && ! $soloAccionId)
                    <button type="button" class="btn btn-danger btn-sm"
                            x-on:click="window.confirmarEliminar('Se eliminará esta acción del plan.').then(ok => ok && $wire.eliminar({{ $accion->id }}))">Eliminar</button>
                @endif

                @if (in_array($accion->estado, [\App\Enums\EstadoAccionPlan::Pendiente, \App\Enums\EstadoAccionPlan::EnCurso], true))
                    @if ($puedeOperar)
                        <button class="btn btn-primary btn-sm" wire:click="guardar({{ $accion->id }})">Guardar</button>
                        <button class="btn btn-success btn-sm" wire:click="marcarCerrada({{ $accion->id }})">Marcar cerrada</button>
                    @endif
                @elseif ($accion->estado === \App\Enums\EstadoAccionPlan::CerradaPendienteValidacion && $esLider)
                    <div class="flex items-center gap-2">
                        <input type="text" class="input input-sm w-48" placeholder="Motivo del rechazo" wire:model="rechazo.{{ $accion->id }}">
                        <button class="btn btn-warning btn-sm" wire:click="rechazar({{ $accion->id }})">Rechazar</button>
                        <button class="btn btn-success btn-sm" wire:click="validar({{ $accion->id }})">Validar</button>
                    </div>
                @endif
            </div>
        </div>
    @empty
        <p class="rounded-lg border border-dashed border-ink-300 bg-ink-50 p-6 text-center text-sm text-ink-400">Sin acciones registradas.</p>
    @endforelse

    @if ($editable && ! $soloAccionId)
        <button class="btn btn-secondary btn-sm" wire:click="agregar">+ Agregar acción</button>
    @endif
</div>

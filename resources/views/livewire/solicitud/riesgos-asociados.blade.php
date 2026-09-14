<div class="space-y-3">
    @forelse ($this->filas as $fila)
        <div wire:key="riesgo-{{ $fila->id }}"
             @class([
                'rounded-lg border p-4',
                'border-amber-300 bg-amber-50' => $fila->estado === \App\Enums\EstadoFila::Huerfana,
                'border-ink-200' => $fila->estado !== \App\Enums\EstadoFila::Huerfana,
             ])>
            <div class="mb-3 flex flex-wrap items-start justify-between gap-2">
                <div class="min-w-0">
                    <span class="badge badge-brand">{{ $fila->proceso_nombre ?: 'Sin proceso' }}</span>
                    @if ($fila->preguntas->count() > 1)
                        <span class="ml-1 text-[11px] text-ink-400">{{ $fila->preguntas->count() }} preguntas de origen</span>
                    @endif
                    <p class="mt-1 text-sm font-medium text-ink-800">{{ $fila->riesgo_texto ?: 'Riesgo manual' }}</p>
                </div>
                <div class="flex items-center gap-2">
                    @if ($fila->nivel)
                        <span class="badge {{ match ($fila->nivel->value) { 'Bajo' => 'badge-green', 'Medio' => 'badge-amber', default => 'badge-red' } }}">
                            NR {{ $fila->nr }} · {{ $fila->nivel->value }}
                        </span>
                    @endif
                </div>
            </div>

            @if ($fila->riesgo_predeterminado_id === null)
                <div class="mb-3 grid gap-3 sm:grid-cols-2">
                    <div><label class="field-label">Proceso</label><input type="text" class="input input-sm" wire:model="edicion.{{ $fila->id }}.proceso_nombre" value="{{ $fila->proceso_nombre }}"></div>
                    <div><label class="field-label">Riesgo</label><input type="text" class="input input-sm" wire:model="edicion.{{ $fila->id }}.riesgo_texto" value="{{ $fila->riesgo_texto }}"></div>
                </div>
            @endif

            <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                <div class="sm:col-span-2 lg:col-span-1"><label class="field-label">Control existente</label><input type="text" class="input input-sm" wire:model="edicion.{{ $fila->id }}.control_existente" value="{{ $fila->control_existente }}"></div>
                <div class="sm:col-span-2 lg:col-span-1"><label class="field-label">Validación requerida</label><input type="text" class="input input-sm" wire:model="edicion.{{ $fila->id }}.accion_requerida" value="{{ $fila->accion_requerida }}"></div>
                <div>
                    <label class="field-label">Responsable</label>
                    <x-user-select :users="$this->usuarios" :model="'edicion.'.$fila->id.'.responsable_id'" :current="$fila->responsable_id" class="w-full" />
                </div>
                <div><label class="field-label">Fecha</label><input type="date" class="input input-sm" wire:model="edicion.{{ $fila->id }}.fecha" value="{{ optional($fila->fecha)->toDateString() }}"></div>
                <div class="grid grid-cols-2 gap-2">
                    <div>
                        <label class="field-label">Probabilidad</label>
                        <select class="select input-sm" wire:model="edicion.{{ $fila->id }}.probabilidad">
                            <option value="">—</option>
                            @foreach (\App\Enums\Probabilidad::opciones() as $valor => $etiqueta)
                                <option value="{{ $valor }}" @selected((int) $fila->probabilidad === $valor)>{{ $etiqueta }} ({{ $valor }})</option>
                            @endforeach
                        </select>
                        @error("edicion.{$fila->id}.probabilidad") <span class="text-[11px] text-rose-600">{{ $message }}</span> @enderror
                    </div>
                    <div>
                        <label class="field-label">Impacto</label>
                        <select class="select input-sm" wire:model="edicion.{{ $fila->id }}.impacto">
                            <option value="">—</option>
                            @foreach (\App\Enums\Impacto::opciones() as $valor => $etiqueta)
                                <option value="{{ $valor }}" @selected((int) $fila->impacto === $valor)>{{ $etiqueta }} ({{ $valor }})</option>
                            @endforeach
                        </select>
                        @error("edicion.{$fila->id}.impacto") <span class="text-[11px] text-rose-600">{{ $message }}</span> @enderror
                    </div>
                </div>
            </div>

            <div class="mt-3 flex justify-end gap-2">
                @if ($fila->estado === \App\Enums\EstadoFila::Huerfana || $fila->riesgo_predeterminado_id === null)
                    <button type="button" class="btn btn-danger btn-sm"
                            x-on:click="window.confirmarEliminar('Se eliminará esta fila de riesgo.').then(ok => ok && $wire.confirmarEliminar({{ $fila->id }}, true))">Eliminar</button>
                @endif
                <button class="btn btn-primary btn-sm" wire:click="guardarFila({{ $fila->id }})">Guardar</button>
            </div>
        </div>
    @empty
        <div class="rounded-lg border border-dashed border-ink-300 bg-ink-50 p-6 text-center text-sm text-ink-400">
            Aún no hay riesgos asociados. Marque preguntas con riesgo como <span class="font-semibold text-emerald-600">Sí</span>.
        </div>
    @endforelse

    <button class="btn btn-secondary btn-sm" wire:click="agregarManual">+ Agregar riesgo manual</button>
</div>

<div class="space-y-3">
    <div class="space-y-2">
        @forelse ($this->criterios as $criterio)
            <div wire:key="cc-{{ $criterio->id }}" class="rounded-lg border border-ink-200 p-3">
                <div class="grid grid-cols-1 gap-2 md:grid-cols-6">
                    <input type="text" class="input input-sm md:col-span-2" wire:model="edicion.{{ $criterio->id }}.descripcion" placeholder="Criterio">
                    <select class="select input-sm" wire:model="edicion.{{ $criterio->id }}.valor">
                        <option value="">—</option>
                        @foreach (['SI' => 'Sí', 'NO' => 'No', 'NA' => 'N/A'] as $v => $etq)
                            <option value="{{ $v }}">{{ $etq }}</option>
                        @endforeach
                    </select>
                    <x-user-select :users="$this->usuarios" :model="'edicion.'.$criterio->id.'.responsable_id'" :current="$criterio->responsable_id" placeholder="Responsable" />
                    <input type="text" class="input input-sm md:col-span-2" wire:model="edicion.{{ $criterio->id }}.detalle" placeholder="Detalle / evidencia">
                </div>
                <div class="mt-2 rounded-lg bg-ink-50 p-2.5">
                    <div class="mb-1 text-[11px] font-semibold text-ink-500">Adjuntos ({{ $criterio->adjuntos->count() }}/{{ \App\Livewire\Solicitud\AprobacionCierre::MAX_ADJUNTOS }})</div>
                    @forelse ($criterio->adjuntos as $adj)
                        <div wire:key="adjcc-{{ $adj->id }}" class="flex items-center justify-between gap-2 py-0.5 text-xs">
                            <a href="{{ route('solicitudes.adjuntos.download', [$solicitud, $adj]) }}" class="font-medium text-brand-700 hover:text-brand-800">{{ $adj->nombre_original }}</a>
                            <span class="text-ink-400">{{ $adj->tamanoLegible() }}</span>
                            @if ($adj->subido_por === auth()->id() || auth()->id() === $solicitud->created_by || auth()->user()->hasRole('administrador'))
                                <button type="button" class="text-rose-600 hover:text-rose-700" wire:click="eliminarAdjunto({{ $adj->id }})">Quitar</button>
                            @endif
                        </div>
                    @empty
                        <p class="text-xs text-ink-400">Sin adjuntos.</p>
                    @endforelse
                    @if ($criterio->adjuntos->count() < \App\Livewire\Solicitud\AprobacionCierre::MAX_ADJUNTOS)
                        <div class="mt-1.5 flex items-center gap-2">
                            <input type="file" class="text-xs" wire:key="file-cc-{{ $criterio->id }}-{{ $criterio->adjuntos->count() }}" wire:model="nuevoAdjunto.{{ $criterio->id }}">
                            <button type="button" class="btn btn-secondary btn-xs" wire:click="subirAdjunto({{ $criterio->id }})" wire:loading.attr="disabled">Subir</button>
                            <span class="text-[11px] text-ink-400">1 a la vez</span>
                        </div>
                    @else
                        <p class="mt-1.5 text-[11px] text-ink-400">Límite de {{ \App\Livewire\Solicitud\AprobacionCierre::MAX_ADJUNTOS }} adjuntos.</p>
                    @endif
                    @error('nuevoAdjunto.'.$criterio->id)<p class="mt-1 text-[11px] text-rose-600">{{ $message }}</p>@enderror
                </div>

                <div class="mt-2 flex justify-end gap-1">
                    <button class="btn btn-primary btn-xs" wire:click="guardar({{ $criterio->id }})">Guardar</button>
                    <button type="button" class="btn btn-danger btn-xs"
                            x-on:click="window.confirmarEliminar('Se eliminará este criterio de cierre.').then(ok => ok && $wire.eliminar({{ $criterio->id }}))">×</button>
                </div>
            </div>
        @empty
            <p class="rounded-lg bg-ink-50 p-4 text-sm text-ink-400">Sin criterios de cierre.</p>
        @endforelse
    </div>

    <div class="flex flex-wrap items-center gap-2">
        <button class="btn btn-secondary btn-sm" wire:click="agregar">+ Agregar criterio</button>
        <button class="btn btn-warning btn-sm" wire:click="comprobarCierre">Comprobar requisitos de cierre</button>
    </div>

    @if ($bloqueos)
        <ul class="list-disc rounded-lg border border-rose-200 bg-rose-50 p-3 pl-8 text-sm text-rose-700">
            @foreach ($bloqueos as $b)<li>{{ $b }}</li>@endforeach
        </ul>
    @endif
</div>

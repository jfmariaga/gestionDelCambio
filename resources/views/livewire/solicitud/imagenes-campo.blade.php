<div class="mt-2">
    @php($editable = auth()->user()->can('update', $solicitud))
    @php($maxKb = config('gestioncambio.adjunto_max_kb'))

    <div class="mb-1.5 text-xs font-semibold text-ink-500">
        Imágenes ({{ $this->imagenes->count() }}/{{ \App\Livewire\Solicitud\ImagenesCampo::MAX_IMAGENES }})
    </div>

    @if ($this->imagenes->isNotEmpty())
        <div class="mb-2 flex flex-wrap gap-2">
            @foreach ($this->imagenes as $img)
                <div wire:key="img-{{ $campo }}-{{ $img->id }}" class="group relative h-12 w-12 flex-none overflow-hidden rounded-md border border-ink-200">
                    <a href="{{ route('solicitudes.adjuntos.inline', [$solicitud, $img]) }}" target="_blank" title="{{ $img->nombre_original }}">
                        <img src="{{ route('solicitudes.adjuntos.inline', [$solicitud, $img]) }}" class="h-full w-full object-cover" alt="{{ $img->nombre_original }}">
                    </a>
                    @if ($editable)
                        <button type="button" wire:click="eliminar({{ $img->id }})" wire:confirm="¿Quitar esta imagen?"
                                class="absolute right-0 top-0 flex h-3.5 w-3.5 items-center justify-center rounded-bl bg-rose-600/90 text-[10px] leading-none text-white hover:bg-rose-700">&times;</button>
                    @endif
                </div>
            @endforeach
        </div>
    @endif

    @if ($editable)
        @if ($this->imagenes->count() < \App\Livewire\Solicitud\ImagenesCampo::MAX_IMAGENES)
            <div x-data="{ nombreArchivo: null }" x-on:imagen-subida="nombreArchivo = null" class="flex flex-wrap items-center gap-2">
                <label class="group flex cursor-pointer items-center gap-1.5 rounded-lg border border-dashed border-ink-300 bg-ink-50 px-2.5 py-1.5 text-xs font-medium text-ink-600 transition-colors hover:border-brand-400 hover:bg-brand-50 hover:text-brand-700">
                    <svg class="h-3.5 w-3.5 flex-none" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M12 16V4M12 4l-4 4M12 4l4 4"/><path d="M4 16v3a1 1 0 0 0 1 1h14a1 1 0 0 0 1-1v-3"/>
                    </svg>
                    <span x-text="nombreArchivo || 'Elegir imagen'" class="max-w-[9rem] truncate"></span>
                    <input type="file" class="hidden" accept="image/png,image/jpeg,image/webp"
                           wire:model="nuevaImagen" wire:key="file-{{ $campo }}-{{ $this->imagenes->count() }}"
                           x-on:change="nombreArchivo = $event.target.files[0]?.name ?? null">
                </label>
                <button type="button" class="btn btn-secondary btn-xs" wire:click="subir"
                        wire:loading.attr="disabled" wire:target="subir,nuevaImagen">
                    <span wire:loading.remove wire:target="subir">Subir</span>
                    <span wire:loading wire:target="subir">Subiendo…</span>
                </button>
                <span class="text-[11px] text-ink-400">JPG/PNG/WEBP · máx. {{ number_format($maxKb / 1024, 0) }} MB</span>
            </div>
        @else
            <p class="text-[11px] text-ink-400">Límite de {{ \App\Livewire\Solicitud\ImagenesCampo::MAX_IMAGENES }} imágenes alcanzado.</p>
        @endif
        @error('nuevaImagen')<p class="mt-1 text-[11px] text-rose-600">{{ $message }}</p>@enderror
    @endif
</div>

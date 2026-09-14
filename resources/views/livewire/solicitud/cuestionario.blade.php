<div class="space-y-4">
    @php($pct = $this->avance['total'] ? round(100 * $this->avance['respondidas'] / $this->avance['total']) : 0)

    {{-- Progress --}}
    <div class="rounded-lg bg-ink-50 p-4">
        <div class="flex items-center justify-between text-xs font-medium text-ink-500">
            <span>Avance del cuestionario</span>
            <span class="tabular-nums text-ink-700">{{ $this->avance['respondidas'] }} / {{ $this->avance['total'] }} · {{ $pct }}%</span>
        </div>
        <div class="mt-2 h-2 w-full overflow-hidden rounded-full bg-ink-200">
            <div class="h-full rounded-full bg-brand-600 transition-all duration-500" style="width: {{ $pct }}%"></div>
        </div>
    </div>

    @if ($this->advertenciasSinRiesgo->isNotEmpty())
        <div class="rounded-lg border border-amber-200 bg-amber-50 p-3 text-sm text-amber-800">
            <p class="font-semibold">Preguntas "Sí" sin riesgo asociado en el catálogo</p>
            <ul class="mt-1 list-disc pl-5 text-amber-700">
                @foreach ($this->advertenciasSinRiesgo as $p)<li>{{ $p->texto }}</li>@endforeach
            </ul>
        </div>
    @endif

    {{-- Process selector on mobile --}}
    <div class="md:hidden">
        <label class="field-label">Proceso</label>
        <select class="select" wire:model.live="procesoActivo">
            @foreach ($this->procesos as $proceso)
                <option value="{{ $proceso->id }}">
                    {{ $proceso->nombre }} ({{ $this->respondidasPorProceso[$proceso->id] ?? 0 }}/{{ $proceso->preguntas_total }})
                </option>
            @endforeach
        </select>
    </div>

    <div class="grid grid-cols-1 gap-5 md:grid-cols-[15rem_minmax(0,1fr)]">
        {{-- Process rail (desktop) --}}
        <nav class="hidden space-y-1 md:block">
            @foreach ($this->procesos as $proceso)
                @php($hechas = $this->respondidasPorProceso[$proceso->id] ?? 0)
                @php($completo = $hechas >= $proceso->preguntas_total)
                <button type="button" wire:key="proc-{{ $proceso->id }}" wire:click="irAProceso({{ $proceso->id }})"
                        @class([
                            'flex w-full items-center gap-2.5 rounded-lg px-3 py-2 text-left text-sm transition-colors',
                            'bg-brand-700 text-white' => $procesoActivo === $proceso->id,
                            'text-ink-600 hover:bg-ink-100' => $procesoActivo !== $proceso->id,
                        ])>
                    <span @class([
                        'flex h-4 w-4 flex-none items-center justify-center rounded-full text-[10px] font-bold',
                        'bg-white/20 text-white' => $procesoActivo === $proceso->id,
                        'bg-emerald-100 text-emerald-700' => $procesoActivo !== $proceso->id && $completo,
                        'bg-ink-200 text-ink-500' => $procesoActivo !== $proceso->id && ! $completo,
                    ])>
                        @if ($completo) ✓ @endif
                    </span>
                    <span class="min-w-0 flex-1 truncate">{{ $proceso->nombre }}</span>
                    <span @class([
                        'flex-none text-[11px] tabular-nums',
                        'text-white/70' => $procesoActivo === $proceso->id,
                        'text-ink-400' => $procesoActivo !== $proceso->id,
                    ])>{{ $hechas }}/{{ $proceso->preguntas_total }}</span>
                </button>
            @endforeach
        </nav>

        {{-- Questions --}}
        <div class="space-y-3">
            @forelse ($this->preguntasDelProceso as $pregunta)
                @php($valor = $respuestas[$pregunta->id] ?? null)
                <div wire:key="preg-{{ $pregunta->id }}"
                     @class([
                        'rounded-lg border p-4 transition-colors',
                        'border-emerald-200 bg-emerald-50/40' => $valor === 'SI',
                        'border-ink-200' => $valor !== 'SI',
                     ])>
                    <p class="text-sm font-medium text-ink-800">{{ $pregunta->texto }}</p>
                    @if ($pregunta->riesgoPredeterminado)
                        <p class="mt-1 flex gap-1.5 text-xs text-ink-400">
                            <svg class="mt-0.5 h-3.5 w-3.5 flex-none" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 9v4M12 17h.01M10.3 3.9 1.8 18a2 2 0 0 0 1.7 3h17a2 2 0 0 0 1.7-3L13.7 3.9a2 2 0 0 0-3.4 0z"/></svg>
                            <span>{{ $pregunta->riesgoPredeterminado->texto }}</span>
                        </p>
                    @endif
                    <div class="seg mt-3">
                        @foreach (['SI' => 'Sí', 'NO' => 'No', 'NA' => 'N/A'] as $v => $etq)
                            <button type="button" wire:click="responder({{ $pregunta->id }}, '{{ $v }}')"
                                    @if ($valor === $v) data-active="{{ strtolower($v) === 'na' ? 'na' : strtolower($v) }}" @endif>
                                {{ $etq }}
                            </button>
                        @endforeach
                    </div>
                </div>
            @empty
                <p class="rounded-lg bg-ink-50 p-4 text-sm text-ink-400">Este proceso no tiene preguntas activas.</p>
            @endforelse
        </div>
    </div>
</div>

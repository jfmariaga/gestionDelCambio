<div class="space-y-4">
    @php($res = $this->resultado)
    <div class="flex flex-wrap items-center gap-x-6 gap-y-2 rounded-lg bg-ink-50 p-4">
        <div>
            <div class="text-xs font-medium uppercase tracking-wide text-ink-400">Suma</div>
            <div class="text-2xl font-bold tabular-nums text-ink-900">{{ $res['suma'] ?? '—' }}</div>
        </div>
        <div>
            <div class="text-xs font-medium uppercase tracking-wide text-ink-400">Clasificación</div>
            @if ($res['clasificacion'])
                <span class="badge {{ match ($res['clasificacion']->value) { 'Menor' => 'badge-green', 'Mayor' => 'badge-amber', 'Crítico' => 'badge-red', default => 'badge-gray' } }} mt-1 text-sm">
                    {{ $res['clasificacion']->value }}
                </span>
            @else
                <div class="mt-1 text-sm text-amber-600">Faltan {{ $res['faltan'] }} criterios</div>
            @endif
        </div>
        <p class="ml-auto text-xs text-ink-400">11–16 Menor · 17–23 Mayor · 24–33 Crítico</p>
    </div>

    <div class="space-y-2">
        @foreach ($this->criterios as $criterio)
            @php($sel = $calificaciones[$criterio->id] ?? null)
            <div wire:key="crit-{{ $criterio->id }}" class="rounded-lg border border-ink-200 p-3">
                <div class="flex items-center justify-between">
                    <p class="text-sm font-semibold text-ink-800">{{ $criterio->nombre }}</p>
                    @if ($sel)<span class="badge badge-brand">Nivel {{ $sel }}</span>@endif
                </div>
                <div class="mt-2 grid grid-cols-1 gap-2 md:grid-cols-3">
                    @foreach ([1 => $criterio->desc_nivel_1, 2 => $criterio->desc_nivel_2, 3 => $criterio->desc_nivel_3] as $nivel => $desc)
                        <button type="button" wire:click="calificar({{ $criterio->id }}, {{ $nivel }})"
                                @class([
                                    'rounded-lg border p-2.5 text-left text-xs transition-colors',
                                    'border-brand-500 bg-brand-50 ring-1 ring-brand-500' => $sel === $nivel,
                                    'border-ink-200 hover:border-ink-300 hover:bg-ink-50' => $sel !== $nivel,
                                ])>
                            <span class="font-semibold text-ink-700">{{ ['', 'Menor (1)', 'Mayor (2)', 'Crítico (3)'][$nivel] }}</span>
                            <span class="mt-1 block text-ink-500">{{ $desc }}</span>
                        </button>
                    @endforeach
                </div>
            </div>
        @endforeach
    </div>
</div>

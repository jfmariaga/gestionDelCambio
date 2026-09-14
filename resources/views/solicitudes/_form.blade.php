@php($s = $solicitud ?? null)
@php($clasif = $s?->clasificacionVigente())
@php($aprobadoresAsignados = $s ? $s->aprobadores->pluck('name')->join(', ') : '')

@if ($errors->any())
    <div class="mb-4 rounded-lg border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700">
        <ul class="list-disc pl-4">
            @foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach
        </ul>
    </div>
@endif

<div class="grid grid-cols-1 gap-4 md:grid-cols-3">
    <div>
        <label class="field-label">Fecha</label>
        <input type="date" name="fecha" class="input"
               value="{{ old('fecha', optional($s?->fecha)->toDateString() ?? now()->toDateString()) }}" required>
    </div>
    <div class="md:col-span-2">
        <label class="field-label">Nombre del cambio</label>
        <input type="text" name="nombre_cambio" class="input" value="{{ old('nombre_cambio', $s?->nombre_cambio) }}" required>
    </div>
    <div>
        <label class="field-label">Solicitante / cargo</label>
        <input type="text" name="solicitante_cargo" class="input" value="{{ old('solicitante_cargo', $s?->solicitante_cargo) }}">
    </div>
    <div>
        <label class="field-label">Área / Proceso <span class="text-rose-500">*</span></label>
        <select name="area_proceso" class="select" required>
            <option value="">Seleccione…</option>
            @foreach (\App\Models\Proceso::activos()->ordenados()->pluck('nombre') as $nombreProceso)
                <option value="{{ $nombreProceso }}" @selected(old('area_proceso', $s?->area_proceso) === $nombreProceso)>{{ $nombreProceso }}</option>
            @endforeach
        </select>
    </div>
    <div>
        <label class="field-label">Tipo de cambio <span class="text-rose-500">*</span></label>
        <select name="tipo_cambio" class="select" required>
            <option value="">Seleccione…</option>
            @foreach (\App\Enums\TipoCambio::opciones() as $valorTipo => $etiquetaTipo)
                <option value="{{ $valorTipo }}" @selected(old('tipo_cambio', $s?->tipo_cambio?->value) === $valorTipo)>{{ $etiquetaTipo }}</option>
            @endforeach
        </select>
    </div>
    <div>
        <label class="field-label">Fecha requerida <span class="text-rose-500">*</span></label>
        <input type="date" name="fecha_requerida" class="input" required value="{{ old('fecha_requerida', optional($s?->fecha_requerida)->toDateString()) }}">
    </div>
    <div x-data="{
            raw: '{{ old('costo_estimado', $s?->costo_estimado ? (int) $s->costo_estimado : '') }}',
            get display() {
                if (this.raw === '' || this.raw === null) return '';
                return new Intl.NumberFormat('es-CO').format(this.raw);
            },
            onInput(e) { this.raw = e.target.value.replace(/\D/g, ''); }
         }">
        <label class="field-label">Costo estimado (COP)</label>
        <div class="relative">
            <span class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3 text-sm text-ink-400">$</span>
            <input type="text" inputmode="numeric" class="input pl-7"
                   x-bind:value="display" x-on:input="onInput($event)" placeholder="0">
        </div>
        <input type="hidden" name="costo_estimado" :value="raw">
    </div>
    <label class="flex items-center gap-2.5 self-end pb-1">
        <input type="hidden" name="requiere_comite" value="0">
        <input type="checkbox" name="requiere_comite" value="1" @checked(old('requiere_comite', $s?->requiere_comite))
               class="rounded border-ink-300 text-brand-600 focus:ring-brand-500">
        <span class="text-sm text-ink-700">¿Requiere comité?</span>
    </label>
    <div>
        <label class="field-label">Aprobador asignado <span class="font-normal text-ink-400">(automático)</span></label>
        <div class="input flex items-center bg-ink-50 text-ink-700">
            @if ($aprobadoresAsignados)
                {{ $aprobadoresAsignados }}
            @elseif ($clasif)
                <span class="text-amber-600">Configure los destinatarios del área en Administración.</span>
            @else
                <span class="text-ink-400">Se asigna al completar la evaluación.</span>
            @endif
        </div>
        <p class="mt-1 text-[11px] text-ink-400">Menor → SIG + dueño del proceso · Mayor → SIG, Ambiental, Calidad, SST + dueño del proceso · Crítico → lo anterior + Gerencia General. Un administrador puede sustituirlo desde la vista de la solicitud.</p>
    </div>
</div>

<div class="mt-5 grid grid-cols-1 gap-4 md:grid-cols-3">
    <div>
        <label class="field-label">Situación actual <span class="text-rose-500">*</span></label>
        <textarea name="situacion_actual" rows="4" class="textarea" required>{{ old('situacion_actual', $s?->situacion_actual) }}</textarea>
    </div>
    <div>
        <label class="field-label">Qué cambiará <span class="text-rose-500">*</span></label>
        <textarea name="que_cambiara" rows="4" class="textarea" required>{{ old('que_cambiara', $s?->que_cambiara) }}</textarea>
    </div>
    <div>
        <label class="field-label">Resultado esperado <span class="text-rose-500">*</span></label>
        <textarea name="resultado_esperado" rows="4" class="textarea" required>{{ old('resultado_esperado', $s?->resultado_esperado) }}</textarea>
    </div>
</div>

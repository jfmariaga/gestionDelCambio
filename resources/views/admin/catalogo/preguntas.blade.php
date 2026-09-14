<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <h1 class="text-lg font-semibold text-ink-900">Administración</h1>
            <form method="POST" action="{{ route('admin.catalogo.importar') }}"
                  data-confirm="Actualiza y crea entradas desde el archivo base. No borra ninguna."
                  data-confirm-title="Reimportar catálogo">
                @csrf
                <button class="btn btn-secondary">Reimportar catálogo base</button>
            </form>
        </div>
    </x-slot>

    @include('admin._nav')

    @if ($errors->any())<div class="card mb-4 border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700"><ul class="list-disc pl-4">@foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>@endif

    {{-- New pregunta --}}
    <form method="POST" action="{{ route('admin.catalogo.preguntas.store') }}" class="card card-pad mb-5"
          x-data="{ proceso: '' }">
        @csrf
        <h2 class="mb-3 text-sm font-semibold text-ink-900">Nueva pregunta clave</h2>
        <div class="grid gap-3 md:grid-cols-2">
            <div>
                <label class="field-label">Proceso</label>
                <select name="proceso_id" x-model="proceso" class="select" required>
                    <option value="">Seleccione…</option>
                    @foreach ($procesos as $p)<option value="{{ $p->id }}">{{ $p->nombre }}</option>@endforeach
                </select>
            </div>
            <div>
                <label class="field-label">Riesgo asociado (opcional)</label>
                <select name="riesgo_predeterminado_id" class="select">
                    <option value="">— Sin riesgo —</option>
                    @foreach ($riesgosPorProceso as $pid => $lista)
                        <template x-if="proceso == '{{ $pid }}'">
                            <optgroup label="Riesgos del proceso">
                                @foreach ($lista as $r)<option value="{{ $r->id }}">{{ Str::limit($r->texto, 90) }}</option>@endforeach
                            </optgroup>
                        </template>
                    @endforeach
                </select>
            </div>
            <div class="md:col-span-2">
                <label class="field-label">Texto de la pregunta</label>
                <input name="texto" class="input" required>
            </div>
            <div><label class="field-label">Dueño por defecto</label><input name="dueno_por_defecto" class="input"></div>
            <div><label class="field-label">Orden</label><input name="orden" type="number" value="0" class="input" required></div>
            <div><label class="field-label">Evidencia por defecto</label><input name="evidencia_por_defecto" class="input"></div>
            <div><label class="field-label">Acción por defecto</label><input name="accion_por_defecto" class="input"></div>
        </div>
        <div class="mt-3 flex justify-end"><button class="btn btn-primary">Agregar pregunta</button></div>
    </form>

    {{-- Filter --}}
    <form method="GET" class="mb-3 flex flex-wrap items-center gap-2 text-sm">
        <label class="text-ink-500">Filtrar por proceso:</label>
        <select name="proceso" class="select input-sm w-56" onchange="this.form.submit()">
            <option value="">Todos</option>
            @foreach ($procesos as $p)<option value="{{ $p->id }}" @selected($procesoId === $p->id)>{{ $p->nombre }}</option>@endforeach
        </select>
    </form>

    {{-- List --}}
    <div class="space-y-2">
        @forelse ($preguntas as $pregunta)
            <div class="card card-pad">
                <form method="POST" action="{{ route('admin.catalogo.preguntas.update', $pregunta) }}" class="space-y-3">
                    @csrf @method('PUT')
                    <div class="grid gap-3 md:grid-cols-2">
                        <div>
                            <label class="field-label">Proceso</label>
                            <select name="proceso_id" class="select input-sm">
                                @foreach ($procesos as $p)<option value="{{ $p->id }}" @selected($pregunta->proceso_id === $p->id)>{{ $p->nombre }}</option>@endforeach
                            </select>
                        </div>
                        <div>
                            <label class="field-label">Riesgo asociado</label>
                            <select name="riesgo_predeterminado_id" class="select input-sm">
                                <option value="">— Sin riesgo —</option>
                                @foreach (($riesgosPorProceso[$pregunta->proceso_id] ?? collect()) as $r)
                                    <option value="{{ $r->id }}" @selected($pregunta->riesgo_predeterminado_id === $r->id)>{{ Str::limit($r->texto, 90) }}</option>
                                @endforeach
                                @if ($pregunta->riesgoPredeterminado && $pregunta->riesgoPredeterminado->proceso_id !== $pregunta->proceso_id)
                                    <option value="{{ $pregunta->riesgo_predeterminado_id }}" selected>{{ Str::limit($pregunta->riesgoPredeterminado->texto, 90) }}</option>
                                @endif
                            </select>
                        </div>
                        <div class="md:col-span-2">
                            <label class="field-label">Texto</label>
                            <input name="texto" value="{{ $pregunta->texto }}" class="input input-sm">
                        </div>
                        <div><label class="field-label">Dueño por defecto</label><input name="dueno_por_defecto" value="{{ $pregunta->dueno_por_defecto }}" class="input input-sm"></div>
                        <div><label class="field-label">Orden</label><input name="orden" type="number" value="{{ $pregunta->orden }}" class="input input-sm"></div>
                        <div><label class="field-label">Evidencia por defecto</label><input name="evidencia_por_defecto" value="{{ $pregunta->evidencia_por_defecto }}" class="input input-sm"></div>
                        <div><label class="field-label">Acción por defecto</label><input name="accion_por_defecto" value="{{ $pregunta->accion_por_defecto }}" class="input input-sm"></div>
                    </div>
                    <div class="flex items-center justify-between">
                        <label class="flex items-center gap-2 text-sm text-ink-600">
                            <input type="checkbox" name="activo" value="1" @checked($pregunta->activo) class="rounded border-ink-300 text-brand-600 focus:ring-brand-500">
                            Activa
                        </label>
                        <button class="btn btn-primary btn-sm">Guardar</button>
                    </div>
                </form>
                <form method="POST" action="{{ route('admin.catalogo.preguntas.destroy', $pregunta) }}" class="mt-1 text-right"
                      data-confirm="Se eliminará esta pregunta del catálogo." data-confirm-title="¿Eliminar pregunta?">
                    @csrf @method('DELETE')
                    <button class="btn btn-danger btn-xs">Eliminar pregunta</button>
                </form>
            </div>
        @empty
            <p class="card card-pad text-center text-sm text-ink-400">Sin preguntas.</p>
        @endforelse
    </div>

    <div class="mt-4">{{ $preguntas->links() }}</div>
</x-app-layout>

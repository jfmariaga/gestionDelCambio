<x-app-layout>
    <x-slot name="header"><h1 class="text-lg font-semibold text-ink-900">Administración</h1></x-slot>

    @include('admin._nav')

    @if ($errors->any())<div class="card mb-4 border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700"><ul class="list-disc pl-4">@foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>@endif

    <form method="POST" action="{{ route('admin.catalogo.riesgos.store') }}" class="card card-pad mb-5">
        @csrf
        <h2 class="mb-3 text-sm font-semibold text-ink-900">Nuevo riesgo predeterminado</h2>
        <div class="grid gap-3 sm:grid-cols-[14rem_1fr_auto]">
            <select name="proceso_id" class="select" required>
                <option value="">Proceso…</option>
                @foreach ($procesos as $p)<option value="{{ $p->id }}">{{ $p->nombre }}</option>@endforeach
            </select>
            <input name="texto" class="input" placeholder="Enunciado del riesgo" required>
            <button class="btn btn-primary">Agregar</button>
        </div>
    </form>

    <div class="space-y-4">
        @foreach ($procesos as $proceso)
            <section class="card">
                <div class="flex items-center justify-between border-b border-ink-200 px-5 py-3">
                    <h3 class="text-sm font-semibold text-ink-900">{{ $proceso->nombre }}</h3>
                    <span class="badge badge-gray">{{ $proceso->riesgosPredeterminados->count() }}</span>
                </div>
                <div class="divide-y divide-ink-100">
                    @forelse ($proceso->riesgosPredeterminados as $r)
                        <div class="flex items-center gap-2 px-5 py-2.5">
                            <form method="POST" action="{{ route('admin.catalogo.riesgos.update', $r) }}" class="flex flex-1 items-center gap-2">
                                @csrf @method('PUT')
                                <input type="hidden" name="proceso_id" value="{{ $proceso->id }}">
                                <input name="texto" value="{{ $r->texto }}" class="input input-sm flex-1">
                                <label class="flex flex-none items-center gap-1.5 text-xs text-ink-500"><input type="checkbox" name="activo" value="1" @checked($r->activo) class="rounded border-ink-300 text-brand-600 focus:ring-brand-500">activo</label>
                                <button class="btn btn-primary btn-xs">Guardar</button>
                            </form>
                            <form method="POST" action="{{ route('admin.catalogo.riesgos.destroy', $r) }}" data-confirm="Se eliminará este riesgo predeterminado." data-confirm-title="¿Eliminar riesgo?">
                                @csrf @method('DELETE')
                                <button class="btn btn-danger btn-xs">×</button>
                            </form>
                        </div>
                    @empty
                        <p class="px-5 py-3 text-sm text-ink-400">Sin riesgos para este proceso.</p>
                    @endforelse
                </div>
            </section>
        @endforeach
    </div>
</x-app-layout>

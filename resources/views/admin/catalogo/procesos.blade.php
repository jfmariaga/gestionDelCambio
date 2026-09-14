<x-app-layout>
    <x-slot name="header"><h1 class="text-lg font-semibold text-ink-900">Administración</h1></x-slot>

    @include('admin._nav')

    @if ($errors->any())<div class="card mb-4 border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700"><ul class="list-disc pl-4">@foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>@endif

    <div class="grid gap-5 lg:grid-cols-[1fr_20rem]">
        <div class="card overflow-hidden">
            <div class="overflow-x-auto">
                <table class="dtable">
                    <thead><tr><th>Proceso</th><th class="text-center">Orden</th><th class="text-center">Preguntas</th><th class="text-center">Riesgos</th><th class="text-center">Activo</th><th></th></tr></thead>
                    <tbody>
                        @foreach ($procesos as $p)
                            <tr>
                                <form method="POST" action="{{ route('admin.catalogo.procesos.update', $p) }}" id="proc-{{ $p->id }}">@csrf @method('PUT')</form>
                                <td><input form="proc-{{ $p->id }}" name="nombre" value="{{ $p->nombre }}" class="input input-sm w-full min-w-[12rem]"></td>
                                <td class="text-center"><input form="proc-{{ $p->id }}" name="orden" type="number" value="{{ $p->orden }}" class="input input-sm w-16 text-center"></td>
                                <td class="text-center text-ink-500">{{ $p->preguntas_clave_count }}</td>
                                <td class="text-center text-ink-500">{{ $p->riesgos_predeterminados_count }}</td>
                                <td class="text-center"><input form="proc-{{ $p->id }}" type="checkbox" name="activo" value="1" @checked($p->activo) class="rounded border-ink-300 text-brand-600 focus:ring-brand-500"></td>
                                <td class="whitespace-nowrap text-right">
                                    <button form="proc-{{ $p->id }}" class="btn btn-primary btn-xs">Guardar</button>
                                    <form method="POST" action="{{ route('admin.catalogo.procesos.destroy', $p) }}" class="inline" data-confirm="Se eliminará el proceso «{{ $p->nombre }}» y sus preguntas y riesgos asociados." data-confirm-title="¿Eliminar proceso?">
                                        @csrf @method('DELETE')
                                        <button class="btn btn-danger btn-xs">×</button>
                                    </form>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>

        <form method="POST" action="{{ route('admin.catalogo.procesos.store') }}" class="card card-pad h-fit space-y-3">
            @csrf
            <h2 class="text-sm font-semibold text-ink-900">Nuevo proceso</h2>
            <div><label class="field-label">Nombre</label><input name="nombre" class="input" required></div>
            <div><label class="field-label">Orden</label><input name="orden" type="number" value="{{ $procesos->max('orden') + 1 }}" class="input" required></div>
            <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="activo" value="1" checked class="rounded border-ink-300 text-brand-600 focus:ring-brand-500"><span class="text-ink-700">Activo</span></label>
            <button class="btn btn-primary w-full">Agregar</button>
        </form>
    </div>
</x-app-layout>

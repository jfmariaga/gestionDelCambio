<x-app-layout>
    <x-slot name="header"><h1 class="text-lg font-semibold text-ink-900">Administración</h1></x-slot>

    @include('admin._nav')

    @if ($errors->any())<div class="card mb-4 border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700"><ul class="list-disc pl-4">@foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>@endif

    <div class="grid gap-5 lg:grid-cols-[1fr_20rem]">
        <div class="card overflow-hidden">
            <div class="overflow-x-auto">
                <table class="dtable">
                    <thead><tr><th>Planta / sede</th><th>Código</th><th class="text-center">Orden</th><th class="text-center">Solicitudes</th><th class="text-center">Activo</th><th></th></tr></thead>
                    <tbody>
                        @foreach ($plantas as $p)
                            <tr>
                                <form method="POST" action="{{ route('admin.plantas.update', $p) }}" id="planta-{{ $p->id }}">@csrf @method('PUT')</form>
                                <td><input form="planta-{{ $p->id }}" name="nombre" value="{{ $p->nombre }}" class="input input-sm w-full min-w-[12rem]"></td>
                                <td><input form="planta-{{ $p->id }}" name="codigo" value="{{ $p->codigo }}" class="input input-sm w-28 uppercase"></td>
                                <td class="text-center"><input form="planta-{{ $p->id }}" name="orden" type="number" value="{{ $p->orden }}" class="input input-sm w-16 text-center"></td>
                                <td class="text-center text-ink-500">{{ $p->solicitudes_count }}</td>
                                <td class="text-center"><input form="planta-{{ $p->id }}" type="checkbox" name="activo" value="1" @checked($p->activo) class="rounded border-ink-300 text-brand-600 focus:ring-brand-500"></td>
                                <td class="whitespace-nowrap text-right">
                                    <button form="planta-{{ $p->id }}" class="btn btn-primary btn-xs">Guardar</button>
                                    <form method="POST" action="{{ route('admin.plantas.destroy', $p) }}" class="inline" data-confirm="Se eliminará la planta «{{ $p->nombre }}»." data-confirm-title="¿Eliminar planta?">
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

        <form method="POST" action="{{ route('admin.plantas.store') }}" class="card card-pad h-fit space-y-3">
            @csrf
            <h2 class="text-sm font-semibold text-ink-900">Nueva planta</h2>
            <div><label class="field-label">Nombre</label><input name="nombre" class="input" required></div>
            <div><label class="field-label">Código</label><input name="codigo" class="input uppercase" required></div>
            <div><label class="field-label">Orden</label><input name="orden" type="number" value="{{ $plantas->max('orden') + 1 }}" class="input" required></div>
            <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="activo" value="1" checked class="rounded border-ink-300 text-brand-600 focus:ring-brand-500"><span class="text-ink-700">Activo</span></label>
            <button class="btn btn-primary w-full">Agregar</button>
        </form>
    </div>
</x-app-layout>

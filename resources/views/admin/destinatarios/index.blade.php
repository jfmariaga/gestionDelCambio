<x-app-layout>
    <x-slot name="header"><h1 class="text-lg font-semibold text-ink-900">Administración</h1></x-slot>

    @include('admin._nav')

    @if ($errors->any())<div class="card mb-4 border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700"><ul class="list-disc pl-4">@foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>@endif

    <p class="mb-4 text-sm text-ink-500">
        Cada movimiento de una solicitud avisa a las áreas correspondientes según la clasificación
        del cambio: <strong>Gestión Integral</strong> y <strong>Jefes</strong> siempre; <strong>SST</strong>,
        <strong>Gestión Ambiental</strong> y <strong>Calidad e Inocuidad</strong> cuando el cambio es Mayor o
        Crítico; el <strong>Comité de cambio</strong> al enviarse a aprobación.
    </p>

    <div class="space-y-4">
        @foreach ($areas as $area)
            <div class="card card-pad">
                <h2 class="mb-3 text-sm font-semibold text-ink-900">{{ $area->nombre }}</h2>

                <ul class="mb-3 divide-y divide-ink-100 text-sm">
                    @forelse ($area->destinatarios as $d)
                        <li class="flex items-center justify-between gap-3 py-2">
                            <span class="text-ink-800">
                                {{ $d->usuario?->name ?? $d->email }}
                                <span class="text-xs text-ink-400">{{ $d->usuario ? '· usuario' : '· correo externo' }}</span>
                                @unless ($d->activo)<span class="badge badge-gray">Inactivo</span>@endunless
                            </span>
                            <span class="flex items-center gap-2">
                                <form method="POST" action="{{ route('admin.destinatarios.update', $d) }}">
                                    @csrf @method('PUT')
                                    <input type="hidden" name="activo" value="{{ $d->activo ? 0 : 1 }}">
                                    <button class="btn btn-ghost btn-xs">{{ $d->activo ? 'Desactivar' : 'Activar' }}</button>
                                </form>
                                <form method="POST" action="{{ route('admin.destinatarios.destroy', $d) }}" data-confirm="¿Quitar este destinatario?">
                                    @csrf @method('DELETE')
                                    <button class="btn btn-danger btn-xs">×</button>
                                </form>
                            </span>
                        </li>
                    @empty
                        <li class="py-2 text-ink-400">Sin destinatarios. Los avisos de esta área quedarán registrados en la bitácora pero no se enviarán.</li>
                    @endforelse
                </ul>

                <form method="POST" action="{{ route('admin.destinatarios.store') }}" class="flex flex-wrap items-end gap-2">
                    @csrf
                    <input type="hidden" name="area_id" value="{{ $area->id }}">
                    <div>
                        <label class="field-label">Usuario</label>
                        <select name="user_id" class="select select-sm">
                            <option value="">—</option>
                            @foreach ($usuarios as $u)<option value="{{ $u->id }}">{{ $u->name }}</option>@endforeach
                        </select>
                    </div>
                    <div>
                        <label class="field-label">o correo externo</label>
                        <input type="email" name="email" class="input input-sm" placeholder="correo@empresa.com">
                    </div>
                    <button class="btn btn-secondary btn-sm">Agregar</button>
                </form>
            </div>
        @endforeach
    </div>
</x-app-layout>

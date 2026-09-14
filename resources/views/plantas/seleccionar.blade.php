<x-app-layout>
    <x-slot name="header">
        <h1 class="text-lg font-semibold text-ink-900">Seleccione su planta / sede</h1>
    </x-slot>

    <div class="mx-auto max-w-lg">
        <div class="card card-pad">
            <p class="mb-4 text-sm text-ink-500">
                Elija la planta en la que va a trabajar. Las solicitudes que registre quedarán
                asociadas a esta planta y el listado se filtrará por ella. Puede cambiarla luego
                desde la barra superior.
            </p>

            @if ($errors->any())
                <div class="mb-4 rounded-lg border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700">
                    <ul class="list-disc pl-4">
                        @foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach
                    </ul>
                </div>
            @endif

            <form method="POST" action="{{ route('plantas.seleccionar.store') }}" class="space-y-4">
                @csrf
                <div>
                    <label class="field-label">Planta</label>
                    <select name="planta_id" class="select" required>
                        <option value="">Seleccione…</option>
                        @foreach ($plantas as $planta)
                            <option value="{{ $planta->id }}" @selected(old('planta_id', auth()->user()->planta_preferida_id) == $planta->id)>
                                {{ $planta->nombre }}
                            </option>
                        @endforeach
                    </select>
                    @if ($plantas->isEmpty())
                        <p class="mt-1 text-[11px] text-amber-600">No hay plantas activas. Un administrador debe crearlas en Administración → Plantas.</p>
                    @endif
                </div>

                <label class="flex items-center gap-2.5">
                    <input type="hidden" name="recordar" value="0">
                    <input type="checkbox" name="recordar" value="1" checked
                           class="rounded border-ink-300 text-brand-600 focus:ring-brand-500">
                    <span class="text-sm text-ink-700">Recordar esta planta para próximos ingresos</span>
                </label>

                <div class="flex justify-end">
                    <button class="btn btn-primary" @disabled($plantas->isEmpty())>Continuar</button>
                </div>
            </form>
        </div>
    </div>
</x-app-layout>

<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center gap-3">
            <a href="{{ route('solicitudes.index') }}" class="btn btn-ghost -ml-2 p-2">
                <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M15 18l-6-6 6-6"/></svg>
            </a>
            <h1 class="text-lg font-semibold text-ink-900">Nueva solicitud de cambio</h1>
        </div>
    </x-slot>

    <div class="mx-auto max-w-2xl">
        <form method="POST" action="{{ route('solicitudes.store') }}" class="card card-pad">
            @csrf

            @if ($errors->any())
                <div class="mb-4 rounded-lg border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700">
                    <ul class="list-disc pl-4">
                        @foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach
                    </ul>
                </div>
            @endif

            <div class="mb-5 grid gap-3 rounded-lg bg-ink-50 p-4 text-sm sm:grid-cols-2">
                <div>
                    <span class="field-label">Consecutivo que se asignará</span>
                    <span class="font-mono text-base font-semibold text-brand-700">{{ $proximoConsecutivo }}</span>
                </div>
                <div>
                    <span class="field-label">Responsable / líder del cambio</span>
                    <span class="font-medium text-ink-800">{{ auth()->user()->name }}</span>
                    <span class="block text-xs text-ink-400">Queda ligado a la solicitud y no se puede reasignar.</span>
                </div>
            </div>

            <p class="mb-4 text-sm text-ink-500">
                Registre lo mínimo para crear la solicitud. Al continuar, <strong>lo primero será la
                evaluación y clasificación del cambio</strong>: de ahí salen el aprobador y las
                secciones que deberá diligenciar.
            </p>

            <div class="grid gap-4 sm:grid-cols-2">
                <div class="sm:col-span-2">
                    <label class="field-label">Nombre del cambio</label>
                    <input type="text" name="nombre_cambio" class="input" value="{{ old('nombre_cambio') }}" required autofocus>
                </div>
                <div>
                    <label class="field-label">Fecha</label>
                    <input type="date" name="fecha" class="input" value="{{ old('fecha', now()->toDateString()) }}" required>
                </div>
                <div>
                    <label class="field-label">Área / Proceso <span class="font-normal text-ink-400">(opcional)</span></label>
                    <input type="text" name="area_proceso" class="input" value="{{ old('area_proceso') }}"
                           placeholder="Ej. Producción">
                    <p class="mt-1 text-[11px] text-ink-400">Si el cambio resulta Menor, el aprobador será el dueño de este proceso.</p>
                </div>
            </div>

            <div class="mt-6 flex justify-end gap-2">
                <a href="{{ route('solicitudes.index') }}" class="btn btn-secondary">Cancelar</a>
                <button class="btn btn-primary">Crear y continuar</button>
            </div>
        </form>
    </div>
</x-app-layout>

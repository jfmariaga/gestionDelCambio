@php
    use App\Enums\EstadoSolicitud;
    use App\Models\SolicitudCambio;

    $porEstado = SolicitudCambio::query()
        ->selectRaw('estado, count(*) as total')
        ->groupBy('estado')->pluck('total', 'estado');

    $total = $porEstado->sum();
    $recientes = SolicitudCambio::latest()->take(6)->get();

    $tiles = [
        ['label' => 'Total', 'value' => $total, 'accent' => 'text-ink-900'],
        ['label' => 'Solicitadas', 'value' => $porEstado[EstadoSolicitud::Solicitado->value] ?? 0, 'accent' => 'text-ink-500'],
        ['label' => 'En evaluación / verificación', 'value' => ($porEstado[EstadoSolicitud::EnEvaluacion->value] ?? 0) + ($porEstado[EstadoSolicitud::EnVerificacion->value] ?? 0), 'accent' => 'text-amber-600'],
        ['label' => 'Aprobadas / en impl.', 'value' => ($porEstado[EstadoSolicitud::Aprobado->value] ?? 0) + ($porEstado[EstadoSolicitud::EnImplementacion->value] ?? 0) + ($porEstado[EstadoSolicitud::Implementado->value] ?? 0), 'accent' => 'text-sky-600'],
        ['label' => 'Cerradas', 'value' => $porEstado[EstadoSolicitud::Cerrado->value] ?? 0, 'accent' => 'text-emerald-600'],
    ];
@endphp

<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <h1 class="text-lg font-semibold text-ink-900">Hola, {{ Str::of(auth()->user()->name)->before(' ') }}</h1>
                <p class="text-sm text-ink-500">Panel de gestión del cambio</p>
            </div>
            @can('create', SolicitudCambio::class)
                <a href="{{ route('solicitudes.create') }}" class="btn btn-primary">
                    <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M12 5v14M5 12h14"/></svg>
                    Nueva solicitud
                </a>
            @endcan
        </div>
    </x-slot>

    <div class="space-y-6">
        <div class="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-5">
            @foreach ($tiles as $t)
                <div class="card card-pad">
                    <div class="text-xs font-medium uppercase tracking-wide text-ink-400">{{ $t['label'] }}</div>
                    <div class="mt-2 text-3xl font-bold {{ $t['accent'] }}">{{ $t['value'] }}</div>
                </div>
            @endforeach
        </div>

        <div class="card">
            <div class="flex items-center justify-between border-b border-ink-200 px-5 py-4 sm:px-6">
                <h2 class="text-sm font-semibold text-ink-900">Solicitudes recientes</h2>
                <a href="{{ route('solicitudes.index') }}" class="text-sm font-medium text-brand-700 hover:text-brand-800">Ver todas →</a>
            </div>
            @forelse ($recientes as $s)
                <a href="{{ route('solicitudes.show', $s) }}"
                   class="flex items-center gap-4 border-b border-ink-100 px-5 py-3 last:border-0 hover:bg-ink-50 sm:px-6">
                    <span class="font-mono text-xs text-ink-400">{{ $s->consecutivo }}</span>
                    <span class="min-w-0 flex-1 truncate text-sm font-medium text-ink-800">{{ $s->nombre_cambio }}</span>
                    <span class="badge {{ $s->estado->badgeClass() }}">{{ $s->estado->etiqueta() }}</span>
                    <span class="hidden text-xs text-ink-400 sm:block">{{ $s->fecha?->format('d/m/Y') }}</span>
                </a>
            @empty
                <p class="px-6 py-8 text-center text-sm text-ink-400">Todavía no hay solicitudes.</p>
            @endforelse
        </div>
    </div>
</x-app-layout>

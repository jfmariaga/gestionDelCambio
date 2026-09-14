<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between gap-3">
            <h1 class="text-lg font-semibold text-ink-900">Notificaciones</h1>
            <form method="POST" action="{{ route('notificaciones.leerTodo') }}">
                @csrf
                <button class="btn btn-secondary btn-sm">Marcar todas como leídas</button>
            </form>
        </div>
    </x-slot>

    <div class="mx-auto max-w-3xl space-y-2">
        @forelse ($notificaciones as $n)
            @php($data = $n->data)
            <div @class([
                'card card-pad flex items-start justify-between gap-4',
                'border-brand-200 bg-brand-50/40' => is_null($n->read_at),
            ])>
                <div class="min-w-0">
                    <p class="text-sm text-ink-800">{{ $data['mensaje'] ?? 'Notificación' }}</p>
                    <p class="mt-1 text-xs text-ink-400">
                        {{ $n->created_at->diffForHumans() }}
                        @if (! empty($data['url']))
                            · <a href="{{ $data['url'] }}" class="text-brand-700 hover:text-brand-800">Abrir</a>
                        @endif
                    </p>
                </div>
                @if (is_null($n->read_at))
                    <form method="POST" action="{{ route('notificaciones.leer', $n->id) }}">
                        @csrf
                        <button class="btn btn-ghost btn-xs">Marcar leída</button>
                    </form>
                @endif
            </div>
        @empty
            <p class="card card-pad text-center text-sm text-ink-400">No tiene notificaciones.</p>
        @endforelse

        <div>{{ $notificaciones->links() }}</div>
    </div>
</x-app-layout>

<div wire:poll.30s>
    <x-dropdown align="right" width="96">
        <x-slot name="trigger">
            <button type="button" class="relative btn btn-ghost p-2" title="Notificaciones">
                <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 8a6 6 0 0 0-12 0c0 7-3 9-3 9h18s-3-2-3-9M13.73 21a2 2 0 0 1-3.46 0"/></svg>
                @if ($this->noLeidas > 0)
                    <span class="absolute -right-0.5 -top-0.5 flex h-4 min-w-4 items-center justify-center rounded-full bg-rose-600 px-1 text-[10px] font-bold text-white">{{ $this->noLeidas > 9 ? '9+' : $this->noLeidas }}</span>
                @endif
            </button>
        </x-slot>

        <x-slot name="content">
            <div class="flex items-center justify-between gap-3 border-b border-ink-100 px-4 py-2">
                <span class="text-sm font-semibold text-ink-800">Notificaciones</span>
                @if ($this->noLeidas > 0)
                    <button type="button" wire:click="marcarTodas" class="text-xs text-brand-700 hover:text-brand-800">Marcar todas leídas</button>
                @endif
            </div>

            <div class="max-h-96 divide-y divide-ink-100 overflow-y-auto">
                @forelse ($this->recientes as $n)
                    @php($data = $n->data)
                    <div @class([
                        'flex items-start justify-between gap-2 px-4 py-3',
                        'bg-brand-50/40' => is_null($n->read_at),
                    ])>
                        <button type="button"
                                wire:click="marcarLeidaYAbrir('{{ $n->id }}', {{ \Illuminate\Support\Js::from($data['url'] ?? null) }})"
                                class="min-w-0 flex-1 text-left">
                            <p class="truncate text-sm text-ink-800">{{ $data['mensaje'] ?? 'Notificación' }}</p>
                            <p class="mt-1 text-xs text-ink-400">{{ $n->created_at->diffForHumans() }}</p>
                        </button>
                    </div>
                @empty
                    <p class="px-4 py-6 text-center text-sm text-ink-400">No tiene notificaciones.</p>
                @endforelse
            </div>

            <div class="border-t border-ink-100 px-4 py-2 text-center">
                <a href="{{ route('notificaciones.index') }}" class="text-xs text-brand-700 hover:text-brand-800">Ver todas</a>
            </div>
        </x-slot>
    </x-dropdown>
</div>

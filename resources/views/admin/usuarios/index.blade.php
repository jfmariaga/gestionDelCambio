<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <h1 class="text-lg font-semibold text-ink-900">Administración</h1>
            <a href="{{ route('admin.usuarios.create') }}" class="btn btn-primary">Nuevo usuario</a>
        </div>
    </x-slot>

    @include('admin._nav')

    <div class="card overflow-hidden">
        <div class="overflow-x-auto">
            <table class="dtable">
                <thead>
                    <tr><th>Nombre</th><th>Email</th><th>Roles</th><th>Procesos asignados</th><th class="text-right">Acciones</th></tr>
                </thead>
                <tbody>
                    @forelse ($usuarios as $u)
                        <tr class="hover:bg-ink-50">
                            <td class="font-medium text-ink-900">{{ $u->name }}</td>
                            <td class="text-ink-500">{{ $u->email }}</td>
                            <td>
                                <div class="flex flex-wrap gap-1">
                                    @forelse ($u->roles as $rol)
                                        <span class="badge badge-brand">{{ Str::headline($rol->name) }}</span>
                                    @empty
                                        <span class="text-ink-300">—</span>
                                    @endforelse
                                </div>
                            </td>
                            <td class="text-ink-500">
                                {{ $u->procesos->pluck('nombre')->join(', ') ?: '—' }}
                            </td>
                            <td class="text-right whitespace-nowrap">
                                <a href="{{ route('admin.usuarios.edit', $u) }}" class="font-medium text-brand-700 hover:text-brand-800">Editar</a>
                                @if ($u->id !== auth()->id())
                                    <span class="text-ink-300">·</span>
                                    <form method="POST" action="{{ route('admin.usuarios.destroy', $u) }}" class="inline"
                                          data-confirm="Se eliminará al usuario «{{ $u->name }}»." data-confirm-title="¿Eliminar usuario?">
                                        @csrf @method('DELETE')
                                        <button class="font-medium text-rose-600 hover:text-rose-700">Eliminar</button>
                                    </form>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="py-8 text-center text-ink-400">Sin usuarios.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="mt-4">{{ $usuarios->links() }}</div>
</x-app-layout>

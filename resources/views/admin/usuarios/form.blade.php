<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center gap-3">
            <a href="{{ route('admin.usuarios.index') }}" class="btn btn-ghost -ml-2 p-2">
                <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M15 18l-6-6 6-6"/></svg>
            </a>
            <h1 class="text-lg font-semibold text-ink-900">{{ $usuario->exists ? 'Editar usuario' : 'Nuevo usuario' }}</h1>
        </div>
    </x-slot>

    <div class="mx-auto max-w-3xl"
         x-data="{ roles: {{ Illuminate\Support\Js::from($rolesUsuario) }} }">
        <form method="POST" class="card card-pad space-y-5"
              action="{{ $usuario->exists ? route('admin.usuarios.update', $usuario) : route('admin.usuarios.store') }}">
            @csrf
            @if ($usuario->exists) @method('PUT') @endif

            @if ($errors->any())
                <div class="rounded-lg border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700">
                    <ul class="list-disc pl-4">@foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
                </div>
            @endif

            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <div>
                    <label class="field-label">Nombre</label>
                    <input type="text" name="name" class="input" value="{{ old('name', $usuario->name) }}" required>
                </div>
                <div>
                    <label class="field-label">Email</label>
                    <input type="email" name="email" class="input" value="{{ old('email', $usuario->email) }}" required>
                </div>
                <div>
                    <label class="field-label">{{ $usuario->exists ? 'Nueva contraseña (opcional)' : 'Contraseña' }}</label>
                    <input type="password" name="password" class="input" @required(! $usuario->exists) autocomplete="new-password">
                </div>
                <div>
                    <label class="field-label">Confirmar contraseña</label>
                    <input type="password" name="password_confirmation" class="input" autocomplete="new-password">
                </div>
            </div>

            <div>
                <label class="field-label">Roles</label>
                <div class="grid grid-cols-1 gap-2 sm:grid-cols-2">
                    @foreach ($roles as $rol)
                        <label class="flex items-center gap-2.5 rounded-lg border border-ink-200 px-3 py-2 text-sm">
                            <input type="checkbox" name="roles[]" value="{{ $rol }}" x-model="roles"
                                   @checked(in_array($rol, old('roles', $rolesUsuario)))
                                   class="rounded border-ink-300 text-brand-600 focus:ring-brand-500">
                            <span class="text-ink-700">{{ Str::headline($rol) }}</span>
                        </label>
                    @endforeach
                </div>
            </div>

            <div x-show="roles.includes('dueno_proceso')" x-cloak x-transition>
                <label class="field-label">Procesos asignados (para dueño de proceso)</label>
                <div class="grid grid-cols-1 gap-1.5 rounded-lg border border-ink-200 p-3 sm:grid-cols-2 lg:grid-cols-3">
                    @foreach ($procesos as $proceso)
                        <label class="flex items-center gap-2 text-sm">
                            <input type="checkbox" name="procesos[]" value="{{ $proceso->id }}"
                                   @checked(in_array($proceso->id, old('procesos', $asignados)))
                                   class="rounded border-ink-300 text-brand-600 focus:ring-brand-500">
                            <span class="text-ink-700">{{ $proceso->nombre }}</span>
                        </label>
                    @endforeach
                </div>
            </div>

            <div class="flex justify-end gap-2">
                <a href="{{ route('admin.usuarios.index') }}" class="btn btn-secondary">Cancelar</a>
                <button class="btn btn-primary">Guardar</button>
            </div>
        </form>
    </div>
</x-app-layout>

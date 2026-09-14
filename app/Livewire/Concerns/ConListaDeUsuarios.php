<?php

namespace App\Livewire\Concerns;

use App\Models\User;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;

trait ConListaDeUsuarios
{
    /**
     * Todos los usuarios activos, para asignar responsables.
     *
     * @return Collection<int,User>
     */
    #[Computed]
    public function usuarios(): Collection
    {
        return User::query()->orderBy('name')->get(['id', 'name']);
    }

    /**
     * Usuarios con rol de aprobador.
     *
     * @return Collection<int,User>
     */
    #[Computed]
    public function aprobadores(): Collection
    {
        return User::query()->role('aprobador')->orderBy('name')->get(['id', 'name']);
    }
}

<?php

namespace Tests;

use App\Models\Planta;
use App\Models\User;
use Database\Seeders\RolesPermisosSeeder;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    /** Crea un usuario con el rol dado (sembrando roles/permisos) y lo autentica. */
    protected function actuarComo(string $rol = 'administrador'): User
    {
        $this->seed(RolesPermisosSeeder::class);

        $planta = Planta::query()->first() ?? Planta::factory()->create(['nombre' => 'Planta Test', 'codigo' => 'TEST']);

        $user = User::factory()->create();
        $user->forceFill(['planta_preferida_id' => $planta->id])->save();
        $user->assignRole($rol);

        $this->actingAs($user);
        $this->withSession(['planta_id' => $planta->id]);

        return $user;
    }

    /** Planta activa en la sesión de pruebas (la primera sembrada o creada por actuarComo). */
    protected function plantaActiva(): Planta
    {
        return Planta::query()->firstOrFail();
    }
}

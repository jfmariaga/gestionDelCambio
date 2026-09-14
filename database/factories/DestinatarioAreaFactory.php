<?php

namespace Database\Factories;

use App\Models\AreaNotificacion;
use App\Models\DestinatarioArea;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DestinatarioArea>
 */
class DestinatarioAreaFactory extends Factory
{
    protected $model = DestinatarioArea::class;

    public function definition(): array
    {
        return [
            'area_id' => AreaNotificacion::query()->inRandomOrder()->value('id')
                ?? AreaNotificacion::factory(),
            'user_id' => User::factory(),
            'email' => null,
            'activo' => true,
        ];
    }

    public function paraArea(string $clave): static
    {
        return $this->state(fn () => [
            'area_id' => AreaNotificacion::firstOrCreate(
                ['clave' => $clave],
                ['nombre' => ucfirst(str_replace('_', ' ', $clave))],
            )->id,
        ]);
    }

    public function correoExterno(string $email): static
    {
        return $this->state(fn () => ['user_id' => null, 'email' => $email]);
    }
}

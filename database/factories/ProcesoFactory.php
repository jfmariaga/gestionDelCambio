<?php

namespace Database\Factories;

use App\Models\Proceso;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Proceso>
 */
class ProcesoFactory extends Factory
{
    protected $model = Proceso::class;

    public function definition(): array
    {
        return [
            'nombre' => ucfirst($this->faker->unique()->words(2, true)),
            'orden' => $this->faker->numberBetween(1, 30),
            'activo' => true,
        ];
    }

    public function inactivo(): static
    {
        return $this->state(fn () => ['activo' => false]);
    }
}

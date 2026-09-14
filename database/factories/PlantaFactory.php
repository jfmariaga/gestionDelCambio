<?php

namespace Database\Factories;

use App\Models\Planta;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Planta>
 */
class PlantaFactory extends Factory
{
    protected $model = Planta::class;

    public function definition(): array
    {
        $nombre = ucfirst($this->faker->unique()->words(2, true));

        return [
            'nombre' => $nombre,
            'codigo' => strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $nombre)),
            'orden' => $this->faker->numberBetween(1, 30),
            'activo' => true,
        ];
    }

    public function inactiva(): static
    {
        return $this->state(fn () => ['activo' => false]);
    }
}

<?php

namespace Database\Factories;

use App\Models\Proceso;
use App\Models\RiesgoPredeterminado;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RiesgoPredeterminado>
 */
class RiesgoPredeterminadoFactory extends Factory
{
    protected $model = RiesgoPredeterminado::class;

    public function definition(): array
    {
        return [
            'proceso_id' => Proceso::factory(),
            'texto' => rtrim($this->faker->unique()->sentence(8), '.').'.',
            'activo' => true,
        ];
    }
}

<?php

namespace Database\Factories;

use App\Models\CriterioRubrica;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CriterioRubrica>
 */
class CriterioRubricaFactory extends Factory
{
    protected $model = CriterioRubrica::class;

    public function definition(): array
    {
        return [
            'nombre' => ucfirst($this->faker->unique()->words(2, true)),
            'desc_nivel_1' => $this->faker->sentence(),
            'desc_nivel_2' => $this->faker->sentence(),
            'desc_nivel_3' => $this->faker->sentence(),
            'orden' => $this->faker->numberBetween(1, 11),
        ];
    }
}

<?php

namespace Database\Factories;

use App\Models\AccionPlan;
use App\Models\SolicitudCambio;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AccionPlan>
 */
class AccionPlanFactory extends Factory
{
    protected $model = AccionPlan::class;

    public function definition(): array
    {
        return [
            'solicitud_cambio_id' => SolicitudCambio::factory(),
            'numero' => $this->faker->numberBetween(1, 10),
            'descripcion' => $this->faker->sentence(),
            'proceso' => $this->faker->word(),
            'estado' => 'Pendiente',
        ];
    }
}

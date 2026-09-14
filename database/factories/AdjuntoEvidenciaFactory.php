<?php

namespace Database\Factories;

use App\Models\AccionPlan;
use App\Models\AdjuntoEvidencia;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AdjuntoEvidencia>
 */
class AdjuntoEvidenciaFactory extends Factory
{
    protected $model = AdjuntoEvidencia::class;

    public function definition(): array
    {
        return [
            'adjuntable_type' => (new AccionPlan)->getMorphClass(),
            'adjuntable_id' => AccionPlan::factory(),
            'disco' => 'local',
            'ruta' => 'adjuntos/demo/'.$this->faker->uuid().'.pdf',
            'nombre_original' => $this->faker->word().'.pdf',
            'mime' => 'application/pdf',
            'tamano' => $this->faker->numberBetween(1_000, 5_000_000),
            'subido_por' => User::factory(),
        ];
    }
}

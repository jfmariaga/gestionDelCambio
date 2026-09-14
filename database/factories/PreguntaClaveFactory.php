<?php

namespace Database\Factories;

use App\Models\PreguntaClave;
use App\Models\Proceso;
use App\Models\RiesgoPredeterminado;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PreguntaClave>
 */
class PreguntaClaveFactory extends Factory
{
    protected $model = PreguntaClave::class;

    public function definition(): array
    {
        $proceso = Proceso::factory()->create();

        return [
            'proceso_id' => $proceso->id,
            'riesgo_predeterminado_id' => RiesgoPredeterminado::factory()
                ->create(['proceso_id' => $proceso->id])->id,
            'texto' => '¿'.rtrim($this->faker->unique()->sentence(9), '.').'?',
            'dueno_por_defecto' => $this->faker->jobTitle(),
            'evidencia_por_defecto' => $this->faker->sentence(4),
            'accion_por_defecto' => $this->faker->sentence(6),
            'orden' => $this->faker->numberBetween(1, 20),
            'activo' => true,
        ];
    }

    /** Ancla la pregunta (y su riesgo) a un proceso concreto. */
    public function paraProceso(Proceso $proceso): static
    {
        return $this->state(fn () => [
            'proceso_id' => $proceso->id,
            'riesgo_predeterminado_id' => RiesgoPredeterminado::factory()
                ->create(['proceso_id' => $proceso->id])->id,
        ]);
    }

    /** Comparte un riesgo predeterminado ya existente (para probar consolidación, FR-021). */
    public function conRiesgo(RiesgoPredeterminado $riesgo): static
    {
        return $this->state(fn () => [
            'proceso_id' => $riesgo->proceso_id,
            'riesgo_predeterminado_id' => $riesgo->id,
        ]);
    }

    /** Pregunta sin riesgo predeterminado asociado (FR-022). */
    public function sinRiesgo(): static
    {
        return $this->state(fn () => ['riesgo_predeterminado_id' => null]);
    }

    public function inactiva(): static
    {
        return $this->state(fn () => ['activo' => false]);
    }
}

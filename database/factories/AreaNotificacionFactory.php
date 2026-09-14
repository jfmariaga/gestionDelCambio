<?php

namespace Database\Factories;

use App\Models\AreaNotificacion;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AreaNotificacion>
 */
class AreaNotificacionFactory extends Factory
{
    protected $model = AreaNotificacion::class;

    public function definition(): array
    {
        $clave = $this->faker->unique()->slug(2);

        return [
            'clave' => $clave,
            'nombre' => ucfirst(str_replace('-', ' ', $clave)),
        ];
    }
}

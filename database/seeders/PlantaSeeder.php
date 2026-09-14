<?php

namespace Database\Seeders;

use App\Models\Planta;
use Illuminate\Database\Seeder;

class PlantaSeeder extends Seeder
{
    public function run(): void
    {
        foreach (config('gestioncambio.plantas_por_defecto') as $planta) {
            Planta::updateOrCreate(
                ['codigo' => $planta['codigo']],
                ['nombre' => $planta['nombre'], 'orden' => $planta['orden'], 'activo' => true],
            );
        }
    }
}

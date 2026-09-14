<?php

namespace Database\Seeders;

use App\Models\AreaNotificacion;
use Illuminate\Database\Seeder;

class AreaNotificacionSeeder extends Seeder
{
    public function run(): void
    {
        foreach (config('gestioncambio.areas_notificacion') as $clave => $nombre) {
            AreaNotificacion::updateOrCreate(['clave' => $clave], ['nombre' => $nombre]);
        }
    }
}

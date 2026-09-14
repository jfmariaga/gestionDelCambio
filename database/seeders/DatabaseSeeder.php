<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            RolesPermisosSeeder::class,
            PlantaSeeder::class,
            AreaNotificacionSeeder::class,
            CatalogoSeeder::class,
        ]);

        $admin = User::factory()->create([
            'name' => 'Administrador',
            'email' => 'admin@example.com',
        ]);
        $admin->assignRole('administrador');

        $solicitante = User::factory()->create([
            'name' => 'Solicitante Demo',
            'email' => 'solicitante@example.com',
        ]);
        $solicitante->assignRole('solicitante');

        $aprobador = User::factory()->create([
            'name' => 'Aprobador Demo',
            'email' => 'aprobador@example.com',
        ]);
        $aprobador->assignRole('aprobador');

        $this->call(DemoSolicitudSeeder::class);
    }
}

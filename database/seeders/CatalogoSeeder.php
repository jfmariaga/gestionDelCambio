<?php

namespace Database\Seeders;

use App\Domain\GestionCambio\ImportadorCatalogo;
use Illuminate\Database\Seeder;

/**
 * Carga el catálogo desde database/seeders/data/catalogo.php (generado del formato oficial Excel)
 * reutilizando ImportadorCatalogo, la misma lógica del comando gestion-cambio:importar-catalogo.
 */
class CatalogoSeeder extends Seeder
{
    public function run(): void
    {
        $data = require database_path('seeders/data/catalogo.php');

        app(ImportadorCatalogo::class)->importar($data);
    }
}

<?php

namespace App\Console\Commands;

use App\Domain\GestionCambio\ImportadorCatalogo;
use Illuminate\Console\Command;

class ImportarCatalogoGestionCambio extends Command
{
    protected $signature = 'gestion-cambio:importar-catalogo
        {archivo? : Ruta a un archivo PHP que devuelve el arreglo del catálogo (por defecto database/seeders/data/catalogo.php)}
        {--recrear : Desactiva las preguntas y riesgos que ya no aparecen en la fuente}';

    protected $description = 'Importa o actualiza el catálogo de preguntas clave y riesgos predeterminados';

    public function handle(ImportadorCatalogo $importador): int
    {
        $ruta = $this->argument('archivo') ?: database_path('seeders/data/catalogo.php');

        if (! is_file($ruta)) {
            $this->error("No se encontró el archivo: {$ruta}");

            return self::FAILURE;
        }

        $data = require $ruta;

        if (! isset($data['procesos'], $data['criterios'], $data['preguntas'])) {
            $this->error('El archivo no tiene la estructura esperada (procesos, criterios, preguntas).');

            return self::FAILURE;
        }

        $resumen = $importador->importar($data, (bool) $this->option('recrear'));

        $this->table(array_keys($resumen), [array_values($resumen)]);
        $this->info('Catálogo importado.');

        return self::SUCCESS;
    }
}

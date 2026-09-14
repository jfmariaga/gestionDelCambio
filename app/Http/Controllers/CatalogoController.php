<?php

namespace App\Http\Controllers;

use App\Domain\GestionCambio\ImportadorCatalogo;

class CatalogoController extends Controller
{
    /** Importa/actualiza el catálogo desde el archivo base database/seeders/data/catalogo.php. */
    public function importar(ImportadorCatalogo $importador)
    {
        $ruta = database_path('seeders/data/catalogo.php');
        abort_unless(is_file($ruta), 404, 'No hay archivo de catálogo para importar.');

        $resumen = $importador->importar(require $ruta);

        return back()->with('status', "Catálogo importado: {$resumen['preguntas']} preguntas, {$resumen['riesgos']} riesgos.");
    }
}

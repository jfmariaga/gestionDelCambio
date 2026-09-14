<?php

namespace App\Domain\GestionCambio;

use App\Models\CriterioRubrica;
use App\Models\PreguntaClave;
use App\Models\Proceso;
use App\Models\RiesgoPredeterminado;
use Illuminate\Support\Facades\DB;

/**
 * Importa/actualiza el catálogo (procesos, criterios de rúbrica, preguntas clave y riesgos
 * predeterminados) desde un arreglo con la forma de `database/seeders/data/catalogo.php`.
 *
 * Idempotente: identifica preguntas y riesgos por (proceso, texto). Con $recrear=true,
 * desactiva las entradas que ya no aparecen en la fuente en vez de borrarlas (FR-005).
 *
 * NOTA: cuando `ext-zip` esté disponible, un lector de `maatwebsite/excel` debe producir el
 * mismo arreglo a partir de la hoja "Riesgos" del `.xlsx`; el resto de la lógica no cambia.
 */
final class ImportadorCatalogo
{
    /**
     * @param  array{procesos:array<int,array{0:string,1:int}>,criterios:array<int,array<string,mixed>>,preguntas:array<int,array<string,mixed>>}  $data
     * @return array{procesos:int,criterios:int,riesgos:int,preguntas:int,desactivadas:int}
     */
    public function importar(array $data, bool $recrear = false): array
    {
        return DB::transaction(function () use ($data, $recrear) {
            $procesos = [];
            foreach ($data['procesos'] as [$nombre, $orden]) {
                $procesos[$nombre] = Proceso::updateOrCreate(
                    ['nombre' => $nombre],
                    ['orden' => $orden, 'activo' => true],
                );
            }

            foreach ($data['criterios'] as $c) {
                CriterioRubrica::updateOrCreate(
                    ['nombre' => $c['nombre']],
                    ['orden' => $c['orden'], 'desc_nivel_1' => $c['n1'], 'desc_nivel_2' => $c['n2'], 'desc_nivel_3' => $c['n3']],
                );
            }

            $preguntasVistas = [];
            $riesgosVistos = [];

            foreach ($data['preguntas'] as $p) {
                $proceso = $procesos[$p['proceso']] ?? ($procesos[$p['proceso']] = Proceso::updateOrCreate(
                    ['nombre' => $p['proceso']],
                    ['orden' => count($procesos) + 1, 'activo' => true],
                ));

                $riesgoId = null;
                if (($p['riesgo'] ?? '') !== '') {
                    $riesgo = RiesgoPredeterminado::updateOrCreate(
                        ['proceso_id' => $proceso->id, 'texto' => $this->recorte($p['riesgo'])],
                        ['activo' => true],
                    );
                    $riesgoId = $riesgo->id;
                    $riesgosVistos[] = $riesgo->id;
                }

                $pregunta = PreguntaClave::updateOrCreate(
                    ['proceso_id' => $proceso->id, 'texto' => $this->recorte($p['pregunta'])],
                    ['riesgo_predeterminado_id' => $riesgoId, 'orden' => $p['orden'] ?? 0, 'activo' => true],
                );
                $preguntasVistas[] = $pregunta->id;
            }

            $desactivadas = 0;
            if ($recrear) {
                $desactivadas += PreguntaClave::whereNotIn('id', $preguntasVistas)->where('activo', true)->update(['activo' => false]);
                $desactivadas += RiesgoPredeterminado::whereNotIn('id', $riesgosVistos)->where('activo', true)->update(['activo' => false]);
            }

            return [
                'procesos' => count($procesos),
                'criterios' => count($data['criterios']),
                'riesgos' => RiesgoPredeterminado::count(),
                'preguntas' => count($preguntasVistas),
                'desactivadas' => $desactivadas,
            ];
        });
    }

    private function recorte(string $texto): string
    {
        return mb_strlen($texto) > 500 ? mb_substr($texto, 0, 500) : $texto;
    }
}

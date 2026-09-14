<?php

namespace Tests\Unit;

use App\Domain\GestionCambio\ClasificadorCambio;
use App\Enums\Clasificacion;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class ClasificadorCambioTest extends TestCase
{
    #[DataProvider('sumas')]
    public function test_clasifica_por_suma(int $suma, Clasificacion $esperada): void
    {
        // 11 criterios que suman `suma` (se reparte lo mejor posible en el rango 1..3).
        $calificaciones = self::repartir($suma, 11);

        $resultado = ClasificadorCambio::evaluar($calificaciones);

        $this->assertTrue($resultado['completa']);
        $this->assertSame($suma, $resultado['suma']);
        $this->assertSame($esperada, $resultado['clasificacion']);
    }

    public static function sumas(): array
    {
        return [
            'minimo menor (11)' => [11, Clasificacion::Menor],
            'tope menor (16)' => [16, Clasificacion::Menor],
            'inicio mayor (17)' => [17, Clasificacion::Mayor],
            'ejemplo mayor (20)' => [20, Clasificacion::Mayor],
            'tope mayor (23)' => [23, Clasificacion::Mayor],
            'inicio critico (24)' => [24, Clasificacion::Critico],
            'maximo critico (33)' => [33, Clasificacion::Critico],
        ];
    }

    public function test_incompleta_no_clasifica_y_reporta_faltantes(): void
    {
        $resultado = ClasificadorCambio::evaluar([3, 3, 2, 1]); // solo 4 de 11

        $this->assertFalse($resultado['completa']);
        $this->assertNull($resultado['suma']);
        $this->assertNull($resultado['clasificacion']);
        $this->assertSame(7, $resultado['faltan']);
    }

    public function test_ignora_calificaciones_invalidas(): void
    {
        $resultado = ClasificadorCambio::evaluar(array_fill(0, 11, 3) + [11 => 5, 12 => 0]);

        $this->assertTrue($resultado['completa']);
        $this->assertSame(33, $resultado['suma']);
        $this->assertSame(Clasificacion::Critico, $resultado['clasificacion']);
    }

    /** @return array<int,int> */
    private static function repartir(int $suma, int $n): array
    {
        $base = intdiv($suma, $n);
        $resto = $suma % $n;
        $out = [];
        for ($k = 0; $k < $n; $k++) {
            $out[] = min(3, max(1, $base + ($k < $resto ? 1 : 0)));
        }

        // Ajuste fino si el clamp alteró la suma.
        $diff = $suma - array_sum($out);
        for ($k = 0; $k < $n && $diff !== 0; $k++) {
            if ($diff > 0 && $out[$k] < 3) {
                $out[$k]++;
                $diff--;
            } elseif ($diff < 0 && $out[$k] > 1) {
                $out[$k]--;
                $diff++;
            }
        }

        return $out;
    }
}

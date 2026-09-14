<?php

namespace Tests\Unit;

use App\Domain\GestionCambio\CalculoRiesgo;
use App\Enums\NivelRiesgo;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class CalculoRiesgoTest extends TestCase
{
    /**
     * NR = probabilidad * impacto; nivel por umbrales 30 / 60 (idéntico al Excel).
     */
    #[DataProvider('casos')]
    public function test_evalua_nr_y_nivel(int $p, int $i, int $nrEsperado, NivelRiesgo $nivelEsperado): void
    {
        $resultado = CalculoRiesgo::evaluar($p, $i);

        $this->assertSame($nrEsperado, $resultado['nr']);
        $this->assertSame($nivelEsperado, $resultado['nivel']);
    }

    public static function casos(): array
    {
        return [
            'frontera bajo (30)' => [5, 6, 30, NivelRiesgo::Bajo],
            'apenas medio (32)' => [4, 8, 32, NivelRiesgo::Medio],
            'medio tipico (36)' => [4, 9, 36, NivelRiesgo::Medio],
            'frontera medio (60)' => [6, 10, 60, NivelRiesgo::Medio],
            'apenas alto (63)' => [7, 9, 63, NivelRiesgo::Alto],
            'maximo (100)' => [10, 10, 100, NivelRiesgo::Alto],
            'minimo (1)' => [1, 1, 1, NivelRiesgo::Bajo],
        ];
    }

    public function test_nivel_31_es_medio(): void
    {
        // No hay par 1..10 x 1..10 que dé exactamente 31; se valida el límite con 32 y 30.
        $this->assertSame(NivelRiesgo::Bajo, CalculoRiesgo::evaluar(5, 6)['nivel']);   // 30
        $this->assertSame(NivelRiesgo::Medio, CalculoRiesgo::evaluar(4, 8)['nivel']);  // 32
    }

    public function test_sin_probabilidad_o_impacto_no_calcula(): void
    {
        $this->assertSame(['nr' => null, 'nivel' => null], CalculoRiesgo::evaluar(null, 5));
        $this->assertSame(['nr' => null, 'nivel' => null], CalculoRiesgo::evaluar(5, null));
        $this->assertSame(['nr' => null, 'nivel' => null], CalculoRiesgo::evaluar(null, null));
    }

    #[DataProvider('fueraDeEscala')]
    public function test_rechaza_valores_fuera_de_1_a_10(int $p, int $i): void
    {
        $this->expectException(InvalidArgumentException::class);
        CalculoRiesgo::evaluar($p, $i);
    }

    public static function fueraDeEscala(): array
    {
        return [[0, 5], [11, 5], [5, 0], [5, 11], [-1, 3]];
    }

    public function test_en_escala(): void
    {
        $this->assertTrue(CalculoRiesgo::enEscala(1));
        $this->assertTrue(CalculoRiesgo::enEscala(10));
        $this->assertFalse(CalculoRiesgo::enEscala(0));
        $this->assertFalse(CalculoRiesgo::enEscala(11));
        $this->assertFalse(CalculoRiesgo::enEscala(null));
    }
}

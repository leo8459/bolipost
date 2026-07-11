<?php

namespace Tests\Unit;

use App\Models\TarifarioTiktoker;
use App\Support\TiktokerTariffPriceCalculator;
use PHPUnit\Framework\TestCase;

class TiktokerTariffPriceCalculatorTest extends TestCase
{
    public function test_uses_classic_two_weight_logic_when_peso3_is_empty(): void
    {
        $tarifario = new TarifarioTiktoker([
            'peso1' => 10,
            'peso2' => 20,
            'peso3' => null,
            'peso_extra' => 4,
        ]);

        $this->assertSame(10.0, TiktokerTariffPriceCalculator::calculate($tarifario, 1.50));
        $this->assertSame(20.0, TiktokerTariffPriceCalculator::calculate($tarifario, 3.25));
        $this->assertSame(28.0, TiktokerTariffPriceCalculator::calculate($tarifario, 7.00));
    }

    public function test_uses_three_weight_logic_without_extra_kilo_when_peso3_exists(): void
    {
        $tarifario = new TarifarioTiktoker([
            'peso1' => 12,
            'peso2' => 18,
            'peso3' => 27,
            'peso_extra' => 5,
        ]);

        $this->assertSame(12.0, TiktokerTariffPriceCalculator::calculate($tarifario, 0.50));
        $this->assertSame(18.0, TiktokerTariffPriceCalculator::calculate($tarifario, 1.75));
        $this->assertSame(27.0, TiktokerTariffPriceCalculator::calculate($tarifario, 4.20));
        $this->assertSame(27.0, TiktokerTariffPriceCalculator::calculate($tarifario, 6.00));
    }

    public function test_adds_pago_destinatario_surcharge_after_weight_calculation(): void
    {
        $tarifario = new TarifarioTiktoker([
            'peso1' => 12,
            'peso2' => 18,
            'peso3' => 27,
            'peso_extra' => 5,
        ]);

        $this->assertSame(29.5, TiktokerTariffPriceCalculator::calculate($tarifario, 4.20, true));
    }
}

<?php

namespace Tests\Unit;

use App\Models\TarifarioTiktoker;
use App\Models\Destino;
use App\Models\Origen;
use App\Support\TiktokerTariffPriceCalculator;
use PHPUnit\Framework\TestCase;

class TiktokerTariffPriceCalculatorTest extends TestCase
{
    public function test_always_uses_peso1_regardless_of_weight(): void
    {
        $tarifario = new TarifarioTiktoker([
            'peso1' => 10,
            'peso2' => 20,
            'peso3' => null,
            'peso_extra' => 4,
        ]);

        $this->assertSame(10.0, TiktokerTariffPriceCalculator::calculate($tarifario, 1.50));
        $this->assertSame(10.0, TiktokerTariffPriceCalculator::calculate($tarifario, 3.25));
        $this->assertSame(10.0, TiktokerTariffPriceCalculator::calculate($tarifario, 7.00));
    }

    public function test_ignores_other_legacy_weight_prices(): void
    {
        $tarifario = new TarifarioTiktoker([
            'peso1' => 12,
            'peso2' => 18,
            'peso3' => 27,
            'peso_extra' => 5,
        ]);

        $this->assertSame(12.0, TiktokerTariffPriceCalculator::calculate($tarifario, 0.50));
        $this->assertSame(12.0, TiktokerTariffPriceCalculator::calculate($tarifario, 1.75));
        $this->assertSame(12.0, TiktokerTariffPriceCalculator::calculate($tarifario, 4.20));
        $this->assertSame(12.0, TiktokerTariffPriceCalculator::calculate($tarifario, 6.00));
    }

    public function test_payment_mode_does_not_change_peso1_price(): void
    {
        $tarifario = new TarifarioTiktoker([
            'peso1' => 12,
            'peso2' => 18,
            'peso3' => 27,
            'peso_extra' => 5,
        ]);

        $this->assertSame(12.0, TiktokerTariffPriceCalculator::calculate($tarifario, 4.20, true));
    }

    public function test_adds_five_bolivianos_for_large_package_with_same_origin_and_destination(): void
    {
        $tarifario = new TarifarioTiktoker(['peso1' => 15]);
        $tarifario->setRelation('origen', new Origen(['nombre_origen' => 'LA PAZ']));
        $tarifario->setRelation('destino', new Destino(['nombre_destino' => 'LA PAZ']));

        $this->assertSame(20.0, TiktokerTariffPriceCalculator::calculate($tarifario, 50, false, true));
    }

    public function test_adds_ten_bolivianos_for_large_package_with_different_destination(): void
    {
        $tarifario = new TarifarioTiktoker(['peso1' => 20]);
        $tarifario->setRelation('origen', new Origen(['nombre_origen' => 'LA PAZ']));
        $tarifario->setRelation('destino', new Destino(['nombre_destino' => 'COCHABAMBA']));

        $this->assertSame(30.0, TiktokerTariffPriceCalculator::calculate($tarifario, 50, false, true));
    }
}

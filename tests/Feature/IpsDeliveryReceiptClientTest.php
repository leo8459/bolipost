<?php

namespace Tests\Feature;

use App\Services\SitraIpsClient;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class IpsDeliveryReceiptClientTest extends TestCase
{
    public static function receiptHistories(): array
    {
        return [[[75, 32], true], [[75], false], [[75, 35, 32], false]];
    }

    #[DataProvider('receiptHistories')]
    public function test_ips_detail_uses_history_to_enable_delivery(array $events, bool $allowed): void
    {
        config()->set('services.sitra_ips.base_url', 'https://ips.test');
        Http::fake(['https://ips.test/api/v1/ips/paquetes/*' => Http::response(['data' => [
            'package' => [
                'codigo' => 'LW945318163DE', 'event_cd' => 75, 'office_cd' => 18,
                'state_cd' => 0, 'destination_country' => 'BO', 'allowed_actions' => ['EMI'],
            ],
            'events' => array_map(fn ($event) => ['EVENT_TYPE_CD' => $event, 'EVENT_OFFICE_CD' => 18], $events),
        ]])]);

        $detail = app(SitraIpsClient::class)->package('LW945318163DE', 18);
        $this->assertSame($allowed, $detail['package']['destination_receipt_confirmed']);
        $this->assertSame($allowed, in_array('EMI', $detail['package']['allowed_actions'], true));
    }
}

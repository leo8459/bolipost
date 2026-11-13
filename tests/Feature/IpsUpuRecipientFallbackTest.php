<?php

namespace Tests\Feature;

use App\Http\Controllers\PaquetesIpsController;
use App\Models\PaqueteUpu;
use App\Models\User;
use App\Models\UserIpsLink;
use App\Services\SitraIpsClient;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Mockery;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class IpsUpuRecipientFallbackTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        (require database_path('migrations/2025_06_11_195557_create_permission_tables.php'))->up();
        (require database_path('migrations/2026_11_12_000000_create_paquetes_upu_table.php'))->up();
        Gate::before(fn () => true);
    }

    private function package(array $overrides = []): array
    {
        return array_replace([
            'codigo' => 'RE052612365ES', 'event_cd' => 32, 'office_cd' => 17,
            'state_cd' => 0, 'destination_country' => 'BO', 'allowed_actions' => ['EMI'],
            'local_id' => '', 'recipient' => null, 'phone' => null, 'city' => 'La Paz',
            'address' => null, 'office_name' => 'LA PAZ LC/AO', 'next_office_name' => '',
            'weight_kg' => 0.140, 'mail_class' => 'U', 'dutiable_ind' => null,
            'stage' => ['label' => 'Recibido', 'tone' => 'info'],
        ], $overrides);
    }

    private function localPackage(array $overrides = []): PaqueteUpu
    {
        return PaqueteUpu::create(array_replace([
            'codigo' => 'RE052612365ES', 'destinatario' => 'Destinatario UPU',
            'telefono' => '70011122', 'destino' => 'LA PAZ', 'zona' => 'Zona UPU', 'peso' => 0.140,
        ], $overrides));
    }

    private function listing(array $packages, bool $json = false, string $search = '')
    {
        $user = new User(['name' => 'Operador', 'ciudad' => 'LA PAZ']);
        $user->id = 7001;
        $user->setRelation('ipsLink', new UserIpsLink([
            'ips_user_pid' => 73, 'ips_office_cd' => 17, 'active' => true,
        ]));
        $this->actingAs($user);
        $request = Request::create('/internacional/almacen/por-entregar', 'GET', ['q' => $search]);
        $request->setUserResolver(fn () => $user);
        $request->setLaravelSession(app('session')->driver());
        if ($json) {
            $request->headers->set('Accept', 'application/json');
        }
        $ips = Mockery::mock(SitraIpsClient::class);
        $ips->shouldReceive('packages')->once()->with(Mockery::on(fn ($filters) =>
            $filters['q'] === $search && $filters['status'] === 'warehouse' && $filters['office_cd'] === 17
        ))->andReturn(['data' => $packages, 'meta' => ['total' => count($packages)]]);

        return app(PaquetesIpsController::class)->warehouse($request, $ips, 'por-entregar');
    }

    public static function missingRecipients(): array
    {
        return ['null' => [null], 'empty' => [''], 'whitespace' => [" \t "]];
    }

    #[DataProvider('missingRecipients')]
    public function test_missing_sitra_recipient_uses_the_matching_upu_record(?string $recipient): void
    {
        $this->localPackage();
        $package = $this->listing([$this->package(['recipient' => $recipient])])
            ->getData()['packages']->items()[0];

        $this->assertSame('Destinatario UPU', $package['recipient']);
        $this->assertSame('70011122', $package['phone']);
        $this->assertSame('Zona UPU', $package['address']);
        $this->assertSame(['EMI'], $package['allowed_actions']);
        $this->assertSame(32, $package['event_cd']);
    }

    public function test_each_populated_sitra_field_takes_priority_over_upu(): void
    {
        $this->localPackage();
        $package = $this->listing([$this->package([
            'recipient' => 'Destinatario SITRA', 'phone' => '70099988', 'address' => 'Calle SITRA',
        ])])->getData()['packages']->items()[0];

        $this->assertSame('Destinatario SITRA', $package['recipient']);
        $this->assertSame('70099988', $package['phone']);
        $this->assertSame('Calle SITRA', $package['address']);
        $this->assertDatabaseHas('paquetes_upu', ['codigo' => 'RE052612365ES', 'destinatario' => 'Destinatario UPU']);
    }

    public function test_only_missing_fields_are_completed_when_sitra_has_some_recipient_data(): void
    {
        $this->localPackage();
        $package = $this->listing([$this->package(['recipient' => 'Nombre SITRA', 'phone' => '   '])])
            ->getData()['packages']->items()[0];

        $this->assertSame('Nombre SITRA', $package['recipient']);
        $this->assertSame('70011122', $package['phone']);
        $this->assertSame('Zona UPU', $package['address']);
    }

    public function test_missing_local_records_and_empty_local_fields_leave_sitra_data_unchanged(): void
    {
        $this->localPackage(['destinatario' => null, 'telefono' => '', 'zona' => '   ']);
        $packages = [$this->package(), $this->package(['codigo' => 'RR123456789DE'])];
        $result = $this->listing($packages)->getData()['packages']->items();

        $this->assertSame($packages, $result);
    }

    public function test_search_fragments_render_the_upu_recipient_and_escape_local_text(): void
    {
        $this->localPackage(['destinatario' => 'MARCO <UPU>']);
        $response = $this->listing([$this->package()], true, 'RE052612365ES');

        $this->assertSame(200, $response->getStatusCode());
        $html = $response->getData(true)['html'];
        $this->assertStringContainsString('RE052612365ES', $html);
        $this->assertStringContainsString('MARCO &lt;UPU&gt;', $html);
        $this->assertStringContainsString('70011122', $html);
        $this->assertStringContainsString('Zona UPU', $html);
        $this->assertStringNotContainsString('MARCO <UPU>', $html);
    }

    public function test_a_page_looks_up_all_missing_recipients_in_one_query(): void
    {
        $this->localPackage();
        $this->localPackage(['codigo' => 'RR123456789DE', 'destinatario' => 'Otro destinatario UPU']);
        DB::enableQueryLog();
        DB::flushQueryLog();
        $packages = $this->listing([$this->package(), $this->package(['codigo' => 'RR123456789DE'])])
            ->getData()['packages']->items();
        $localQueries = collect(DB::getQueryLog())->filter(fn ($query) => str_contains($query['query'], 'from "paquetes_upu"'));
        DB::disableQueryLog();

        $this->assertCount(1, $localQueries);
        $this->assertSame('Destinatario UPU', $packages[0]['recipient']);
        $this->assertSame('Otro destinatario UPU', $packages[1]['recipient']);
    }
}

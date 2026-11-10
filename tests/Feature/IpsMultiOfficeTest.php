<?php

namespace Tests\Feature;

use App\Http\Controllers\PaquetesIpsController;
use App\Models\User;
use App\Models\UserIpsLink;
use App\Services\SitraIpsClient;
use Illuminate\Http\Request;
use Mockery;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class IpsMultiOfficeTest extends TestCase
{
    private User $operator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->operator = new User(['name' => 'Operador IPS']);
        $this->operator->id = 7001;
        $this->operator->setRelation('ipsLink', new UserIpsLink([
            'ips_user_pid' => 73, 'ips_office_cd' => 17, 'ips_office_name' => 'La Paz', 'active' => true,
            'last_snapshot' => [
                'office_cd' => 17, 'office_fcd' => 'BOLPBA', 'office_name' => 'La Paz',
                'offices' => [
                    ['office_cd' => 17, 'office_fcd' => 'BOLPBA', 'office_name' => 'La Paz'],
                    ['office_cd' => 18, 'office_fcd' => 'BOSRZA', 'office_name' => 'Santa Cruz'],
                ],
            ],
        ]));
    }

    public function test_two_offices_keep_independent_trays_in_the_same_browser_session(): void
    {
        $ips = Mockery::mock(SitraIpsClient::class);
        $ips->shouldReceive('package')->twice()->andReturnUsing(fn ($code, $office) => ['package' => [
            'codigo' => $code, 'operational_office_cd' => $office, 'office_cd' => $office,
            'allowed_actions' => [], 'operational_event_cd' => 32, 'event_at' => '2026-11-09T12:00:00Z',
        ]]);
        $ips->shouldReceive('packages')->andReturn(['data' => [], 'meta' => ['total' => 0]]);
        $ips->shouldReceive('deliveryCatalog')->andReturn([]);
        $controller = app(PaquetesIpsController::class);

        $first = $controller->addSelection($this->request(17, 'POST', ['codigo' => 'PACKAGE17']), $ips);
        $second = $controller->addSelection($this->request(18, 'POST', ['codigo' => 'PACKAGE18']), $ips);
        $this->assertStringContainsString('office_cd=17', $first->getData(true)['remove_url']);
        $this->assertStringContainsString('office_cd=18', $second->getData(true)['remove_url']);

        foreach ([[17, 'PACKAGE17'], [18, 'PACKAGE18'], [17, 'PACKAGE17']] as [$office, $code]) {
            $data = $controller->index($this->request($office), $ips)->getData();
            $this->assertSame([$code], array_keys($data['selection']));
            $this->assertSame($office, $data['link']->ips_office_cd);
            $this->assertStringContainsString('office_cd='.$office, $data['listingUrl']);
            $this->assertStringContainsString('office_cd='.$office, $data['selectionAddUrl']);
            $this->assertStringContainsString('office_cd='.$office, $data['operateUrl']);
        }
        $this->assertSame(17, $this->operator->ipsLink->ips_office_cd);
    }

    public function test_pending_receptacle_operations_keep_their_office_and_idempotency_key(): void
    {
        $session = app('session')->driver();
        $original = [
            'office_cd' => 17, 'event' => 'RECEPTACLE_OPEN',
            'idempotency_key' => 'pending-office-17', 'codigo' => 'SACK123',
        ];
        $session->put('ips_receptacle_pending:7001:abrir:SACK123', $original);
        $ips = Mockery::mock(SitraIpsClient::class);
        $ips->shouldReceive('receptacle')->once()->with('SACK123', 18)->andReturn(['receptacle' => [
            'allowed_actions' => ['RECEPTACLE_OPEN'], 'event_cd' => 130, 'event_at' => '2026-11-09T12:00:00Z',
        ]]);
        $payloads = [];
        $ips->shouldReceive('receptacleEvent')->twice()->andReturnUsing(function ($code, $payload) use (&$payloads) {
            $payloads[] = $payload;

            return ['status' => 'processing', 'message' => 'Pendiente'];
        });
        $controller = app(PaquetesIpsController::class);
        foreach ([18, 17] as $office) {
            $response = $controller->operateReceptacle($this->request($office, 'POST', [
                'codigo' => 'SACK123', 'confirmed' => '1', 'physical_receipt_confirmed' => '1', 'idempotency_key' => 'pending-office-'.$office,
            ]), $ips, 'abrir');
            $this->assertStringContainsString('office_cd='.$office, $response->getTargetUrl());
        }

        $this->assertSame([18, 17], array_column($payloads, 'office_cd'));
        $this->assertSame(['pending-office-18', 'pending-office-17'], array_column($payloads, 'idempotency_key'));
        $this->assertSame($original, $session->get('ips_receptacle_pending:7001:abrir:SACK123'));
        $this->assertSame(18, $session->get('ips_receptacle_pending:7001_office_18:abrir:SACK123')['office_cd']);
    }

    public function test_unassigned_office_is_rejected_before_contacting_sitra(): void
    {
        $ips = Mockery::mock(SitraIpsClient::class);
        $ips->shouldNotReceive('package');
        $ips->shouldNotReceive('packages');

        try {
            app(PaquetesIpsController::class)->addSelection($this->request(19, 'POST', ['codigo' => 'PACKAGE19']), $ips);
            $this->fail('Una oficina no asignada no debe permitir operaciones.');
        } catch (HttpException $exception) {
            $this->assertSame(403, $exception->getStatusCode());
        }
    }

    public function test_office_navigation_and_search_keep_the_selected_office(): void
    {
        $ips = Mockery::mock(SitraIpsClient::class);
        $ips->shouldReceive('packages')->once()->with(Mockery::on(fn ($filters) => $filters['office_cd'] === 18))
            ->andReturn(['data' => [], 'meta' => ['total' => 0]]);
        $ips->shouldReceive('deliveryCatalog')->andReturn([]);
        $request = $this->request(18);
        $data = app(PaquetesIpsController::class)->index($request, $ips)->getData();
        $this->app->instance('request', $request);
        $html = view('paquetes_ips.partials.listing', $data)->render();
        $offices = view('paquetes_ips.partials.offices', $data)->render();

        $this->assertStringContainsString('name="office_cd" value="18"', $html);
        $this->assertStringContainsString('office_cd=18', $html);
        $this->assertStringContainsString('office_cd=17', $offices);
        $this->assertStringContainsString('office_cd=18', $offices);
        $this->assertStringContainsString('target="_blank"', $offices);
    }

    private function request(int $office, string $method = 'GET', array $data = []): Request
    {
        $request = Request::create('/ips?office_cd='.$office, $method, $data);
        $request->setUserResolver(fn () => $this->operator);
        $request->setLaravelSession(app('session')->driver());
        if ($method === 'POST') {
            $request->headers->set('Accept', 'application/json');
        }

        return $request;
    }
}

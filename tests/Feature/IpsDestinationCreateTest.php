<?php

namespace Tests\Feature;

use App\Http\Controllers\PaquetesIpsController;
use App\Models\User;
use App\Models\UserIpsLink;
use App\Services\SitraIpsClient;
use App\Services\SitraTrackingEventsClient;
use App\Support\InternationalMenuAcl;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Mockery;
use Tests\TestCase;

class IpsDestinationCreateTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        (require database_path('migrations/2025_06_11_195557_create_permission_tables.php'))->up();
        (require database_path('migrations/2026_11_12_000000_create_paquetes_upu_table.php'))->up();
        Gate::before(fn () => true);
    }

    private function request(array $input = []): Request
    {
        $user = new User(['name' => 'Operador', 'ciudad' => 'LA PAZ']);
        $user->id = 7001;
        $user->setRelation('ipsLink', new UserIpsLink([
            'ips_user_pid' => 73, 'ips_office_cd' => 17, 'active' => true,
        ]));
        $request = Request::create('/internacional/recepcion-oficina/registrar-envio', 'POST', $input);
        $request->setUserResolver(fn () => $user);
        $request->setLaravelSession(app('session')->driver());

        return $request;
    }

    private function input(): array
    {
        return [
            'codigo' => 'LW945318163DE', 'idempotency_key' => 'destination-create-test-01',
            'weight_kg' => '0.140', 'mail_class' => 'U', 'origin_country' => 'DE',
            'physical_receipt_confirmed' => '1',
            'sender' => ['name' => 'Remitente', 'address' => 'Calle 1', 'city' => 'Berlin', 'country' => 'DE'],
            'recipient' => ['name' => 'Destinatario', 'address' => 'Calle 2', 'city' => 'La Paz', 'country' => 'BO'],
        ];
    }

    public function test_reception_and_delivery_views_show_only_their_own_actions(): void
    {
        foreach (['reception', 'delivery'] as $window) {
            $request = $this->request();
            $this->actingAs($request->user());
            config()->set('adminlte.filters', []);
            config()->set('adminlte.menu', []);
            $ips = Mockery::mock(SitraIpsClient::class);
            $ips->shouldReceive('packages')->once()->andReturn(['data' => [[
                'codigo' => 'LW945318163DE', 'event_cd' => $window === 'reception' ? 35 : 32,
                'office_cd' => 17, 'state_cd' => 0, 'destination_country' => 'BO',
                'local_id' => '', 'recipient' => 'Destinatario', 'phone' => null, 'city' => 'La Paz',
                'address' => 'Calle de prueba', 'office_name' => 'La Paz', 'next_office_name' => '',
                'weight_kg' => 0.140, 'mail_class' => 'U', 'dutiable_ind' => null,
                'stage' => ['label' => 'Paquete de prueba', 'tone' => 'info'],
                'allowed_actions' => ['EMG', 'EMI', 'EDH', 'HANDOVER_AGENT'],
            ]], 'meta' => ['total' => 1]]);
            $ips->shouldReceive('deliveryCatalog')->andReturn([]);
            $controller = app(PaquetesIpsController::class);
            $view = $window === 'reception'
                ? $controller->index($request, $ips, 'recepcion-oficina')
                : $controller->warehouse($request, $ips, 'por-entregar');
            $expected = $window === 'reception' ? 'EMG' : 'EMI';
            $this->assertSame([$expected], array_keys($view->getData()['actions']));
            $this->assertSame([$expected], $view->getData()['packages']->items()[0]['allowed_actions']);
            $html = $view->with('errors', new \Illuminate\Support\ViewErrorBag)->render();
            $document = new \DOMDocument;
            $previous = libxml_use_internal_errors(true);
            try {
                $document->loadHTML($html);
            } finally {
                libxml_clear_errors();
                libxml_use_internal_errors($previous);
            }
            $xpath = new \DOMXPath($document);
            $options = [];
            foreach ($xpath->query('//select[@id="ips-action"]/option[@value!=""]') as $option) {
                $options[] = $option->getAttribute('value');
            }
            $this->assertSame([$expected], $options);
            $buttons = [];
            foreach ($xpath->query('//button[@data-action]') as $button) {
                $buttons[] = $button->getAttribute('data-action');
            }
            $this->assertSame($window === 'reception' ? [] : ['EMI'], $buttons);
            $this->assertStringContainsString('/internacional/recepcion-oficina', $html);
            $this->assertStringContainsString('/internacional/almacen/por-entregar', $html);
        }
    }

    public function test_missing_l_code_offers_ips_registration_in_the_destination_office(): void
    {
        $request = $this->request(['q' => 'LW945318163DE']);
        $ips = Mockery::mock(SitraIpsClient::class);
        $ips->shouldReceive('package')->once()->with('LW945318163DE', 17)
            ->andThrow((new Response(new \GuzzleHttp\Psr7\Response(404, [], '{"message":"Paquete no encontrado en IPS."}')))->toException());
        $ips->shouldReceive('deliveryCatalog')->andReturn([]);
        $ips->shouldReceive('catalog')->once()->with(17)->andReturn([
            'mail_classes' => [['MAIL_CLASS_CD' => 'U', 'MAIL_CLASS_NM' => 'Cartas']],
            'countries' => [['COUNTRY_CD' => 'DE', 'COUNTRY_NM' => 'Alemania'], ['COUNTRY_CD' => 'BO', 'COUNTRY_NM' => 'Bolivia']],
        ]);
        $view = app(PaquetesIpsController::class)->index($request, $ips, 'recepcion-oficina');
        $this->assertNull($view->getData()['error']);
        $this->assertSame('LW945318163DE', $view->getData()['missingPackageCode']);
        $this->assertNotEmpty($view->getData()['inboundCreateKey']);
        $this->actingAs($request->user());
        config()->set('adminlte.filters', []);
        config()->set('adminlte.menu', []);
        $html = $view->with('errors', new \Illuminate\Support\ViewErrorBag)->render();
        $this->assertStringContainsString('Crear y recibir envío L en IPS', $html);
        $this->assertStringNotContainsString('name="receptacle_pid"', $html);
    }

    public function test_creation_records_the_actual_arrival_in_ips_and_then_opens_delivery(): void
    {
        $request = $this->request($this->input());
        $ips = Mockery::mock(SitraIpsClient::class);
        $ips->shouldReceive('user')->once()->with(73)->andReturn(['user_pid' => 73, 'office_cd' => 17]);
        $ips->shouldReceive('createPackage')->once()->with(Mockery::on(fn ($payload) =>
            $payload['event'] === 'EMG' && $payload['codigo'] === 'LW945318163DE'
            && $payload['office_cd'] === 17 && $payload['actor_user_pid'] === 73
            && $payload['physical_receipt_confirmed'] === true
            && ! isset($payload['receptacle_pid']) && ! isset($payload['condition_cd'])
        ))->andReturn(['status' => 'succeeded']);
        $tracking = Mockery::mock(SitraTrackingEventsClient::class);
        $tracking->shouldReceive('forget')->once()->with('LW945318163DE');

        $response = app(PaquetesIpsController::class)->createDestinationPackage($request, $ips, $tracking);

        $this->assertStringContainsString('/internacional/almacen/por-entregar', $response->getTargetUrl());
        $this->assertDatabaseHas('paquetes_upu', ['codigo' => 'LW945318163DE', 'destino' => 'LA PAZ', 'peso' => 0.140]);
        $this->assertFalse($request->session()->has('ips_destination_create_pending:7001:LW945318163DE'));
    }

    public function test_uncertain_creation_reuses_the_original_payload_without_creating_a_local_receipt(): void
    {
        $request = $this->request($this->input());
        $ips = Mockery::mock(SitraIpsClient::class);
        $ips->shouldReceive('user')->once()->andReturn(['user_pid' => 73, 'office_cd' => 17]);
        $ips->shouldReceive('createPackage')->once()->ordered()->andThrow(new ConnectionException('Unavailable'));
        $tracking = Mockery::mock(SitraTrackingEventsClient::class);
        $tracking->shouldReceive('forget')->once()->with('LW945318163DE');
        $controller = app(PaquetesIpsController::class);
        $controller->createDestinationPackage($request, $ips, $tracking);
        $this->assertDatabaseCount('paquetes_upu', 0);
        $payload = $request->session()->get('ips_destination_create_pending:7001:LW945318163DE');
        $this->assertSame('EMG', $payload['event']);
        $request->user()->ciudad = 'COCHABAMBA';
        $request->replace(['codigo' => 'LW945318163DE', 'idempotency_key' => $payload['idempotency_key']]);
        $ips->shouldReceive('createPackage')->once()->ordered()->with($payload)->andReturn(['status' => 'succeeded']);
        $controller->createDestinationPackage($request, $ips, $tracking);
        $this->assertDatabaseCount('paquetes_upu', 1);
        $this->assertDatabaseHas('paquetes_upu', ['codigo' => 'LW945318163DE', 'destino' => 'LA PAZ']);
    }

    public function test_destination_creation_rejects_other_code_types_before_sending_to_ips(): void
    {
        $request = $this->request(array_replace($this->input(), ['codigo' => 'CP556326695CN']));
        $ips = Mockery::mock(SitraIpsClient::class);
        $ips->shouldNotReceive('createPackage');
        $this->expectException(ValidationException::class);
        app(PaquetesIpsController::class)->createDestinationPackage($request, $ips, Mockery::mock(SitraTrackingEventsClient::class));
    }

    public function test_creation_requires_explicit_physical_receipt_confirmation(): void
    {
        $input = $this->input();
        unset($input['physical_receipt_confirmed']);
        $ips = Mockery::mock(SitraIpsClient::class);
        $ips->shouldNotReceive('createPackage');
        $this->expectException(ValidationException::class);
        app(PaquetesIpsController::class)->createDestinationPackage($this->request($input), $ips, Mockery::mock(SitraTrackingEventsClient::class));
    }

    public function test_local_save_can_be_recovered_using_the_original_ips_creation(): void
    {
        $request = $this->request($this->input());
        $ips = Mockery::mock(SitraIpsClient::class);
        $ips->shouldReceive('user')->once()->andReturn(['user_pid' => 73, 'office_cd' => 17]);
        $ips->shouldReceive('createPackage')->once()->ordered()->andReturnUsing(function () {
            \Illuminate\Support\Facades\Schema::drop('paquetes_upu');
            return ['status' => 'succeeded'];
        });
        $tracking = Mockery::mock(SitraTrackingEventsClient::class);
        $tracking->shouldReceive('forget')->once()->with('LW945318163DE');
        $controller = app(PaquetesIpsController::class);
        $controller->createDestinationPackage($request, $ips, $tracking);
        $payload = $request->session()->get('ips_destination_create_pending:7001:LW945318163DE');
        $this->assertNotNull($payload);
        (require database_path('migrations/2026_11_12_000000_create_paquetes_upu_table.php'))->up();
        $ips->shouldReceive('createPackage')->once()->ordered()->with($payload)->andReturn(['status' => 'succeeded']);
        $controller->createDestinationPackage($request, $ips, $tracking);
        $this->assertDatabaseCount('paquetes_upu', 1);
    }

    public function test_destination_creation_route_uses_the_receive_destination_menu_permission(): void
    {
        $request = $this->request();
        $request->setRouteResolver(fn () => app('router')->getRoutes()->match($request));
        $this->assertSame(InternationalMenuAcl::SUBMENU_RECEIVE_DESTINATION, InternationalMenuAcl::permissionForRequest($request));
    }
}

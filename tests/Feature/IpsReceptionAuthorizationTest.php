<?php

namespace Tests\Feature;

use App\Http\Controllers\PaquetesIpsController;
use App\Models\PaqueteUpu;
use App\Models\User;
use App\Models\UserIpsLink;
use App\Services\SitraIpsClient;
use App\Services\SitraTrackingEventsClient;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Mockery;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class IpsReceptionAuthorizationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        (require database_path('migrations/2026_11_12_000000_create_paquetes_upu_table.php'))->up();
    }

    public static function receptionResults(): array
    {
        return [['succeeded'], ['processing'], ['uncertain'], ['rejected']];
    }

    #[DataProvider('receptionResults')]
    public function test_destination_receipt_clears_only_confirmed_packages(string $status): void
    {
        $request = $this->operationRequest(18, 'EMG');
        $key = 'ips_international_emg_workbench.7001_office_18';
        $selection = $request->session()->get('ips_workbench.7001_office_18');
        $selection['OTHERPACKAGE'] = ['package' => ['codigo' => 'OTHERPACKAGE'], 'status' => 'ready', 'payload' => null];
        $request->session()->put($key, $selection);
        $ips = Mockery::mock(SitraIpsClient::class);
        $ips->shouldReceive('user')->once()->with(73)->andReturn($this->snapshot());
        $ips->shouldReceive('package')->once()->with('PACKAGE01', 18)->andReturn(['package' => $this->package('EMG')]);
        $ips->shouldReceive('event')->once()->andReturn(['status' => $status]);
        $tracking = Mockery::mock(SitraTrackingEventsClient::class);
        if ($status === 'succeeded') {
            $tracking->shouldReceive('forget')->once()->with('PACKAGE01');
        } else {
            $tracking->shouldNotReceive('forget');
        }

        $result = app(PaquetesIpsController::class)->operateInternational($request, $ips, $tracking, 'recepcion-oficina')->getData(true);

        $this->assertSame($status, $result['status']);
        $this->assertSame($status === 'succeeded', $result['upu_saved'] ?? false);
        $this->assertDatabaseCount('paquetes_upu', $status === 'succeeded' ? 1 : 0);
        $remaining = $request->session()->get($key);
        $this->assertArrayHasKey('OTHERPACKAGE', $remaining);
        if ($status === 'succeeded') {
            $this->assertArrayNotHasKey('PACKAGE01', $remaining);
            $this->assertTrue($result['selection_removed']);
            $this->assertSame(1, $result['count']);
            $this->assertDatabaseHas('paquetes_upu', [
                'codigo' => 'PACKAGE01', 'destinatario' => 'Destinatario IPS',
                'telefono' => '+591 70000000', 'destino' => 'LA PAZ',
                'zona' => 'Zona Central, calle 1', 'peso' => 1.275,
            ]);
        } else {
            $this->assertSame($status, $remaining['PACKAGE01']['status']);
            $this->assertNotEmpty($remaining['PACKAGE01']['payload']['idempotency_key']);
        }
    }

    public function test_pending_receipt_preserves_local_data_and_does_not_duplicate_a_retry(): void
    {
        $request = $this->operationRequest(18, 'EMG');
        $key = 'ips_international_emg_workbench.7001_office_18';
        $request->session()->put($key, $request->session()->get('ips_workbench.7001_office_18'));
        $request->merge(['destino' => 'PANDO', 'destinatario' => 'Alterado', 'peso' => 99, 'zona' => 'Zona corregida por el operador']);
        $ips = Mockery::mock(SitraIpsClient::class);
        $ips->shouldReceive('user')->once()->andReturn($this->snapshot());
        $current = $this->package('EMG');
        $current['recipient'] = 'Destinatario actualizado';
        $ips->shouldReceive('package')->once()->andReturn(['package' => $current]);
        $ips->shouldReceive('event')->once()->ordered()->andReturn(['status' => 'processing']);
        $tracking = Mockery::mock(SitraTrackingEventsClient::class);
        $tracking->shouldReceive('forget')->twice()->with('PACKAGE01');
        $controller = app(PaquetesIpsController::class);

        $first = $controller->operateInternational($request, $ips, $tracking, 'recepcion-oficina')->getData(true);

        $this->assertSame('processing', $first['status']);
        $this->assertDatabaseCount('paquetes_upu', 0);
        $pending = $request->session()->get($key);
        $payload = $pending['PACKAGE01']['payload'];
        $this->assertArrayNotHasKey('zona', $payload);
        $request->user()->ciudad = 'COCHABAMBA';
        $request->merge(['zona' => 'Otra dirección durante el reintento']);
        $ips->shouldReceive('event')->twice()->ordered()->with('PACKAGE01', $payload)->andReturn(['status' => 'succeeded']);

        $second = $controller->operateInternational($request, $ips, $tracking, 'recepcion-oficina')->getData(true);
        $request->session()->put($key, $pending);
        $third = $controller->operateInternational($request, $ips, $tracking, 'recepcion-oficina')->getData(true);

        $this->assertSame('succeeded', $second['status']);
        $this->assertSame('succeeded', $third['status']);
        $this->assertDatabaseCount('paquetes_upu', 1);
        $package = PaqueteUpu::sole();
        $this->assertSame('Destinatario actualizado', $package->destinatario);
        $this->assertSame('LA PAZ', $package->destino);
        $this->assertSame('1.275', $package->peso);
        $this->assertSame('Zona corregida por el operador', $package->zona);
    }

    public static function editedZones(): array
    {
        return [['Zona Sur, calle 2, casa 10'], [null]];
    }

    #[DataProvider('receptionResults')]
    public function test_receipt_modal_saves_its_upu_data_only_after_ips_confirmation(string $status): void
    {
        $request = $this->operationRequest(18, 'EMG');
        $request->session()->put('ips_international_emg_workbench.7001_office_18', $request->session()->get('ips_workbench.7001_office_18'));
        $request->merge(['upu_receipt' => [
            'destinatario' => 'Destinatario del modal', 'telefono' => '70011122', 'zona' => 'Zona registrada', 'peso' => '0.140',
        ]]);
        $ips = Mockery::mock(SitraIpsClient::class);
        $ips->shouldReceive('user')->once()->andReturn($this->snapshot());
        $ips->shouldReceive('package')->once()->andReturn(['package' => $this->package('EMG')]);
        $ips->shouldReceive('event')->once()->with('PACKAGE01', Mockery::on(fn ($payload) =>
            ! array_key_exists('upu_receipt', $payload) && ! array_key_exists('destinatario', $payload)
            && ! array_key_exists('peso', $payload) && ! array_key_exists('zona', $payload)
        ))->andReturn(['status' => $status]);
        $tracking = Mockery::mock(SitraTrackingEventsClient::class);
        if ($status === 'succeeded') $tracking->shouldReceive('forget')->once();
        else $tracking->shouldNotReceive('forget');
        $result = app(PaquetesIpsController::class)->operateInternational($request, $ips, $tracking, 'recepcion-oficina')->getData(true);
        $this->assertSame($status, $result['status']);
        $this->assertDatabaseCount('paquetes_upu', $status === 'succeeded' ? 1 : 0);
        if ($status === 'succeeded') {
            $this->assertDatabaseHas('paquetes_upu', [
                'codigo' => 'PACKAGE01', 'destinatario' => 'Destinatario del modal', 'telefono' => '70011122',
                'zona' => 'Zona registrada', 'peso' => 0.140, 'destino' => 'LA PAZ',
            ]);
        }
    }

    public function test_pending_receipt_preserves_the_upu_modal_data_on_retry(): void
    {
        $request = $this->operationRequest(18, 'EMG');
        $key = 'ips_international_emg_workbench.7001_office_18';
        $request->session()->put($key, $request->session()->get('ips_workbench.7001_office_18'));
        $request->merge(['upu_receipt' => ['destinatario' => 'Registro original', 'telefono' => '70011122', 'zona' => 'Zona original', 'peso' => '0.140']]);
        $ips = Mockery::mock(SitraIpsClient::class);
        $ips->shouldReceive('user')->once()->andReturn($this->snapshot());
        $ips->shouldReceive('package')->once()->andReturn(['package' => $this->package('EMG')]);
        $ips->shouldReceive('event')->once()->ordered()->andReturn(['status' => 'processing']);
        $tracking = Mockery::mock(SitraTrackingEventsClient::class);
        $tracking->shouldReceive('forget')->once()->with('PACKAGE01');
        $controller = app(PaquetesIpsController::class);
        $controller->operateInternational($request, $ips, $tracking, 'recepcion-oficina');
        $pending = $request->session()->get($key)['PACKAGE01'];
        $this->assertSame('Registro original', $pending['upu_receipt']['destinatario']);
        $this->assertDatabaseCount('paquetes_upu', 0);
        $request->user()->ciudad = 'COCHABAMBA';
        $request->merge(['upu_receipt' => ['destinatario' => 'Registro cambiado', 'peso' => '9.999']]);
        $ips->shouldReceive('event')->once()->ordered()->with('PACKAGE01', $pending['payload'])->andReturn(['status' => 'succeeded']);
        $controller->operateInternational($request, $ips, $tracking, 'recepcion-oficina');
        $this->assertDatabaseHas('paquetes_upu', [
            'codigo' => 'PACKAGE01', 'destinatario' => 'Registro original', 'telefono' => '70011122',
            'zona' => 'Zona original', 'peso' => 0.140, 'destino' => 'LA PAZ',
        ]);
        $this->assertDatabaseCount('paquetes_upu', 1);
    }

    public static function invalidUpuModalData(): array
    {
        return [[['destinatario' => ''], 'upu_receipt.destinatario'], [['telefono' => str_repeat('1', 65)], 'upu_receipt.telefono'],
            [['peso' => '-1'], 'upu_receipt.peso'], [['peso' => '0.1234'], 'upu_receipt.peso'],
            [['codigo' => 'OTHERPACKAGE'], 'upu_receipt'], [['destino' => 'PANDO'], 'upu_receipt']];
    }

    #[DataProvider('invalidUpuModalData')]
    public function test_invalid_upu_modal_fields_are_rejected_before_ips_writes(array $changes, string $field): void
    {
        $request = $this->operationRequest(18, 'EMG');
        $request->merge(['upu_receipt' => array_replace(['destinatario' => 'Destinatario', 'peso' => '0.140'], $changes)]);
        $ips = Mockery::mock(SitraIpsClient::class);
        $ips->shouldNotReceive('event');
        try {
            app(PaquetesIpsController::class)->operateInternational($request, $ips, Mockery::mock(SitraTrackingEventsClient::class), 'recepcion-oficina');
            $this->fail('Debe validar los datos UPU antes de escribir en IPS.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey($field, $exception->errors());
        }
        $this->assertDatabaseCount('paquetes_upu', 0);
    }

    #[DataProvider('editedZones')]
    public function test_receipt_saves_the_edited_zone_only_in_bolipost(?string $zone): void
    {
        $request = $this->operationRequest(18, 'EMG');
        $request->merge(['zona' => $zone]);
        $request->session()->put('ips_international_emg_workbench.7001_office_18', $request->session()->get('ips_workbench.7001_office_18'));
        $ips = Mockery::mock(SitraIpsClient::class);
        $ips->shouldReceive('user')->once()->andReturn($this->snapshot());
        $ips->shouldReceive('package')->once()->andReturn(['package' => $this->package('EMG')]);
        $ips->shouldReceive('event')->once()->with('PACKAGE01', Mockery::on(
            fn (array $payload): bool => ! array_key_exists('zona', $payload)
        ))->andReturn(['status' => 'succeeded']);
        $tracking = Mockery::mock(SitraTrackingEventsClient::class);
        $tracking->shouldReceive('forget')->once()->with('PACKAGE01');

        $result = app(PaquetesIpsController::class)->operateInternational($request, $ips, $tracking, 'recepcion-oficina')->getData(true);

        $this->assertSame('succeeded', $result['status']);
        $this->assertSame($zone, PaqueteUpu::sole()->zona);
    }

    public function test_invalid_zone_is_rejected_before_registering_the_receipt(): void
    {
        $request = $this->operationRequest(18, 'EMG');
        $request->merge(['zona' => str_repeat('a', 1001)]);
        $ips = Mockery::mock(SitraIpsClient::class);
        $ips->shouldNotReceive('event');

        try {
            app(PaquetesIpsController::class)->operateInternational($request, $ips, Mockery::mock(SitraTrackingEventsClient::class), 'recepcion-oficina');
            $this->fail('Debe validar la zona antes de recibir el paquete.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('zona', $exception->errors());
        }
        $this->assertDatabaseCount('paquetes_upu', 0);
    }

    public function test_local_save_failure_keeps_the_receipt_available_for_retry(): void
    {
        $request = $this->operationRequest(18, 'EMG');
        $key = 'ips_international_emg_workbench.7001_office_18';
        $request->session()->put($key, $request->session()->get('ips_workbench.7001_office_18'));
        $ips = Mockery::mock(SitraIpsClient::class);
        $ips->shouldReceive('user')->once()->andReturn($this->snapshot());
        $ips->shouldReceive('package')->once()->andReturn(['package' => $this->package('EMG')]);
        $ips->shouldReceive('event')->once()->ordered()->andReturnUsing(function (): array {
            Schema::drop('paquetes_upu');

            return ['status' => 'succeeded'];
        });
        $tracking = Mockery::mock(SitraTrackingEventsClient::class);
        $tracking->shouldReceive('forget')->once()->with('PACKAGE01');
        $controller = app(PaquetesIpsController::class);

        $result = $controller->operateInternational($request, $ips, $tracking, 'recepcion-oficina')->getData(true);

        $this->assertSame('uncertain', $result['status']);
        $this->assertStringContainsString('falta guardar el paquete en Bolipost', $result['message']);
        $pending = $request->session()->get($key)['PACKAGE01'];
        $this->assertSame('uncertain', $pending['status']);
        $this->assertNotEmpty($pending['payload']['idempotency_key']);
        (require database_path('migrations/2026_11_12_000000_create_paquetes_upu_table.php'))->up();
        $ips->shouldReceive('event')->once()->ordered()->with('PACKAGE01', $pending['payload'])->andReturn(['status' => 'succeeded']);

        $retry = $controller->operateInternational($request, $ips, $tracking, 'recepcion-oficina')->getData(true);

        $this->assertSame('succeeded', $retry['status']);
        $this->assertTrue($retry['selection_removed']);
        $this->assertDatabaseCount('paquetes_upu', 1);
    }

    public function test_receipt_requires_the_logged_in_users_department_before_writing_to_ips(): void
    {
        $request = $this->operationRequest(18, 'EMG');
        $request->user()->ciudad = null;
        $request->session()->put('ips_international_emg_workbench.7001_office_18', $request->session()->get('ips_workbench.7001_office_18'));
        $ips = Mockery::mock(SitraIpsClient::class);
        $ips->shouldNotReceive('event');

        try {
            app(PaquetesIpsController::class)->operateInternational($request, $ips, Mockery::mock(SitraTrackingEventsClient::class), 'recepcion-oficina');
            $this->fail('No debe recibir sin el departamento del usuario.');
        } catch (HttpException $exception) {
            $this->assertSame(422, $exception->getStatusCode());
        }
        $this->assertDatabaseCount('paquetes_upu', 0);
    }

    public function test_previously_confirmed_receipt_is_saved_locally_before_removing_it(): void
    {
        $request = $this->operationRequest(18, 'EMG');
        $key = 'ips_international_emg_workbench.7001_office_18';
        $selection = $request->session()->get('ips_workbench.7001_office_18');
        $selection['PACKAGE01']['status'] = 'succeeded';
        $request->session()->put($key, $selection);
        $ips = Mockery::mock(SitraIpsClient::class);
        $ips->shouldNotReceive('event');

        $result = app(PaquetesIpsController::class)->operateInternational(
            $request, $ips, Mockery::mock(SitraTrackingEventsClient::class), 'recepcion-oficina'
        )->getData(true);

        $this->assertSame('succeeded', $result['status']);
        $this->assertTrue($result['selection_removed']);
        $this->assertSame([], $request->session()->get($key));
        $this->assertDatabaseHas('paquetes_upu', ['codigo' => 'PACKAGE01', 'destino' => 'LA PAZ']);
    }

    public function test_already_received_package_returns_a_modal_error_without_adding_it(): void
    {
        $request = $this->operationRequest(18, 'EMG');
        $ips = Mockery::mock(SitraIpsClient::class);
        $ips->shouldReceive('package')->once()->with('PACKAGE01', 18)->andReturn(['package' => [
            'codigo' => 'PACKAGE01', 'operational_event_cd' => 32, 'event_cd' => 35,
            'allowed_actions' => ['EMG'],
        ]]);
        $ips->shouldNotReceive('event');

        $response = app(PaquetesIpsController::class)->addInternationalSelection($request, $ips, 'recepcion-oficina');

        $this->assertSame(409, $response->getStatusCode());
        $this->assertSame('already_received', $response->getData(true)['error_code']);
        $this->assertNull($request->session()->get('ips_international_emg_workbench.7001_office_18'));
    }

    public static function officesAndActions(): array
    {
        return [[17, 'EMG'], [18, 'EMG'], [17, 'EMI'], [18, 'EMI']];
    }

    public static function actionsInTheWrongWindow(): array
    {
        return [['reception', 'EMI'], ['delivery', 'EMG'], ['delivery', 'EDH'], ['delivery', 'HANDOVER_AGENT'], ['delivery', 'CUSTOMS_PRESENT'], ['delivery', 'EDG'], ['delivery', 'EMH']];
    }

    #[DataProvider('actionsInTheWrongWindow')]
    public function test_each_window_rejects_actions_that_belong_to_other_steps(string $window, string $action): void
    {
        $ips = Mockery::mock(SitraIpsClient::class);
        $ips->shouldNotReceive('event');
        $ips->shouldNotReceive('deliver');
        $request = $this->operationRequest(18, $action);
        try {
            $controller = app(PaquetesIpsController::class);
            $tracking = Mockery::mock(SitraTrackingEventsClient::class);
            if ($window === 'reception') {
                $controller->operateInternational($request, $ips, $tracking, 'recepcion-oficina');
            } else {
                $controller->operateDelivery($request, $ips, $tracking);
            }
            $this->fail('La ventana debe rechazar una acción de otro paso.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('action', $exception->errors());
        }
    }

    public function test_receipt_and_delivery_use_separate_windows_and_separate_trays(): void
    {
        $request = $this->operationRequest(18, 'EMG');
        $receiptKey = 'ips_international_emg_workbench.7001_office_18';
        $deliveryKey = 'ips_delivery_workbench.7001_office_18';
        $request->session()->put($receiptKey, $request->session()->get('ips_workbench.7001_office_18'));
        $received = $this->package('EMI');
        $received['allowed_actions'] = ['EMI', 'EDH', 'HANDOVER_AGENT'];
        $ips = Mockery::mock(SitraIpsClient::class);
        $ips->shouldReceive('user')->twice()->andReturn($this->snapshot());
        $ips->shouldReceive('package')->times(3)->andReturn(['package' => $this->package('EMG')], ['package' => $received], ['package' => $received]);
        $ips->shouldReceive('event')->once()->with('PACKAGE01', Mockery::on(fn ($payload) => $payload['event'] === 'EMG'))
            ->andReturn(['status' => 'succeeded']);
        $ips->shouldReceive('deliver')->once()->with('PACKAGE01', Mockery::on(fn ($payload) => $payload['event'] === 'EMI'))
            ->andReturn(['status' => 'succeeded']);
        $tracking = Mockery::mock(SitraTrackingEventsClient::class);
        $tracking->shouldReceive('forget')->twice()->with('PACKAGE01');
        $controller = app(PaquetesIpsController::class);

        $receipt = $controller->operateInternational($request, $ips, $tracking, 'recepcion-oficina')->getData(true);
        $this->assertSame('succeeded', $receipt['status']);
        $this->assertSame([], $request->session()->get($receiptKey));
        $this->assertFalse($request->session()->has($deliveryKey));

        $request->merge(['action' => 'EMI']);
        $selection = $controller->addDeliverySelection($request, $ips)->getData(true);
        $this->assertSame(['EMI'], $selection['package']['allowed_actions']);
        $this->assertArrayHasKey('PACKAGE01', $request->session()->get($deliveryKey));
        $delivery = $controller->operateDelivery($request, $ips, $tracking)->getData(true);
        $this->assertSame('succeeded', $delivery['status']);
        $this->assertSame([], $request->session()->get($receiptKey));
        $this->assertDatabaseCount('paquetes_upu', 1);
    }

    public static function unreceivedDeliveryPackages(): array
    {
        return [[35, 0, 18], [31, 1, 18], [34, 1, 18], [38, 0, 18], [75, 0, 18], [74, 0, 18], [32, 0, 17]];
    }

    #[DataProvider('unreceivedDeliveryPackages')]
    public function test_delivery_rechecks_receipt_before_writing_even_with_a_saved_selection(int $event, int $state, int $office): void
    {
        $request = $this->operationRequest(18, 'EMI');
        $ips = Mockery::mock(SitraIpsClient::class);
        $ips->shouldReceive('user')->once()->andReturn($this->snapshot());
        $package = array_replace($this->package('EMI'), [
            'event_cd' => $event, 'state_cd' => $state, 'office_cd' => $office,
            'destination_receipt_confirmed' => false,
        ]);
        $ips->shouldReceive('package')->once()->andReturn(['package' => $package]);
        $ips->shouldNotReceive('deliver');
        $ips->shouldNotReceive('event');
        $result = app(PaquetesIpsController::class)->operate($request, $ips, Mockery::mock(SitraTrackingEventsClient::class))->getData(true);
        $this->assertSame('rejected', $result['status']);
        $this->assertStringContainsString('Primero debes recibir', $result['message']);
        $this->assertNull($request->session()->get('ips_workbench.7001_office_18')['PACKAGE01']['payload']);
    }

    public function test_unreceived_package_cannot_be_added_for_delivery_even_when_ips_advertises_emi(): void
    {
        $ips = Mockery::mock(SitraIpsClient::class);
        $ips->shouldReceive('package')->once()->andReturn(['package' => array_replace($this->package('EMI'), ['event_cd' => 38])]);
        $this->expectException(HttpException::class);
        app(PaquetesIpsController::class)->addDeliverySelection($this->operationRequest(18, 'EMI'), $ips);
    }

    public function test_an_old_combined_customs_and_delivery_payload_cannot_bypass_receipt(): void
    {
        $request = $this->operationRequest(18, 'EMI');
        $key = 'ips_workbench.7001_office_18';
        $selection = $request->session()->get($key);
        $selection['PACKAGE01']['payload'] = ['event' => 'EMI', 'customs_return_confirmed' => true, 'idempotency_key' => 'old-combined-operation'];
        $selection['PACKAGE01']['status'] = 'uncertain';
        $request->session()->put($key, $selection);
        $ips = Mockery::mock(SitraIpsClient::class);
        $ips->shouldNotReceive('deliver');
        $this->expectException(HttpException::class);
        $this->expectExceptionMessage('Primero debes recibir');
        app(PaquetesIpsController::class)->operate($request, $ips, Mockery::mock(SitraTrackingEventsClient::class));
    }

    #[DataProvider('officesAndActions')]
    public function test_receipt_and_delivery_verify_primary_and_secondary_offices_before_writing(int $office, string $action): void
    {
        $ips = Mockery::mock(SitraIpsClient::class);
        $ips->shouldReceive('user')->once()->with(73)->andReturn($this->snapshot());
        $ips->shouldReceive('package')->once()->with('PACKAGE01', $office)->andReturn(['package' => $this->package($action, $office)]);
        $ips->shouldReceive($action === 'EMI' ? 'deliver' : 'event')->once()
            ->with('PACKAGE01', Mockery::on(fn ($payload) => $payload['office_cd'] === $office
                && $payload['actor_user_pid'] === 73 && $payload['event'] === $action))
            ->andReturn(['status' => 'succeeded']);
        $tracking = Mockery::mock(SitraTrackingEventsClient::class);
        $tracking->shouldReceive('forget')->once()->with('PACKAGE01');

        $request = $this->operationRequest($office, $action);
        $response = app(PaquetesIpsController::class)->operate($request, $ips, $tracking);

        $this->assertSame('succeeded', $response->getData(true)['status']);
        $this->assertSame(17, $request->user()->ipsLink->ips_office_cd);
    }

    public function test_office_removed_in_sitra_is_rejected_even_with_a_saved_selection(): void
    {
        $ips = Mockery::mock(SitraIpsClient::class);
        $ips->shouldReceive('user')->once()->with(73)->andReturn(['user_pid' => 73, 'office_cd' => 17, 'offices' => []]);
        $this->assertBlockedBeforeWrite($ips, 403);
    }

    public function test_unavailable_sitra_does_not_allow_a_new_receipt(): void
    {
        $ips = Mockery::mock(SitraIpsClient::class);
        $ips->shouldReceive('user')->once()->andThrow(new ConnectionException('Unavailable'));
        $this->assertBlockedBeforeWrite($ips, 503);
    }

    public function test_pending_receipt_keeps_its_original_payload_and_idempotency_key(): void
    {
        $request = $this->operationRequest(18, 'EMG');
        $key = 'ips_workbench.7001_office_18';
        $selection = $request->session()->get($key);
        $payload = ['event' => 'EMG', 'office_cd' => 18, 'actor_user_pid' => 73, 'idempotency_key' => 'original-key'];
        $selection['PACKAGE01']['payload'] = $payload;
        $selection['PACKAGE01']['status'] = 'uncertain';
        $request->session()->put($key, $selection);
        $ips = Mockery::mock(SitraIpsClient::class);
        $ips->shouldNotReceive('user');
        $ips->shouldNotReceive('package');
        $ips->shouldReceive('event')->once()->with('PACKAGE01', $payload)->andReturn(['status' => 'processing']);

        $response = app(PaquetesIpsController::class)->operate($request, $ips, Mockery::mock(SitraTrackingEventsClient::class));

        $this->assertSame('processing', $response->getData(true)['status']);
        $this->assertSame($payload, $request->session()->get($key)['PACKAGE01']['payload']);
    }

    public static function serverErrors(): array
    {
        return [['Server Error'], ['Server Error &#x20;'], ['Internal Server Error'], [null]];
    }

    #[DataProvider('serverErrors')]
    public function test_sitra_server_error_has_a_clear_message_and_preserves_the_original_operation(?string $message): void
    {
        $ips = Mockery::mock(SitraIpsClient::class);
        $ips->shouldReceive('user')->once()->with(73)->andReturn($this->snapshot());
        $ips->shouldReceive('package')->once()->with('PACKAGE01', 18)->andReturn(['package' => $this->package('EMG')]);
        $remoteResponse = new \Illuminate\Http\Client\Response(new \GuzzleHttp\Psr7\Response(
            500, [], $message === null ? '<html>Server Error</html>' : json_encode(['message' => $message])
        ));
        $ips->shouldReceive('event')->once()->ordered()->andThrow($remoteResponse->toException());
        $tracking = Mockery::mock(SitraTrackingEventsClient::class);
        $tracking->shouldNotReceive('forget');
        $request = $this->operationRequest(18, 'EMG');
        $controller = app(PaquetesIpsController::class);

        $result = $controller->operate($request, $ips, $tracking)->getData(true);

        $this->assertSame('uncertain', $result['status']);
        $this->assertStringContainsString('SITRA devolvió un error interno (HTTP 500)', $result['message']);
        $this->assertStringNotContainsString('&#x20;', $result['message']);
        $key = 'ips_workbench.7001_office_18';
        $payload = $request->session()->get($key)['PACKAGE01']['payload'];
        $this->assertNotEmpty($payload['idempotency_key']);
        $ips->shouldReceive('event')->once()->ordered()->with('PACKAGE01', $payload)->andReturn(['status' => 'processing']);

        $retry = $controller->operate($request, $ips, $tracking)->getData(true);

        $this->assertSame('processing', $retry['status']);
        $this->assertSame($payload, $request->session()->get($key)['PACKAGE01']['payload']);
    }

    private function assertBlockedBeforeWrite(SitraIpsClient $ips, int $status): void
    {
        $ips->shouldNotReceive('package');
        $ips->shouldNotReceive('event');
        $ips->shouldNotReceive('deliver');
        $request = $this->operationRequest(18, 'EMG');
        try {
            app(PaquetesIpsController::class)->operate($request, $ips, Mockery::mock(SitraTrackingEventsClient::class));
            $this->fail('No debe registrar el movimiento sin verificar la oficina.');
        } catch (HttpException $exception) {
            $this->assertSame($status, $exception->getStatusCode());
        }
        $this->assertNull($request->session()->get('ips_workbench.7001_office_18')['PACKAGE01']['payload']);
    }

    private function snapshot(): array
    {
        return ['user_pid' => 73, 'office_cd' => 17, 'offices' => [['office_cd' => 18, 'office_name' => 'Secundaria']]];
    }

    private function package(string $action, int $office = 18): array
    {
        return [
            'codigo' => 'PACKAGE01', 'event_cd' => $action === 'EMI' ? 32 : 35, 'event_at' => '2026-11-09T12:00:00Z', 'allowed_actions' => [$action],
            'office_cd' => $office, 'state_cd' => 0, 'destination_country' => 'BO',
            'recipient' => 'Destinatario IPS', 'phone' => '+591 70000000',
            'address' => 'Zona Central, calle 1', 'city' => 'Santa Cruz', 'weight_kg' => 1.275,
        ];
    }

    private function operationRequest(int $office, string $action): Request
    {
        $user = new User(['name' => 'Operador', 'ciudad' => 'LA PAZ']);
        $user->id = 7001;
        $user->setRelation('ipsLink', new UserIpsLink([
            'ips_user_pid' => 73, 'ips_office_cd' => 17, 'active' => true, 'last_snapshot' => $this->snapshot(),
        ]));
        $request = Request::create('/ips/operar?office_cd='.$office, 'POST', [
            'codigo' => 'PACKAGE01', 'action' => $action, 'confirmed' => '1',
            'physical_receipt_confirmed' => '1', 'delivery_mode' => 'NO_DOMICILIARIA',
        ]);
        $request->setUserResolver(fn () => $user);
        $request->setLaravelSession(app('session')->driver());
        $request->headers->set('Accept', 'application/json');
        $request->session()->put('ips_workbench.7001'.($office === 17 ? '' : '_office_'.$office), [
            'PACKAGE01' => ['package' => $this->package($action, $office), 'status' => 'ready', 'payload' => null],
        ]);

        return $request;
    }
}

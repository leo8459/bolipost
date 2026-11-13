<?php

namespace Tests\Feature;

use App\Http\Controllers\PaquetesIpsController;
use App\Models\User;
use App\Models\UserIpsLink;
use App\Services\IpsCombinedOfficeWorkbench;
use App\Services\SitraIpsClient;
use App\Services\SitraTrackingEventsClient;
use Illuminate\Http\Client\Response;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Mockery;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class IpsCombinedOfficesTest extends TestCase
{
    private array $offices = [
        ['office_cd' => 17, 'office_fcd' => 'BOLPBA', 'office_name' => 'LA PAZ LC/AO'],
        ['office_cd' => 18, 'office_fcd' => 'BOLPBB', 'office_name' => 'LA PAZ EMS'],
        ['office_cd' => 19, 'office_fcd' => 'BOLPBC', 'office_name' => 'LA PAZ CP-AVION'],
    ];

    protected function setUp(): void
    {
        parent::setUp();
        (require database_path('migrations/2025_06_11_195557_create_permission_tables.php'))->up();
        (require database_path('migrations/2026_11_12_000000_create_paquetes_upu_table.php'))->up();
        Gate::before(fn () => true);
        config()->set('adminlte.filters', []);
        config()->set('adminlte.menu', []);
    }

    private function snapshot(): array
    {
        return ['user_pid' => 73, 'office_cd' => 17, 'office_fcd' => 'BOLPBA', 'office_name' => 'LA PAZ LC/AO', 'offices' => $this->offices];
    }

    private function request(array $input = []): Request
    {
        $user = new User(['name' => 'Operador', 'ciudad' => 'LA PAZ']);
        $user->id = 7001;
        $user->setRelation('ipsLink', new UserIpsLink([
            'ips_user_pid' => 73, 'ips_office_cd' => 17, 'ips_office_fcd' => 'BOLPBA',
            'ips_office_name' => 'LA PAZ LC/AO', 'active' => true, 'last_snapshot' => $this->snapshot(),
        ]));
        $this->actingAs($user);
        $request = Request::create('/internacional/recepcion-oficina', 'POST', $input);
        $request->setUserResolver(fn () => $user);
        $request->setLaravelSession(app('session')->driver());
        $request->headers->set('Accept', 'application/json');

        return $request;
    }

    private function package(int $office, bool $received = false): array
    {
        return [
            'codigo' => 'PACKAGE'.$office, 'id' => 'package-'.$office,
            'event_cd' => $received ? 32 : 35, 'event_at' => '2026-11-13T12:00:00+00:00',
            'office_cd' => $received ? $office : 9, 'next_office_cd' => $received ? null : $office,
            'state_cd' => 0, 'destination_country' => 'BO',
            'stage' => ['label' => $received ? 'Recibido' : 'Despachado', 'tone' => 'info'],
            'allowed_actions' => [$received ? 'EMI' : 'EMG'], 'local_id' => '',
            'recipient' => 'Destinatario', 'phone' => null, 'city' => 'La Paz', 'address' => 'Calle de prueba',
            'office_name' => $received ? 'Oficina '.$office : 'Origen', 'next_office_name' => $received ? '' : 'Oficina '.$office,
            'weight_kg' => 0.140, 'mail_class' => 'U', 'dutiable_ind' => null,
        ];
    }

    private function selfRoutedLetter(): array
    {
        return array_replace($this->package(17), [
            'codigo' => 'LW545318163DE', 'event_cd' => 13, 'office_cd' => 17,
            'next_office_cd' => 17, 'state_cd' => 6, 'allowed_actions' => [],
            'office_name' => 'LA PAZ LC/AO', 'next_office_name' => 'LA PAZ LC/AO',
        ]);
    }

    public function test_a_self_routed_letter_is_included_in_the_table_via_the_pending_sack_queue(): void
    {
        $request = $this->request();
        $request->headers->set('Accept', 'text/html');
        $ips = Mockery::mock(SitraIpsClient::class);
        $ips->shouldReceive('packages')->times(6)->andReturnUsing(function ($filters) {
            $found = $filters['office_cd'] === 17 && $filters['status'] === 'pending';
            if ($filters['status'] === 'pending') {
                $this->assertSame(13, $filters['event_cd']);
            } else {
                $this->assertNotContains(13, $filters['event_cd']);
            }
            return ['data' => $found ? [$this->selfRoutedLetter()] : [], 'meta' => ['total' => $found ? 1 : 0]];
        });
        $ips->shouldReceive('deliveryCatalog')->andReturn([]);
        $view = app(PaquetesIpsController::class)->index($request, $ips, 'recepcion-oficina');
        $this->assertSame(1, $view->getData()['packages']->total());
        $package = $view->getData()['packages']->items()[0];
        $this->assertSame('LW545318163DE', $package['codigo']);
        $this->assertSame([18, 19], array_column($package['receipt_office_options'], 'office_cd'));
        $html = view('paquetes_ips.partials.listing', $view->getData())->render();
        $this->assertStringContainsString('LW545318163DE', $html);
        $this->assertStringNotContainsString('name="receipt_office_cd"', $html);
        $this->assertStringContainsString('Agregar a recepción', $html);
        $this->assertStringContainsString('en el modal', $html);
    }

    public function test_exact_lookup_from_page_40_returns_the_letter_and_receipt_choices_on_page_one(): void
    {
        $request = $this->request(['q' => 'LW545318163DE', 'page' => 40]);
        $request->headers->set('Accept', 'text/html');
        $ips = Mockery::mock(SitraIpsClient::class);
        $ips->shouldReceive('package')->once()->with('LW545318163DE', 17)->andReturn(['package' => $this->selfRoutedLetter()]);
        $ips->shouldReceive('deliveryCatalog')->andReturn([]);
        $view = app(PaquetesIpsController::class)->index($request, $ips, 'recepcion-oficina');
        $this->assertNull($view->getData()['error']);
        $this->assertSame(1, $view->getData()['packages']->currentPage());
        $this->assertSame('LW545318163DE', $view->getData()['packages']->items()[0]['codigo']);
        $this->assertNotEmpty($view->getData()['packages']->items()[0]['receipt_office_options']);
    }

    public function test_scanning_a_self_routed_letter_prepares_it_automatically_without_receiving_it(): void
    {
        $ips = Mockery::mock(SitraIpsClient::class);
        $ips->shouldReceive('package')->once()->with('LW545318163DE', 17)->andReturn(['package' => $this->selfRoutedLetter()]);
        $ips->shouldNotReceive('event');
        $response = app(PaquetesIpsController::class)->addInternationalSelection($this->request(['codigo' => 'LW545318163DE']), $ips, 'recepcion-oficina');
        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame(['EMG'], $response->getData(true)['package']['allowed_actions']);
        $this->assertTrue($response->getData(true)['package']['needs_receipt_office']);
        $this->assertSame(18, $response->getData(true)['package']['receipt_office_locked_cd']);
        $this->assertSame(1, $response->getData(true)['count']);
        $this->assertDatabaseCount('paquetes_upu', 0);
    }

    public function test_the_receipt_modal_selects_the_actual_office_before_the_first_ips_write(): void
    {
        $ips = Mockery::mock(SitraIpsClient::class);
        $ips->shouldReceive('package')->once()->with('LW545318163DE', 17)->andReturn(['package' => $this->selfRoutedLetter()]);
        $ips->shouldReceive('package')->once()->with('LW545318163DE', 18)->andReturn(['package' => $this->selfRoutedLetter()]);
        $ips->shouldReceive('user')->once()->andReturn($this->snapshot());
        $ips->shouldReceive('event')->once()->with('LW545318163DE', Mockery::on(fn ($payload) =>
            $payload['office_cd'] === 18 && $payload['event'] === 'EMG' && $payload['route_mismatch_confirmed'] === true
        ))->andReturn(['status' => 'succeeded']);
        $ips->shouldNotReceive('deliver');
        $tracking = Mockery::mock(SitraTrackingEventsClient::class);
        $tracking->shouldReceive('forget')->once()->with('LW545318163DE');
        $controller = app(PaquetesIpsController::class);
        $controller->addInternationalSelection($this->request(['codigo' => 'LW545318163DE']), $ips, 'recepcion-oficina');
        $result = $controller->operateInternational($this->request([
            'codigo' => 'LW545318163DE', 'action' => 'EMG', 'confirmed' => '1', 'physical_receipt_confirmed' => '1',
            'route_mismatch_confirmed' => '1',
        ]), $ips, $tracking, 'recepcion-oficina')->getData(true);
        $this->assertSame('succeeded', $result['status']);
        $this->assertDatabaseHas('paquetes_upu', ['codigo' => 'LW545318163DE']);
    }

    public static function missingModalConfirmations(): array
    {
        return [[[], 'physical'], [['physical_receipt_confirmed' => '1', 'route_mismatch_confirmed' => '1', 'receipt_office_cd' => 19], 'office'],
            [['physical_receipt_confirmed' => '1', 'receipt_office_cd' => 18], 'route']];
    }

    public function test_the_office_cannot_be_changed_when_preparing_a_receipt(): void
    {
        $ips = Mockery::mock(SitraIpsClient::class);
        $ips->shouldReceive('package')->once()->with('LW545318163DE', 17)->andReturn(['package' => $this->selfRoutedLetter()]);
        $ips->shouldNotReceive('event');
        $request = $this->request(['codigo' => 'LW545318163DE', 'office_cd' => 19, 'receipt_office_cd' => 19]);
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('La oficina asignada automáticamente no se puede cambiar.');
        app(PaquetesIpsController::class)->addInternationalSelection($request, $ips, 'recepcion-oficina');
    }

    public function test_the_automatic_office_uses_the_linked_operator_office_instead_of_a_client_override(): void
    {
        $request = $this->request(['codigo' => 'LW545318163DE', 'office_cd' => 18]);
        $request->user()->ipsLink->ips_office_cd = 19;
        $ips = Mockery::mock(SitraIpsClient::class);
        $ips->shouldReceive('package')->once()->with('LW545318163DE', 17)->andReturn(['package' => $this->selfRoutedLetter()]);
        $result = app(PaquetesIpsController::class)->addInternationalSelection($request, $ips, 'recepcion-oficina')->getData(true);
        $this->assertSame(19, $result['package']['receipt_office_locked_cd']);
    }

    public function test_a_pending_modal_receipt_retries_the_original_office_and_idempotency_key(): void
    {
        $ips = Mockery::mock(SitraIpsClient::class);
        $ips->shouldReceive('package')->once()->with('LW545318163DE', 17)->andReturn(['package' => $this->selfRoutedLetter()]);
        $ips->shouldReceive('package')->once()->with('LW545318163DE', 18)->andReturn(['package' => $this->selfRoutedLetter()]);
        $ips->shouldReceive('user')->once()->andReturn($this->snapshot());
        $ips->shouldReceive('event')->once()->ordered()->andReturn(['status' => 'processing']);
        $tracking = Mockery::mock(SitraTrackingEventsClient::class);
        $tracking->shouldReceive('forget')->once()->with('LW545318163DE');
        $controller = app(PaquetesIpsController::class);
        $controller->addInternationalSelection($this->request(['codigo' => 'LW545318163DE']), $ips, 'recepcion-oficina');
        $confirmation = ['codigo' => 'LW545318163DE', 'action' => 'EMG', 'confirmed' => '1',
            'physical_receipt_confirmed' => '1', 'route_mismatch_confirmed' => '1', 'receipt_office_cd' => 18];
        $request = $this->request($confirmation);
        $result = $controller->operateInternational($request, $ips, $tracking, 'recepcion-oficina')->getData(true);
        $this->assertSame('processing', $result['status']);
        $entry = $request->session()->get('ips_international_emg_workbench.7001_offices_17_18_19')['LW545318163DE'];
        $this->assertFalse($entry['needs_receipt_office']);
        $this->assertSame(18, $entry['office_cd']);
        $this->assertArrayNotHasKey('receipt_office_options', $entry['package']);
        $ips->shouldReceive('event')->once()->ordered()->with('LW545318163DE', $entry['payload'])->andReturn(['status' => 'succeeded']);
        unset($confirmation['receipt_office_cd']);
        $result = $controller->operateInternational($this->request($confirmation), $ips, $tracking, 'recepcion-oficina')->getData(true);
        $this->assertSame('succeeded', $result['status']);
        $this->assertDatabaseCount('paquetes_upu', 1);
    }

    #[DataProvider('missingModalConfirmations')]
    public function test_automatic_selection_cannot_replace_the_receipt_modal_confirmations(array $confirmation, string $missing): void
    {
        $ips = Mockery::mock(SitraIpsClient::class);
        $ips->shouldReceive('package')->once()->andReturn(['package' => $this->selfRoutedLetter()]);
        $ips->shouldNotReceive('event');
        $ips->shouldNotReceive('user');
        $controller = app(PaquetesIpsController::class);
        $controller->addInternationalSelection($this->request(['codigo' => 'LW545318163DE']), $ips, 'recepcion-oficina');
        $request = $this->request($confirmation + ['codigo' => 'LW545318163DE', 'action' => 'EMG', 'confirmed' => '1']);
        if ($missing === 'physical') {
            $response = $controller->operateInternational($request, $ips, Mockery::mock(SitraTrackingEventsClient::class), 'recepcion-oficina');
            $this->assertSame(422, $response->getStatusCode());
        } else {
            try {
                $controller->operateInternational($request, $ips, Mockery::mock(SitraTrackingEventsClient::class), 'recepcion-oficina');
                $this->fail('Debe exigir la confirmación que falta en el modal.');
            } catch (ValidationException $exception) {
                $this->assertSame('office', $missing);
                $this->assertArrayHasKey('receipt_office_cd', $exception->errors());
            } catch (HttpException $exception) {
                $this->assertSame('route', $missing);
                $this->assertSame(422, $exception->getStatusCode());
            }
        }
        $this->assertDatabaseCount('paquetes_upu', 0);
    }

    public function test_physical_receipt_in_ems_keeps_the_route_mismatch_confirmation_and_does_not_deliver(): void
    {
        $ips = Mockery::mock(SitraIpsClient::class);
        $ips->shouldReceive('package')->once()->with('LW545318163DE', 17)->andReturn(['package' => $this->selfRoutedLetter()]);
        $ips->shouldReceive('package')->twice()->with('LW545318163DE', 18)->andReturn(['package' => $this->selfRoutedLetter()]);
        $ips->shouldReceive('user')->once()->andReturn($this->snapshot());
        $ips->shouldReceive('event')->once()->with('LW545318163DE', Mockery::on(fn ($payload) =>
            $payload['event'] === 'EMG' && $payload['office_cd'] === 18
            && $payload['route_mismatch_confirmed'] === true && $payload['physical_receipt_confirmed'] === true
        ))->andReturn(['status' => 'succeeded']);
        $ips->shouldNotReceive('deliver');
        $tracking = Mockery::mock(SitraTrackingEventsClient::class);
        $tracking->shouldReceive('forget')->once()->with('LW545318163DE');
        $controller = app(PaquetesIpsController::class);
        $selected = $controller->addInternationalSelection($this->request([
            'codigo' => 'LW545318163DE', 'receipt_office_cd' => 18,
        ]), $ips, 'recepcion-oficina')->getData(true);
        $this->assertTrue($selected['route_mismatch_receipt']);
        $this->assertSame('LA PAZ EMS', $selected['package']['workbench_office_name']);
        $result = $controller->operateInternational($this->request([
            'codigo' => 'LW545318163DE', 'action' => 'EMG', 'confirmed' => '1',
            'physical_receipt_confirmed' => '1', 'route_mismatch_confirmed' => '1',
        ]), $ips, $tracking, 'recepcion-oficina')->getData(true);
        $this->assertSame('succeeded', $result['status']);
        $this->assertDatabaseHas('paquetes_upu', ['codigo' => 'LW545318163DE']);
    }

    public static function windows(): array
    {
        return [['reception'], ['delivery']];
    }

    #[DataProvider('windows')]
    public function test_both_windows_list_all_three_offices_in_one_table(string $window): void
    {
        $request = $this->request(['office_cd' => 19]);
        $request->headers->set('Accept', 'text/html');
        $ips = Mockery::mock(SitraIpsClient::class);
        $ips->shouldReceive('packages')->times($window === 'reception' ? 6 : 3)->andReturnUsing(function ($filters) use ($window) {
            $this->assertContains($filters['office_cd'], [17, 18, 19]);
            if (($filters['status'] ?? '') === 'pending') {
                $this->assertSame(13, $filters['event_cd']);
                return ['data' => [], 'meta' => ['total' => 0]];
            }
            return ['data' => [$this->package($filters['office_cd'], $window === 'delivery')], 'meta' => ['total' => 1]];
        });
        $ips->shouldReceive('deliveryCatalog')->andReturn([]);
        $controller = app(PaquetesIpsController::class);
        $view = $window === 'reception' ? $controller->index($request, $ips, 'recepcion-oficina') : $controller->warehouse($request, $ips, 'por-entregar');
        $data = $view->getData();
        $this->assertSame(3, $data['packages']->total());
        $this->assertCount(3, $data['combinedOffices']);
        $this->assertSame(['PACKAGE17', 'PACKAGE18', 'PACKAGE19'], array_column($data['packages']->items(), 'codigo'));
        foreach ($data['packages'] as $package) {
            $this->assertSame([$window === 'reception' ? 'EMG' : 'EMI'], $package['allowed_actions']);
        }
        $this->assertStringNotContainsString('office_cd=', $data['listingUrl']);
        $this->assertStringNotContainsString('office_cd=', $data['selectionAddUrl']);
        $this->assertStringNotContainsString('office_cd=', $data['operateUrl']);
        $html = view('paquetes_ips.partials.offices', $data)->render();
        $this->assertStringContainsString('BOLPBA', $html);
        $this->assertStringContainsString('BOLPBB', $html);
        $this->assertStringContainsString('BOLPBC', $html);
        $this->assertStringNotContainsString('target="_blank"', $html);
        $html = view('paquetes_ips.partials.listing', $data)->render();
        foreach ([17, 18, 19] as $office) {
            $this->assertStringContainsString('PACKAGE'.$office, $html);
        }
        $this->assertStringNotContainsString('name="office_cd"', $html);
    }

    public function test_a_mixed_batch_receives_then_delivers_in_each_packages_actual_office(): void
    {
        $received = [];
        $written = [];
        $ips = Mockery::mock(SitraIpsClient::class);
        $ips->shouldReceive('package')->andReturnUsing(function ($code, $office) use (&$received) {
            $target = (int) substr($code, -2);
            $package = $this->package($target, isset($received[$code]));
            if ($office !== $target) {
                $package['allowed_actions'] = [];
            }
            return ['package' => $package];
        });
        $ips->shouldReceive('user')->times(6)->andReturn($this->snapshot());
        $ips->shouldReceive('event')->times(3)->andReturnUsing(function ($code, $payload) use (&$received, &$written) {
            $this->assertSame((int) substr($code, -2), $payload['office_cd']);
            $this->assertSame('EMG', $payload['event']);
            $received[$code] = true;
            $written[] = [$code, 'EMG', $payload['office_cd']];
            return ['status' => 'succeeded'];
        });
        $ips->shouldReceive('deliver')->times(3)->andReturnUsing(function ($code, $payload) use (&$written) {
            $this->assertSame((int) substr($code, -2), $payload['office_cd']);
            $this->assertSame('EMI', $payload['event']);
            $written[] = [$code, 'EMI', $payload['office_cd']];
            return ['status' => 'succeeded'];
        });
        $tracking = Mockery::mock(SitraTrackingEventsClient::class);
        $tracking->shouldReceive('forget')->times(6);
        $controller = app(PaquetesIpsController::class);
        foreach (['EMG', 'EMI'] as $action) {
            foreach ([17, 18, 19] as $office) {
                $request = $this->request(['codigo' => 'PACKAGE'.$office]);
                $result = $action === 'EMG' ? $controller->addInternationalSelection($request, $ips, 'recepcion-oficina') : $controller->addDeliverySelection($request, $ips);
                $this->assertSame($office, $result->getData(true)['package']['workbench_office_cd']);
            }
            $key = 'ips_'.($action === 'EMG' ? 'international_emg' : 'delivery').'_workbench.7001_offices_17_18_19';
            $this->assertCount(3, $request->session()->get($key));
            foreach ([17, 18, 19] as $office) {
                // A stale or altered office input must never change the selected package's office.
                $request = $this->request(['codigo' => 'PACKAGE'.$office, 'office_cd' => 17, 'action' => $action,
                    'confirmed' => '1', 'physical_receipt_confirmed' => '1', 'delivery_mode' => 'NO_DOMICILIARIA']);
                $result = $action === 'EMG' ? $controller->operateInternational($request, $ips, $tracking, 'recepcion-oficina') : $controller->operateDelivery($request, $ips, $tracking);
                $this->assertSame('succeeded', $result->getData(true)['status']);
            }
        }
        $this->assertCount(6, $written);
        $this->assertDatabaseCount('paquetes_upu', 3);
    }

    public function test_group_pagination_is_shared_even_when_office_queues_have_many_pages(): void
    {
        $rows = [];
        foreach ([17, 18, 19] as $rank => $office) {
            for ($n = 0; $n < 150; $n++) {
                $package = $this->package($office);
                $package['codigo'] = 'PACKAGE'.$office.'X'.$n;
                $package['event_at'] = gmdate('c', 1794567600 - ($n * 3 + $rank));
                $rows[$office][] = $package;
            }
        }
        $ips = Mockery::mock(SitraIpsClient::class);
        $ips->shouldReceive('packages')->times(9)->andReturnUsing(function ($filters) use ($rows) {
            $this->assertSame(100, $filters['per_page']);
            if (($filters['status'] ?? '') === 'pending') {
                return ['data' => [], 'meta' => ['total' => 0]];
            }
            return ['data' => array_slice($rows[$filters['office_cd']], ($filters['page'] - 1) * 100, 100), 'meta' => ['total' => 150]];
        });
        $result = (new IpsCombinedOfficeWorkbench($ips))->packages(['page' => 5, 'per_page' => 25, 'status' => 'reception'], $this->offices);
        $expected = collect($rows)->flatten(1)->sortByDesc('event_at')->slice(100, 25)->pluck('codigo')->values()->all();
        $this->assertSame(450, $result['meta']['total']);
        $this->assertSame($expected, array_column($result['data'], 'codigo'));
    }

    public function test_previously_pending_receipts_are_kept_when_the_old_office_trays_are_combined(): void
    {
        $request = $this->request();
        $request->headers->set('Accept', 'text/html');
        $payload = ['event' => 'EMG', 'office_cd' => 18, 'idempotency_key' => 'original-office-18-key'];
        $request->session()->put('ips_international_emg_workbench.7001_office_18', [
            'PACKAGE18' => ['package' => $this->package(18), 'status' => 'uncertain', 'payload' => $payload],
        ]);
        $ips = Mockery::mock(SitraIpsClient::class);
        $ips->shouldReceive('packages')->times(6)->andReturn(['data' => [], 'meta' => ['total' => 0]]);
        $ips->shouldReceive('deliveryCatalog')->andReturn([]);
        $view = app(PaquetesIpsController::class)->index($request, $ips, 'recepcion-oficina');
        $entry = $view->getData()['selection']['PACKAGE18'];
        $this->assertSame(18, $entry['office_cd']);
        $this->assertSame('uncertain', $entry['status']);
        $this->assertSame($payload, $entry['payload']);
        $this->assertSame($payload, $request->session()->get('ips_international_emg_workbench.7001_offices_17_18_19')['PACKAGE18']['payload']);
    }

    public function test_a_secondary_office_removed_in_ips_is_still_blocked_before_a_grouped_receipt(): void
    {
        $ips = Mockery::mock(SitraIpsClient::class);
        $ips->shouldReceive('package')->twice()->andReturn(['package' => $this->package(19)]);
        $ips->shouldReceive('user')->once()->andReturn(['user_pid' => 73, 'office_cd' => 17, 'offices' => [$this->offices[0], $this->offices[1]]]);
        $ips->shouldNotReceive('event');
        $controller = app(PaquetesIpsController::class);
        $controller->addInternationalSelection($this->request(['codigo' => 'PACKAGE19']), $ips, 'recepcion-oficina');
        $this->expectException(HttpException::class);
        $this->expectExceptionMessage('ya no esta asignada');
        $controller->operateInternational($this->request([
            'codigo' => 'PACKAGE19', 'action' => 'EMG', 'confirmed' => '1', 'physical_receipt_confirmed' => '1',
        ]), $ips, Mockery::mock(SitraTrackingEventsClient::class), 'recepcion-oficina');
    }

    public function test_scanning_a_package_for_an_unassigned_office_never_adds_it(): void
    {
        $ips = Mockery::mock(SitraIpsClient::class);
        $ips->shouldReceive('package')->once()->with('PACKAGE20', 17)->andReturn(['package' => $this->package(20)]);
        $this->expectException(HttpException::class);
        app(PaquetesIpsController::class)->addInternationalSelection($this->request(['codigo' => 'PACKAGE20']), $ips, 'recepcion-oficina');
    }

    public function test_a_new_l_code_requires_the_actual_receiving_office_to_be_selected(): void
    {
        $ips = Mockery::mock(SitraIpsClient::class);
        $ips->shouldNotReceive('createPackage');
        try {
            app(PaquetesIpsController::class)->createDestinationPackage($this->request([
                'codigo' => 'LW945318163DE', 'idempotency_key' => 'new-group-letter-01',
            ]), $ips, Mockery::mock(SitraTrackingEventsClient::class));
            $this->fail('Debe seleccionar la oficina de recepción.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('office_cd', $exception->errors());
        }
    }

    public function test_uncertain_letter_creation_in_a_secondary_office_is_visible_in_the_shared_view(): void
    {
        $request = $this->request(['q' => 'LW945318163DE']);
        $request->headers->set('Accept', 'text/html');
        $pending = ['codigo' => 'LW945318163DE', 'office_cd' => 18, 'idempotency_key' => 'pending-ems-letter-01'];
        $request->session()->put('ips_destination_create_pending:7001_office_18:LW945318163DE', $pending);
        $ips = Mockery::mock(SitraIpsClient::class);
        $ips->shouldReceive('package')->once()->with('LW945318163DE', 17)->andThrow((new Response(new \GuzzleHttp\Psr7\Response(404)))->toException());
        $ips->shouldReceive('deliveryCatalog')->andReturn([]);
        $ips->shouldReceive('catalog')->andReturn([]);
        $view = app(PaquetesIpsController::class)->index($request, $ips, 'recepcion-oficina');
        $this->assertSame($pending, $view->getData()['inboundPending']);
        $html = $view->with('errors', new \Illuminate\Support\ViewErrorBag)->render();
        $this->assertStringContainsString('name="office_cd" value="18"', $html);
        $this->assertStringContainsString('pending-ems-letter-01', $html);
    }
}

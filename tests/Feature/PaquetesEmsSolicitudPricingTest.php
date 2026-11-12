<?php

namespace Tests\Feature;

use App\Http\Controllers\PaquetesEmsController;
use App\Models\SolicitudCliente;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Mockery;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class PaquetesEmsSolicitudPricingTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        foreach (['clientes', 'servicio', 'origen', 'destino', 'servicio_extras', 'estados', 'app_settings'] as $name) {
            Schema::create($name, function (Blueprint $table) use ($name): void {
                $table->id();
                $columns = match ($name) {
                    'origen' => ['nombre_origen'],
                    'destino' => ['nombre_destino'],
                    'servicio_extras' => ['nombre', 'descripcion'],
                    'estados' => ['nombre_estado'],
                    'app_settings' => ['key', 'value'],
                    default => [],
                };
                foreach ($columns as $column) {
                    $table->string($column)->nullable();
                }
            });
        }
        Schema::create('tarifario_tiktoker', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('origen_id');
            $table->unsignedBigInteger('destino_id');
            $table->unsignedBigInteger('servicio_extra_id');
            $table->decimal('peso1', 10, 2);
            $table->integer('tiempo_entrega');
        });
        (require database_path('migrations/2026_03_17_210000_create_solicitud_clientes_table.php'))->up();
        Schema::table('solicitud_clientes', function (Blueprint $table): void {
            $table->string('forma_pago')->nullable();
            $table->string('tipo_pago')->nullable();
            $table->decimal('recargo_pago_destino', 10, 2)->default(0);
            $table->boolean('recargo_volumen')->default(false);
        });

        DB::table('origen')->insert(['id' => 1, 'nombre_origen' => 'LA PAZ']);
        DB::table('destino')->insert(['id' => 1, 'nombre_destino' => 'COCHABAMBA']);
        DB::table('servicio_extras')->insert(['id' => 1, 'nombre' => 'Puerta a puerta', 'descripcion' => 'Puerta a puerta']);
        DB::table('estados')->insert([
            ['id' => 1, 'nombre_estado' => 'SOLICITUD'],
            ['id' => 2, 'nombre_estado' => 'ALMACEN'],
        ]);
        DB::table('tarifario_tiktoker')->insert([
            'id' => 1, 'origen_id' => 1, 'destino_id' => 1,
            'servicio_extra_id' => 1, 'peso1' => 20, 'tiempo_entrega' => 24,
        ]);
    }

    public static function weights(): array
    {
        return [
            'minimum' => [0.001, '20.00', '0.00'],
            'below threshold' => [2.999, '20.00', '0.00'],
            'three kilos' => [3.000, '20.00', '0.00'],
            'first extra gram' => [3.001, '30.00', '10.00'],
            '3500 grams' => [3.500, '30.00', '10.00'],
            'four kilos' => [4.000, '30.00', '10.00'],
            'second extra kilo' => [4.001, '40.00', '20.00'],
            '4500 grams' => [4.500, '40.00', '20.00'],
            'five kilos' => [5.000, '40.00', '20.00'],
            'third extra kilo' => [5.001, '50.00', '30.00'],
        ];
    }

    #[DataProvider('weights')]
    public function test_quote_charges_each_started_kilo_after_three(float $weight, string $price, string $surcharge): void
    {
        $response = app(PaquetesEmsController::class)->quoteSolicitud($this->request(['peso' => $weight]));
        $payload = $response->getData(true);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame($price, $payload['precio']);
        $this->assertSame($surcharge, $payload['recargo_peso']);
        $this->assertSame('20.00', $payload['precio_base']);
    }

    public function test_save_recalculates_weight_and_other_surcharges_and_provides_pdf_download(): void
    {
        $request = $this->request([
            'peso' => 4.500, 'forma_pago' => 'destino', 'recargo_volumen' => 1,
            'precio_referencial' => '1.00', 'cantidad' => 1, 'contenido' => 'Ropa',
            'nombre_remitente' => 'Remitente', 'carnet' => '123456',
            'nombre_destinatario' => 'Destinatario',
            'direccion_recojo' => 'Calle 1', 'direccion_entrega' => 'Calle 2',
        ]);
        $controller = app(PaquetesEmsController::class);
        $quote = $controller->quoteSolicitud($request)->getData(true);
        $response = $controller->storeSolicitud($request);
        $solicitud = SolicitudCliente::query()->sole();

        $this->assertSame('42.50', $solicitud->precio);
        $this->assertFalse($solicitud->recargo_volumen);
        $this->assertSame('destino', $solicitud->forma_pago);
        $this->assertNull($solicitud->tipo_pago);
        $this->assertSame($quote['precio'], $solicitud->precio);
        $this->assertSame(2, (int) $solicitud->estado_id);
        $this->assertSame(route('paquetes-ems.solicitudes.index'), $response->getTargetUrl());
        $this->assertSame(
            route('paquetes-ems.solicitudes.ticket', ['solicitud' => $solicitud, 'download' => 1]),
            session('solicitud_ticket_url')
        );

        $pdf = $controller->ticketSolicitud($this->request(['download' => 1]), $solicitud);
        $this->assertSame('application/pdf', $pdf->headers->get('Content-Type'));
        $this->assertStringContainsString('attachment;', $pdf->headers->get('Content-Disposition'));
        $this->assertStringContainsString('solicitud-'.$solicitud->codigo_solicitud.'.pdf', $pdf->headers->get('Content-Disposition'));
        $this->assertStringStartsWith('%PDF-', $pdf->getContent());

        $view = $controller->ticketSolicitud($this->request(), $solicitud);
        $this->assertSame('paquetes_ems.solicitud-ticket', $view->name());
        $this->assertStringContainsString(
            'Si el peso del paquete supera los 3 kg, se le cobrar&aacute;n 10 Bs por cada kilo extra o fracci&oacute;n.',
            $view->render()
        );
    }

    public function test_search_uses_saved_price_instead_of_tariff_price(): void
    {
        $solicitud = $this->createPendingSolicitud(30);
        $request = $this->request();
        $request->headers->set('Accept', 'application/json');
        $request->query->set('q', $solicitud->codigo_solicitud);

        $payload = app(PaquetesEmsController::class)->indexSolicitudes($request)->getData(true);

        $this->assertCount(1, $payload['data']);
        $this->assertSame(30, (int) $payload['data'][0]['precio_guardado']);
    }

    public static function extraKilos(): array
    {
        return [[0, '30.00'], [1, '40.00'], [2, '50.00'], [3, '60.00'], [4, '70.00']];
    }

    #[DataProvider('extraKilos')]
    public function test_sending_to_warehouse_adds_extra_kilos_to_saved_weight_and_price_only_once(int $extraKilos, string $expected): void
    {
        $solicitud = $this->createPendingSolicitud(30);
        $request = $this->request([
            'solicitud_ids' => [$solicitud->id],
            'kilos_extras' => [$solicitud->id => $extraKilos],
            'precio' => '1.00',
        ]);
        $controller = app(PaquetesEmsController::class);

        $controller->sendSolicitudesToAlmacen($request);
        $this->assertSame($expected, $solicitud->refresh()->precio);
        $this->assertSame(number_format(3.500 + $extraKilos, 3, '.', ''), $solicitud->peso);
        $this->assertSame(2, (int) $solicitud->estado_id);
        $this->assertSame(1, (int) $solicitud->tarifario_tiktoker_id);
        $this->assertSame(
            route('paquetes-ems.solicitudes.tickets', ['solicitud_ids' => [$solicitud->id]]),
            session('solicitud_ticket_url')
        );

        session()->forget('solicitud_ticket_url');

        $controller->sendSolicitudesToAlmacen($request);
        $this->assertSame($expected, $solicitud->refresh()->precio);
        $this->assertSame(number_format(3.500 + $extraKilos, 3, '.', ''), $solicitud->peso);
        $this->assertNull(session('solicitud_ticket_url'));
    }

    public function test_sending_without_extra_kilos_preserves_all_saved_surcharges(): void
    {
        $solicitud = $this->createPendingSolicitud(42.50);
        app(PaquetesEmsController::class)->sendSolicitudesToAlmacen($this->request([
            'solicitud_ids' => [$solicitud->id],
        ]));

        $this->assertSame('42.50', $solicitud->refresh()->precio);
        $this->assertSame('3.500', $solicitud->peso);
    }

    public static function invalidExtraKilos(): array
    {
        return [[-1], [1.5], ['abc']];
    }

    #[DataProvider('invalidExtraKilos')]
    public function test_extra_kilos_must_be_non_negative_integers(mixed $value): void
    {
        $solicitud = $this->createPendingSolicitud(30);

        try {
            app(PaquetesEmsController::class)->sendSolicitudesToAlmacen($this->request([
                'solicitud_ids' => [$solicitud->id],
                'kilos_extras' => [$solicitud->id => $value],
            ]));
            $this->fail('Los kilos extras invalidos deben rechazarse.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('kilos_extras.'.$solicitud->id, $exception->errors());
            $this->assertSame('30.00', $solicitud->refresh()->precio);
            $this->assertSame('3.500', $solicitud->peso);
            $this->assertSame(1, (int) $solicitud->estado_id);
        }
    }

    public function test_warehouse_pdf_contains_only_updated_tickets_with_saved_weight_and_final_price(): void
    {
        $first = $this->createPendingSolicitud(30);
        $second = $first->replicate();
        $second->fill(['codigo_solicitud' => 'SL00000147LP', 'barcode' => 'SL00000147LP', 'peso' => 4.250, 'precio' => 40]);
        $second->save();
        $alreadySent = $first->replicate();
        $alreadySent->fill(['codigo_solicitud' => 'SL00000148LP', 'barcode' => 'SL00000148LP', 'estado_id' => 2]);
        $alreadySent->save();
        $controller = app(PaquetesEmsController::class);
        $controller->sendSolicitudesToAlmacen($this->request([
            'solicitud_ids' => [$first->id, $second->id, $alreadySent->id],
            'kilos_extras' => [$first->id => 2, $second->id => 1],
        ]));
        $this->assertSame(
            route('paquetes-ems.solicitudes.tickets', ['solicitud_ids' => [$first->id, $second->id]]),
            session('solicitud_ticket_url')
        );

        $pdf = $controller->ticketsSolicitudes($this->request([
            'solicitud_ids' => [$first->id, $second->id],
        ]));
        $this->assertSame('application/pdf', $pdf->headers->get('Content-Type'));
        $this->assertStringContainsString('attachment;', $pdf->headers->get('Content-Disposition'));
        $this->assertStringContainsString('solicitudes-almacen.pdf', $pdf->headers->get('Content-Disposition'));

        $document = (new \Smalot\PdfParser\Parser())->parseContent($pdf->getContent());
        $this->assertCount(2, $document->getPages());
        $text = $document->getText();
        $this->assertStringContainsString($first->codigo_solicitud, $text);
        $this->assertStringContainsString($second->codigo_solicitud, $text);
        $this->assertStringNotContainsString($alreadySent->codigo_solicitud, $text);
        $this->assertStringContainsString('5,500 kg', $text);
        $this->assertStringContainsString('5,250 kg', $text);
        $this->assertSame('3.500', $alreadySent->refresh()->peso);
        $this->assertStringContainsString('Bs 50,00', $text);
    }

    public function test_warehouse_operator_can_download_one_updated_ticket(): void
    {
        $solicitud = $this->createPendingSolicitud(30);
        $controller = app(PaquetesEmsController::class);
        $controller->sendSolicitudesToAlmacen($this->request([
            'solicitud_ids' => [$solicitud->id],
            'kilos_extras' => [$solicitud->id => 1],
        ]));
        $request = $this->request(['solicitud_ids' => [$solicitud->id]]);
        $user = Mockery::mock(User::class)->makePartial();
        $user->shouldReceive('can')->andReturnUsing(fn ($permission) => $permission === 'feature.paquetes-ems.solicitudes.index.assign');
        $request->setUserResolver(fn () => $user);

        $pdf = $controller->ticketsSolicitudes($request);
        $this->assertStringContainsString('solicitud-'.$solicitud->codigo_solicitud.'.pdf', $pdf->headers->get('Content-Disposition'));
        $document = (new \Smalot\PdfParser\Parser())->parseContent($pdf->getContent());
        $this->assertCount(1, $document->getPages());
        $this->assertStringContainsString('4,500 kg', $document->getText());
        $this->assertStringContainsString('Bs 40,00', $document->getText());
        $this->assertContains(
            'feature.paquetes-ems.solicitudes.index.assign',
            \App\Support\AclPermissionRegistry::authorizationPermissionsForRouteAccess('paquetes-ems.solicitudes.tickets')
        );
    }

    public function test_updated_tickets_require_permission(): void
    {
        $request = $this->request(['solicitud_ids' => [1]]);
        $user = Mockery::mock(User::class)->makePartial();
        $user->shouldReceive('can')->andReturn(false);
        $request->setUserResolver(fn () => $user);

        $this->expectException(\Symfony\Component\HttpKernel\Exception\HttpException::class);
        app(PaquetesEmsController::class)->ticketsSolicitudes($request);
    }

    public function test_cash_payment_at_origin_is_saved_as_paid_without_qr_verification(): void
    {
        \Illuminate\Support\Facades\Http::preventStrayRequests();
        $controller = app(PaquetesEmsController::class);
        $controller->storeSolicitud($this->request([
            'cantidad' => 1, 'contenido' => 'Ropa', 'nombre_remitente' => 'Remitente',
            'carnet' => '123456', 'nombre_destinatario' => 'Destinatario',
            'direccion_recojo' => 'Calle 1', 'direccion_entrega' => 'Calle 2',
        ]));
        $solicitud = SolicitudCliente::query()->sole();

        $this->assertSame('pagado', $solicitud->forma_pago);
        $this->assertSame('efectivo', $solicitud->tipo_pago);
        $this->assertSame('Pagado', $solicitud->forma_pago_label);
        $this->assertFalse($solicitud->pago_destinatario);
        $this->assertSame('0.00', $solicitud->recargo_pago_destino);
        $this->assertSame('30.00', $solicitud->precio);

        $pdf = $controller->ticketSolicitud($this->request(['download' => 1]), $solicitud);
        $text = (new \Smalot\PdfParser\Parser())->parseContent($pdf->getContent())->getText();
        $this->assertStringContainsString('PAGADO', $text);
        $this->assertStringContainsString('Efectivo', $text);

        $lookup = $this->request();
        $lookup->query->set('codigo_solicitud', $solicitud->codigo_solicitud);
        $payload = $controller->findSolicitud($lookup)->getData(true);
        $this->assertSame('pagado', $payload['forma_pago']);
        $this->assertSame('efectivo', $payload['tipo_pago']);
    }

    public static function invalidCashPaymentTypes(): array
    {
        return [[null], ['tarjeta']];
    }

    #[DataProvider('invalidCashPaymentTypes')]
    public function test_origin_payment_requires_a_supported_payment_type(mixed $type): void
    {
        $this->expectException(ValidationException::class);
        app(PaquetesEmsController::class)->storeSolicitud($this->request([
            'tipo_pago' => $type,
            'cantidad' => 1, 'contenido' => 'Ropa', 'nombre_remitente' => 'Remitente',
            'carnet' => '123456', 'nombre_destinatario' => 'Destinatario',
            'direccion_recojo' => 'Calle 1', 'direccion_entrega' => 'Calle 2',
        ]));
    }

    public static function qrPaymentStates(): array
    {
        return [
            'pending QR stays pending' => ['origen', 'qr', 'origen'],
            'confirmed QR stays paid' => ['pagado', 'qr', 'pagado'],
            'switching cash to QR requires confirmation' => ['pagado', 'efectivo', 'origen'],
        ];
    }

    #[DataProvider('qrPaymentStates')]
    public function test_qr_selection_preserves_only_previously_confirmed_qr_payment(string $originalStatus, string $originalType, string $expectedStatus): void
    {
        \Illuminate\Support\Facades\Http::preventStrayRequests();
        $solicitud = $this->createPendingSolicitud(30);
        $solicitud->update(['forma_pago' => $originalStatus, 'tipo_pago' => $originalType]);
        $controller = app(PaquetesEmsController::class);
        $controller->storeSolicitud($this->request([
            'solicitud_id' => $solicitud->id, 'tipo_pago' => 'qr',
            'cantidad' => 1, 'contenido' => 'Ropa', 'nombre_remitente' => 'Remitente',
            'carnet' => '123456', 'nombre_destinatario' => 'Destinatario',
            'direccion_recojo' => 'Calle 1', 'direccion_entrega' => 'Calle 2',
        ]));

        $this->assertSame($expectedStatus, $solicitud->refresh()->forma_pago);
        $this->assertSame('qr', $solicitud->tipo_pago);
        $this->assertSame('30.00', $solicitud->precio);
        $this->assertSame('0.00', $solicitud->recargo_pago_destino);
        $this->assertStringContainsString('>QR</span>', $controller->ticketSolicitud($this->request(), $solicitud)->render());
    }

    private function createPendingSolicitud(float $price): SolicitudCliente
    {
        app(PaquetesEmsController::class)->storeSolicitud($this->request([
            'cantidad' => 1, 'contenido' => 'Ropa', 'nombre_remitente' => 'Remitente',
            'carnet' => '123456', 'nombre_destinatario' => 'Destinatario',
            'direccion_recojo' => 'Calle 1', 'direccion_entrega' => 'Calle 2',
        ]));
        $solicitud = SolicitudCliente::query()->sole();
        $solicitud->update(['estado_id' => 1, 'precio' => $price]);

        return $solicitud;
    }

    private function request(array $data = []): Request
    {
        $request = Request::create('/paquetes-ems/solicitudes', 'POST', array_merge([
            'servicio_extra_id' => 1, 'origen' => 'LA PAZ', 'destino_id' => 1,
            'peso' => 3.500, 'forma_pago' => 'origen',
            'tipo_pago' => ($data['forma_pago'] ?? 'origen') === 'origen' ? 'efectivo' : null,
        ], $data));
        $user = Mockery::mock(User::class)->makePartial();
        $user->shouldReceive('can')->andReturnUsing(fn ($permission) => in_array($permission, [
            'feature.paquetes-ems.solicitudes.index.create',
            'feature.paquetes-ems.solicitudes.index.assign',
            'paquetes-ems.solicitudes.index',
        ], true));
        $request->setUserResolver(fn () => $user);

        return $request;
    }
}

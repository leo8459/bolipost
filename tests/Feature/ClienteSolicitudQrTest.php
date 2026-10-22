<?php

namespace Tests\Feature;

use App\Models\Cliente;
use App\Models\ClienteSolicitudQr;
use App\Models\SolicitudCliente;
use App\Models\Destino;
use App\Models\ServicioExtra;
use App\Services\ClienteSolicitudFacturacionService;
use App\Services\ClienteSolicitudQrService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\TestCase;

class ClienteSolicitudQrTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:', 'cache.default' => 'array']);
        DB::purge('sqlite');
        config(['services.facturacion_bridge.base_url' => 'https://facturacion.test', 'services.facturacion_bridge.token' => 'test']);
        Http::preventStrayRequests();
        $this->travelTo(now()->startOfSecond());
        Schema::create('clientes', function (Blueprint $table) {
            $table->id();
        });
        Schema::create('estados', function (Blueprint $table) {
            $table->id();
            $table->string('nombre_estado');
        });
        Schema::create('solicitud_clientes', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('cliente_id');
            $table->unsignedBigInteger('estado_id');
            $table->string('codigo_solicitud')->nullable();
            $table->string('forma_pago');
            $table->text('justificacion')->nullable();
            $table->timestamps();
        });
        (require database_path('migrations/2026_10_22_150000_create_cliente_solicitud_qrs_table.php'))->up();
        DB::table('clientes')->insert(['id' => 1]);
        DB::table('estados')->insert([['id' => 1, 'nombre_estado' => 'SOLICITUD'], ['id' => 2, 'nombre_estado' => 'CANCELADO']]);
    }

    private function qr(int $ageHours = 48, string $formaPago = 'origen', bool $withSolicitud = true): ClienteSolicitudQr
    {
        $solicitud = $withSolicitud
            ? SolicitudCliente::create(['cliente_id' => 1, 'estado_id' => 1, 'forma_pago' => $formaPago])
            : null;

        return ClienteSolicitudQr::create([
            'attempt_id' => (string) Str::uuid(), 'cliente_id' => 1, 'solicitud_id' => $solicitud?->id,
            'cart_id' => 123, 'internal_code' => 'TEST-QR', 'qr_image' => 'test-image',
            'solicitud_data' => [], 'amount' => 20,
            'generated_at' => now()->subHours($ageHours), 'expires_at' => now()->subHours($ageHours)->addHours(48),
        ]);
    }

    private function payment(string $status): void
    {
        Http::fake(['facturacion.test/cart/consultar' => Http::response(['ok' => true, 'cart' => ['id' => 123, 'estado_pago' => $status]])]);
    }

    public function test_unpaid_request_is_cancelled_at_exactly_48_hours(): void
    {
        $qr = $this->qr();
        $this->payment('pendiente');
        app(ClienteSolicitudQrService::class)->sync($qr);
        $this->assertSame('cancelled', $qr->fresh()->status);
        $this->assertSame(2, $qr->solicitud->estado_id);
        Http::assertSent(fn ($request) => $request['cart_id'] === 123 && $request['origen_usuario_id'] === 'cliente_online_1');
    }

    public function test_request_is_not_cancelled_before_deadline(): void
    {
        $qr = $this->qr(47);
        $this->payment('pending');
        app(ClienteSolicitudQrService::class)->sync($qr);
        $this->assertSame('pending', $qr->fresh()->status);
        $this->assertSame(1, $qr->solicitud->estado_id);
    }

    public function test_confirmed_payment_prevents_cancellation_after_deadline(): void
    {
        $qr = $this->qr(49);
        $this->payment('paid');
        app(ClienteSolicitudQrService::class)->sync($qr);
        $this->assertSame('paid', $qr->fresh()->status);
        $this->assertSame('pagado', $qr->solicitud->forma_pago);
        $this->assertSame(1, $qr->solicitud->estado_id);
        app(ClienteSolicitudQrService::class)->sync($qr);
        Http::assertSentCount(1);
    }

    public function test_unknown_status_does_not_cancel_request(): void
    {
        $qr = $this->qr(49);
        $this->payment('unknown');
        try {
            app(ClienteSolicitudQrService::class)->sync($qr);
            $this->fail('An unknown payment status must be retried.');
        } catch (\RuntimeException $exception) {
            $this->assertSame('pending', $qr->fresh()->status);
            $this->assertSame(1, $qr->solicitud->estado_id);
        }
    }

    public function test_unavailable_payment_service_does_not_cancel_request(): void
    {
        $qr = $this->qr(49);
        Http::fake(['*' => Http::response(['ok' => false], 503)]);
        $this->artisan('clientes:sync-qr')->assertFailed();
        $this->assertSame('pending', $qr->fresh()->status);
        $this->assertSame(1, $qr->solicitud->estado_id);
    }

    public function test_destination_payment_is_not_cancelled(): void
    {
        $qr = $this->qr(49, 'destino');
        $this->payment('pending');
        app(ClienteSolicitudQrService::class)->sync($qr);
        $this->assertSame(1, $qr->solicitud->estado_id);
    }

    public function test_expired_unregistered_qr_is_cancelled_without_creating_a_shipment(): void
    {
        $qr = $this->qr(48, 'origen', false);
        $this->payment('pending');
        app(ClienteSolicitudQrService::class)->sync($qr);
        $this->assertSame('cancelled', $qr->fresh()->status);
        $this->assertNull($qr->fresh()->solicitud_id);
        $this->assertSame(0, SolicitudCliente::count());
    }

    public function test_generated_qr_is_saved_and_retry_reuses_same_qr_and_expiry(): void
    {
        Schema::create('sucursales', function (Blueprint $table) {
            $table->id();
            $table->string('municipio');
            $table->integer('codigoSucursal');
            $table->integer('puntoVenta');
        });
        DB::table('sucursales')->insert(['municipio' => 'LA PAZ', 'codigoSucursal' => 1, 'puntoVenta' => 1]);
        Http::fake([
            'facturacion.test/cart/cliente-online' => Http::response(['ok' => true, 'cart' => null]),
            'facturacion.test/cart/items/upsert' => Http::response(['ok' => true, 'cart' => ['id' => 123]]),
            'facturacion.test/cart/billing' => Http::response(['ok' => true]),
            'facturacion.test/cart/emitir' => Http::response(['ok' => true, 'respuesta' => ['image_data' => 'original-image', 'internal_code' => 'original-code']]),
        ]);
        $cliente = new Cliente;
        $cliente->id = 1;
        $servicio = new ServicioExtra(['nombre' => 'Puerta a puerta']);
        $destino = new Destino(['nombre_destino' => 'ORURO']);
        $data = [
            'qr_attempt_id' => (string) Str::uuid(), 'origen' => 'LA PAZ', 'cantidad' => 1,
            'nombre_destinatario' => 'Destino', 'contenido' => 'Documento', 'nombre_remitente' => 'Cliente',
            'carnet' => '1234567', 'direccion_recojo' => 'Origen', 'direccion_entrega' => 'Destino',
            'tipo_documento' => '1', 'numero_documento' => '1234567', 'razon_social' => 'Cliente', 'correo_facturacion' => 'test@example.com',
        ];
        $service = app(ClienteSolicitudFacturacionService::class);
        $first = $service->generarQr($cliente, $data, $servicio, $destino, 20);
        $this->travel(1)->hours();
        $retry = $service->generarQr($cliente, $data, $servicio, $destino, 20);
        $this->assertSame($first['id'], $retry['id']);
        $this->assertSame($first['expires_at'], $retry['expires_at']);
        $this->assertSame('original-image', $retry['qr_image']);
        $qr = ClienteSolicitudQr::firstOrFail();
        $this->assertEquals(48, $qr->generated_at->diffInHours($qr->expires_at));
        Http::assertSentCount(4);
    }
}

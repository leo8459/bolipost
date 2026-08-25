<?php

namespace Tests\Feature;

use App\Http\Controllers\DashboardController;
use App\Models\Role;
use App\Models\SolicitudCliente;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use ReflectionMethod;
use Tests\TestCase;

class DeliveryExpressPickupAlertTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::dropIfExists('solicitud_clientes');
        Schema::create('solicitud_clientes', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('estado_id');
            $table->string('codigo_solicitud')->nullable();
            $table->string('barcode')->nullable();
            $table->string('origen')->nullable();
            $table->string('direccion_recojo')->nullable();
            $table->string('nombre_remitente')->nullable();
            $table->string('telefono_remitente')->nullable();
            $table->string('contenido')->nullable();
            $table->unsignedInteger('cantidad')->nullable();
            $table->unsignedBigInteger('servicio_extra_id')->nullable();
            $table->timestamps();
        });
    }

    protected function tearDown(): void
    {
        Schema::dropIfExists('solicitud_clientes');

        parent::tearDown();
    }

    public function test_alert_only_contains_pending_requests_from_the_logged_in_users_department(): void
    {
        SolicitudCliente::query()->insert([
            $this->requestRow('LP-001', ' la paz ', 28),
            $this->requestRow('LP-002', 'LA PAZ', 28),
            $this->requestRow('CBBA-001', 'COCHABAMBA', 28),
            $this->requestRow('LP-CLOSED', 'LA PAZ', 30),
        ]);

        $user = new User([
            'ciudad' => 'LA PAZ',
            'regionales' => ['LA PAZ'],
        ]);
        $user->setRelation('roles', collect());

        $alert = $this->invokePickupAlert($user);

        $this->assertSame(2, $alert['count']);
        $this->assertSame('LA PAZ', $alert['scope_label']);
        $this->assertSame(['LA PAZ'], $alert['departments']->pluck('departamento')->all());
        $this->assertEqualsCanonicalizing(
            ['LP-001', 'LP-002'],
            $alert['requests']->pluck('codigo_solicitud')->all()
        );
    }

    public function test_delivery_express_role_receives_the_national_pickup_modal(): void
    {
        SolicitudCliente::query()->insert([
            $this->requestRow('LP-001', 'LA PAZ', 28),
            $this->requestRow('CBBA-001', 'COCHABAMBA', 28),
            $this->requestRow('CLOSED', 'SANTA CRUZ', 30),
        ]);

        $user = new User([
            'ciudad' => 'LA PAZ',
            'regionales' => ['LA PAZ'],
        ]);
        $user->setRelation('roles', collect([
            new Role(['name' => 'delivery_express', 'guard_name' => 'web']),
        ]));

        $alert = $this->invokePickupAlert($user);

        $this->assertSame(2, $alert['count']);
        $this->assertTrue($alert['is_national']);
        $this->assertSame('NIVEL NACIONAL', $alert['scope_label']);
        $this->assertEqualsCanonicalizing(
            ['LA PAZ', 'COCHABAMBA'],
            $alert['departments']->pluck('departamento')->all()
        );
        $this->assertEqualsCanonicalizing(
            ['LP-001', 'CBBA-001'],
            $alert['requests']->pluck('codigo_solicitud')->all()
        );
    }

    private function invokePickupAlert(User $user): array
    {
        $method = new ReflectionMethod(DashboardController::class, 'buildDeliveryExpressPickupAlert');

        return $method->invoke(
            app(DashboardController::class),
            28,
            $user,
            false,
            'LA PAZ'
        );
    }

    private function requestRow(string $code, string $origin, int $stateId): array
    {
        return [
            'estado_id' => $stateId,
            'codigo_solicitud' => $code,
            'origen' => $origin,
            'created_at' => now(),
            'updated_at' => now(),
        ];
    }
}

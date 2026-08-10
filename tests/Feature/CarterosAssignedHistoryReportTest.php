<?php

namespace Tests\Feature;

use App\Http\Controllers\CarterosController;
use App\Models\User;
use App\Support\AclPermissionRegistry;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class CarterosAssignedHistoryReportTest extends TestCase
{
    private const EVENT_TABLES = [
        'eventos_ems',
        'eventos_certi',
        'eventos_ordi',
        'eventos_contrato',
        'eventos_tiktoker',
    ];

    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('eventos', function (Blueprint $table): void {
            $table->id();
            $table->string('nombre_evento');
            $table->timestamps();
        });

        foreach (self::EVENT_TABLES as $tableName) {
            Schema::create($tableName, function (Blueprint $table): void {
                $table->id();
                $table->string('codigo');
                $table->unsignedBigInteger('evento_id');
                $table->unsignedBigInteger('user_id')->nullable();
                $table->timestamps();
            });
        }
    }

    protected function tearDown(): void
    {
        foreach (array_reverse(self::EVENT_TABLES) as $tableName) {
            Schema::dropIfExists($tableName);
        }
        Schema::dropIfExists('eventos');

        parent::tearDown();
    }

    public function test_report_uses_assignment_events_and_keeps_historical_packages(): void
    {
        $assignedEvent = $this->event('Paquete en camino para entrega fisica. Asignado a CARTERO por Supervisor a Juan Perez.');
        $legacyAssignedEvent = $this->event('Paquete en camino para entrega fisica.');
        $changedEvent = $this->event('Cambio de cartero realizado por Supervisor. De Maria Rojas a Juan Perez.');
        $otherCarteroEvent = $this->event('Paquete en camino para entrega fisica. Asignado a CARTERO por Supervisor a Otra Persona.');
        $deliveryEvent = $this->event('Paquete entregado exitosamente.');

        $this->packageEvent('eventos_ems', 'EMS-001', $assignedEvent, 25, '2026-08-05 08:00:00');
        $this->packageEvent('eventos_certi', 'CERTI-001', $changedEvent, 99, '2026-08-05 09:00:00');
        $this->packageEvent('eventos_ordi', 'ORDI-LEGACY', $legacyAssignedEvent, 25, '2026-08-05 09:30:00');
        $this->packageEvent('eventos_ems', 'EMS-001', $assignedEvent, 25, '2026-08-05 10:00:00');
        $this->packageEvent('eventos_ordi', 'ORDI-OTRO', $otherCarteroEvent, 88, '2026-08-05 11:00:00');
        $this->packageEvent('eventos_contrato', 'CONT-ENTREGADO', $deliveryEvent, 25, '2026-08-05 12:00:00');
        $this->packageEvent('eventos_tiktoker', 'SOL-FUERA', $assignedEvent, 25, '2026-08-07 08:00:00');

        $controller = new class extends CarterosController {
            public function rows(User $cartero, Carbon $desde, Carbon $hasta)
            {
                return $this->historicalAssignmentEvents($cartero, $desde, $hasta);
            }
        };

        $cartero = new User(['name' => 'Juan Perez']);
        $cartero->id = 25;

        $rows = $controller->rows(
            $cartero,
            Carbon::parse('2026-08-05 00:00:00'),
            Carbon::parse('2026-08-06 23:59:59'),
        );

        $this->assertSame(['EMS-001', 'CERTI-001', 'ORDI-LEGACY'], $rows->pluck('codigo')->all());
        $this->assertSame(['EMS', 'CERTI', 'ORDI'], $rows->pluck('tipo_paquete')->all());
    }

    public function test_report_button_permission_is_available_for_role_assignment(): void
    {
        $this->assertContains(
            'feature.carteros.asignados.report',
            AclPermissionRegistry::allPermissionNames(),
        );
    }

    private function event(string $name): int
    {
        return (int) DB::table('eventos')->insertGetId([
            'nombre_evento' => $name,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function packageEvent(string $table, string $code, int $eventId, int $userId, string $createdAt): void
    {
        DB::table($table)->insert([
            'codigo' => $code,
            'evento_id' => $eventId,
            'user_id' => $userId,
            'created_at' => $createdAt,
            'updated_at' => $createdAt,
        ]);
    }
}

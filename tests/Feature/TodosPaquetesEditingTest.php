<?php

namespace Tests\Feature;

use App\Http\Controllers\TodosPaquetesController;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class TodosPaquetesEditingTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('paquetes_ems', function (Blueprint $table): void {
            $table->id();
            $table->string('codigo')->unique();
            $table->string('origen')->nullable();
            $table->string('ciudad')->nullable();
            $table->decimal('precio', 10, 2)->nullable();
            $table->timestamps();
        });

        Schema::create('paquetes_contrato', function (Blueprint $table): void {
            $table->id();
            $table->string('codigo')->unique();
            $table->dateTime('fecha_recojo')->nullable();
            $table->timestamps();
        });
        DB::table('paquetes_contrato')->insert([
            'id' => 1,
            'codigo' => 'CONTRATO-EDIT-1',
            'fecha_recojo' => null,
        ]);

        DB::table('paquetes_ems')->insert([
            [
                'id' => 1,
                'codigo' => 'EMS-EDIT-1',
                'origen' => 'CHUQUISACA',
                'ciudad' => 'BENI',
                'precio' => 10,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'id' => 2,
                'codigo' => 'EMS-EDIT-2',
                'origen' => 'PANDO',
                'ciudad' => 'LA PAZ',
                'precio' => 20,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
    }

    protected function tearDown(): void
    {
        Schema::dropIfExists('paquetes_ems');
        Schema::dropIfExists('paquetes_contrato');
        Schema::dropIfExists('solicitud_clientes');

        parent::tearDown();
    }

    public function test_edit_accepts_decimal_comma_and_saves_capital_city_names(): void
    {
        $request = Request::create('/todos-paquetes/ems/1/datos', 'PUT', [
            'codigo' => 'EMS-EDIT-1',
            'origen' => 'CHUQUISACA',
            'ciudad' => 'BENI',
            'precio' => '32,00',
        ]);

        (new TodosPaquetesController())->updateDatos($request, 'ems', 1);

        $row = DB::table('paquetes_ems')->where('id', 1)->first();

        $this->assertSame('SUCRE', $row->origen);
        $this->assertSame('TRINIDAD', $row->ciudad);
        $this->assertSame(32.0, (float) $row->precio);
    }

    public function test_edit_reports_duplicate_code_as_validation_error(): void
    {
        $request = Request::create('/todos-paquetes/ems/1/datos', 'PUT', [
            'codigo' => 'EMS-EDIT-2',
        ]);

        try {
            (new TodosPaquetesController())->updateDatos($request, 'ems', 1);
            $this->fail('The duplicate code should not reach the database update.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('codigo', $exception->errors());
        }
    }

    public function test_edit_can_complete_pickup_date_and_preserves_it_when_omitted(): void
    {
        $controller = new TodosPaquetesController();
        $controller->updateDatos(Request::create('/todos-paquetes/contrato/1/datos', 'PUT', [
            'fecha_recojo' => '2026-09-16T14:35:12',
        ]), 'contrato', 1);

        $controller->updateDatos(Request::create('/todos-paquetes/contrato/1/datos', 'PUT', [
            'codigo' => 'CONTRATO-EDIT-1',
        ]), 'contrato', 1);

        $this->assertSame('2026-09-16 14:35:12', DB::table('paquetes_contrato')->value('fecha_recojo'));

        $resolveEditing = new \ReflectionMethod($controller, 'resolveEditing');
        $editing = $resolveEditing->invoke($controller, Request::create('/todos-paquetes?edit_type=contrato&edit_id=1'));
        $this->assertSame('2026-09-16T14:35:12', $editing['values']['fecha_recojo']);
    }

    public function test_edit_rejects_invalid_pickup_date(): void
    {
        try {
            (new TodosPaquetesController())->updateDatos(Request::create('/todos-paquetes/contrato/1/datos', 'PUT', [
                'fecha_recojo' => 'no-es-fecha',
            ]), 'contrato', 1);
            $this->fail('An invalid pickup date should be rejected.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('fecha_recojo', $exception->errors());
            $this->assertNull(DB::table('paquetes_contrato')->value('fecha_recojo'));
        }
    }

    public function test_solicitud_reprint_returns_ticket_from_the_packages_view(): void
    {
        Schema::create('solicitud_clientes', function (Blueprint $table): void {
            $table->id();
            $table->string('codigo_solicitud');
        });
        DB::table('solicitud_clientes')->insert(['id' => 1, 'codigo_solicitud' => 'SOL-REPRINT-1']);

        $view = (new TodosPaquetesController())->reimprimirGuia('solicitud', 1);

        $this->assertSame('paquetes_ems.solicitud-ticket', $view->name());
        $this->assertSame('SOL-REPRINT-1', $view->getData()['solicitud']->codigo_solicitud);
    }
}

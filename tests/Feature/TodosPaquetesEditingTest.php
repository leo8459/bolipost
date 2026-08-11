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
}

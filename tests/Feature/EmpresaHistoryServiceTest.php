<?php

namespace Tests\Feature;

use App\Models\Empresa;
use App\Services\EmpresaHistoryService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

class EmpresaHistoryServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('empresa', function (Blueprint $table): void {
            $table->id();
            $table->string('nombre');
            $table->string('sigla')->nullable();
            $table->string('codigo_cliente')->nullable();
            $table->string('nit', 32)->nullable();
            $table->string('clasificacion')->nullable();
            $table->string('documentacion_legal')->nullable();
            $table->date('inicio_contrato')->nullable();
            $table->date('fin_contrato')->nullable();
            $table->string('cobertura')->nullable();
            $table->decimal('presupuesto', 15, 2)->nullable();
            $table->string('documento_pdf_path')->nullable();
            $table->timestamps();
        });

        Schema::create('users', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('empresa_id')->nullable();
            $table->timestamp('auto_baja_empresa_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('empresa_historiales', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('empresa_id')->nullable();
            $table->unsignedBigInteger('archivado_por')->nullable();
            $table->string('nombre');
            $table->string('sigla')->nullable();
            $table->string('codigo_cliente')->nullable();
            $table->string('nit', 32)->nullable();
            $table->string('clasificacion')->nullable();
            $table->string('documentacion_legal')->nullable();
            $table->date('inicio_contrato')->nullable();
            $table->date('fin_contrato')->nullable();
            $table->string('cobertura')->nullable();
            $table->decimal('presupuesto', 15, 2)->nullable();
            $table->string('documento_pdf_path')->nullable();
            $table->json('datos_completos');
            $table->timestamps();
        });

        Storage::fake('public');
    }

    protected function tearDown(): void
    {
        Schema::dropIfExists('empresa_historiales');
        Schema::dropIfExists('users');
        Schema::dropIfExists('empresa');

        parent::tearDown();
    }

    public function test_it_copies_the_current_pdf_and_company_data_before_renewing_the_contract(): void
    {
        Storage::disk('public')->put('empresa-documentos/contrato-vigente.pdf', 'PDF ANTERIOR');

        $empresa = Empresa::query()->create([
            'nombre' => 'EMPRESA PRUEBA',
            'sigla' => 'EP',
            'codigo_cliente' => 'CLI-10',
            'nit' => '123456789',
            'clasificacion' => 'PUBLICA',
            'documentacion_legal' => 'CONTRATO',
            'inicio_contrato' => '2025-01-01',
            'fin_contrato' => '2025-12-31',
            'cobertura' => 'NACIONAL',
            'presupuesto' => 1000,
            'documento_pdf_path' => 'empresa-documentos/contrato-vigente.pdf',
        ]);

        $history = app(EmpresaHistoryService::class)->archiveAndRenew(
            $empresa,
            [
                'nombre' => 'EMPRESA PRUEBA',
                'sigla' => 'EP',
                'codigo_cliente' => 'CLI-10',
                'nit' => '987654321',
                'clasificacion' => 'PUBLICA',
                'documentacion_legal' => 'ADENDA',
                'inicio_contrato' => '2026-01-01',
                'fin_contrato' => '2026-12-31',
                'cobertura' => 'NACIONAL',
                'presupuesto' => 1500,
            ],
            UploadedFile::fake()->create('contrato-nuevo.pdf', 10, 'application/pdf'),
            null
        );

        $empresa->refresh();

        $this->assertSame('2025-01-01', $history->inicio_contrato->toDateString());
        $this->assertSame('2025-12-31', $history->fin_contrato->toDateString());
        $this->assertSame('123456789', $history->nit);
        $this->assertSame('123456789', $history->datos_completos['nit']);
        $this->assertSame('empresa-documentos/contrato-vigente.pdf', $history->datos_completos['documento_pdf_path']);
        $this->assertNotNull($history->documento_pdf_path);
        Storage::disk('public')->assertExists($history->documento_pdf_path);
        $this->assertSame('PDF ANTERIOR', Storage::disk('public')->get($history->documento_pdf_path));

        $this->assertSame('2026-01-01', (string) $empresa->inicio_contrato);
        $this->assertSame('2026-12-31', (string) $empresa->fin_contrato);
        $this->assertSame('ADENDA', $empresa->documentacion_legal);
        $this->assertSame('987654321', $empresa->nit);
        $this->assertNotSame('empresa-documentos/contrato-vigente.pdf', $empresa->documento_pdf_path);
        Storage::disk('public')->assertExists($empresa->documento_pdf_path);
        Storage::disk('public')->assertMissing('empresa-documentos/contrato-vigente.pdf');
    }

    public function test_it_registers_a_company_with_its_nit(): void
    {
        $component = $this->companyComponent([
            'nombre' => 'EMPRESA CON NIT',
            'sigla' => 'ECN',
            'codigo_cliente' => 'CLI-20',
            'nit' => '00123456789',
            'clasificacion' => 'PRIVADA',
            'documentacion_legal' => 'CONTRATO',
            'inicio_contrato' => '2026-01-01',
            'fin_contrato' => '2026-12-31',
            'cobertura' => 'NACIONAL',
        ]);

        $validation = Validator::make(get_object_vars($component), $component->testRules());
        $this->assertFalse($validation->fails());

        Empresa::query()->create($component->testPayload());

        $this->assertDatabaseHas('empresa', [
            'codigo_cliente' => 'CLI-20',
            'nit' => '00123456789',
        ]);
    }

    public function test_it_rejects_a_nit_with_non_numeric_characters(): void
    {
        $component = $this->companyComponent([
            'nombre' => 'EMPRESA CON NIT INVALIDO',
            'sigla' => 'ENI',
            'codigo_cliente' => 'CLI-30',
            'nit' => '123-ABC',
            'clasificacion' => 'PRIVADA',
            'documentacion_legal' => 'CONTRATO',
            'inicio_contrato' => '2026-01-01',
            'fin_contrato' => '2026-12-31',
            'cobertura' => 'NACIONAL',
        ]);

        $validation = Validator::make(get_object_vars($component), $component->testRules());
        $this->assertTrue($validation->fails());
        $this->assertArrayHasKey('nit', $validation->errors()->toArray());

        $this->assertDatabaseMissing('empresa', [
            'codigo_cliente' => 'CLI-30',
        ]);
    }

    private function companyComponent(array $values): \App\Livewire\Empresa
    {
        $component = new class extends \App\Livewire\Empresa
        {
            public function testRules(): array
            {
                return $this->rules();
            }

            public function testPayload(): array
            {
                return $this->payload();
            }
        };

        foreach ($values as $property => $value) {
            $component->{$property} = $value;
        }

        return $component;
    }

}

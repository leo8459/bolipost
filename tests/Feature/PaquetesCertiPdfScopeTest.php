<?php

namespace Tests\Feature;

use App\Http\Controllers\PaquetesCertiController;
use App\Models\User;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use ReflectionMethod;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class PaquetesCertiPdfScopeTest extends TestCase
{
    public function test_delivery_pdf_embeds_a_font_compatible_with_browser_pdf_viewers(): void
    {
        $package = (object) [
            'codigo' => 'LX070928052NL',
            'destinatario' => 'DESTINATARIO DE PRUEBA',
            'cuidad' => 'LA PAZ',
            'ventanillaRef' => (object) ['nombre_ventanilla' => 'UNICA'],
            'ventanilla' => 'UNICA',
            'aduana' => 'NO',
            'tipo' => 'PP',
            'peso' => '0.115',
            'precio' => '25.00',
            'estado' => (object) ['nombre_estado' => 'ENTREGADO'],
        ];

        $pdf = Pdf::loadView('paquetes_certi.reporte_baja', [
            'packages' => collect([$package]),
            'printedByName' => 'USUARIO DE PRUEBA',
        ])->setPaper('A4')->output();

        $this->assertStringStartsWith('%PDF', $pdf);
        $this->assertStringContainsString('/FontFile2', $pdf);
        $this->assertStringContainsString('DejaVuSans', $pdf);
    }

    public function test_pdf_scope_recognizes_auxiliar_unica_like_inventory_does(): void
    {
        $user = \Mockery::mock(User::class)->makePartial();
        $user->id = 18;
        $user->shouldReceive('hasRole')
            ->andReturnUsing(fn (string $role): bool => in_array($role, [
                'auxiliar_unica',
                'auxiliar_urbano',
                'auxiliar_urbano_dnd',
            ], true));

        $this->actingAs($user);

        $method = new ReflectionMethod(PaquetesCertiController::class, 'restrictedVentanillaNames');

        $this->assertSame(['UNICA'], $method->invoke(new PaquetesCertiController()));
    }

    public function test_administrator_can_choose_the_name_printed_on_the_pdf(): void
    {
        Schema::create('users', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->softDeletes();
        });

        DB::table('users')->insert([
            'id' => 42,
            'name' => 'Nanda Flores Yujra',
        ]);

        $administrator = \Mockery::mock(User::class)->makePartial();
        $administrator->id = 1;
        $administrator->name = 'Administrador';
        $administrator->shouldReceive('hasRole')
            ->andReturnUsing(fn (string $role): bool => $role === config('acl.super_admin_role', 'administrador'));

        $this->actingAs($administrator);

        $request = Request::create('/paquetes-certificados/baja-pdf', 'GET', [
            'printed_for_user_id' => 42,
        ]);
        $method = new ReflectionMethod(PaquetesCertiController::class, 'resolvePrintedByName');

        $this->assertSame('Nanda Flores Yujra', $method->invoke(new PaquetesCertiController(), $request));
    }

    public function test_non_administrator_cannot_choose_the_name_printed_on_the_pdf(): void
    {
        $user = \Mockery::mock(User::class)->makePartial();
        $user->id = 18;
        $user->name = 'Usuario normal';
        $user->shouldReceive('hasRole')->andReturnFalse();

        $this->actingAs($user);

        $request = Request::create('/paquetes-certificados/baja-pdf', 'GET', [
            'printed_for_user_id' => 42,
        ]);
        $method = new ReflectionMethod(PaquetesCertiController::class, 'resolvePrintedByName');

        try {
            $method->invoke(new PaquetesCertiController(), $request);
            $this->fail('Se esperaba que el cambio de usuario fuera rechazado.');
        } catch (HttpException $exception) {
            $this->assertSame(403, $exception->getStatusCode());
        }
    }
}

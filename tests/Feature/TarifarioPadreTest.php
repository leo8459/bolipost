<?php

namespace Tests\Feature;

use App\Models\Servicio;
use App\Models\TarifarioPadre;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class TarifarioPadreTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $route = app('router')->getRoutes()->getByName('tarifario-padre.update');
        $middleware = app('router')->gatherRouteMiddleware($route);
        $this->withoutMiddleware(array_values(array_filter($middleware,
            fn ($name) => $name !== SubstituteBindings::class
        )));

        Schema::create('servicio', function (Blueprint $table) {
            $table->id();
            $table->string('nombre_servicio');
            $table->timestamps();
        });
        (require database_path('migrations/2026_10_09_120000_create_tarifario_padre_table.php'))->up();
    }

    public function test_parent_can_be_created_renamed_and_deleted(): void
    {
        $this->post(route('tarifario-padre.store'), ['nombre' => 'Tarifario 1'])
            ->assertRedirect(route('tarifario-padre.index'));
        $padre = TarifarioPadre::firstOrFail();

        $this->put(route('tarifario-padre.update', $padre), ['nombre' => 'Tarifario 2'])
            ->assertSessionHasNoErrors();
        $this->assertSame('Tarifario 2', $padre->fresh()->nombre);

        $this->delete(route('tarifario-padre.destroy', $padre))->assertSessionHas('success');
        $this->assertDatabaseMissing('tarifario_padre', ['id' => $padre->id]);
    }

    public function test_parent_requires_a_unique_nonempty_name(): void
    {
        $padre = TarifarioPadre::create(['nombre' => 'Tarifario 1']);
        $this->post(route('tarifario-padre.store'), ['nombre' => ''])->assertSessionHasErrors('nombre');
        $this->post(route('tarifario-padre.store'), ['nombre' => 'Tarifario 1'])->assertSessionHasErrors('nombre');
        session()->forget('errors');
        $this->put(route('tarifario-padre.update', $padre), ['nombre' => 'Tarifario 1'])->assertSessionHasNoErrors();
    }

    public function test_service_can_be_assigned_reassigned_and_unlinked(): void
    {
        $uno = TarifarioPadre::create(['nombre' => 'Tarifario 1']);
        $dos = TarifarioPadre::create(['nombre' => 'Tarifario 2']);
        $this->post(route('servicios.store'), [
            'nombre_servicio' => 'EMS', 'tarifario_padre_id' => $uno->id,
        ])->assertSessionHasNoErrors();
        $servicio = Servicio::firstOrFail();
        $this->assertTrue($servicio->tarifarioPadre->is($uno));
        $this->assertSame(1, $uno->servicios()->count());

        $this->delete(route('tarifario-padre.destroy', $uno))->assertSessionHas('error');
        $this->assertDatabaseHas('tarifario_padre', ['id' => $uno->id]);

        $this->put(route('servicios.update', $servicio), [
            'nombre_servicio' => 'EMS', 'tarifario_padre_id' => $dos->id,
        ])->assertSessionHasNoErrors();
        $this->assertTrue($servicio->fresh()->tarifarioPadre->is($dos));
        $this->delete(route('tarifario-padre.destroy', $uno))->assertSessionHas('success');

        $this->put(route('servicios.update', $servicio), [
            'nombre_servicio' => 'EMS', 'tarifario_padre_id' => null,
        ])->assertSessionHasNoErrors();
        $this->assertNull($servicio->fresh()->tarifario_padre_id);
    }

    public function test_service_rejects_a_nonexistent_parent(): void
    {
        $this->post(route('servicios.store'), [
            'nombre_servicio' => 'EMS', 'tarifario_padre_id' => 999,
        ])->assertSessionHasErrors('tarifario_padre_id');
        $this->assertDatabaseCount('servicio', 0);
    }

    public function test_existing_services_remain_unassigned_after_migration(): void
    {
        $migration = require database_path('migrations/2026_10_09_120000_create_tarifario_padre_table.php');
        $migration->down();
        $servicio = Servicio::create(['nombre_servicio' => 'Existente']);
        $migration->up();
        $this->assertNull($servicio->fresh()->tarifario_padre_id);
        $this->assertSame('Existente', $servicio->fresh()->nombre_servicio);
    }
}

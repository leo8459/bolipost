<?php

namespace Tests\Feature;

use App\Http\Controllers\DashboardController;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use ReflectionMethod;
use Tests\TestCase;

class DashboardCompanyExclusionTest extends TestCase
{
    public function test_packages_and_events_exclude_test_companies_with_user_fallback(): void
    {
        Schema::create('empresa', function (Blueprint $table) {
            $table->id();
            $table->string('nombre');
        });
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->integer('empresa_id')->nullable();
        });
        Schema::create('paquetes_contrato', function (Blueprint $table) {
            $table->id();
            $table->string('codigo');
            $table->integer('empresa_id')->nullable();
            $table->integer('user_id')->nullable();
        });
        Schema::create('eventos_contrato', function (Blueprint $table) {
            $table->id();
            $table->string('codigo');
        });
        DB::table('empresa')->insert([
            ['id' => 1, 'nombre' => ' EMPRESA '],
            ['id' => 2, 'nombre' => 'Cliente PrUeBa interno'],
            ['id' => 3, 'nombre' => 'Empresa comercial real'],
        ]);
        DB::table('users')->insert(['id' => 1, 'empresa_id' => 1]);
        foreach ([
            ['generic', 1, null],
            ['test', 2, null],
            ['fallback', null, 1],
            ['direct-real', 3, 1],
            ['unknown', null, null],
        ] as [$code, $company, $user]) {
            DB::table('paquetes_contrato')->insert(['codigo' => $code, 'empresa_id' => $company, 'user_id' => $user]);
            DB::table('eventos_contrato')->insert(['codigo' => $code]);
        }

        $controller = app(DashboardController::class);
        $config = ['table' => 'paquetes_contrato'];
        $packages = DB::table('paquetes_contrato as p');
        (new ReflectionMethod($controller, 'excludeTestCompanyPackages'))->invoke($controller, $packages, $config, 'p');
        $this->assertSame(['direct-real', 'unknown'], $packages->orderBy('codigo')->pluck('codigo')->all());

        $events = DB::table('eventos_contrato as ev');
        (new ReflectionMethod($controller, 'excludeTestCompanyEvents'))->invoke($controller, $events, $config, 'ev');
        $this->assertSame(['direct-real', 'unknown'], $events->orderBy('codigo')->pluck('codigo')->all());

        $unaliased = DB::table('paquetes_contrato');
        (new ReflectionMethod($controller, 'excludeTestCompanyPackages'))->invoke($controller, $unaliased, $config);
        $this->assertSame(2, $unaliased->count());
    }
}

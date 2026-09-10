<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class UserResolvedDriverTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('drivers', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('nombre');
            $table->string('email')->nullable();
            $table->boolean('activo')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('vehicle_assignments', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('driver_id');
            $table->unsignedBigInteger('vehicle_id')->nullable();
            $table->date('fecha_inicio')->nullable();
            $table->date('fecha_fin')->nullable();
            $table->boolean('activo')->default(true);
            $table->timestamps();
        });
    }

    public function test_prioritizes_duplicate_driver_profile_with_current_assignment(): void
    {
        $user = new User;
        $user->id = 81;
        $user->exists = true;

        $historicalDriverId = DB::table('drivers')->insertGetId([
            'user_id' => 81,
            'nombre' => 'Perfil historico',
            'activo' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $currentDriverId = DB::table('drivers')->insertGetId([
            'user_id' => 81,
            'nombre' => 'Perfil vigente',
            'activo' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('vehicle_assignments')->insert([
            [
                'driver_id' => $historicalDriverId,
                'vehicle_id' => 2,
                'fecha_inicio' => now()->subYear()->toDateString(),
                'fecha_fin' => now()->subDay()->toDateString(),
                'activo' => false,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'driver_id' => $currentDriverId,
                'vehicle_id' => 4,
                'fecha_inicio' => now()->subDay()->toDateString(),
                'fecha_fin' => null,
                'activo' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        $this->assertSame($currentDriverId, $user->resolvedDriver()?->id);
        $this->assertSame('Perfil vigente', $user->resolvedDriver()?->nombre);
    }
}

<?php

namespace Tests\Feature;

use App\Support\TiktokerEvent;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class TiktokerEventTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::dropIfExists('eventos');
        Schema::create('eventos', function (Blueprint $table) {
            $table->id();
            $table->string('nombre_evento');
            $table->timestamps();
        });
    }

    public function test_creates_missing_tiktoker_event_once(): void
    {
        $eventName = TiktokerEvent::RECIBIDA_ALMACEN;

        $firstId = TiktokerEvent::resolveId($eventName);
        $secondId = TiktokerEvent::resolveId($eventName);

        $this->assertGreaterThan(0, $firstId);
        $this->assertSame($firstId, $secondId);
        $this->assertDatabaseHas('eventos', [
            'id' => $firstId,
            'nombre_evento' => $eventName,
        ]);
        $this->assertSame(
            1,
            DB::table('eventos')
                ->whereRaw('TRIM(UPPER(nombre_evento)) = ?', [mb_strtoupper($eventName)])
                ->count()
        );
    }
}

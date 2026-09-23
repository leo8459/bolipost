<?php

namespace Tests\Feature;

use App\Http\Controllers\TodosPaquetesController;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class TodosPaquetesHistoryReportTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('users', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('alias')->nullable();
            $table->string('email')->nullable();
            $table->string('ciudad')->nullable();
        });
        Schema::create('eventos', function (Blueprint $table): void {
            $table->id();
            $table->string('nombre_evento');
            $table->timestamps();
        });
        Schema::create('eventos_ems', function (Blueprint $table): void {
            $table->id();
            $table->string('codigo');
            $table->unsignedBigInteger('evento_id');
            $table->unsignedBigInteger('user_id')->nullable();
            $table->text('detalle_evento')->nullable();
            $table->timestamps();
        });
    }

    protected function tearDown(): void
    {
        Schema::dropIfExists('eventos_ems');
        Schema::dropIfExists('eventos');
        Schema::dropIfExists('users');

        parent::tearDown();
    }

    public function test_history_report_collects_events_in_order_with_user_regional_and_dates(): void
    {
        $userId = DB::table('users')->insertGetId([
            'name' => 'Operador La Paz',
            'alias' => 'olapaz',
            'email' => 'operador@correos.gob.bo',
            'ciudad' => 'LA PAZ',
        ]);
        $admittedId = DB::table('eventos')->insertGetId([
            'nombre_evento' => 'Envío admitido.',
            'created_at' => '2026-09-20 08:00:00',
            'updated_at' => '2026-09-20 08:00:00',
        ]);
        $transitId = DB::table('eventos')->insertGetId([
            'nombre_evento' => 'Envío en tránsito.',
            'created_at' => '2026-09-21 11:30:00',
            'updated_at' => '2026-09-21 11:30:00',
        ]);

        DB::table('eventos_ems')->insert([
            [
                'codigo' => 'c0040a5663bo',
                'evento_id' => $admittedId,
                'user_id' => $userId,
                'created_at' => '2026-09-20 08:05:00',
                'updated_at' => '2026-09-20 08:05:00',
            ],
            [
                'codigo' => 'C0040A5663BO',
                'evento_id' => $transitId,
                'user_id' => $userId,
                'created_at' => '2026-09-21 11:35:00',
                'updated_at' => '2026-09-21 11:35:00',
            ],
        ]);

        $controller = new TodosPaquetesController;
        $method = new \ReflectionMethod($controller, 'historyEventsForPackages');
        $events = $method->invoke($controller, collect([
            (object) ['type_key' => 'ems', 'codigo' => 'C0040A5663BO'],
        ]))->get('ems|C0040A5663BO');

        $this->assertCount(2, $events);
        $this->assertSame('Envío admitido.', $events[0]->nombre_evento);
        $this->assertSame('2026-09-20 08:05:00', Carbon::parse($events[0]->created_at)->format('Y-m-d H:i:s'));
        $this->assertSame('Operador La Paz', $events[0]->usuario_nombre);
        $this->assertSame('olapaz', $events[0]->usuario_alias);
        $this->assertSame('operador@correos.gob.bo', $events[0]->usuario_email);
        $this->assertSame('LA PAZ', $events[0]->usuario_regional);
        $this->assertSame('Evento inicial', $events[0]->tiempo_desde_anterior);
        $this->assertSame('1 d 3 h 30 min', $events[0]->tiempo_hasta_siguiente);
        $this->assertSame('Envío en tránsito.', $events[1]->nombre_evento);
        $this->assertSame('1 d 3 h 30 min', $events[1]->tiempo_desde_anterior);
        $this->assertSame('Último evento registrado', $events[1]->tiempo_hasta_siguiente);
    }

    public function test_report_code_parser_accepts_lines_commas_and_spaces_without_duplicates(): void
    {
        $controller = new TodosPaquetesController;
        $method = new \ReflectionMethod($controller, 'parseHistoryReportCodes');

        $codes = $method->invoke($controller, " c0040a5663bo\nC0040A5662BO, C0040A5663BO;C0040A38636BO ");

        $this->assertSame([
            'C0040A5663BO',
            'C0040A5662BO',
            'C0040A38636BO',
        ], $codes->all());
    }

    public function test_pdf_view_explains_latest_event_and_missing_guides(): void
    {
        $event = (object) [
            'nombre_evento' => 'Envío entregado al destinatario.',
            'detalle_evento' => null,
            'created_at' => '2026-09-22 15:10:00',
            'usuario_nombre' => 'Responsable de entrega',
            'usuario_alias' => 'rentrega',
            'usuario_email' => 'entrega@correos.gob.bo',
            'usuario_regional' => 'SANTA CRUZ',
            'cliente_nombre' => null,
            'tiempo_hasta_siguiente' => 'Último evento registrado',
        ];
        $package = (object) [
            'codigo' => 'C0040A5663BO',
            'tipo' => 'EMS',
            'origen' => 'LA PAZ',
            'destino' => 'SANTA CRUZ',
            'estado_nombre' => 'ENTREGADO',
            'updated_at' => '2026-09-22 15:10:00',
            'destinatario' => 'Destinatario prueba',
            'remitente' => 'Remitente prueba',
            'empresa' => '',
            'peso' => '1.500',
            'precio' => '25.00',
            'justificacion' => '',
            'eventos' => collect([$event]),
            'duracion_historial' => 'Un solo evento',
        ];

        $html = view('todos_paquetes.historial-pdf', [
            'packages' => collect([$package]),
            'requestedCodes' => collect(['C0040A5663BO', 'NO-EXISTE']),
            'notFoundCodes' => collect(['NO-EXISTE']),
            'generatedAt' => Carbon::parse('2026-09-23 10:00:00'),
            'generatedBy' => (object) ['name' => 'Director financiero'],
            'totalEvents' => 1,
        ])->render();

        $this->assertStringContainsString('Qué pasó por última vez', $html);
        $this->assertStringContainsString('Tiempo desde el evento anterior', $html);
        $this->assertStringContainsString('Evento inicial', $html);
        $this->assertStringContainsString('Último evento registrado', $html);
        $this->assertStringContainsString('Duración total registrada', $html);
        $this->assertStringContainsString('Envío entregado al destinatario.', $html);
        $this->assertStringContainsString('Responsable de entrega', $html);
        $this->assertStringContainsString('SANTA CRUZ', $html);
        $this->assertStringContainsString('Guías no encontradas', $html);
        $this->assertStringContainsString('NO-EXISTE', $html);
    }
}

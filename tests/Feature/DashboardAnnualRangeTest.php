<?php

namespace Tests\Feature;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class DashboardAnnualRangeTest extends TestCase
{
    public function test_dashboard_opens_on_the_current_calendar_year_with_all_months_selected(): void
    {
        $this->travelTo(now()->setDate(2026, 11, 26));
        $this->withoutMiddleware();

        // The dashboard's aggregate SQL uses PostgreSQL functions unavailable in SQLite.
        DB::connection()->pretend(function (): void {
            $response = $this->get(route('dashboard'));

            $response->assertOk()
                ->assertViewHas('rangoKey', 'year')
                ->assertViewHas('rangoAnio', 2026)
                ->assertViewHas('rangoDesde', '2026-01-01')
                ->assertViewHas('rangoHasta', '2026-12-31')
                ->assertViewHas('agrupacion', 'month')
                ->assertViewHas('trendLabels', array_map(fn (int $month) => sprintf('2026-%02d', $month), range(1, 12)))
                ->assertViewHas('insightsEjecutivos', fn (array $insights) => $insights['ultimo_periodo']['label'] === '2026-11');

            $this->assertSame(12, preg_match_all('/data-chart-month value="\d+" checked/', $response->getContent()));
            $response->assertSee('<option value="2026" selected>2026</option>', false);

            $this->get(route('dashboard', ['modules' => ['ems'], 'range' => 'all']))
                ->assertOk()
                ->assertViewHas('rangoKey', 'all');

            $this->get(route('dashboard', [
                'modules' => ['ems'], 'range' => 'year', 'from' => '2026-04-01', 'to' => '2026-04-30',
            ]))->assertOk()
                ->assertViewHas('rangoKey', 'custom')
                ->assertViewHas('rangoDesde', '2026-04-01')
                ->assertViewHas('rangoHasta', '2026-04-30');
        });
    }

    public function test_historical_year_filters_the_dashboard_and_sets_the_volume_year(): void
    {
        $this->travelTo(now()->setDate(2026, 11, 26));
        $this->withoutMiddleware();

        DB::connection()->pretend(function (): void {
            $response = $this->get(route('dashboard', ['anio' => 2024]));

            $response->assertOk()
                ->assertViewHas('rangoAnio', 2024)
                ->assertViewHas('rangoDesde', '2024-01-01')
                ->assertViewHas('rangoHasta', '2024-12-31')
                ->assertViewHas('trendLabels', array_map(fn (int $month) => sprintf('2024-%02d', $month), range(1, 12)))
                ->assertViewHas('insightsEjecutivos', fn (array $insights) => $insights['ultimo_periodo']['label'] === '2024-12')
                ->assertSee('<option value="2024" selected>2024</option>', false);
            $this->assertMatchesRegularExpression('/id="chartVolumenYear"[^>]*value="2024"/', $response->getContent());

            $this->get(route('dashboard', ['anio' => '2027']))
                ->assertOk()
                ->assertViewHas('rangoAnio', 2026);
        });
    }

    public function test_volume_counts_records_from_january_through_december(): void
    {
        $this->travelTo(now()->setDate(2026, 11, 26));

        Schema::create('estados', function (Blueprint $table): void {
            $table->id();
            $table->string('nombre_estado');
        });
        Schema::create('paquetes_ems', function (Blueprint $table): void {
            $table->id();
            $table->string('codigo');
            $table->string('origen');
            $table->decimal('peso', 10, 3);
            $table->unsignedBigInteger('estado_id');
            $table->timestamp('created_at');
        });

        DB::table('estados')->insert(['id' => 1, 'nombre_estado' => 'ENTREGADO']);
        foreach (['2025-12-31 23:59:59', '2026-01-01 00:00:00', '2026-12-31 23:59:59', '2027-01-01 00:00:00'] as $index => $date) {
            DB::table('paquetes_ems')->insert([
                'codigo' => 'EMS-'.$index,
                'origen' => 'LA PAZ',
                'peso' => 1,
                'estado_id' => 1,
                'created_at' => $date,
            ]);
        }

        $this->withoutMiddleware()->getJson(route('dashboard.chart-volume-data', [
            'modules' => ['ems'],
            'anio' => 2026,
            'meses' => range(1, 12),
            'departamentos' => ['LA PAZ'],
        ]))->assertOk()
            ->assertJsonPath('totales.registrados', 2)
            ->assertJsonPath('totales.peso', 2)
            ->assertJsonPath('totales.entregas', 2);
    }
}

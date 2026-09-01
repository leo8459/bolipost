<?php

namespace Tests\Unit;

use Tests\TestCase;

class DashboardPendingPickupRuleTest extends TestCase
{
    public function test_dashboard_only_classifies_pending_shipments_after_pickup_or_reception(): void
    {
        $dashboard = str_replace("\r\n", "\n", file_get_contents(app_path('Http/Controllers/DashboardController.php')));
        $indicator = str_replace("\r\n", "\n", file_get_contents(app_path('Http/Controllers/IndicadorController.php')));

        $this->assertStringContainsString('$pendientes = $correctos + $atrasados + $rezago;', $dashboard);
        $this->assertStringContainsString("'operational_start_events' => [295]", $dashboard);
        $this->assertStringContainsString("->whereIn('evento_id', \$config['operational_start_events'])", $dashboard);
        $this->assertStringContainsString("->whereIn('inicio_operativo.evento_id', \$config['operational_start_events'])", $dashboard);
        $this->assertStringNotContainsString(
            '$this->safeCarbonValue($row->solicitud_at ?? null)' . "\n" . '                    ?? $this->safeCarbonValue($row->created_at ?? null)',
            $dashboard
        );
        $this->assertStringNotContainsString(
            '$this->safeCarbon($row->solicitud_at ?? null)' . "\n" . '            ?? $this->safeCarbon($row->fecha_registro ?? null)',
            $indicator
        );
    }
}

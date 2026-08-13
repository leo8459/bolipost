<?php

namespace Tests\Unit;

use App\Http\Controllers\DashboardController;
use ReflectionMethod;
use Tests\TestCase;

class DashboardDepartmentCoverageTest extends TestCase
{
    public function test_national_pending_summary_always_contains_sucre_trinidad_and_all_nine_departments(): void
    {
        $method = new ReflectionMethod(DashboardController::class, 'completeDepartmentPendingSummary');

        $departments = $method->invoke(app(DashboardController::class), collect([
            (object) ['ciudad' => 'LA PAZ', 'pendientes' => 3],
        ]));

        $this->assertCount(9, $departments);
        $this->assertContains('SUCRE', $departments->pluck('department')->all());
        $this->assertContains('TRINIDAD', $departments->pluck('department')->all());
        $this->assertSame(0, $departments->firstWhere('department', 'SUCRE')->total_pendientes);
        $this->assertSame(0, $departments->firstWhere('department', 'TRINIDAD')->total_pendientes);
    }
}

<?php

use App\Http\Controllers\DashboardController;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

require __DIR__ . '/../vendor/autoload.php';

$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

if (DB::connection()->getDriverName() !== 'pgsql') {
    fwrite(STDERR, "Este benchmark requiere PostgreSQL.\n");
    exit(1);
}

DB::statement('CREATE TEMP TABLE paquetes_ems AS
    SELECT
        id,
        CONCAT(\'BENCH-\', id) AS codigo,
        CASE WHEN id % 10 = 0 THEN 2 ELSE 1 END AS estado_id,
        1.25::numeric(10,2) AS peso,
        10.00::numeric(10,2) AS precio,
        \'LA PAZ\' AS origen,
        CASE WHEN id % 3 = 0 THEN \'LA PAZ\' ELSE \'SANTA CRUZ\' END AS ciudad,
        (CURRENT_TIMESTAMP - ((id % 365) || \' days\')::interval) AS created_at,
        CURRENT_TIMESTAMP AS updated_at
    FROM generate_series(1, 1000000) AS id');

DB::statement('CREATE TEMP TABLE eventos_ems AS
    SELECT
        id,
        CONCAT(\'BENCH-\', id) AS codigo,
        295 AS evento_id,
        1 AS user_id,
        (CURRENT_TIMESTAMP - ((id % 365) || \' days\')::interval) AS created_at,
        CURRENT_TIMESTAMP AS updated_at
    FROM generate_series(1, 1000000) AS id');

DB::statement('CREATE INDEX bench_paquetes_ems_state_created_idx ON paquetes_ems (estado_id, created_at)');
DB::statement('CREATE INDEX bench_paquetes_ems_codigo_idx ON paquetes_ems (codigo)');
DB::statement('CREATE INDEX bench_eventos_ems_lookup_idx ON eventos_ems (evento_id, created_at, codigo)');
DB::statement('CREATE INDEX bench_eventos_ems_codigo_idx ON eventos_ems (codigo)');
DB::statement('ANALYZE paquetes_ems');
DB::statement('ANALYZE eventos_ems');

$startedAt = microtime(true);
$queryStats = [];
DB::listen(function ($query) use (&$queryStats): void {
    $queryStats[] = [
        'ms' => (float) $query->time,
        'sql' => preg_replace('/\s+/', ' ', $query->sql),
    ];
});
$controller = app(DashboardController::class);
$user = User::query()->first();
if ($user) {
    Auth::login($user);
}

$request = Request::create('/dashboard', 'GET', [
    'modules' => ['ems'],
    'range' => 'all',
    'group' => 'month',
]);

$buildDashboardData = new ReflectionMethod($controller, 'buildDashboardData');
$buildDashboardData->setAccessible(true);
$data = $buildDashboardData->invoke($controller, $request, false, false);
$html = view('dashboard', $data)->render();
$alertMethod = new ReflectionMethod($controller, 'buildRegionalPendingAlert');
$alertMethod->setAccessible(true);
$alertStartedAt = microtime(true);
$alert = $alertMethod->invoke($controller, (string) ($user->ciudad ?? ''), true);
$alertElapsedMs = round((microtime(true) - $alertStartedAt) * 1000, 1);
$elapsedMs = round((microtime(true) - $startedAt) * 1000, 1);

echo json_encode([
    'rows' => 1000000,
    'elapsed_ms' => $elapsedMs,
    'html_bytes' => strlen($html),
    'removed_blocks_present' => str_contains($html, 'Registrados Hoy') || str_contains($html, 'Como leer este panel'),
    'regional_alert_count' => (int) ($alert['count'] ?? 0),
    'regional_alert_ms' => $alertElapsedMs,
    'peak_memory_mb' => round(memory_get_peak_usage(true) / 1048576, 1),
    'query_count' => count($queryStats),
    'query_ms_total' => round(array_sum(array_column($queryStats, 'ms')), 1),
    'slowest_queries' => array_slice(collect($queryStats)->sortByDesc('ms')->values()->all(), 0, 5),
], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . PHP_EOL;

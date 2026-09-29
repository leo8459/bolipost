<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class WindowLoadMetricsController extends Controller
{
    public function index()
    {
        $periodStart = now()->subDays(30);

        $metrics = DB::table('window_load_metrics')
            ->select([
                'route_name',
                'window_name',
                DB::raw('COUNT(*) as sample_count'),
                DB::raw('AVG(load_time_ms) as average_load_time_ms'),
                DB::raw('AVG(server_time_ms) as average_server_time_ms'),
            ])
            ->where('measured_at', '>=', $periodStart)
            ->groupBy('route_name', 'window_name')
            ->orderByDesc('average_load_time_ms')
            ->get();

        return view('administrador.cargado-ventanas', compact('metrics', 'periodStart'));
    }

    public function store(Request $request): JsonResponse
    {
        abort_unless(
            Auth::guard('web')->check() || Auth::guard('cliente')->check(),
            401,
            'Debes iniciar sesión para registrar la medición.'
        );

        $data = $request->validate([
            'route_name' => ['required', 'string', 'max:190', 'regex:/^[A-Za-z0-9_.:\/-]+$/'],
            'window_name' => ['required', 'string', 'max:180'],
            'load_time_ms' => ['required', 'integer', 'min:0', 'max:180000'],
            'server_time_ms' => ['nullable', 'integer', 'min:0', 'max:180000'],
        ]);

        DB::table('window_load_metrics')->insert([
            ...$data,
            'measured_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return response()->json(['stored' => true], 201);
    }
}

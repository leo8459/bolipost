<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\ChasquiLocationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ChasquiLocationApiController extends Controller
{
    public function heartbeat(Request $request, ChasquiLocationService $locations): JsonResponse
    {
        $payload = $request->validate([
            'device_id' => ['nullable', 'string', 'max:190'],
            'device_name' => ['nullable', 'string', 'max:120'],
            'latitude' => ['required', 'numeric', 'between:-90,90'],
            'longitude' => ['required', 'numeric', 'between:-180,180'],
            'accuracy_m' => ['nullable', 'numeric', 'min:0', 'max:10000'],
            'speed_kmh' => ['nullable', 'numeric', 'min:0', 'max:300'],
            'heading' => ['nullable', 'numeric', 'between:0,360'],
            'battery_percent' => ['nullable', 'numeric', 'between:0,100'],
            'is_moving' => ['nullable', 'boolean'],
            'gps_enabled' => ['nullable', 'boolean'],
            'gps_mocked' => ['nullable', 'boolean'],
            'sent_at' => ['nullable', 'date'],
        ]);

        /** @var User $user */
        $user = $request->user();
        $location = $locations->record($user, $payload);

        return response()->json([
            'message' => 'Ubicacion de ChasquiApp recibida.',
            'data' => $location,
        ], 202);
    }
}

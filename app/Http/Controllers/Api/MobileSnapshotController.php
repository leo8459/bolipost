<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\MobileDbSnapshot;
use App\Services\MobileSyncProcessor;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class MobileSnapshotController extends Controller
{
    public function store(Request $request, MobileSyncProcessor $processor)
    {
        $user = $request->user();
        if (!$user) {
            return response()->json([
                'status' => 'error',
                'message' => 'No autenticado.',
            ], 401);
        }

        $userId = (int) $user->id;
        $key = $request->input('snapshot_key') ?? $request->input('record_id');

        if (!$key) {
            $key = 'snap_' . $userId . '_' . now()->format('Ymd_His');
        }

        try {
            $snapshot = MobileDbSnapshot::create([
                'user_id' => $userId,
                'snapshot_key' => $key,
                'action' => $request->input('action', 'LOCAL_DB_SNAPSHOT_CHUNK'),
                'model' => $request->input('model', 'unknown'),
                'payload_json' => json_encode($request->all(), JSON_UNESCAPED_UNICODE),
            ]);

            $processor->processSnapshot($key);

            return response()->json([
                'status' => 'success',
                'message' => 'Datos procesados correctamente',
                'snapshot_id' => $snapshot->id,
                'key_used' => $key,
            ], 201);
        } catch (\Exception $e) {
            Log::error('Error en MobileSnapshotController: ' . $e->getMessage());

            return response()->json([
                'status' => 'error',
                'message' => 'Error: ' . $e->getMessage(),
            ], 500);
        }
    }
}

<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\MobileDbSnapshot;
use App\Services\MobileSyncProcessor;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class MobileDbSnapshotController extends Controller
{
    public function chunk(Request $request)
    {
        $payload = $request->validate([
            'user_id' => 'nullable|integer|min:0',
            'action' => 'required|string|max:120',
            'model' => 'required|string|max:190',
            'record_id' => 'nullable|integer|min:0',
            'changes_json' => 'nullable',
            'snapshot_key' => 'nullable|string|max:120',
            'sent_at' => 'nullable|date',
            'table_name' => 'nullable|string|max:120',
            'page' => 'nullable|integer|min:1',
            'total_pages' => 'nullable|integer|min:1',
        ]);

        $resolvedUserId = (int) ($request->user()?->id ?? 0);
        if ($resolvedUserId <= 0 && !empty($payload['user_id'])) {
            $resolvedUserId = (int) $payload['user_id'];
        }

        $changesRaw = $payload['changes_json'] ?? null;
        $changesDecoded = null;
        if (is_string($changesRaw)) {
            $decoded = json_decode($changesRaw, true);
            $changesDecoded = is_array($decoded) ? $decoded : null;
        } elseif (is_array($changesRaw)) {
            $changesDecoded = $changesRaw;
        }

        $tableName = (string) ($payload['table_name'] ?? '');
        if ($tableName === '' && str_starts_with((string) $payload['model'], 'mobile_sqlite.')) {
            $tableName = (string) substr((string) $payload['model'], strlen('mobile_sqlite.'));
        }
        if ($tableName === '' && !empty($changesDecoded['table'])) {
            $tableName = (string) $changesDecoded['table'];
        }
        if ($tableName === '' && !empty($changesDecoded['table_name'])) {
            $tableName = (string) $changesDecoded['table_name'];
        }
        $tableName = trim($tableName) !== '' ? trim($tableName) : null;

        $page = (int) ($payload['page'] ?? 0);
        if ($page <= 0 && !empty($changesDecoded['page'])) {
            $page = (int) $changesDecoded['page'];
        }
        if ($page <= 0 && !empty($payload['record_id']) && str_contains((string) $payload['action'], 'CHUNK')) {
            $page = (int) $payload['record_id'];
        }
        $page = $page > 0 ? $page : null;

        $totalPages = (int) ($payload['total_pages'] ?? 0);
        if ($totalPages <= 0 && !empty($changesDecoded['total_pages'])) {
            $totalPages = (int) $changesDecoded['total_pages'];
        }
        if ($totalPages <= 0 && !empty($changesDecoded['pages_total'])) {
            $totalPages = (int) $changesDecoded['pages_total'];
        }
        $totalPages = $totalPages > 0 ? $totalPages : null;

        $sentAt = null;
        if (!empty($payload['sent_at'])) {
            $sentAt = Carbon::parse((string) $payload['sent_at']);
        } elseif (!empty($changesDecoded['sent_at'])) {
            $sentAt = Carbon::parse((string) $changesDecoded['sent_at']);
        } else {
            $sentAt = now();
        }

        $snapshotKey = trim((string) ($payload['snapshot_key'] ?? ''));
        if ($snapshotKey === '' && !empty($changesDecoded['snapshot_key'])) {
            $snapshotKey = (string) $changesDecoded['snapshot_key'];
        }
        if ($snapshotKey === '' && !empty($changesDecoded['snapshot_id'])) {
            $snapshotKey = (string) $changesDecoded['snapshot_id'];
        }
        if ($snapshotKey === '') {
            $snapshotKey = implode(':', [
                $resolvedUserId > 0 ? $resolvedUserId : 'guest',
                $sentAt->format('YmdHis'),
                $tableName ?: 'all',
            ]);
        }

        $payloadJson = is_string($changesRaw)
            ? $changesRaw
            : json_encode($changesRaw ?? $changesDecoded ?? [], JSON_UNESCAPED_UNICODE);

        $row = MobileDbSnapshot::create([
            'user_id' => $resolvedUserId > 0 ? $resolvedUserId : null,
            'snapshot_key' => $snapshotKey,
            'sent_at' => $sentAt,
            'action' => (string) $payload['action'],
            'model' => (string) $payload['model'],
            'table_name' => $tableName,
            'record_id' => isset($payload['record_id']) ? (int) $payload['record_id'] : null,
            'page' => $page,
            'total_pages' => $totalPages,
            'payload_json' => (string) ($payloadJson ?? ''),
            'payload_size' => mb_strlen((string) ($payloadJson ?? ''), '8bit'),
            'ip_address' => $request->ip(),
            'user_agent' => (string) $request->userAgent(),
        ]);

        $shouldProcessNow = false;
        $action = mb_strtoupper((string) ($payload['action'] ?? ''));
        $model = mb_strtolower((string) ($payload['model'] ?? ''));

        if (str_contains($action, 'FULL')) {
            $shouldProcessNow = true;
        }
        if ($model === 'mobile_sqlite.full') {
            $shouldProcessNow = true;
        }
        if (is_array($changesDecoded) && isset($changesDecoded['tables']) && is_array($changesDecoded['tables'])) {
            $shouldProcessNow = true;
        }

        $processed = false;
        $processError = null;
        if ($shouldProcessNow && $this->isSnapshotProcessingEnabled()) {
            try {
                app(MobileSyncProcessor::class)->processSnapshot($snapshotKey);
                $processed = true;
            } catch (\Throwable $e) {
                $processError = $e->getMessage();
                Log::warning('Error procesando snapshot FULL en /chunk', [
                    'snapshot_key' => $snapshotKey,
                    'error' => $processError,
                ]);
            }
        }

        return response()->json([
            'message' => $processed ? 'Snapshot FULL recibido y procesado.' : 'Chunk recibido.',
            'snapshot_row_id' => $row->id,
            'snapshot_key' => $snapshotKey,
            'table_name' => $tableName,
            'page' => $page,
            'total_pages' => $totalPages,
            'processed' => $processed,
            'process_error' => $processError,
        ], 201);
    }

    public function finish(Request $request)
    {
        $payload = $request->validate([
            'user_id' => 'nullable|integer|min:0',
            'snapshot_key' => 'nullable|string|max:120',
            'sent_at' => 'nullable|date',
            'table_name' => 'nullable|string|max:120',
            'model' => 'nullable|string|max:190',
        ]);

        $resolvedUserId = (int) ($request->user()?->id ?? 0);
        if ($resolvedUserId <= 0 && !empty($payload['user_id'])) {
            $resolvedUserId = (int) $payload['user_id'];
        }

        $query = MobileDbSnapshot::query();

        // Filtros de búsqueda (esto ya lo tienes bien)
        if ($resolvedUserId > 0) $query->where('user_id', $resolvedUserId);
        if (!empty($payload['snapshot_key'])) $query->where('snapshot_key', (string) $payload['snapshot_key']);

        $rows = $query->orderBy('id')->get();
        if ($rows->isEmpty()) {
            return response()->json(['message' => 'No se encontraron chunks.'], 404);
        }

        // Lógica para verificar si todas las páginas llegaron
        $tables = $rows
            ->groupBy(fn(MobileDbSnapshot $row) => (string) ($row->table_name ?: $row->model))
            ->map(function ($group) {
                $pages = $group->pluck('page')->filter(fn($p) => !is_null($p))->unique()->sort()->values();
                $expected = (int) ($group->max('total_pages') ?? 0);
                $missing = [];
                if ($expected > 0) {
                    for ($i = 1; $i <= $expected; $i++) {
                        if (!$pages->contains($i)) $missing[] = $i;
                    }
                }
                return [
                    'rows' => $group->count(),
                    'is_complete' => $expected === 0 ? true : empty($missing),
                ];
            });

        // --- PASO 2: DISPARAR EL SERVICIO ---
        $isComplete = $tables->every(fn($info) => (bool) ($info['is_complete'] ?? false));

        $processed = false;
        if ($isComplete && $this->isSnapshotProcessingEnabled()) {
            // Instanciamos el procesador que creaste en app/Services
            $processor = new MobileSyncProcessor();

            // Usamos la snapshot_key del payload o la última recibida
            $keyToProcess = $payload['snapshot_key'] ?? $rows->last()->snapshot_key;

            $processor->processSnapshot($keyToProcess);
            $processed = true;

            Log::info("¡Éxito! Sincronización procesada para la llave: " . $keyToProcess);
        }

        return response()->json([
            'message' => $isComplete
                ? ($processed ? 'Snapshot completo y procesado.' : 'Snapshot completo (sin procesamiento).')
                : 'Snapshot incompleto.',
            'is_complete' => $isComplete,
            'processed' => $processed,
            'snapshot_key' => $rows->last()?->snapshot_key,
            'tables' => $tables,
        ]);
    }

    // Unificamos el 'store' para que no use auth()->id()
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

        $snapshot = MobileDbSnapshot::create([
            'user_id' => $userId,
            'snapshot_key' => $request->snapshot_key ?? 'manual_' . time(),
            'table_name' => $request->table ?? 'route_points',
            'action' => 'LOCAL_DB_SNAPSHOT_CHUNK',
            'model' => $request->model ?? 'unknown',
            'payload_json' => json_encode($request->all()),
        ]);

        if ($this->isSnapshotProcessingEnabled()) {
            $processor->processSnapshot($snapshot->snapshot_key);
        }

        return response()->json(['status' => 'success']);
    }

    private function isSnapshotProcessingEnabled(): bool
    {
        return (bool) env('MOBILE_SNAPSHOT_PROCESS_ENABLED', false);
    }
}

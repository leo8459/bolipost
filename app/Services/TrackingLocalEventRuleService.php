<?php

namespace App\Services;

use App\Http\Controllers\BusquedaController;
use App\Models\TrackingLocalEventRule;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class TrackingLocalEventRuleService
{
    private const CACHE_KEY = 'tracking:local_event_rules:v1';

    public function present(?string $rawEventName, ?string $sourceTable, int|string|null $eventId): ?string
    {
        $sourceTable = $this->normalizeSourceTable($sourceTable);
        $rawEventName = $this->normalizeText($rawEventName ?? '');
        $eventId = is_numeric($eventId) ? (int) $eventId : null;

        $rule = $this->resolveRule($sourceTable, $eventId, $rawEventName);

        if ($rule && ! $rule->is_visible) {
            return null;
        }

        $displayName = trim((string) ($rule?->display_name ?? ''));

        if ($displayName === '') {
            $displayName = $rawEventName;
        }

        return $displayName !== '' ? $displayName : null;
    }

    public function syncFromLocalCatalog(): array
    {
        $rows = collect(BusquedaController::trackingLocalSources())
            ->filter(fn (array $source) => Schema::hasTable($source['tabla']))
            ->flatMap(function (array $source) {
                return collect(DB::table($source['tabla'] . ' as ee')
                    ->leftJoin('eventos as e', 'e.id', '=', 'ee.evento_id')
                    ->selectRaw('DISTINCT ee.evento_id as event_id, TRIM(e.nombre_evento) as raw_name')
                    ->whereNotNull('ee.evento_id')
                    ->whereNotNull('e.nombre_evento')
                    ->whereRaw("TRIM(e.nombre_evento) <> ''")
                    ->get())
                    ->map(fn (object $row) => [
                        'event_id' => isset($row->event_id) ? (int) $row->event_id : null,
                        'raw_name' => $this->normalizeText((string) ($row->raw_name ?? '')),
                    ]);
            })
            ->filter(fn (array $row) => ($row['event_id'] ?? null) !== null || ($row['raw_name'] ?? '') !== '')
            ->unique(fn (array $row) => implode('|', [
                (string) ($row['event_id'] ?? ''),
                (string) ($row['raw_name'] ?? ''),
            ]))
            ->values();

        $created = 0;
        $updated = 0;

        foreach ($rows as $row) {
            $eventId = $row['event_id'];
            $rawName = $row['raw_name'];

            $rule = TrackingLocalEventRule::query()
                ->where('source_table', '*')
                ->where(function ($query) use ($eventId, $rawName) {
                    if ($eventId !== null) {
                        $query->where('event_id', $eventId);
                    }

                    if ($rawName !== '') {
                        $method = $eventId !== null ? 'orWhere' : 'where';
                        $query->{$method}('raw_name', $rawName);
                    }
                })
                ->first();

            if ($rule) {
                $dirty = false;

                if ($rule->event_id === null && $eventId !== null) {
                    $rule->event_id = $eventId;
                    $dirty = true;
                }

                if (trim((string) $rule->raw_name) === '' && $rawName !== '') {
                    $rule->raw_name = $rawName;
                    $dirty = true;
                }

                if (trim((string) $rule->display_name) === '' && $rawName !== '') {
                    $rule->display_name = $rawName;
                    $dirty = true;
                }

                if ($dirty) {
                    $rule->save();
                    $updated++;
                }

                continue;
            }

            TrackingLocalEventRule::create([
                'source_table' => '*',
                'event_id' => $eventId,
                'raw_name' => $rawName,
                'display_name' => $rawName !== '' ? $rawName : null,
                'is_visible' => true,
                'sort_order' => 0,
            ]);

            $created++;
        }

        $this->clearCache();

        return [
            'total' => $rows->count(),
            'created' => $created,
            'updated' => $updated,
        ];
    }

    public function commonSourceOptions(): array
    {
        return [
            '*' => 'General',
            'eventos_ems' => 'Locales - EMS',
            'eventos_certi' => 'Locales - Certi',
            'eventos_contrato' => 'Locales - Contrato',
            'eventos_ordi' => 'Locales - Ordi',
            'eventos_tiktoker' => 'Locales - Tiktoker',
        ];
    }

    public function clearCache(): void
    {
        Cache::forget(self::CACHE_KEY);
    }

    public function normalizeText(string $value): string
    {
        return trim(preg_replace('/\s+/', ' ', trim($value)) ?? trim($value));
    }

    private function resolveRule(string $sourceTable, ?int $eventId, string $rawEventName): ?TrackingLocalEventRule
    {
        $rules = $this->rules();

        $candidates = [
            fn (TrackingLocalEventRule $rule) => $rule->source_table === $sourceTable && $eventId !== null && (int) $rule->event_id === $eventId,
            fn (TrackingLocalEventRule $rule) => $rule->source_table === $sourceTable && $rawEventName !== '' && $rule->raw_name === $rawEventName,
            fn (TrackingLocalEventRule $rule) => $rule->source_table === '*' && $eventId !== null && (int) $rule->event_id === $eventId,
            fn (TrackingLocalEventRule $rule) => $rule->source_table === '*' && $rawEventName !== '' && $rule->raw_name === $rawEventName,
        ];

        foreach ($candidates as $candidate) {
            $match = $rules->first($candidate);

            if ($match) {
                return $match;
            }
        }

        return null;
    }

    private function rules(): Collection
    {
        return Cache::rememberForever(self::CACHE_KEY, function () {
            return TrackingLocalEventRule::query()
                ->orderByRaw("CASE WHEN source_table = '*' THEN 1 ELSE 0 END")
                ->orderBy('sort_order')
                ->orderBy('source_table')
                ->orderBy('event_id')
                ->orderBy('raw_name')
                ->get();
        });
    }

    private function normalizeSourceTable(?string $sourceTable): string
    {
        $sourceTable = trim((string) $sourceTable);

        return $sourceTable !== '' ? $sourceTable : '*';
    }
}

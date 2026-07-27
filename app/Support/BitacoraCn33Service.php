<?php

namespace App\Support;

use App\Models\Bitacora;
use Carbon\Carbon;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

class BitacoraCn33Service
{
    private const ALERT_START_DATE = '2026-07-17 00:00:00';
    private const EVENTO_ID_SACA_INTERNA_CREADA_SALIDA = 240;
    private const CN33_PREFIX_BY_REGIONAL = [
        'LA PAZ' => 'LPZ',
        'COCHABAMBA' => 'CBB',
        'SANTA CRUZ' => 'SRZ',
        'ORURO' => 'ORU',
        'POTOSI' => 'POI',
        'TARIJA' => 'TJA',
        'SUCRE' => 'SRE',
        'CHUQUISACA' => 'SRE',
        'TRINIDAD' => 'TDD',
        'BENI' => 'TDD',
        'COBIJA' => 'CIJ',
        'PANDO' => 'CIJ',
    ];

    public function getDispatchSummary(string $codEspecial, ?string $regional = null): array
    {
        $codigo = $this->normalizeCode($codEspecial);
        $regional = $this->normalizeRegional($regional);
        if ($codigo === '') {
            return [
                'cod_especial' => '',
                'exists' => false,
                'has_bitacora' => false,
                'total_registros' => 0,
                'peso' => null,
                'precio_total' => null,
                'first_created_at' => null,
                'last_created_at' => null,
                'days_delay' => 0,
            ];
        }

        $summary = DB::query()
            ->fromSub($this->cn33SourceUnionQuery(), 'cn33_source')
            ->selectRaw('
                count(*) as total_registros,
                min(created_at) as first_created_at,
                max(created_at) as last_created_at,
                coalesce(min(dispatch_created_at), min(created_at)) as dispatch_created_at,
                sum(coalesce(peso, 0)) as peso_total,
                sum(coalesce(precio, 0)) as precio_total
            ')
            ->where('cod_especial_normalizado', $codigo)
            ->when($regional !== null, function ($query) use ($regional) {
                $query->where('regional_normalizada', $regional);
            })
            ->first();

        $firstCreatedAt = $summary?->first_created_at ? Carbon::parse($summary->first_created_at) : null;
        $dispatchCreatedAt = $summary?->dispatch_created_at ? Carbon::parse($summary->dispatch_created_at) : $firstCreatedAt;

        return [
            'cod_especial' => $codigo,
            'exists' => (int) ($summary->total_registros ?? 0) > 0,
            'has_bitacora' => Bitacora::query()
                ->whereRaw('trim(upper(cod_especial)) = ?', [$codigo])
                ->when($regional !== null && $this->cn33PrefixForRegional($regional) !== null, function ($query) use ($regional) {
                    $query->whereRaw('trim(upper(cod_especial)) like ?', [$this->cn33PrefixForRegional($regional) . '%']);
                })
                ->exists(),
            'total_registros' => (int) ($summary->total_registros ?? 0),
            'peso' => $summary && $summary->peso_total !== null ? number_format((float) $summary->peso_total, 3, '.', '') : null,
            'precio_total' => $summary && $summary->precio_total !== null ? number_format((float) $summary->precio_total, 2, '.', '') : null,
            'first_created_at' => $firstCreatedAt?->toDateTimeString(),
            'last_created_at' => $summary?->last_created_at ? Carbon::parse($summary->last_created_at)->toDateTimeString() : null,
            'dispatch_created_at' => $dispatchCreatedAt?->toDateTimeString(),
            'days_delay' => $dispatchCreatedAt ? max(0, $dispatchCreatedAt->diffInDays(now())) : 0,
        ];
    }

    public function getPendingRegistrationAlert(int $graceHours = 24, int $previewLimit = 5000, ?string $regional = null): array
    {
        $regional = $this->normalizeRegional($regional);
        $alertStartDate = Carbon::parse(self::ALERT_START_DATE);
        $bitacorasRegistradas = Bitacora::query()
            ->selectRaw('trim(upper(cod_especial)) as cod_especial_normalizado, min(created_at) as bitacora_created_at')
            ->whereNotNull('cod_especial')
            ->whereRaw("trim(cod_especial) <> ''")
            ->when($regional !== null && $this->cn33PrefixForRegional($regional) !== null, function ($query) use ($regional) {
                $query->whereRaw('trim(upper(cod_especial)) like ?', [$this->cn33PrefixForRegional($regional) . '%']);
            })
            ->groupBy(DB::raw('trim(upper(cod_especial))'));

        $pendingBaseQuery = DB::query()
            ->fromSub($this->cn33SourceUnionQuery(), 'cn33_source')
            ->leftJoinSub($bitacorasRegistradas, 'bitacoras_registradas', function ($join) {
                $join->on('bitacoras_registradas.cod_especial_normalizado', '=', 'cn33_source.cod_especial_normalizado');
            })
            ->selectRaw('
                cn33_source.cod_especial_normalizado as cod_especial,
                cn33_source.regional_normalizada as regional,
                min(cn33_source.created_at) as first_created_at,
                max(cn33_source.created_at) as last_created_at,
                coalesce(min(cn33_source.dispatch_created_at), min(cn33_source.created_at)) as dispatch_created_at,
                sum(coalesce(cn33_source.peso, 0)) as peso_total,
                sum(coalesce(cn33_source.precio, 0)) as precio_total,
                count(*) as total_registros
            ')
            ->whereNull('bitacoras_registradas.bitacora_created_at')
            ->when($regional !== null, function ($query) use ($regional) {
                $query->where('cn33_source.regional_normalizada', $regional);
            })
            ->groupBy('cn33_source.cod_especial_normalizado', 'cn33_source.regional_normalizada')
            ->havingRaw('coalesce(min(cn33_source.dispatch_created_at), min(cn33_source.created_at)) >= ?', [$alertStartDate])
            ->havingRaw('coalesce(min(cn33_source.dispatch_created_at), min(cn33_source.created_at)) <= ?', [now()->subHours($graceHours)])
            ->orderByRaw('coalesce(min(cn33_source.dispatch_created_at), min(cn33_source.created_at)) asc');

        $rows = (clone $pendingBaseQuery)
            ->limit($previewLimit)
            ->get()
            ->map(function ($row) {
                $firstCreatedAt = Carbon::parse($row->first_created_at);
                $dispatchCreatedAt = !empty($row->dispatch_created_at) ? Carbon::parse($row->dispatch_created_at) : $firstCreatedAt;
                $daysDelay = max(1, $dispatchCreatedAt->diffInDays(now()));

                return (object) [
                    'cod_especial' => (string) ($row->cod_especial ?? ''),
                    'numero_despacho' => (string) ($row->cod_especial ?? ''),
                    'regional' => (string) ($row->regional ?? ''),
                    'first_created_at' => $firstCreatedAt,
                    'last_created_at' => !empty($row->last_created_at) ? Carbon::parse($row->last_created_at) : null,
                    'dispatch_created_at' => $dispatchCreatedAt,
                    'peso_total' => round((float) ($row->peso_total ?? 0), 3),
                    'precio_total' => round((float) ($row->precio_total ?? 0), 2),
                    'total_registros' => (int) ($row->total_registros ?? 0),
                    'days_delay' => $daysDelay,
                ];
            })
            ->values();

        $pendingCount = (int) $rows->count();

        if ($pendingCount === 0) {
            return [
                'count' => 0,
                'grace_hours' => $graceHours,
                'max_days_delay' => 0,
                'regional' => $regional,
                'alert_start_date' => $alertStartDate,
                'rows' => collect(),
            ];
        }

        return [
            'count' => $pendingCount,
            'grace_hours' => $graceHours,
            'max_days_delay' => (int) $rows->max('days_delay'),
            'regional' => $regional,
            'alert_start_date' => $alertStartDate,
            'rows' => $rows,
        ];
    }

    private function sourceUnionQuery(): Builder
    {
        $ems = DB::table('paquetes_ems')
            ->leftJoinSub($this->dispatchEventsSubquery('eventos_ems'), 'ems_dispatch_events', function ($join) {
                $join->on('ems_dispatch_events.codigo_normalizado', '=', DB::raw('trim(upper(paquetes_ems.codigo))'));
            })
            ->selectRaw("
                trim(upper(cod_especial)) as cod_especial_normalizado,
                trim(upper(coalesce(origen, ''))) as regional_normalizada,
                paquetes_ems.created_at,
                ems_dispatch_events.dispatch_created_at as dispatch_created_at,
                coalesce(paquetes_ems.peso, 0) as peso,
                coalesce(paquetes_ems.precio, 0) as precio
            ")
            ->whereNotNull('cod_especial')
            ->whereRaw("trim(cod_especial) <> ''");

        $contratos = DB::table('paquetes_contrato')
            ->leftJoinSub($this->dispatchEventsSubquery('eventos_contrato'), 'contrato_dispatch_events', function ($join) {
                $join->on('contrato_dispatch_events.codigo_normalizado', '=', DB::raw('trim(upper(paquetes_contrato.codigo))'));
            })
            ->selectRaw("
                trim(upper(cod_especial)) as cod_especial_normalizado,
                trim(upper(coalesce(origen, ''))) as regional_normalizada,
                paquetes_contrato.created_at,
                contrato_dispatch_events.dispatch_created_at as dispatch_created_at,
                coalesce(paquetes_contrato.peso, 0) as peso,
                coalesce(paquetes_contrato.precio, 0) as precio
            ")
            ->whereNotNull('cod_especial')
            ->whereRaw("trim(cod_especial) <> ''");

        $ordinarios = DB::table('paquetes_ordi')
            ->selectRaw("
                trim(upper(cod_especial)) as cod_especial_normalizado,
                trim(upper(coalesce(ciudad, ''))) as regional_normalizada,
                created_at,
                created_at as dispatch_created_at,
                coalesce(peso, 0) as peso,
                coalesce(precio, 0) as precio
            ")
            ->whereNotNull('cod_especial')
            ->whereRaw("trim(cod_especial) <> ''");

        $certificados = DB::table('paquetes_certi')
            ->selectRaw("
                trim(upper(cod_especial)) as cod_especial_normalizado,
                trim(upper(coalesce(cuidad, ''))) as regional_normalizada,
                created_at,
                created_at as dispatch_created_at,
                coalesce(peso, 0) as peso,
                coalesce(precio, 0) as precio
            ")
            ->whereNotNull('cod_especial')
            ->whereRaw("trim(cod_especial) <> ''");

        $ems->unionAll($contratos);
        $ems->unionAll($ordinarios);
        $ems->unionAll($certificados);

        return $ems;
    }

    private function cn33SourceUnionQuery(): Builder
    {
        $emsRegionalExpression = $this->cn33RegionalExpressionSql("trim(upper(coalesce(origen, '')))");
        $contratoRegionalExpression = $this->cn33RegionalExpressionSql("trim(upper(coalesce(origen, '')))");

        $ems = DB::table('paquetes_ems')
            ->leftJoinSub($this->dispatchEventsSubquery('eventos_ems'), 'ems_dispatch_events', function ($join) {
                $join->on('ems_dispatch_events.codigo_normalizado', '=', DB::raw('trim(upper(paquetes_ems.codigo))'));
            })
            ->selectRaw("
                trim(upper(cod_especial)) as cod_especial_normalizado,
                {$emsRegionalExpression} as regional_normalizada,
                paquetes_ems.created_at,
                ems_dispatch_events.dispatch_created_at as dispatch_created_at,
                coalesce(paquetes_ems.peso, 0) as peso,
                coalesce(paquetes_ems.precio, 0) as precio
            ")
            ->whereNotNull('cod_especial')
            ->whereRaw("trim(cod_especial) <> ''");

        $contratos = DB::table('paquetes_contrato')
            ->leftJoinSub($this->dispatchEventsSubquery('eventos_contrato'), 'contrato_dispatch_events', function ($join) {
                $join->on('contrato_dispatch_events.codigo_normalizado', '=', DB::raw('trim(upper(paquetes_contrato.codigo))'));
            })
            ->selectRaw("
                trim(upper(cod_especial)) as cod_especial_normalizado,
                {$contratoRegionalExpression} as regional_normalizada,
                paquetes_contrato.created_at,
                contrato_dispatch_events.dispatch_created_at as dispatch_created_at,
                coalesce(paquetes_contrato.peso, 0) as peso,
                coalesce(paquetes_contrato.precio, 0) as precio
            ")
            ->whereNotNull('cod_especial')
            ->whereRaw("trim(cod_especial) <> ''");

        $ems->unionAll($contratos);

        return $ems;
    }

    private function normalizeCode(string $codEspecial): string
    {
        return strtoupper(trim($codEspecial));
    }

    private function normalizeRegional(?string $regional): ?string
    {
        $value = strtoupper(trim((string) $regional));

        return $value !== '' ? $value : null;
    }

    private function cn33PrefixForRegional(string $regional): ?string
    {
        return self::CN33_PREFIX_BY_REGIONAL[strtoupper(trim($regional))] ?? null;
    }

    private function cn33RegionalExpressionSql(string $fallbackRegionalExpression): string
    {
        $codeExpression = 'trim(upper(cod_especial))';
        $cases = [];

        foreach (self::CN33_PREFIX_BY_REGIONAL as $regional => $prefix) {
            if ($regional === 'CHUQUISACA' || $regional === 'BENI' || $regional === 'PANDO') {
                continue;
            }

            $cases[] = "when {$codeExpression} like '{$prefix}%' then '{$regional}'";
        }

        return 'case ' . implode(' ', $cases) . " else {$fallbackRegionalExpression} end";
    }

    private function dispatchEventsSubquery(string $eventTable): Builder
    {
        return DB::table($eventTable)
            ->selectRaw('trim(upper(codigo)) as codigo_normalizado, min(created_at) as dispatch_created_at')
            ->where('evento_id', self::EVENTO_ID_SACA_INTERNA_CREADA_SALIDA)
            ->groupBy(DB::raw('trim(upper(codigo))'));
    }
}

<?php

namespace App\Services;

use App\Mail\DailyClosingMail;
use App\Models\AppSetting;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

class DailyClosingMailService
{
    public const ENABLED_SETTING = 'operations.daily_closing_enabled';

    public const LAST_SENT_SETTING = 'operations.daily_closing_last_sent';

    public function automaticSendingEnabled(): bool
    {
        return AppSetting::getValue(self::ENABLED_SETTING, '1') === '1';
    }

    public function setAutomaticSendingEnabled(bool $enabled): void
    {
        AppSetting::setValue(self::ENABLED_SETTING, $enabled ? '1' : '0');
    }

    public function report(?CarbonImmutable $cutoff = null): array
    {
        $cutoff ??= CarbonImmutable::now('America/La_Paz');
        // Stored timestamps follow the application's timezone; the closing day is Bolivian.
        $start = $cutoff->startOfDay()->setTimezone(config('app.timezone'));
        $end = $cutoff->setTimezone(config('app.timezone'));
        $modules = [];
        foreach ([
            ['Contratos', 'paquetes_contrato', 'estados_id', 'eventos_contrato', 'id_paquetes_contrato', 'destino'],
            ['EMS', 'paquetes_ems', 'estado_id', 'eventos_ems', 'id_paquetes_ems', 'ciudad'],
        ] as [$label, $table, $state, $events, $assignment, $destination]) {
            $activity = DB::table($events.' as ev')
                ->leftJoin('eventos as definition', 'definition.id', '=', 'ev.evento_id')
                ->whereBetween('ev.created_at', [$start, $end])
                ->select('ev.evento_id', 'definition.nombre_evento')
                ->selectRaw('COUNT(*) as movimientos, COUNT(DISTINCT ev.codigo) as paquetes')
                ->groupBy('ev.evento_id', 'definition.nombre_evento')->orderBy('ev.evento_id')->get();

            // Only the latest assignment is the current owner, even when history remains.
            $latest = DB::table('cartero')->whereNotNull($assignment)
                ->select($assignment)->selectRaw('MAX(id) as latest_id')->groupBy($assignment);
            $pending = DB::table($table.' as pkg')
                ->leftJoin('estados as state', 'state.id', '=', 'pkg.'.$state)
                ->leftJoinSub($latest, 'latest', fn ($join) => $join->on('latest.'.$assignment, '=', 'pkg.id'))
                ->leftJoin('cartero as owner', 'owner.id', '=', 'latest.latest_id')
                ->leftJoin('estados as owner_state', 'owner_state.id', '=', 'owner.id_estados')
                ->leftJoin('users as courier', 'courier.id', '=', 'owner.id_user')
                ->where('pkg.created_at', '<=', $end)
                ->where(fn ($query) => $query->whereNull('state.nombre_estado')->orWhereRaw("UPPER(TRIM(state.nombre_estado)) NOT IN ('ENTREGADO', 'CANCELADO')"))
                ->select('pkg.codigo', 'pkg.origen', 'pkg.'.$destination.' as destino', 'state.nombre_estado as estado', 'pkg.created_at')
                ->selectRaw($table === 'paquetes_contrato'
                    ? 'pkg.provincia_origen, pkg.provincia as provincia_destino'
                    : 'NULL as provincia_origen, NULL as provincia_destino')
                ->selectRaw("CASE WHEN UPPER(TRIM(state.nombre_estado)) = 'CARTERO' AND UPPER(TRIM(owner_state.nombre_estado)) = 'CARTERO' THEN courier.name ELSE NULL END as cartero")
                ->selectRaw("CASE WHEN UPPER(TRIM(state.nombre_estado)) = 'CARTERO' AND UPPER(TRIM(owner_state.nombre_estado)) = 'CARTERO' THEN courier.id ELSE NULL END as cartero_id")
                ->orderBy('pkg.created_at')->orderBy('pkg.id')->get();
            $history = collect();
            foreach ($pending->pluck('codigo')->unique()->chunk(500) as $codes) {
                $history = $history->concat(DB::table($events.' as ev')
                    ->leftJoin('eventos as definition', 'definition.id', '=', 'ev.evento_id')
                    ->leftJoin('users as actor', 'actor.id', '=', 'ev.user_id')
                    ->whereIn('ev.codigo', $codes->all())
                    ->where('ev.created_at', '<=', $end)
                    ->select('ev.codigo', 'ev.evento_id', 'ev.created_at', 'definition.nombre_evento', 'actor.name as user_name')
                    ->orderBy('ev.created_at')->orderBy('ev.id')->get());
            }
            $historyByCode = $history->groupBy('codigo');
            foreach ($pending as $row) {
                $row->history = $historyByCode->get($row->codigo, collect())->map(fn ($event) => [
                    'event' => $event->nombre_evento ?? 'Evento '.$event->evento_id,
                    'user' => trim((string) $event->user_name) ?: 'Usuario no disponible',
                    'date' => CarbonImmutable::parse($event->created_at, config('app.timezone'))
                        ->setTimezone('America/La_Paz')->format('d/m/Y H:i:s'),
                ])->all();
            }
            $couriers = $pending->whereNotNull('cartero_id')->groupBy('cartero_id')->map(fn ($rows) => [
                'name' => $rows->first()->cartero,
                'pending' => $rows->count(),
            ])->values();
            $modules[] = [
                'name' => $label,
                'registered' => DB::table($table)->whereBetween('created_at', [$start, $end])->count(),
                'delivered' => DB::table($events)->where('evento_id', 316)->whereBetween('created_at', [$start, $end])->distinct()->count('codigo'),
                'activity' => $activity,
                'pending' => $pending,
                'couriers' => $couriers,
                'unassigned' => $pending->whereNull('cartero_id')->count(),
            ];
        }

        return ['date' => $cutoff->toDateString(), 'cutoff' => $cutoff->format('d/m/Y H:i'), 'modules' => $modules];
    }

    public function send(array $recipients, ?array $report = null, bool $automatic = false): int
    {
        $report ??= $this->report();
        foreach ($recipients as $recipient) {
            Mail::to($recipient)->send(new DailyClosingMail($report));
        }
        // A manual preview must not suppress the scheduled evening closing.
        if ($automatic && $recipients !== []) {
            AppSetting::setValue(self::LAST_SENT_SETTING, $report['date']);
        }

        return count($recipients);
    }
}

<?php

namespace App\Services;

use App\Mail\DailyClosingMail;
use App\Models\AppSetting;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

class DailyClosingMailService
{
    public const RECIPIENTS_SETTING = 'operations.daily_closing_email_recipients';

    public const ENABLED_SETTING = 'operations.daily_closing_enabled';

    public const LAST_SENT_SETTING = 'operations.daily_closing_last_sent';

    public function recipients(): array
    {
        $decoded = json_decode((string) AppSetting::getValue(self::RECIPIENTS_SETTING, '[]'), true);

        if (! is_array($decoded)) {
            return [];
        }

        return collect($decoded)
            ->filter(fn ($email) => is_string($email) && filter_var($email, FILTER_VALIDATE_EMAIL))
            ->map(fn (string $email) => mb_strtolower(trim($email)))
            ->unique()
            ->values()
            ->all();
    }

    public function saveRecipients(array $recipients): void
    {
        AppSetting::setValue(self::RECIPIENTS_SETTING, json_encode(array_values($recipients)));
    }

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
            $movements = DB::table($events.' as ev')
                ->join($table.' as pkg', 'pkg.codigo', '=', 'ev.codigo')
                ->leftJoin('eventos as definition', 'definition.id', '=', 'ev.evento_id')
                ->leftJoin('users as actor', 'actor.id', '=', 'ev.user_id')
                ->leftJoin('estados as state', 'state.id', '=', 'pkg.'.$state)
                ->leftJoinSub($latest, 'movement_latest', fn ($join) => $join->on('movement_latest.'.$assignment, '=', 'pkg.id'))
                ->leftJoin('cartero as owner', 'owner.id', '=', 'movement_latest.latest_id')
                ->leftJoin('users as courier', 'courier.id', '=', 'owner.id_user')
                ->whereBetween('ev.created_at', [$start, $end])
                ->select(
                    'ev.id as movement_id',
                    'ev.codigo',
                    'ev.evento_id',
                    'ev.created_at',
                    'definition.nombre_evento',
                    'actor.name as user_name',
                    'pkg.origen',
                    'pkg.'.$destination.' as destino',
                    'state.nombre_estado as estado',
                    'courier.name as cartero'
                )
                ->selectRaw($table === 'paquetes_contrato'
                    ? 'pkg.provincia_origen, pkg.provincia as provincia_destino'
                    : 'NULL as provincia_origen, NULL as provincia_destino')
                ->orderBy('ev.created_at')
                ->orderBy('ev.id')
                ->get();
            foreach ($movements as $movement) {
                $movement->event_date = CarbonImmutable::parse($movement->created_at, config('app.timezone'))
                    ->setTimezone('America/La_Paz')->format('d/m/Y H:i:s');
            }
            $dailyPackages = $movements->groupBy('codigo')->map(function ($packageMovements) {
                $package = $packageMovements->first();

                return (object) [
                    'codigo' => $package->codigo,
                    'origen' => $package->origen,
                    'provincia_origen' => $package->provincia_origen,
                    'destino' => $package->destino,
                    'provincia_destino' => $package->provincia_destino,
                    'estado' => $package->estado,
                    'cartero' => $package->cartero,
                    'timeline' => $packageMovements->values()->map(fn ($movement, $index) => ($index + 1).') '.implode(' · ', [
                        $movement->event_date,
                        $movement->nombre_evento ?? 'Evento '.$movement->evento_id,
                        $movement->user_name ?: 'Usuario no disponible',
                    ]))->implode("\n"),
                ];
            })->values();
            $modules[] = [
                'name' => $label,
                'registered' => DB::table($table)->whereBetween('created_at', [$start, $end])->count(),
                'delivered' => DB::table($events)->where('evento_id', 316)->whereBetween('created_at', [$start, $end])->distinct()->count('codigo'),
                'activity' => $activity,
                'movements' => $movements,
                'daily_packages' => $dailyPackages,
                'moved_packages' => $movements->pluck('codigo')->unique()->count(),
            ];
        }

        return [
            'date' => $cutoff->toDateString(),
            'cutoff' => $cutoff->format('d/m/Y H:i'),
            'is_full_day' => $cutoff->isEndOfDay(),
            'modules' => $modules,
        ];
    }

    public function reportForDate(string $date): array
    {
        $selectedDay = CarbonImmutable::createFromFormat('!Y-m-d', $date, 'America/La_Paz');
        $now = CarbonImmutable::now('America/La_Paz');
        $cutoff = $selectedDay->isSameDay($now) ? $now : $selectedDay->endOfDay();

        return $this->report($cutoff);
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

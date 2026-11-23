<?php

namespace App\Services;

use App\Models\Estado;
use App\Models\User;
use App\Support\CarteroEvent;
use App\Support\ChasquiCartero;
use App\Support\ContractReportScope;
use App\Support\EncargadoEvent;
use Carbon\CarbonImmutable;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class DailyDeliveryReport
{
    public const TIMEZONE = 'America/La_Paz';

    private const REGIONAL_DESTINATIONS = [
        'COBIJA' => ['PANDO'],
        'COCHABAMBA' => ['COCHABAMBA IVIRGARZAMA'],
        'LA PAZ' => [],
        'ORURO' => [],
        'POTOSI' => ['POTOSÍ'],
        'SANTA CRUZ' => ['SANTA CRUZ DE LA SIERRA'],
        'SUCRE' => ['CHUQUISACA'],
        'TARIJA' => [],
        'TRINIDAD' => ['BENI', 'RIBERALTA', 'RURRENABAQUE', 'TRINIDAD-RIBERALTA'],
    ];

    private const MODULES = [
        'ems' => ['EMS', 'paquetes_ems', 'estado_id', 'eventos_ems', 'ciudad'],
        'contratos' => ['Contratos', 'paquetes_contrato', 'estados_id', 'eventos_contrato', 'destino'],
    ];

    public static function regionals(): array
    {
        return array_keys(self::REGIONAL_DESTINATIONS);
    }

    public static function regionalFor(?string $destination): ?string
    {
        $destination = mb_strtoupper(trim((string) $destination));
        foreach (self::REGIONAL_DESTINATIONS as $regional => $aliases) {
            if ($destination === $regional || in_array($destination, $aliases, true)) {
                return $regional;
            }
        }

        return null;
    }

    public function forDate(string $date, ?string $regional = null): array
    {
        return $this->through($this->cutoffForDate($date), $regional);
    }

    public function cutoffForDate(string $date): CarbonImmutable
    {
        $day = CarbonImmutable::createFromFormat('!Y-m-d', $date, self::TIMEZONE);
        $now = CarbonImmutable::now(self::TIMEZONE);

        return $day->isSameDay($now) ? $now : $day->endOfDay();
    }

    public function through(CarbonImmutable $cutoff, ?string $regional = null): array
    {
        $cutoff = $cutoff->setTimezone(self::TIMEZONE);
        $period = [
            $cutoff->startOfDay()->setTimezone(config('app.timezone')),
            $cutoff->setTimezone(config('app.timezone')),
        ];
        $canceledId = Estado::query()->whereRaw("TRIM(UPPER(nombre_estado)) = 'CANCELADO'")->value('id');
        // Historical reassignment text can refer to an inactive user with the same name.
        $users = User::withTrashed()->orderBy('name')->orderBy('id')->get(['id', 'name', 'ciudad', 'deleted_at'])->keyBy('id');
        $usersByName = $users->groupBy(fn (User $user) => mb_strtolower(trim($user->name)));
        $courierIds = User::query()
            ->whereHas('roles', fn ($roles) => $roles->whereIn(DB::raw('LOWER(roles.name)'), ChasquiCartero::ROLES))
            ->pluck('users.id');

        $modules = [];
        $activity = [];
        foreach (self::MODULES as $key => [$label, $table, $stateColumn, $eventTable, $destinationColumn]) {
            $packages = DB::table($table.' as pkg');
            if ($regional !== null) {
                $aliases = self::REGIONAL_DESTINATIONS[$regional] ?? null;
                $destinations = $aliases === null ? [] : [$regional, ...$aliases];
                $destination = DB::raw("UPPER(TRIM(REPLACE(pkg.{$destinationColumn}, 'í', 'Í')))");
                $packages->whereIn($destination, $destinations);
            }
            if ($canceledId !== null) {
                $packages->where(fn (Builder $q) => $q->whereNull('pkg.'.$stateColumn)->orWhere('pkg.'.$stateColumn, '<>', $canceledId));
            }
            if ($key === 'contratos') {
                $packages->whereNotExists(ContractReportScope::excludedCompanyQuery('pkg'));
            }

            $events = (clone $packages)
                ->join($eventTable.' as ev', 'ev.codigo', '=', 'pkg.codigo')
                ->whereBetween('ev.created_at', $period);
            $assigned = $this->assignments($events, $usersByName);
            $delivered = (clone $events)->where('ev.evento_id', CarteroEvent::ENTREGADO_ID)
                ->distinct()->get(['ev.codigo', 'ev.user_id']);

            $modules[$key] = [
                'name' => $label,
                'registered' => (clone $packages)->whereBetween('pkg.created_at', $period)->distinct()->count('pkg.codigo'),
                'assigned' => $assigned->unique('codigo')->count(),
                'delivered' => $delivered->unique('codigo')->count(),
            ];

            foreach (['assigned' => $assigned, 'delivered' => $delivered] as $metric => $rows) {
                foreach ($rows->groupBy('user_id') as $userId => $userRows) {
                    if ($userId !== '') {
                        $activity[$userId][$key][$metric] = $userRows->unique('codigo')->count();
                    }
                }
            }
        }

        $couriers = $users->only($courierIds->merge(array_keys($activity))->all())
            ->reject(fn (User $user) => $user->trashed())
            ->filter(fn (User $user) => $regional === null || self::regionalFor($user->ciudad) === $regional)
            ->map(function (User $user) use ($activity) {
                $counts = [];
                foreach (array_keys(self::MODULES) as $key) {
                    $counts[$key] = [
                        'assigned' => $activity[$user->id][$key]['assigned'] ?? 0,
                        'delivered' => $activity[$user->id][$key]['delivered'] ?? 0,
                    ];
                }

                return [
                    'name' => $user->name,
                    'department' => trim((string) $user->ciudad) ?: 'Sin departamento',
                    'modules' => $counts,
                    'assigned' => array_sum(array_column($counts, 'assigned')),
                    'delivered' => array_sum(array_column($counts, 'delivered')),
                ];
            })
            ->sortBy([['delivered', 'desc'], ['assigned', 'desc'], ['name', 'asc']])
            ->values();

        return [
            'date' => $cutoff->toDateString(),
            'cutoff' => $cutoff->format('d/m/Y H:i:s'),
            'regional' => $regional,
            'modules' => $modules,
            'totals' => [
                'registered' => array_sum(array_column($modules, 'registered')),
                'assigned' => array_sum(array_column($modules, 'assigned')),
                'delivered' => array_sum(array_column($modules, 'delivered')),
            ],
            'couriers' => $couriers,
        ];
    }

    private function assignments(Builder $events, Collection $usersByName): Collection
    {
        $catalog = (clone $events)->join('eventos as definition', 'definition.id', '=', 'ev.evento_id');
        $initial = clone $catalog;
        CarteroEvent::filterAssignments($initial, 'definition.nombre_evento');
        $assigned = $initial->distinct()->get(['ev.codigo', 'ev.user_id']);

        $changes = $catalog->where(function (Builder $query): void {
            $query->whereIn('definition.nombre_evento', [CarteroEvent::CAMBIADO, EncargadoEvent::CARTERO_CAMBIADO])
                ->orWhere('definition.nombre_evento', 'like', 'Cambio de cartero realizado por %')
                ->orWhere('definition.nombre_evento', 'like', '%Asigno al cartero: %');
        })->get(['ev.codigo', 'ev.detalle_evento', 'definition.nombre_evento']);

        return $assigned->merge($changes->map(function ($event) use ($usersByName) {
            // Changes store the operator in user_id and the new cartero in the event detail.
            $text = trim((string) ($event->detalle_evento ?: $event->nombre_evento));
            preg_match('/(?:\. De .* a |\. Asigno al cartero: )(.+)\.$/iu', $text, $match);
            $name = mb_strtolower(trim($match[1] ?? ''));
            $matches = $usersByName->get($name, collect());

            return (object) [
                'codigo' => $event->codigo,
                'user_id' => $matches->count() === 1 ? $matches->first()->id : null,
            ];
        }));
    }
}

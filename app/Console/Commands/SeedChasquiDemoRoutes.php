<?php

namespace App\Console\Commands;

use App\Models\ChasquiLocationPoint;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

class SeedChasquiDemoRoutes extends Command
{
    protected $signature = 'chasqui:seed-demo-routes {--days=14 : Cantidad de dias anteriores a hoy}';

    protected $description = 'Crea recorridos ficticios diarios para los carteros de demostracion';

    public function handle(): int
    {
        $days = max(1, min(90, (int) $this->option('days')));
        $carteros = User::query()
            ->whereIn('alias', ['jvargas', 'jfernandez'])
            ->get()
            ->keyBy('alias');

        if (! $carteros->has('jvargas') || ! $carteros->has('jfernandez')) {
            $this->error('No se encontraron los usuarios jvargas y jfernandez.');

            return self::FAILURE;
        }

        $routeTemplates = [
            'jvargas' => [
                [
                    [-16.4994051, -68.1352510], [-16.5049500, -68.1293000],
                    [-16.5124500, -68.1266500], [-16.5193500, -68.1202500],
                    [-16.5288500, -68.1098500], [-16.4994051, -68.1352510],
                ],
                [
                    [-16.4994051, -68.1352510], [-16.5052000, -68.1438000],
                    [-16.5126000, -68.1515000], [-16.5214000, -68.1588000],
                    [-16.4994051, -68.1352510],
                ],
                [
                    [-16.4994051, -68.1352510], [-16.5005000, -68.1260000],
                    [-16.5046000, -68.1172000], [-16.5115000, -68.1086000],
                    [-16.4994051, -68.1352510],
                ],
            ],
            'jfernandez' => [
                [
                    [-16.4994051, -68.1352510], [-16.4963000, -68.1261000],
                    [-16.4901500, -68.1194500], [-16.4827500, -68.1132000],
                    [-16.4758000, -68.1069000], [-16.4994051, -68.1352510],
                ],
                [
                    [-16.4994051, -68.1352510], [-16.4943000, -68.1435000],
                    [-16.4882000, -68.1512000], [-16.4805000, -68.1582000],
                    [-16.4994051, -68.1352510],
                ],
                [
                    [-16.4994051, -68.1352510], [-16.5073000, -68.1301000],
                    [-16.5162000, -68.1213000], [-16.5247000, -68.1121000],
                    [-16.4994051, -68.1352510],
                ],
            ],
        ];

        $now = now();
        $rows = [];
        $roadRoutes = [];

        $this->info('Calculando recorridos sobre calles reales...');
        foreach ($routeTemplates as $alias => $templates) {
            foreach ($templates as $templateIndex => $waypoints) {
                $roadRoutes[$alias][$templateIndex] = $this->fetchRoadRoute($waypoints);
            }
        }

        foreach (range(1, $days) as $daysAgo) {
            $date = $now->copy()->subDays($daysAgo)->startOfDay();

            foreach ($roadRoutes as $alias => $routes) {
                $user = $carteros->get($alias);
                $points = $routes[($daysAgo - 1) % count($routes)];
                $startedAt = $date->copy()->addHours(8)->addMinutes($alias === 'jvargas' ? 10 : 25);
                $locationId = $user->id.':demo-history';

                foreach ($points as $index => [$latitude, $longitude]) {
                    $sentAt = $startedAt->copy()->addMinutes($index * 2);
                    $rows[] = [
                        'location_id' => $locationId,
                        'sent_at' => $sentAt,
                        'user_id' => (int) $user->id,
                        'device_id' => 'demo-history-'.$alias,
                        'device_name' => 'Ruta ficticia EMS',
                        'latitude' => $latitude,
                        'longitude' => $longitude,
                        'accuracy_m' => 6.0 + (($index + $daysAgo) % 5),
                        'speed_kmh' => $index === 0 || $index === array_key_last($points)
                            ? 0
                            : 8 + (($index + $daysAgo) % 10),
                        'heading' => null,
                        'battery_percent' => max(35, 96 - $index),
                        'is_moving' => $index > 0 && $index < array_key_last($points),
                        'gps_enabled' => true,
                        'gps_mocked' => false,
                        'is_simulated' => true,
                        'route_label' => 'Ruta ficticia EMS '.$date->toDateString(),
                        'received_at' => $sentAt,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];
                }
            }
        }

        $userIds = $carteros->pluck('id')->map(fn ($id) => (int) $id)->all();
        $from = $now->copy()->subDays($days)->startOfDay();
        $until = $now->copy()->subDay()->endOfDay();

        DB::transaction(function () use ($userIds, $from, $until, $rows): void {
            ChasquiLocationPoint::query()
                ->whereIn('user_id', $userIds)
                ->where('is_simulated', true)
                ->whereBetween('sent_at', [$from, $until])
                ->delete();

            collect($rows)->chunk(500)->each(function ($chunk): void {
                ChasquiLocationPoint::query()->insert($chunk->all());
            });
        });

        $inserted = count($rows);

        $this->info("Recorridos ficticios listos: {$days} dias, 2 carteros, {$inserted} puntos nuevos.");

        return self::SUCCESS;
    }

    /**
     * @param  array<int, array{0: float, 1: float}>  $waypoints
     * @return array<int, array{0: float, 1: float}>
     */
    private function fetchRoadRoute(array $waypoints): array
    {
        $coordinates = collect($waypoints)
            ->map(fn (array $point): string => $point[1].','.$point[0])
            ->implode(';');
        $url = 'https://router.project-osrm.org/route/v1/driving/'.$coordinates;
        $response = Http::withHeaders(['User-Agent' => 'Bolipost route demo generator'])
            ->timeout(40)
            ->retry(3, 750)
            ->get($url, [
                'overview' => 'full',
                'geometries' => 'geojson',
                'steps' => 'false',
            ])
            ->throw();
        $coordinates = $response->json('routes.0.geometry.coordinates', []);

        if (count($coordinates) < 2) {
            throw new \RuntimeException('El servicio de rutas no devolvio una geometria valida.');
        }

        $step = max(1, (int) ceil(count($coordinates) / 180));
        $points = collect($coordinates)
            ->filter(fn (array $point, int $index): bool => $index % $step === 0)
            ->map(fn (array $point): array => [(float) $point[1], (float) $point[0]])
            ->values()
            ->all();
        $last = $coordinates[array_key_last($coordinates)];
        $lastPoint = [(float) $last[1], (float) $last[0]];

        if ($points[array_key_last($points)] !== $lastPoint) {
            $points[] = $lastPoint;
        }

        return $points;
    }
}

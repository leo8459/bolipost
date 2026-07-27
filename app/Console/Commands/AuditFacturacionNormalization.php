<?php

namespace App\Console\Commands;

use App\Http\Controllers\MisVentasController;
use App\Models\User;
use App\Services\FacturacionCartService;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

class AuditFacturacionNormalization extends Command
{
    protected $signature = 'facturacion:auditar-normalizacion
        {regional : Regional a revisar, por ejemplo TRINIDAD}
        {--from=2020-01-01 : Fecha inicial}
        {--to=2030-12-31 : Fecha final}
        {--limit=500 : Limite por consulta al bridge}';

    protected $description = 'Audita ventas de facturacion por regional y propone normalizacion sin aplicar cambios.';

    public function handle(FacturacionCartService $service): int
    {
        $regional = strtoupper(trim((string) $this->argument('regional')));
        if ($regional === '') {
            $this->error('Debes indicar una regional valida.');

            return self::FAILURE;
        }

        $users = User::query()
            ->with('sucursal')
            ->orderBy('name')
            ->get()
            ->filter(fn (User $user) => $user->can('feature.dashboard.facturacion'))
            ->filter(fn (User $user) => $this->userBelongsToRegional($user, $regional))
            ->values();

        if ($users->isEmpty()) {
            $this->warn("No se encontraron cajeros con facturacion en la regional {$regional}.");

            return self::SUCCESS;
        }

        $filters = [
            'estado' => 'all',
            'estado_emision' => 'all',
            'from' => (string) $this->option('from'),
            'to' => (string) $this->option('to'),
            'q' => '',
            'per_page' => 100,
            'page' => 1,
            'limite' => max(50, min(500, (int) $this->option('limit'))),
        ];

        $controller = app(MisVentasController::class);
        $normalizeRows = new \ReflectionMethod(MisVentasController::class, 'normalizeKardexRows');
        $normalizeRows->setAccessible(true);
        $applyFilters = new \ReflectionMethod(MisVentasController::class, 'applyLocalFilters');
        $applyFilters->setAccessible(true);
        $normalizedKey = new \ReflectionMethod(MisVentasController::class, 'normalizedVentaRowKey');
        $normalizedKey->setAccessible(true);

        $allRaw = collect();
        $processedUsers = collect();

        foreach ($users as $user) {
            $this->line("Leyendo {$user->name}...");

            try {
                $kardex = $service->fetchKardexVentas($user, $filters);
                $ventas = $service->fetchVentas($user, $filters);

                $raw = collect($ventas['carts'] ?? [])
                    ->concat(collect($kardex['detalle'] ?? []))
                    ->map(fn ($row) => is_array($row) ? (object) $row : $row)
                    ->filter(fn ($row) => is_object($row))
                    ->values();

                $allRaw = $allRaw->concat($raw);
                $processedUsers->push([
                    'id' => $user->id,
                    'usuario' => $user->name,
                    'sucursal' => $this->userSucursalName($user),
                    'regional' => $regional,
                ]);
            } catch (\Throwable $e) {
                $this->warn("No se pudo leer {$user->name}: {$e->getMessage()}");
            }
        }

        $rows = $normalizeRows->invoke($controller, $allRaw)
            ->unique(fn ($row) => $normalizedKey->invoke($controller, $row))
            ->values();
        $rows = $applyFilters->invoke($controller, $rows, $filters);

        $facturadas = $rows->filter(
            fn ($row) => strtoupper(trim((string) data_get($row, 'estado_emision', ''))) === 'FACTURADA'
        )->values();

        $auditRows = $facturadas->flatMap(function ($venta) {
            $items = collect((array) data_get($venta, 'items', []))
                ->map(fn ($item) => is_array($item) ? (object) $item : $item)
                ->filter(fn ($item) => is_object($item))
                ->values();

            return $items->map(function ($item) use ($venta) {
                $proposal = $this->proposeNormalization($item);
                $currentTitle = trim((string) data_get($item, 'titulo', ''));
                $currentService = trim((string) data_get($item, 'nombre_servicio', ''));
                $currentDescription = trim((string) data_get($item, 'resumen_origen.descripcion_servicio', ''));

                return [
                    'venta_id' => (int) data_get($venta, 'id', 0),
                    'codigo_orden' => trim((string) data_get($venta, 'codigo_orden', '')),
                    'emitido_en' => trim((string) data_get($venta, 'emitido_en', data_get($venta, 'created_at', ''))),
                    'numero_factura' => trim((string) data_get($venta, 'respuesta_emision.factura.nroFactura', '')),
                    'estado_emision' => trim((string) data_get($venta, 'estado_emision', '')),
                    'item_id' => (int) data_get($item, 'id', 0),
                    'codigo_item' => trim((string) data_get($item, 'codigo', '')),
                    'codigo_paquete' => trim((string) data_get($item, 'resumen_origen.codigo_paquete', data_get($item, 'codigo_paquete', ''))),
                    'origen_tipo' => $this->shortOriginType((string) data_get($item, 'origen_tipo', '')),
                    'titulo_actual' => $currentTitle,
                    'nombre_servicio_actual' => $currentService,
                    'descripcion_actual' => $currentDescription,
                    'titulo_propuesto' => $proposal['titulo'],
                    'nombre_servicio_propuesto' => $proposal['nombre_servicio'],
                    'descripcion_propuesta' => $proposal['descripcion_servicio'],
                    'regla' => $proposal['regla'],
                    'confianza' => $proposal['confianza'],
                    'cambia' => $currentTitle !== $proposal['titulo']
                        || $currentService !== $proposal['nombre_servicio']
                        || $currentDescription !== $proposal['descripcion_servicio'],
                ];
            });
        })->values();

        $changed = $auditRows->where('cambia', true)->values();
        $unchanged = $auditRows->where('cambia', false)->values();

        $outputDir = storage_path('app/facturacion-audits');
        if (! File::exists($outputDir)) {
            File::makeDirectory($outputDir, 0777, true);
        }

        $timestamp = now()->format('Ymd-His');
        $baseName = 'facturacion-audit-' . Str::slug($regional) . '-' . $timestamp;
        $jsonPath = $outputDir . DIRECTORY_SEPARATOR . $baseName . '.json';
        $csvPath = $outputDir . DIRECTORY_SEPARATOR . $baseName . '.csv';

        File::put($jsonPath, json_encode([
            'generated_at' => now()->toDateTimeString(),
            'regional' => $regional,
            'filters' => $filters,
            'cashiers' => $processedUsers->values()->all(),
            'summary' => [
                'ventas_facturadas' => $facturadas->count(),
                'items_auditados' => $auditRows->count(),
                'items_con_cambio' => $changed->count(),
                'items_sin_cambio' => $unchanged->count(),
                'reglas' => $auditRows->groupBy('regla')->map->count()->sortDesc()->all(),
                'confianza' => $auditRows->groupBy('confianza')->map->count()->sortDesc()->all(),
            ],
            'rows' => $auditRows->all(),
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

        $this->writeCsv($csvPath, $auditRows);

        $this->info("Regional: {$regional}");
        $this->info('Cajeros procesados: ' . $processedUsers->count());
        $this->info('Ventas facturadas: ' . $facturadas->count());
        $this->info('Items auditados: ' . $auditRows->count());
        $this->info('Items con cambio propuesto: ' . $changed->count());
        $this->info('Items sin cambio: ' . $unchanged->count());
        $this->line('Reglas detectadas:');
        foreach ($auditRows->groupBy('regla')->map->count()->sortDesc() as $rule => $count) {
            $this->line(" - {$rule}: {$count}");
        }
        $this->line('Confianza:');
        foreach ($auditRows->groupBy('confianza')->map->count()->sortDesc() as $level => $count) {
            $this->line(" - {$level}: {$count}");
        }

        $this->line('Muestra de cambios propuestos:');
        foreach ($changed->take(10) as $row) {
            $this->line(
                sprintf(
                    '[%s] %s | %s => %s',
                    $row['origen_tipo'] !== '' ? $row['origen_tipo'] : 'SIN_ORIGEN',
                    $row['codigo_orden'] !== '' ? $row['codigo_orden'] : 'SIN_ORDEN',
                    $row['descripcion_actual'] !== '' ? $row['descripcion_actual'] : '(vacio)',
                    $row['descripcion_propuesta']
                )
            );
        }

        $this->info('JSON: ' . $jsonPath);
        $this->info('CSV: ' . $csvPath);

        return self::SUCCESS;
    }

    private function userBelongsToRegional(User $user, string $regional): bool
    {
        $regionales = method_exists($user, 'regionalesLista') ? $user->regionalesLista() : [];
        if (collect($regionales)->contains(fn ($value) => strtoupper(trim((string) $value)) === $regional)) {
            return true;
        }

        $candidates = [
            $user->sucursal?->municipio,
            $user->ciudad,
            $user->sucursal?->nombre,
            $user->sucursal?->descripcion,
        ];

        return collect($candidates)
            ->filter()
            ->map(fn ($value) => strtoupper(trim((string) $value)))
            ->contains($regional);
    }

    private function userSucursalName(User $user): string
    {
        return trim((string) ($user->sucursal?->nombre ?? $user->sucursal?->descripcion ?? $user->sucursal?->municipio ?? 'SIN SUCURSAL'));
    }

    private function shortOriginType(string $originType): string
    {
        $originType = trim($originType, '\\');

        return $originType !== '' ? class_basename($originType) : '';
    }

    private function proposeNormalization(object $item): array
    {
        $originType = $this->shortOriginType((string) data_get($item, 'origen_tipo', ''));
        $title = trim((string) data_get($item, 'titulo', ''));
        $service = trim((string) data_get($item, 'nombre_servicio', ''));
        $description = trim((string) data_get($item, 'resumen_origen.descripcion_servicio', ''));
        $serviceName = $service !== '' ? $service : $title;
        $combined = strtoupper(trim(implode(' ', array_filter([$originType, $title, $service, $description]))));

        return match (true) {
            $originType === 'SolicitudCliente' || str_contains($combined, 'PUERTA A PUERTA') => [
                'titulo' => 'Delivery Express',
                'nombre_servicio' => 'Delivery Express',
                'descripcion_servicio' => 'Servicio de puerta a puerta - Envio paqueteria',
                'regla' => 'solicitud_delivery_puerta_puerta',
                'confianza' => 'alta',
            ],
            $originType === 'SolicitudCliente' || str_contains($combined, 'PUERTA A VENTANILLA') => [
                'titulo' => 'Delivery Express',
                'nombre_servicio' => 'Delivery Express',
                'descripcion_servicio' => 'Servicio de puerta a ventanilla - Envio paqueteria',
                'regla' => 'solicitud_delivery_puerta_ventanilla',
                'confianza' => 'alta',
            ],
            $originType === 'SolicitudCliente' || str_contains($combined, 'VENTANILLA A VENTANILLA') => [
                'titulo' => 'Delivery Express',
                'nombre_servicio' => 'Delivery Express',
                'descripcion_servicio' => 'Servicio de ventanilla a ventanilla - Envio paqueteria',
                'regla' => 'solicitud_delivery_ventanilla_ventanilla',
                'confianza' => 'alta',
            ],
            $originType === 'PaqueteCerti' => [
                'titulo' => 'Certificadas',
                'nombre_servicio' => 'Certificadas',
                'descripcion_servicio' => 'Servicio Certificadas - Entrega de paqueteria',
                'regla' => 'panel_certi',
                'confianza' => 'alta',
            ],
            $originType === 'PaqueteOrdi' => [
                'titulo' => 'Ordinarias',
                'nombre_servicio' => 'Ordinarias',
                'descripcion_servicio' => 'Servicio Ordinarias - Entrega de paqueteria',
                'regla' => 'panel_ordi',
                'confianza' => 'alta',
            ],
            $originType === 'PaqueteEms' => [
                'titulo' => $this->normalizeAdmisionesTitle($serviceName),
                'nombre_servicio' => $this->normalizeAdmisionesTitle($serviceName),
                'descripcion_servicio' => 'Servicio ' . $this->normalizeAdmisionesTitle($serviceName) . ' - Envio de paqueteria',
                'regla' => 'admisiones_ems',
                'confianza' => 'alta',
            ],
            $originType === 'ConceptoFacturacion' => array_merge(
                $this->normalizeConceptoByName($serviceName),
                ['regla' => 'concepto_facturable', 'confianza' => 'alta']
            ),
            $originType === 'PaqueteInt' => [
                'titulo' => $title !== '' ? $title : ($serviceName !== '' ? $serviceName : 'Internacional'),
                'nombre_servicio' => $serviceName !== '' ? $serviceName : ($title !== '' ? $title : 'Internacional'),
                'descripcion_servicio' => $description !== '' ? $description : (($serviceName !== '' ? $serviceName : 'Internacional') . ' - Revision manual'),
                'regla' => 'internacional_revision',
                'confianza' => 'media',
            ],
            default => [
                'titulo' => $title,
                'nombre_servicio' => $service,
                'descripcion_servicio' => $description,
                'regla' => 'sin_regla',
                'confianza' => 'baja',
            ],
        };
    }

    private function normalizeAdmisionesTitle(string $name): string
    {
        $normalized = strtoupper(trim($name));

        return match ($normalized) {
            'CERTIFICADAS' => 'Certificadas',
            'ORDINARIAS' => 'Ordinarias',
            'CIUDADES_INTERMEDIAS' => 'Ciudades Intermedias',
            'CIUDADES_INTERMEDIAS_TRINIDAD_COBIJA' => 'Ciudades Intermedias Trinidad Cobija',
            'ECA' => 'ECA',
            'EMS_LOCAL_COBERTURA_1' => 'EMS Local Cobertura 1',
            'EMS_LOCAL_COBERTURA_2' => 'EMS Local Cobertura 2',
            'EMS_LOCAL_COBERTURA_3' => 'EMS Local Cobertura 3',
            'EMS_LOCAL_COBERTURA_4' => 'EMS Local Cobertura 4',
            'EMS_NACIONAL' => 'EMS Nacional',
            'ENCOMIENDA' => 'Encomienda',
            'INTERNACIONAL' => 'Internacional',
            'SUPER_EXPRESS_NACIONAL' => 'Super Express Nacional',
            'TRINIDAD_COBIJA' => 'Trinidad Cobija',
            default => Str::of(str_replace('_', ' ', $normalized))
                ->lower()
                ->replaceMatches('/\bems\b/', 'EMS')
                ->replaceMatches('/\beca\b/', 'ECA')
                ->title()
                ->toString(),
        };
    }

    private function normalizeConceptoByName(string $name): array
    {
        $normalized = strtoupper(trim(preg_replace('/\s+/', ' ', $name) ?? ''));

        return match ($normalized) {
            'AEROLINEA' => [
                'titulo' => 'Aerolinea',
                'nombre_servicio' => 'Aerolinea',
                'descripcion_servicio' => 'Servicio Aerolinea - Pago de envio',
            ],
            'CASILLA' => [
                'titulo' => 'Casilla',
                'nombre_servicio' => 'Casilla',
                'descripcion_servicio' => 'Servicio Casilla - Pago casilla',
            ],
            'EMS INTERNACIONAL' => [
                'titulo' => 'EMS Internacional',
                'nombre_servicio' => 'EMS Internacional',
                'descripcion_servicio' => 'Servicio EMS Internacional - Entrega/Envio de Paqueteria',
            ],
            'ENCOMIENDA INTERNACIONAL' => [
                'titulo' => 'Encomienda Internacional',
                'nombre_servicio' => 'Encomienda Internacional',
                'descripcion_servicio' => 'Servicio Encomienda Internacional - Entrega/Envio de Paqueteria',
            ],
            'ESTAMPILLAS' => [
                'titulo' => 'Estampillas',
                'nombre_servicio' => 'Estampillas',
                'descripcion_servicio' => 'Servicio Venta de Estampillas - Venta',
            ],
            'ORDINARIAS INTERNACIONAL' => [
                'titulo' => 'Ordinarias Internacional',
                'nombre_servicio' => 'Ordinarias Internacional',
                'descripcion_servicio' => 'Servicio Ordinaria Internacional - Entrega/Envio de Paqueteria',
            ],
            'TARJETA POSTAL' => [
                'titulo' => 'Tarjeta postal',
                'nombre_servicio' => 'Tarjeta postal',
                'descripcion_servicio' => 'Servicio Venta de Tarjeta Postal - Venta',
            ],
            default => [
                'titulo' => $name,
                'nombre_servicio' => $name,
                'descripcion_servicio' => $name,
            ],
        };
    }

    private function writeCsv(string $path, Collection $rows): void
    {
        $handle = fopen($path, 'wb');
        if ($handle === false) {
            throw new \RuntimeException('No se pudo escribir el CSV de auditoria.');
        }

        $headers = [
            'venta_id',
            'codigo_orden',
            'emitido_en',
            'numero_factura',
            'estado_emision',
            'item_id',
            'codigo_item',
            'codigo_paquete',
            'origen_tipo',
            'titulo_actual',
            'nombre_servicio_actual',
            'descripcion_actual',
            'titulo_propuesto',
            'nombre_servicio_propuesto',
            'descripcion_propuesta',
            'regla',
            'confianza',
            'cambia',
        ];

        fputcsv($handle, $headers);
        foreach ($rows as $row) {
            fputcsv($handle, array_map(
                fn ($key) => is_bool($row[$key] ?? null) ? (($row[$key] ?? false) ? '1' : '0') : (string) ($row[$key] ?? ''),
                $headers
            ));
        }

        fclose($handle);
    }
}

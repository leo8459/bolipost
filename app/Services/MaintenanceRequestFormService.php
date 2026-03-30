<?php

namespace App\Services;

use App\Models\Driver;
use App\Models\Vehicle;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class MaintenanceRequestFormService
{
    public const TYPE_VEHICULO = 'vehiculo';
    public const TYPE_MOTO = 'moto';

    public function resolveFormType(Vehicle $vehicle): string
    {
        $vehicleType = trim((string) ($vehicle->maintenance_form_type ?? ''));
        if (in_array($vehicleType, [self::TYPE_MOTO, self::TYPE_VEHICULO], true)) {
            return $vehicleType;
        }

        $classType = trim((string) ($vehicle->vehicleClass?->maintenance_form_type ?? ''));
        if (in_array($classType, [self::TYPE_MOTO, self::TYPE_VEHICULO], true)) {
            return $classType;
        }

        return $this->inferFormTypeFromVehicle($vehicle);
    }

    public function buildDownloadResponse(Vehicle $vehicle, ?Driver $driver = null): Response
    {
        $formType = $this->resolveFormType($vehicle);
        $view = $formType === self::TYPE_MOTO
            ? 'reports.maintenance-request-form-moto'
            : 'reports.maintenance-request-form-vehiculo';

        $pdf = Pdf::loadView($view, $this->buildPayload($vehicle, $driver))
            ->setPaper('letter', 'portrait');

        $slugPlate = Str::slug((string) ($vehicle->placa ?? 'sin-placa'));
        $fileName = sprintf(
            'solicitud-mantenimiento-%s-%s.pdf',
            $formType,
            $slugPlate !== '' ? $slugPlate : 'vehiculo'
        );

        return $pdf->download($fileName);
    }

    private function buildPayload(Vehicle $vehicle, ?Driver $driver): array
    {
        $kilometraje = $vehicle->kilometraje_actual
            ?? $vehicle->kilometraje_inicial
            ?? $vehicle->kilometraje;
        $today = now();

        return [
            'brand' => trim((string) ($vehicle->brand?->nombre ?? $vehicle->marca ?? '')),
            'model' => trim((string) ($vehicle->modelo ?? '')),
            'color' => trim((string) ($vehicle->color ?? '')),
            'plate' => trim((string) ($vehicle->placa ?? '')),
            'year' => $vehicle->anio ? (string) $vehicle->anio : '',
            'entryDay' => $today->format('d'),
            'entryMonth' => $today->format('m'),
            'entryYear' => $today->format('Y'),
            'odometer' => $kilometraje !== null ? number_format((float) $kilometraje, 0, ',', '.') : '',
            'driverName' => trim((string) ($driver?->nombre ?? '')),
            'assets' => $this->resolveTemplateAssets($this->resolveFormType($vehicle)),
        ];
    }

    private function inferFormTypeFromVehicle(Vehicle $vehicle): string
    {
        $haystack = Str::lower(trim(implode(' ', array_filter([
            (string) ($vehicle->modelo ?? ''),
            (string) ($vehicle->vehicleClass?->modelo ?? ''),
            (string) ($vehicle->vehicleClass?->nombre ?? ''),
        ]))));

        foreach (['moto', 'motocic', 'scooter', 'cuatrimoto', 'quadratrack', 'atv'] as $needle) {
            if ($needle !== '' && str_contains($haystack, $needle)) {
                return self::TYPE_MOTO;
            }
        }

        return self::TYPE_VEHICULO;
    }

    private function resolveTemplateAssets(string $formType): array
    {
        $definitions = $formType === self::TYPE_MOTO
            ? [
                'gauge' => [
                    public_path('images/maintenance-forms/moto/moto-fuel-gauge.png'),
                    public_path('images/moto-fuel-gauge.png'),
                ],
                'left' => [
                    public_path('images/maintenance-forms/moto/moto-left-view.png'),
                    public_path('images/moto-left-view.png'),
                ],
                'right' => [
                    public_path('images/maintenance-forms/moto/moto-right-view.png'),
                    public_path('images/moto-right-view.png'),
                ],
            ]
            : [
                'top' => [
                    public_path('images/maintenance-forms/vehiculo/vehicle-top-view.png'),
                    public_path('images/vehicle-top-view.png'),
                ],
                'front' => [
                    public_path('images/maintenance-forms/vehiculo/vehicle-front-view.png'),
                    public_path('images/vehicle-front-view.png'),
                ],
                'side_upper' => [
                    public_path('images/maintenance-forms/vehiculo/vehicle-side-view-upper.png'),
                    public_path('images/vehicle-side-view-upper.png'),
                ],
                'rear' => [
                    public_path('images/maintenance-forms/vehiculo/vehicle-rear-view.png'),
                    public_path('images/vehicle-rear-view.png'),
                ],
                'side_lower' => [
                    public_path('images/maintenance-forms/vehiculo/vehicle-side-view-lower.png'),
                    public_path('images/vehicle-side-view-lower.png'),
                ],
                'gauge' => [
                    public_path('images/maintenance-forms/vehiculo/vehicle-fuel-gauge.png'),
                    public_path('images/vehicle-fuel-gauge.png'),
                ],
            ];

        $resolved = [];
        foreach ($definitions as $key => $paths) {
            $resolved[$key] = $this->resolveFirstAvailableImageDataUri((array) $paths);
        }

        return $resolved;
    }

    private function resolveFirstAvailableImageDataUri(array $paths): ?string
    {
        foreach ($paths as $path) {
            $resolved = $this->resolveImageDataUri((string) $path);
            if ($resolved !== null) {
                return $resolved;
            }
        }

        return null;
    }

    private function resolveImageDataUri(string $path): ?string
    {
        if (!File::exists($path)) {
            return null;
        }

        $contents = File::get($path);
        if ($contents === '') {
            return null;
        }

        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        $mime = match ($extension) {
            'jpg', 'jpeg' => 'image/jpeg',
            'gif' => 'image/gif',
            'webp' => 'image/webp',
            default => 'image/png',
        };

        return sprintf('data:%s;base64,%s', $mime, base64_encode($contents));
    }
}

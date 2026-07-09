<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Workshop;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class WorkshopActaReportController extends Controller
{
    public function show(Request $request, Workshop $workshop)
    {
        abort_unless(in_array($this->currentUser()?->role, ['admin', 'recepcion', 'taller'], true), 403);

        $this->loadActaRelations($workshop);

        $vehicle = $workshop->vehicle;
        abort_unless($vehicle !== null, 404);

        $providerUser = $workshop->workshopCatalog?->user;
        $providerName = trim((string) ($providerUser?->name ?? $workshop->workshopCatalog?->nombre ?? $workshop->nombre_taller ?? ''));
        $providerCi = trim((string) ($providerUser?->ci ?? ''));
        $serviceType = trim((string) (
            $workshop->maintenanceAlert?->maintenanceType?->nombre
            ?? $workshop->maintenanceAppointment?->tipoMantenimiento?->nombre
            ?? $workshop->maintenanceLog?->maintenanceType?->nombre
            ?? $workshop->maintenanceLog?->tipo
            ?? 'mantenimiento'
        ));
        $serviceDescription = $this->buildServiceDescription($workshop, $serviceType);
        $city = $this->resolveCity($request, $workshop);
        $serviceDates = $this->formatServiceDates($workshop);
        $inspectorName = trim((string) ($request->user()?->name ?? ''));
        $inspectorRole = $this->resolveInspectorRole($request);
        $vehicleChasis = $this->resolveVehicleIdentifier($request, 'chasis', $vehicle->chasis ?? null);
        $vehicleMotor = $this->resolveVehicleIdentifier($request, 'motor', $vehicle->motor ?? null);

        if (! $request->boolean('print') && ($vehicleChasis === '' || $vehicleMotor === '')) {
            return view('reports.workshop-acta-complete', [
                'workshop' => $workshop,
                'vehicle' => $vehicle,
                'vehicleChasis' => $vehicleChasis,
                'vehicleMotor' => $vehicleMotor,
            ]);
        }

        $pdf = Pdf::loadView('reports.workshop-acta-pdf', [
            'workshop' => $workshop,
            'vehicle' => $vehicle,
            'vehicleChasis' => $vehicleChasis,
            'vehicleMotor' => $vehicleMotor,
            'providerName' => $providerName,
            'providerCi' => $providerCi,
            'serviceType' => $serviceType,
            'serviceDescription' => $serviceDescription,
            'city' => $city,
            'serviceDates' => $serviceDates,
            'inspectorName' => $inspectorName,
            'inspectorRole' => $inspectorRole,
            'generatedAt' => now(),
        ])->setPaper('letter', 'portrait');

        $filename = sprintf(
            'acta-mantenimiento-%s-%s.pdf',
            strtolower((string) ($vehicle->placa ?? 'vehiculo')),
            (string) $workshop->id
        );

        return response($pdf->output(), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="' . $filename . '"',
        ]);
    }

    public function storeVehicleIdentifiers(Request $request, Workshop $workshop)
    {
        abort_unless(in_array($this->currentUser()?->role, ['admin', 'recepcion', 'taller'], true), 403);

        $this->loadActaRelations($workshop);

        $vehicle = $workshop->vehicle;
        abort_unless($vehicle !== null, 404);

        $data = $request->validate([
            'chasis' => ['required', 'string', 'max:80', 'regex:/^[A-Za-z0-9\-\/\.]+$/'],
            'motor' => ['required', 'string', 'max:80', 'regex:/^[A-Za-z0-9\-\/\.]+$/'],
        ], [
            'chasis.required' => 'El chasis es obligatorio para generar el acta.',
            'chasis.max' => 'El chasis no debe superar :max caracteres.',
            'chasis.regex' => 'El chasis solo puede contener letras, numeros, guiones, puntos o barras.',
            'motor.required' => 'El motor es obligatorio para generar el acta.',
            'motor.max' => 'El motor no debe superar :max caracteres.',
            'motor.regex' => 'El motor solo puede contener letras, numeros, guiones, puntos o barras.',
        ]);

        $vehicle->forceFill([
            'chasis' => $this->normalizeVehicleIdentifier($data['chasis']),
            'motor' => $this->normalizeVehicleIdentifier($data['motor']),
        ])->save();

        return redirect()->route('workshops.acta', [
            'workshop' => $workshop->id,
            'print' => 1,
        ]);
    }

    private function currentUser(): ?User
    {
        $user = Auth::user();

        return $user instanceof User ? $user : null;
    }

    private function loadActaRelations(Workshop $workshop): void
    {
        $workshop->load([
            'vehicle.brand',
            'vehicle.vehicleClass',
            'driver.user.sucursal',
            'workshopCatalog.user.sucursal',
            'maintenanceAlert.maintenanceType',
            'maintenanceAppointment.tipoMantenimiento',
            'maintenanceLog',
        ]);
    }

    private function resolveCity(Request $request, Workshop $workshop): string
    {
        $candidates = [
            $request->user()?->sucursal?->municipio,
            $workshop->driver?->user?->sucursal?->municipio,
            $workshop->workshopCatalog?->user?->sucursal?->municipio,
            $request->user()?->ciudad,
        ];

        foreach ($candidates as $candidate) {
            $value = trim((string) $candidate);
            if ($value !== '') {
                return $value;
            }
        }

        return 'Cobija';
    }

    private function formatServiceDates(Workshop $workshop): string
    {
        $start = $workshop->fecha_ingreso ?? $workshop->attention_started_at ?? $workshop->created_at;
        $end = $workshop->fecha_salida ?? $workshop->fecha_listo ?? $workshop->fecha_cierre ?? $start;

        if (!$start) {
            return 'sin fecha registrada';
        }

        $startDate = Carbon::parse($start);
        $endDate = $end ? Carbon::parse($end) : $startDate;

        $monthStart = mb_strtolower($startDate->locale('es')->translatedFormat('F'));
        $monthEnd = mb_strtolower($endDate->locale('es')->translatedFormat('F'));

        if ($startDate->isSameDay($endDate)) {
            return $startDate->translatedFormat('j') . ' de ' . $monthStart . ' de ' . $startDate->translatedFormat('Y');
        }

        if ($startDate->isSameMonth($endDate) && $startDate->isSameYear($endDate)) {
            return $startDate->translatedFormat('j') . ' y ' . $endDate->translatedFormat('j') . ' de ' . $monthStart . ' de ' . $startDate->translatedFormat('Y');
        }

        return $startDate->translatedFormat('j') . ' de ' . $monthStart . ' de ' . $startDate->translatedFormat('Y')
            . ' al '
            . $endDate->translatedFormat('j') . ' de ' . $monthEnd . ' de ' . $endDate->translatedFormat('Y');
    }

    private function resolveInspectorRole(Request $request): string
    {
        $role = trim((string) ($request->user()?->role ?? ''));

        return match ($role) {
            'admin' => 'Administrador',
            'recepcion' => 'Recepcion',
            'taller' => 'Taller',
            default => '',
        };
    }

    private function resolveVehicleIdentifier(Request $request, string $field, mixed $vehicleValue): string
    {
        $requestValue = trim((string) $request->query($field, ''));
        $value = $requestValue !== '' ? $requestValue : trim((string) $vehicleValue);

        return $this->normalizeVehicleIdentifier($value);
    }

    private function normalizeVehicleIdentifier(mixed $value): string
    {
        return mb_strtoupper(trim((string) $value));
    }

    private function buildServiceDescription(Workshop $workshop, string $serviceType): string
    {
        $parts = collect([
            $serviceType,
            trim((string) ($workshop->diagnostico ?? '')),
            trim((string) ($workshop->observaciones_tecnicas ?? '')),
        ])->filter(fn ($value) => $value !== '');

        $text = $parts->implode(', ');

        return $text !== '' ? $text : 'revision mecanica y trabajos requeridos para su correcto funcionamiento';
    }
}

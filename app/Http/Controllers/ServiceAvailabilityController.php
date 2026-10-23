<?php

namespace App\Http\Controllers;

use App\Models\AppSetting;
use App\Support\ServiceAvailability;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class ServiceAvailabilityController extends Controller
{
    public function index(): View
    {
        return view('admin.habilitaciones.index', [
            'services' => ServiceAvailability::all(),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'ems' => ['required', 'boolean'],
            'contratos' => ['required', 'boolean'],
            'solicitud' => ['required', 'boolean'],
        ]);

        DB::transaction(function () use ($validated): void {
            foreach (ServiceAvailability::SETTING_KEYS as $service => $settingKey) {
                AppSetting::setValue($settingKey, (bool) $validated[$service] ? '1' : '0');
            }
        });

        return redirect()
            ->route('admin.service-availability.index')
            ->with('success', 'La disponibilidad de los servicios se actualizó correctamente.');
    }
}

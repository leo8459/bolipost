<?php

namespace App\Http\Controllers;

use App\Models\AppSetting;
use Illuminate\Http\Request;

class AppConfigController extends Controller
{
    public function edit()
    {
        $settings = [
            'latestVersion' => AppSetting::getValue('mobile.latestVersion', '1.0.0'),
            'minimumVersion' => AppSetting::getValue('mobile.minimumVersion', '1.0.0'),
            'forceUpdate' => AppSetting::getValue('mobile.forceUpdate', '0') === '1',
            'downloadUrl' => AppSetting::getValue('mobile.downloadUrl', ''),
            'title' => AppSetting::getValue('mobile.updateTitle', 'Nueva versión disponible'),
            'message' => AppSetting::getValue('mobile.updateMessage', 'Hay una actualización disponible.'),
        ];

        return view('configuracion.aplicacion', compact('settings'));
    }

    public function update(Request $request)
    {
        $data = $request->validate([
            'latestVersion' => ['required', 'string', 'max:30'],
            'minimumVersion' => ['required', 'string', 'max:30'],
            'forceUpdate' => ['nullable', 'boolean'],
            'downloadUrl' => ['nullable', 'url', 'max:500'],
            'title' => ['nullable', 'string', 'max:120'],
            'message' => ['nullable', 'string', 'max:300'],
        ]);

        AppSetting::setValue('mobile.latestVersion', $data['latestVersion']);
        AppSetting::setValue('mobile.minimumVersion', $data['minimumVersion']);
        AppSetting::setValue('mobile.forceUpdate', !empty($data['forceUpdate']) ? '1' : '0');
        AppSetting::setValue('mobile.downloadUrl', $data['downloadUrl'] ?? '');
        AppSetting::setValue('mobile.updateTitle', $data['title'] ?? '');
        AppSetting::setValue('mobile.updateMessage', $data['message'] ?? '');

        return back()->with('status', 'Configuración de aplicación actualizada.');
    }

    public function publicVersion()
    {
        return response()->json([
            'latestVersion' => AppSetting::getValue('mobile.latestVersion', '1.0.0'),
            'minimumVersion' => AppSetting::getValue('mobile.minimumVersion', '1.0.0'),
            'forceUpdate' => AppSetting::getValue('mobile.forceUpdate', '0') === '1',
            'downloadUrl' => AppSetting::getValue('mobile.downloadUrl', ''),
            'title' => AppSetting::getValue('mobile.updateTitle', 'Nueva versión disponible'),
            'message' => AppSetting::getValue('mobile.updateMessage', 'Hay una actualización disponible.'),
        ]);
    }
}

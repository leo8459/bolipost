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
            'title' => AppSetting::getValue('mobile.updateTitle', 'Nueva version disponible'),
            'message' => AppSetting::getValue('mobile.updateMessage', 'Hay una actualizacion disponible.'),
            'carteroNotificationEnabled' => AppSetting::getValue('chasqui.notifications.enabled', '1') === '1',
            'carteroNotificationIntervalMinutes' => (int) AppSetting::getValue('chasqui.notifications.interval_minutes', '15'),
            'carteroNotificationTitle' => AppSetting::getValue('chasqui.notifications.title', 'ChasquiApp'),
            'carteroNotificationMessage' => AppSetting::getValue('chasqui.notifications.message', 'Tienes paquetes pendientes'),
            'facturacionShowFacturaElectronica' => AppSetting::getValue('facturacion.show_factura_electronica', '1') === '1',
            'facturacionShowQrFactura' => AppSetting::getValue('facturacion.show_qr_factura', '1') === '1',
            'facturacionShowQrSolo' => AppSetting::getValue('facturacion.show_qr_solo', '1') === '1',
            'facturacionMonitorDefaultUrl' => AppSetting::getValue('facturacion.monitor_default_url', ''),
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
            'carteroNotificationEnabled' => ['nullable', 'boolean'],
            'carteroNotificationIntervalMinutes' => ['required', 'integer', 'min:1', 'max:1440'],
            'carteroNotificationTitle' => ['required', 'string', 'max:80'],
            'carteroNotificationMessage' => ['required', 'string', 'max:180'],
            'facturacionShowFacturaElectronica' => ['nullable', 'boolean'],
            'facturacionShowQrFactura' => ['nullable', 'boolean'],
            'facturacionShowQrSolo' => ['nullable', 'boolean'],
            'facturacionMonitorDefaultUrl' => ['nullable', 'url', 'max:2048'],
        ]);

        AppSetting::setValue('mobile.latestVersion', $data['latestVersion']);
        AppSetting::setValue('mobile.minimumVersion', $data['minimumVersion']);
        AppSetting::setValue('mobile.forceUpdate', !empty($data['forceUpdate']) ? '1' : '0');
        AppSetting::setValue('mobile.downloadUrl', $data['downloadUrl'] ?? '');
        AppSetting::setValue('mobile.updateTitle', $data['title'] ?? '');
        AppSetting::setValue('mobile.updateMessage', $data['message'] ?? '');
        AppSetting::setValue('chasqui.notifications.enabled', ! empty($data['carteroNotificationEnabled']) ? '1' : '0');
        AppSetting::setValue('chasqui.notifications.interval_minutes', (string) $data['carteroNotificationIntervalMinutes']);
        AppSetting::setValue('chasqui.notifications.title', $data['carteroNotificationTitle']);
        AppSetting::setValue('chasqui.notifications.message', $data['carteroNotificationMessage']);
        AppSetting::setValue('facturacion.show_factura_electronica', !empty($data['facturacionShowFacturaElectronica']) ? '1' : '0');
        AppSetting::setValue('facturacion.show_qr_factura', !empty($data['facturacionShowQrFactura']) ? '1' : '0');
        AppSetting::setValue('facturacion.show_qr_solo', !empty($data['facturacionShowQrSolo']) ? '1' : '0');
        AppSetting::setValue('facturacion.monitor_default_url', $data['facturacionMonitorDefaultUrl'] ?? '');

        return back()->with('status', 'Configuracion de aplicacion actualizada.');
    }

    public function publicVersion()
    {
        return response()->json([
            'latestVersion' => AppSetting::getValue('mobile.latestVersion', '1.0.0'),
            'minimumVersion' => AppSetting::getValue('mobile.minimumVersion', '1.0.0'),
            'forceUpdate' => AppSetting::getValue('mobile.forceUpdate', '0') === '1',
            'downloadUrl' => AppSetting::getValue('mobile.downloadUrl', ''),
            'title' => AppSetting::getValue('mobile.updateTitle', 'Nueva version disponible'),
            'message' => AppSetting::getValue('mobile.updateMessage', 'Hay una actualizacion disponible.'),
        ]);
    }
}

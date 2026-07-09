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
            'facturacionShowFacturaElectronica' => AppSetting::getValue('facturacion.show_factura_electronica', '1') === '1',
            'facturacionShowQrFactura' => AppSetting::getValue('facturacion.show_qr_factura', '1') === '1',
            'facturacionShowQrSolo' => AppSetting::getValue('facturacion.show_qr_solo', '1') === '1',
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
            'facturacionShowFacturaElectronica' => ['nullable', 'boolean'],
            'facturacionShowQrFactura' => ['nullable', 'boolean'],
            'facturacionShowQrSolo' => ['nullable', 'boolean'],
        ]);

        AppSetting::setValue('mobile.latestVersion', $data['latestVersion']);
        AppSetting::setValue('mobile.minimumVersion', $data['minimumVersion']);
        AppSetting::setValue('mobile.forceUpdate', !empty($data['forceUpdate']) ? '1' : '0');
        AppSetting::setValue('mobile.downloadUrl', $data['downloadUrl'] ?? '');
        AppSetting::setValue('mobile.updateTitle', $data['title'] ?? '');
        AppSetting::setValue('mobile.updateMessage', $data['message'] ?? '');
        AppSetting::setValue('facturacion.show_factura_electronica', !empty($data['facturacionShowFacturaElectronica']) ? '1' : '0');
        AppSetting::setValue('facturacion.show_qr_factura', !empty($data['facturacionShowQrFactura']) ? '1' : '0');
        AppSetting::setValue('facturacion.show_qr_solo', !empty($data['facturacionShowQrSolo']) ? '1' : '0');

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

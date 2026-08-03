<?php

namespace App\Http\Controllers;

use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class FacturacionQrMonitorController extends Controller
{
    public function display(Request $request, string $monitor): View
    {
        return view('facturacion.monitor-qr', [
            'monitorKey' => $monitor,
            'signedUrl' => $request->fullUrl(),
        ]);
    }
}

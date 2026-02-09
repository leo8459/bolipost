<?php

namespace App\Http\Controllers;

use App\Models\PaqueteCerti;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;

class PaquetesCertiController extends Controller
{
    public function almacen()
    {
        return view('paquetes_certi.almacen');
    }

    public function inventario()
    {
        return view('paquetes_certi.inventario');
    }

    public function rezago()
    {
        return view('paquetes_certi.rezago');
    }

    public function todos()
    {
        return view('paquetes_certi.todos');
    }

    public function bajaPdf(Request $request)
    {
        $ids = collect(explode(',', (string) $request->query('ids')))
            ->filter()
            ->map(fn ($id) => (int) $id)
            ->values()
            ->all();

        $packages = PaqueteCerti::query()
            ->with(['estado', 'ventanillaRef'])
            ->whereIn('id', $ids)
            ->orderBy('id')
            ->get();

        $pdf = Pdf::loadView('paquetes_certi.reporte_baja', [
            'packages' => $packages,
        ])->setPaper('A4');

        return $pdf->download('reporte-baja.pdf');
    }
}

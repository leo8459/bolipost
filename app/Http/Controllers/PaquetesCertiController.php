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
        $this->authorizeAnyPermission([
            'feature.paquetes-certificados.almacen.dropoff',
            'feature.paquetes-certificados.inventario.export',
        ]);

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

    public function rezagoPdf(Request $request)
    {
        $this->authorizeAnyPermission([
            'feature.paquetes-certificados.almacen.rezago',
        ]);

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

        $pdf = Pdf::loadView('paquetes_certi.reporte_rezago', [
            'packages' => $packages,
        ])->setPaper('A4');

        return $pdf->download('reporte-rezago.pdf');
    }

    /**
     * @param  array<int, string>  $permissions
     */
    private function authorizeAnyPermission(array $permissions): void
    {
        $user = auth()->user();

        if (! $user) {
            abort(403, 'No tienes permiso para realizar esta accion.');
        }

        $superAdminRole = (string) config('acl.super_admin_role', 'administrador');

        if ($superAdminRole !== '' && method_exists($user, 'hasRole') && $user->hasRole($superAdminRole)) {
            return;
        }

        foreach ($permissions as $permission) {
            if (is_string($permission) && $permission !== '' && $user->can($permission)) {
                return;
            }
        }

        abort(403, 'No tienes permiso para realizar esta accion.');
    }
}

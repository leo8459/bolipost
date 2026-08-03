<?php

namespace App\Http\Controllers;

use App\Models\PaqueteCerti;
use App\Models\User as UserModel;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;

class PaquetesCertiController extends Controller
{
    private const ROLE_VENTANILLA_MAP = [
        'auxiliar_urbano_dnd' => ['DND', 'CASILLA'],
        'auxiliar_urbano' => ['DD'],
        'auxiliar_7' => ['DD'],
        'auxiliar_urbano_casilla' => ['DND', 'CASILLA'],
        'encargado_urbano' => ['DD', 'DND'],
    ];

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
            ->tap(fn ($query) => $this->applyRoleVentanillaScope($query))
            ->whereIn('id', $ids)
            ->orderBy('id')
            ->get();

        abort_if(
            $packages->isEmpty(),
            404,
            'No se encontraron paquetes autorizados para generar el formulario de entrega.'
        );

        $printedByName = $this->resolvePrintedByName($request);

        $pdf = Pdf::loadView('paquetes_certi.reporte_baja', [
            'packages' => $packages,
            'printedByName' => $printedByName,
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
            ->tap(fn ($query) => $this->applyRoleVentanillaScope($query))
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

    private function applyRoleVentanillaScope($query): void
    {
        $ventanillas = $this->restrictedVentanillaNames();

        if ($ventanillas === null) {
            return;
        }

        $query->where(function ($restrictedQuery) use ($ventanillas) {
            $restrictedQuery->where(function ($ventanillaColumnQuery) use ($ventanillas) {
                foreach ($ventanillas as $ventanilla) {
                    $ventanillaColumnQuery->orWhereRaw('trim(upper(ventanilla)) = ?', [$ventanilla]);
                }
            })->orWhereHas('ventanillaRef', function ($ventanillaQuery) use ($ventanillas) {
                $ventanillaQuery->where(function ($restrictedVentanillaQuery) use ($ventanillas) {
                    foreach ($ventanillas as $ventanilla) {
                        $restrictedVentanillaQuery->orWhereRaw('trim(upper(nombre_ventanilla)) = ?', [$ventanilla]);
                    }
                });
            });
        });
    }

    private function restrictedVentanillaNames(): ?array
    {
        $user = auth()->user();

        if (! $user || ! method_exists($user, 'hasRole')) {
            return null;
        }

        $superAdminRole = (string) config('acl.super_admin_role', 'administrador');
        if ($superAdminRole !== '' && $user->hasRole($superAdminRole)) {
            return null;
        }

        // Debe coincidir con el alcance usado por el componente de Inventario.
        // Sin este caso, un usuario de Ventanilla Unica ve el paquete en pantalla,
        // pero la consulta del PDF lo excluye y DomPDF genera una hoja en blanco.
        if ($user->hasRole('encargado_unica') || $user->hasRole('auxiliar_unica')) {
            return ['UNICA'];
        }

        foreach (self::ROLE_VENTANILLA_MAP as $role => $ventanillas) {
            if ($user->hasRole($role)) {
                return $ventanillas;
            }
        }

        return null;
    }

    private function resolvePrintedByName(Request $request): string
    {
        $authenticatedUser = $request->user() ?? auth()->user();
        abort_unless($authenticatedUser, 403, 'No tienes permiso para realizar esta accion.');

        $selectedUserId = (int) $request->query('printed_for_user_id', 0);
        if ($selectedUserId <= 0) {
            return (string) $authenticatedUser->name;
        }

        $superAdminRole = trim((string) config('acl.super_admin_role', 'administrador'));
        $isAdministrator = $superAdminRole !== ''
            && method_exists($authenticatedUser, 'hasRole')
            && $authenticatedUser->hasRole($superAdminRole);

        abort_unless($isAdministrator, 403, 'Solo un administrador puede elegir el usuario de la reimpresion.');

        return (string) UserModel::query()->findOrFail($selectedUserId)->name;
    }
}

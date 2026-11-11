<?php

namespace App\Http\Controllers;

use App\Support\PackageControlScope;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class PackageControlController extends Controller
{
    public function contratos(Request $request): View
    {
        return $this->index($request, 'contratos');
    }

    public function ems(Request $request): View
    {
        return $this->index($request, 'ems');
    }

    private function index(Request $request, string $service): View
    {
        $validated = $request->validate([
            'q' => ['nullable', 'string', 'max:180'],
            'estado' => ['nullable', 'string', 'max:100'],
            'entrega' => ['nullable', 'in:todos,entregados,no_entregados'],
            'fecha_desde' => ['nullable', 'date'],
            'fecha_hasta' => ['nullable', 'date', 'after_or_equal:fecha_desde'],
        ]);
        $filters = [
            'q' => trim((string) ($validated['q'] ?? '')),
            'estado' => trim((string) ($validated['estado'] ?? '')),
            'entrega' => (string) ($validated['entrega'] ?? 'todos'),
            'fecha_desde' => trim((string) ($validated['fecha_desde'] ?? '')),
            'fecha_hasta' => trim((string) ($validated['fecha_hasta'] ?? '')),
        ];
        $departmentScope = PackageControlScope::forUser($request->user());
        $query = DB::query()->fromSub($this->packagesQuery($service), 'paquetes');

        if ($departmentScope !== null) {
            $query->where(function (Builder $scope) use ($departmentScope): void {
                $scope->whereRaw('1 = 0');
                foreach ($departmentScope as $department) {
                    $scope->orWhereRaw("trim(upper(coalesce(origen, ''))) = ?", [$department])
                        ->orWhereRaw("trim(upper(coalesce(destino, ''))) = ?", [$department]);
                }
            });
        }

        // Keep the state selector available when another state is selected.
        $estados = (clone $query)->select('estado_nombre')->distinct()->orderBy('estado_nombre')->pluck('estado_nombre');

        $query->when($filters['q'] !== '', function (Builder $query) use ($filters): void {
            $query->where(function (Builder $search) use ($filters): void {
                foreach (['codigo', 'codigo_madre', 'cod_especial', 'cliente', 'cliente_codigo', 'remitente', 'destinatario', 'origen', 'destino'] as $column) {
                    $search->orWhereRaw('upper(coalesce('.$column.", '')) LIKE ?", ['%'.mb_strtoupper($filters['q']).'%']);
                }
            });
        })
            ->when($filters['estado'] !== '', fn (Builder $query) => $query->whereRaw('trim(upper(estado_nombre)) = ?', [mb_strtoupper($filters['estado'])]))
            ->when($filters['entrega'] === 'entregados', fn (Builder $query) => $query->whereRaw('trim(upper(estado_nombre)) = ?', ['ENTREGADO']))
            ->when($filters['entrega'] === 'no_entregados', fn (Builder $query) => $query->whereRaw('trim(upper(estado_nombre)) <> ?', ['ENTREGADO']))
            ->when($filters['fecha_desde'] !== '', fn (Builder $query) => $query->whereDate('created_at', '>=', $filters['fecha_desde']))
            ->when($filters['fecha_hasta'] !== '', fn (Builder $query) => $query->whereDate('created_at', '<=', $filters['fecha_hasta']));

        // Legacy contracts store quantities such as "10 cajas" in a text column.
        $quantitySql = DB::connection()->getDriverName() === 'pgsql'
            ? "CAST(SUBSTRING(TRIM(CAST(cantidad AS TEXT)) FROM '^[0-9]+') AS BIGINT)"
            : 'CAST(cantidad AS INTEGER)';
        $summary = (array) (clone $query)->selectRaw("COUNT(*) as total,
            COALESCE(SUM({$quantitySql}), 0) as packages,
            COALESCE(SUM(CASE WHEN trim(upper(estado_nombre)) = 'ENTREGADO' THEN 1 ELSE 0 END), 0) as delivered,
            COALESCE(SUM(CASE WHEN trim(upper(estado_nombre)) IN ('TRANSITO', 'TRÁNSITO', 'EN TRANSITO', 'EN TRÁNSITO') THEN 1 ELSE 0 END), 0) as transit,
            COALESCE(SUM(CASE WHEN trim(upper(estado_nombre)) IN ('DEVOLUCION', 'DEVOLUCIÓN', 'DEVUELTO', 'DEVUELTOS') THEN 1 ELSE 0 END), 0) as returned
        ")->first();
        $summary = array_map('intval', $summary);
        $summary['not_delivered'] = $summary['total'] - $summary['delivered'];

        return view('control-paquetes.'.$service, [
            'service' => $service,
            'serviceLabel' => $service === 'contratos' ? 'Contratos' : 'EMS',
            'serviceIcon' => $service === 'contratos' ? 'fas fa-file-contract' : 'fas fa-mail-bulk',
            'routeName' => 'control-paquetes.'.$service.'.index',
            'paquetes' => (clone $query)->orderByDesc('created_at')->orderByDesc('id')->paginate(30)->withQueryString(),
            'filters' => $filters,
            'summary' => $summary,
            'estados' => $estados,
            'departmentScope' => $departmentScope,
        ]);
    }

    private function packagesQuery(string $service): Builder
    {
        if ($service === 'contratos') {
            return DB::table('paquetes_contrato as paquete')
                ->leftJoin('estados as estado', 'estado.id', '=', 'paquete.estados_id')
                ->leftJoin('empresa as empresa_directa', 'empresa_directa.id', '=', 'paquete.empresa_id')
                ->leftJoin('users as usuario', 'usuario.id', '=', 'paquete.user_id')
                ->leftJoin('empresa as empresa_usuario', 'empresa_usuario.id', '=', 'usuario.empresa_id')
                ->selectRaw("paquete.id, paquete.codigo, paquete.codigo_madre, paquete.cod_especial,
                    paquete.origen, paquete.destino, paquete.nombre_r as remitente,
                    paquete.telefono_r as telefono_remitente, paquete.nombre_d as destinatario,
                    paquete.telefono_d as telefono_destinatario, paquete.direccion_d as direccion,
                    paquete.contenido, paquete.cantidad, paquete.peso, paquete.precio,
                    paquete.observacion, paquete.created_at, paquete.fecha_recojo,
                    COALESCE(NULLIF(trim(empresa_directa.nombre), ''), NULLIF(trim(empresa_usuario.nombre), ''), paquete.nombre_r) as cliente,
                    COALESCE(NULLIF(trim(empresa_directa.codigo_cliente), ''), empresa_usuario.codigo_cliente, '') as cliente_codigo,
                    COALESCE(estado.nombre_estado, 'Sin estado') as estado_nombre");
        }

        return DB::table('paquetes_ems as paquete')
            ->leftJoin('estados as estado', 'estado.id', '=', 'paquete.estado_id')
            ->selectRaw("paquete.id, paquete.codigo, '' as codigo_madre, paquete.cod_especial,
                paquete.origen, paquete.ciudad as destino, paquete.nombre_remitente as remitente,
                paquete.telefono_remitente, paquete.nombre_destinatario as destinatario,
                paquete.telefono_destinatario, paquete.direccion, paquete.contenido,
                paquete.cantidad, paquete.peso, paquete.precio, paquete.observacion,
                paquete.created_at, NULL as fecha_recojo, paquete.nombre_remitente as cliente,
                '' as cliente_codigo, COALESCE(estado.nombre_estado, 'Sin estado') as estado_nombre");
    }
}

<?php

namespace App\Http\Controllers;

use App\Models\FacturacionCart;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class MisVentasController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();
        abort_unless($user && $user->can('feature.dashboard.facturacion'), 403, 'No tienes permiso para ver tus ventas.');

        $validated = $request->validate([
            'estado' => ['nullable', 'in:all,borrador,emitido'],
            'estado_emision' => ['nullable', 'in:all,FACTURADA,PENDIENTE,RECHAZADA,ERROR'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
            'q' => ['nullable', 'string', 'max:120'],
            'per_page' => ['nullable', 'integer', 'min:10', 'max:100'],
        ]);

        $estado = (string) ($validated['estado'] ?? 'all');
        $estadoEmision = (string) ($validated['estado_emision'] ?? 'all');
        $search = trim((string) ($validated['q'] ?? ''));
        $perPage = (int) ($validated['per_page'] ?? 20);

        $query = FacturacionCart::query()
            ->with('items')
            ->where('user_id', $user->id)
            ->latest('emitido_en')
            ->latest('id');

        if ($estado !== 'all') {
            $query->where('estado', $estado);
        }

        if ($estadoEmision !== 'all') {
            $query->whereRaw('upper(coalesce(estado_emision, ?)) = ?', ['', strtoupper($estadoEmision)]);
        }

        if (!empty($validated['from'])) {
            $query->whereDate('created_at', '>=', $validated['from']);
        }

        if (!empty($validated['to'])) {
            $query->whereDate('created_at', '<=', $validated['to']);
        }

        if ($search !== '') {
            $like = '%' . $search . '%';
            $query->where(function ($sub) use ($like) {
                $sub->where('codigo_orden', 'like', $like)
                    ->orWhere('codigo_seguimiento', 'like', $like)
                    ->orWhere('numero_documento', 'like', $like)
                    ->orWhere('razon_social', 'like', $like)
                    ->orWhere('mensaje_emision', 'like', $like);
            });
        }

        $carts = $query->paginate($perPage)->withQueryString();

        $summaryBase = FacturacionCart::query()
            ->where('user_id', $user->id);

        $summary = [
            'totalVentas' => (clone $summaryBase)->where('estado', 'emitido')->count(),
            'totalBorradores' => (clone $summaryBase)->where('estado', 'borrador')->count(),
            'facturadas' => (clone $summaryBase)->whereRaw("upper(coalesce(estado_emision, '')) = 'FACTURADA'")->count(),
            'pendientes' => (clone $summaryBase)->whereRaw("upper(coalesce(estado_emision, '')) = 'PENDIENTE'")->count(),
            'rechazadas' => (clone $summaryBase)->whereRaw("upper(coalesce(estado_emision, '')) = 'RECHAZADA'")->count(),
            'montoTotal' => (float) ((clone $summaryBase)->where('estado', 'emitido')->sum('total')),
        ];

        return view('facturacion.mis-ventas', [
            'carts' => $carts,
            'summary' => $summary,
            'filters' => [
                'estado' => $estado,
                'estado_emision' => $estadoEmision,
                'from' => $validated['from'] ?? null,
                'to' => $validated['to'] ?? null,
                'q' => $search,
                'per_page' => $perPage,
            ],
        ]);
    }
}

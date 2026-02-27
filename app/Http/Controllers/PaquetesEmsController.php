<?php

namespace App\Http\Controllers;

use App\Models\Estado;
use App\Models\PaqueteEms;
use App\Models\Recojo as RecojoContrato;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PaquetesEmsController extends Controller
{
    public function index()
    {
        return view('paquetes_ems.index');
    }

    public function almacen()
    {
        return view('paquetes_ems.almacen');
    }

    public function ventanilla()
    {
        return view('paquetes_ems.ventanilla');
    }

    public function recibirRegional()
    {
        return view('paquetes_ems.recibir-regional');
    }

    public function entregados(Request $request)
    {
        $search = trim((string) $request->query('q', ''));

        $estadoDomicilioId = Estado::query()
            ->whereRaw('trim(upper(nombre_estado)) = ?', ['DOMICILIO'])
            ->value('id');

        $emsQuery = PaqueteEms::query()
            ->leftJoin('cartero as asignacion', 'asignacion.id_paquetes_ems', '=', 'paquetes_ems.id')
            ->leftJoin('users as cartero_user', 'cartero_user.id', '=', 'asignacion.id_user')
            ->select([
                DB::raw("'EMS' as tipo_paquete"),
                'paquetes_ems.id',
                'paquetes_ems.codigo',
                DB::raw('paquetes_ems.nombre_destinatario as destinatario'),
                DB::raw('paquetes_ems.telefono_destinatario as telefono'),
                DB::raw('paquetes_ems.ciudad as ciudad'),
                'paquetes_ems.peso',
                DB::raw('paquetes_ems.updated_at as fecha_entrega'),
                'asignacion.recibido_por',
                'asignacion.descripcion',
                'cartero_user.name as asignado_a',
            ])
            ->when($estadoDomicilioId, function ($query) use ($estadoDomicilioId) {
                $query->where('paquetes_ems.estado_id', (int) $estadoDomicilioId);
            }, function ($query) {
                $query->whereRaw('1 = 0');
            })
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($sub) use ($search) {
                    $sub->where('paquetes_ems.codigo', 'like', '%' . $search . '%')
                        ->orWhere('paquetes_ems.nombre_destinatario', 'like', '%' . $search . '%')
                        ->orWhere('paquetes_ems.telefono_destinatario', 'like', '%' . $search . '%')
                        ->orWhere('paquetes_ems.ciudad', 'like', '%' . $search . '%')
                        ->orWhere('asignacion.recibido_por', 'like', '%' . $search . '%')
                        ->orWhere('asignacion.descripcion', 'like', '%' . $search . '%')
                        ->orWhere('cartero_user.name', 'like', '%' . $search . '%');
                });
            });

        $contratosQuery = RecojoContrato::query()
            ->leftJoin('cartero as asignacion', 'asignacion.id_paquetes_contrato', '=', 'paquetes_contrato.id')
            ->leftJoin('users as cartero_user', 'cartero_user.id', '=', 'asignacion.id_user')
            ->select([
                DB::raw("'CONTRATO' as tipo_paquete"),
                'paquetes_contrato.id',
                'paquetes_contrato.codigo',
                DB::raw('paquetes_contrato.nombre_d as destinatario'),
                DB::raw('paquetes_contrato.telefono_d as telefono'),
                DB::raw('paquetes_contrato.destino as ciudad'),
                'paquetes_contrato.peso',
                DB::raw('paquetes_contrato.updated_at as fecha_entrega'),
                'asignacion.recibido_por',
                'asignacion.descripcion',
                'cartero_user.name as asignado_a',
            ])
            ->when($estadoDomicilioId, function ($query) use ($estadoDomicilioId) {
                $query->where('paquetes_contrato.estados_id', (int) $estadoDomicilioId);
            }, function ($query) {
                $query->whereRaw('1 = 0');
            })
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($sub) use ($search) {
                    $sub->where('paquetes_contrato.codigo', 'like', '%' . $search . '%')
                        ->orWhere('paquetes_contrato.nombre_d', 'like', '%' . $search . '%')
                        ->orWhere('paquetes_contrato.telefono_d', 'like', '%' . $search . '%')
                        ->orWhere('paquetes_contrato.destino', 'like', '%' . $search . '%')
                        ->orWhere('asignacion.recibido_por', 'like', '%' . $search . '%')
                        ->orWhere('asignacion.descripcion', 'like', '%' . $search . '%')
                        ->orWhere('cartero_user.name', 'like', '%' . $search . '%');
                });
            });

        $paquetes = DB::query()
            ->fromSub($emsQuery->unionAll($contratosQuery), 'entregados')
            ->orderByDesc('fecha_entrega')
            ->paginate(20)
            ->withQueryString();

        return view('paquetes_ems.entregados', [
            'paquetes' => $paquetes,
            'search' => $search,
            'estadoDomicilioDisponible' => (bool) $estadoDomicilioId,
        ]);
    }
}

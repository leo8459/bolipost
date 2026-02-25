<?php

namespace App\Http\Controllers;

use App\Models\Estado;
use App\Models\PaqueteEms;
use Illuminate\Http\Request;

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

        $paquetes = PaqueteEms::query()
            ->leftJoin('cartero as asignacion', 'asignacion.id_paquetes_ems', '=', 'paquetes_ems.id')
            ->leftJoin('users as cartero_user', 'cartero_user.id', '=', 'asignacion.id_user')
            ->select([
                'paquetes_ems.id',
                'paquetes_ems.codigo',
                'paquetes_ems.nombre_destinatario',
                'paquetes_ems.telefono_destinatario',
                'paquetes_ems.ciudad',
                'paquetes_ems.peso',
                'paquetes_ems.updated_at',
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
            })
            ->orderByDesc('paquetes_ems.updated_at')
            ->paginate(20)
            ->withQueryString();

        return view('paquetes_ems.entregados', [
            'paquetes' => $paquetes,
            'search' => $search,
            'estadoDomicilioDisponible' => (bool) $estadoDomicilioId,
        ]);
    }
}

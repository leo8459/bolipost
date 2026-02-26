<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class EventoController extends Controller
{
    public function index()
    {
        return view('evento.index');
    }

    public function emsIndex(Request $request)
    {
        $search = trim((string) $request->query('q', ''));

        $eventosEms = DB::table('eventos_ems as ee')
            ->leftJoin('eventos as e', 'e.id', '=', 'ee.evento_id')
            ->leftJoin('users as u', 'u.id', '=', 'ee.user_id')
            ->select([
                'ee.id',
                'ee.codigo',
                'ee.evento_id',
                'ee.user_id',
                'ee.created_at',
                'e.nombre_evento as evento_nombre',
                'u.name as usuario_nombre',
            ])
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($sub) use ($search) {
                    $sub->where('ee.codigo', 'like', '%' . $search . '%')
                        ->orWhere('e.nombre_evento', 'like', '%' . $search . '%')
                        ->orWhere('u.name', 'like', '%' . $search . '%');
                });
            })
            ->orderByDesc('ee.id')
            ->paginate(20)
            ->withQueryString();

        return view('eventos_ems.index', [
            'eventosEms' => $eventosEms,
            'search' => $search,
        ]);
    }
}


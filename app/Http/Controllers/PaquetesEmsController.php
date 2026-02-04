<?php

namespace App\Http\Controllers;

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
}

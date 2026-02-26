<?php

namespace App\Http\Controllers;

class PaquetesOrdiController extends Controller
{
    public function index()
    {
        return view('paquetes_ordi.index');
    }

    public function despacho()
    {
        return view('paquetes_ordi.despacho');
    }

    public function almacen()
    {
        return view('paquetes_ordi.almacen');
    }

    public function entregado()
    {
        return view('paquetes_ordi.entregado');
    }

    public function rezago()
    {
        return view('paquetes_ordi.rezago');
    }
}

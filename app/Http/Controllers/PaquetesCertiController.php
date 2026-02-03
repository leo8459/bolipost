<?php

namespace App\Http\Controllers;

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
}

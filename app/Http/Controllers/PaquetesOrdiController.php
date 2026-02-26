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
}

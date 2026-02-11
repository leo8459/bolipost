<?php

namespace App\Http\Controllers;

class DespachoController extends Controller
{
    public function index()
    {
        return view('despacho.index');
    }

    public function expedicion()
    {
        return view('despacho.expedicion');
    }
}

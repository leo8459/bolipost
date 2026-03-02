<?php

namespace App\Http\Controllers;

class EventoController extends Controller
{
    public function index()
    {
        return view('evento.index');
    }

    public function emsIndex()
    {
        return view('eventos_ems.index');
    }

    public function certiIndex()
    {
        return view('eventos_certi.index');
    }

    public function ordiIndex()
    {
        return view('eventos_ordi.index');
    }
}


<?php

namespace App\Http\Controllers;

use App\Models\Despacho;
use Barryvdh\DomPDF\Facade\Pdf;

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

    public function admitidos()
    {
        return view('despacho.admitidos');
    }

    public function expedicionPdf($id)
    {
        $despacho = Despacho::query()
            ->with(['sacas' => function ($query) {
                $query->orderByRaw('CAST(nro_saca AS INTEGER) ASC');
            }])
            ->findOrFail($id);

        $sacas = $despacho->sacas;

        $oficinas = [
            'BOLPZ' => 'LA PAZ',
            'BOTJA' => 'TARIJA',
            'BOPOI' => 'POTOSI',
            'BOCIJ' => 'PANDO',
            'BOCBB' => 'COCHABAMBA',
            'BOORU' => 'ORURO',
            'BOTDD' => 'BENI',
            'BOSRE' => 'SUCRE',
            'BOSRZ' => 'SANTA CRUZ',
            'PELIM' => 'PERU/LIMA',
        ];

        $siglaIata = [
            'BOLPZ' => 'LPZ',
            'BOTJA' => 'TJA',
            'BOPOI' => 'POI',
            'BOCIJ' => 'CIJ',
            'BOCBB' => 'CBB',
            'BOORU' => 'ORU',
            'BOTDD' => 'TDD',
            'BOSRE' => 'SRE',
            'BOSRZ' => 'SRZ',
            'PELIM' => 'LIM',
        ];

        $data = [
            'despacho' => $despacho,
            'sacas' => $sacas,
            'identificador' => (string) $despacho->identificador,
            'ciudadOrigen' => $oficinas[$despacho->oforigen] ?? $despacho->oforigen,
            'ciudadDestino' => $oficinas[$despacho->ofdestino] ?? $despacho->ofdestino,
            'siglaOrigen' => $despacho->oforigen,
            'siglaOrigenIata' => $siglaIata[$despacho->oforigen] ?? $despacho->oforigen,
            'siglaDestinoIata' => $siglaIata[$despacho->ofdestino] ?? $despacho->ofdestino,
            'ofdestino' => $despacho->ofdestino,
            'categoria' => $despacho->categoria,
            'subclase' => $despacho->subclase,
            'ano' => (string) $despacho->anio,
            'created_at' => optional($despacho->created_at)->format('Y-m-d'),
            'totalContenido' => $sacas->count(),
            'totalContenidoR' => 0,
            'totalContenidoB' => 0,
            'sacasm' => 0,
            'listas' => 0,
            'totalPaquetes' => (int) $sacas->sum(fn ($saca) => (int) ($saca->paquetes ?? 0)),
            'peso' => round((float) $sacas->sum(fn ($saca) => (float) ($saca->peso ?? 0)), 3),
            'nropaquetesbl' => 0,
            'nropaquetesro' => 0,
            'nropaquetesco' => 0,
            'nropaquetescp' => 0,
            'nropaquetesems' => 0,
            'nropaquetessu' => 0,
            'nropaquetesof' => 0,
            'nropaquetesii' => 0,
            'nropaqueteset' => 0,
            'nropaquetessn' => 0,
        ];

        $pdf = Pdf::loadView('despacho.expedicion-pdf', $data)->setPaper('A4');

        return $pdf->stream('despacho-' . $despacho->identificador . '.pdf');
    }
}

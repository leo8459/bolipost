<?php

namespace App\Http\Controllers;

use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class CnGenerationController extends Controller
{
    /** @var array<string, string> */
    private const COUNTRIES = [
        'AR' => 'Argentina',
        'BO' => 'Bolivia',
        'BR' => 'Brasil',
        'CL' => 'Chile',
        'CO' => 'Colombia',
        'CR' => 'Costa Rica',
        'CU' => 'Cuba',
        'DO' => 'Republica Dominicana',
        'EC' => 'Ecuador',
        'SV' => 'El Salvador',
        'GT' => 'Guatemala',
        'HN' => 'Honduras',
        'MX' => 'Mexico',
        'NI' => 'Nicaragua',
        'PA' => 'Panama',
        'PY' => 'Paraguay',
        'PE' => 'Peru',
        'PR' => 'Puerto Rico',
        'UY' => 'Uruguay',
        'VE' => 'Venezuela',
        'CA' => 'Canada',
        'US' => 'Estados Unidos',
        'DE' => 'Alemania',
        'BE' => 'Belgica',
        'ES' => 'Espana',
        'FR' => 'Francia',
        'IT' => 'Italia',
        'NL' => 'Paises Bajos',
        'PT' => 'Portugal',
        'GB' => 'Reino Unido',
        'CH' => 'Suiza',
        'CN' => 'China',
        'KR' => 'Corea del Sur',
        'IN' => 'India',
        'JP' => 'Japon',
        'AU' => 'Australia',
        'NZ' => 'Nueva Zelanda',
    ];

    /** @var array<string, string> */
    private const COUNTRY_DISPATCH_CODES = [
        'AR' => 'BUE', 'BO' => 'LPB', 'BR' => 'SAO', 'CL' => 'SCL', 'CO' => 'BOG',
        'CR' => 'SJO', 'CU' => 'HAV', 'DO' => 'SDQ', 'EC' => 'UIO', 'SV' => 'SAL',
        'GT' => 'GUA', 'HN' => 'TGU', 'MX' => 'MEX', 'NI' => 'MGA', 'PA' => 'PTY',
        'PY' => 'ASU', 'PE' => 'LIM', 'PR' => 'SJU', 'UY' => 'MVD', 'VE' => 'CCS',
        'CA' => 'YTO', 'US' => 'NYC', 'DE' => 'FRA', 'BE' => 'BRU', 'ES' => 'MAD',
        'FR' => 'PAR', 'IT' => 'MXP', 'NL' => 'HAG', 'PT' => 'LIS', 'GB' => 'LON',
        'CH' => 'ZRH', 'CN' => 'BJS', 'KR' => 'SEL', 'IN' => 'DEL', 'JP' => 'TYO',
        'AU' => 'SYD', 'NZ' => 'AKL',
    ];

    public function index()
    {
        return view('cn-generation.index', [
            'countries' => self::COUNTRIES,
            'countryDispatchCodes' => self::COUNTRY_DISPATCH_CODES,
            'defaultDate' => now()->format('Y-m-d'),
        ]);
    }

    public function pdf(Request $request)
    {
        $validated = $request->validate([
            'fecha' => ['required', 'date'],
            'hoja_ruta' => ['required', 'string', 'max:30'],
            'despacho' => ['required', 'string', 'max:30'],
            'administracion_expedidora' => ['required', 'string', 'max:80'],
            'oficina_cambio' => ['required', 'string', 'max:80'],
            'servicio' => ['required', 'string', 'max:60'],
            'transporte' => ['nullable', 'string', 'max:60'],
            'itinerario' => ['nullable', 'string', 'max:120'],
            'boletin' => ['nullable', 'string', 'max:30'],
            'observaciones_globales' => ['nullable', 'string', 'max:500'],
            'rows' => ['required', 'array', 'min:1', 'max:100'],
            'rows.*.pais_codigo' => ['required', Rule::in(array_keys(self::COUNTRIES))],
            'rows.*.oficina_destino' => ['required', 'string', 'max:40'],
            'rows.*.envio' => ['required', 'string', 'max:40'],
            'rows.*.origen' => ['required', 'string', 'max:15'],
            'rows.*.destino' => ['required', 'string', 'max:15'],
            'rows.*.peso' => ['required', 'numeric', 'min:0.001', 'max:999999.999'],
            'rows.*.valor_declarado' => ['nullable', 'numeric', 'min:0', 'max:999999999.99'],
            'rows.*.porte_expedidor' => ['nullable', 'numeric', 'min:0', 'max:999999999.99'],
            'rows.*.porte_destinatario' => ['nullable', 'numeric', 'min:0', 'max:999999999.99'],
            'rows.*.observacion' => ['nullable', 'string', 'max:120'],
        ], [
            'rows.required' => 'Añade al menos un pais de destino.',
            'rows.*.pais_codigo.required' => 'Selecciona el pais de destino de cada envio.',
            'rows.*.oficina_destino.required' => 'La oficina de destino es obligatoria.',
            'rows.*.envio.required' => 'El codigo de envio es obligatorio.',
            'rows.*.peso.required' => 'El peso es obligatorio.',
            'rows.*.peso.min' => 'El peso debe ser mayor a cero.',
        ]);

        $rows = collect($validated['rows'])->map(function (array $row): array {
            $countryCode = strtoupper($row['pais_codigo']);

            return [
                'pais_codigo' => $countryCode,
                'pais_nombre' => self::COUNTRIES[$countryCode],
                'oficina_destino' => strtoupper(trim($row['oficina_destino'])),
                'envio' => strtoupper(trim($row['envio'])),
                'origen' => strtoupper(trim($row['origen'])),
                'destino' => strtoupper(trim($row['destino'])),
                'peso' => (float) $row['peso'],
                'valor_declarado' => (float) ($row['valor_declarado'] ?? 0),
                'porte_expedidor' => (float) ($row['porte_expedidor'] ?? 0),
                'porte_destinatario' => (float) ($row['porte_destinatario'] ?? 0),
                'observacion' => trim((string) ($row['observacion'] ?? '')),
            ];
        });

        $data = array_merge([
            'transporte' => '',
            'itinerario' => '',
            'boletin' => '',
            'observaciones_globales' => '',
        ], $validated, [
            'rows' => $rows,
            'totalPeso' => $rows->sum('peso'),
            'totalValor' => $rows->sum('valor_declarado'),
            'totalPorteExpedidor' => $rows->sum('porte_expedidor'),
            'totalPorteDestinatario' => $rows->sum('porte_destinatario'),
            'destinations' => $rows->groupBy('pais_codigo')->map(fn ($items) => [
                'codigo' => $items->first()['pais_codigo'],
                'pais' => $items->first()['pais_nombre'],
                'oficina' => $items->first()['oficina_destino'],
                'cantidad' => $items->count(),
                'peso' => $items->sum('peso'),
            ])->values(),
        ]);

        $pdf = Pdf::loadView('cn-generation.pdf', $data)->setPaper('a4', 'portrait');
        $filename = 'hoja-ruta-cn-'.preg_replace('/[^A-Za-z0-9_-]+/', '-', $validated['despacho']).'.pdf';

        return response()->streamDownload(
            fn () => print ($pdf->output()),
            $filename,
            ['Content-Type' => 'application/pdf']
        );
    }
}

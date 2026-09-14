<?php

namespace App\Http\Controllers;

use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Milon\Barcode\DNS1D;

class MarbeteController extends Controller
{
    private const MAIL_CATEGORY = 'A';

    /** @var array<string, array{pais: string, ciudad: string, impc: string, descarga: string}> */
    private const DESTINATIONS = [
        'AR' => ['pais' => 'ARGENTINA', 'ciudad' => 'BUENOS AIRES', 'impc' => 'ARBUEA', 'descarga' => 'BUE'],
        'BO' => ['pais' => 'BOLIVIA', 'ciudad' => 'LA PAZ', 'impc' => 'BOLPBA', 'descarga' => 'LPB'],
        'BR' => ['pais' => 'BRASIL', 'ciudad' => 'SAO PAULO', 'impc' => 'BRSAOA', 'descarga' => 'SAO'],
        'CL' => ['pais' => 'CHILE', 'ciudad' => 'SANTIAGO', 'impc' => 'CLSCLA', 'descarga' => 'SCL'],
        'CO' => ['pais' => 'COLOMBIA', 'ciudad' => 'BOGOTA', 'impc' => 'COBOGA', 'descarga' => 'BOG'],
        'CR' => ['pais' => 'COSTA RICA', 'ciudad' => 'SAN JOSE', 'impc' => 'CRSJOA', 'descarga' => 'SJO'],
        'CU' => ['pais' => 'CUBA', 'ciudad' => 'LA HABANA', 'impc' => 'CUHAVA', 'descarga' => 'HAV'],
        'DO' => ['pais' => 'REPUBLICA DOMINICANA', 'ciudad' => 'SANTO DOMINGO', 'impc' => 'DOSDQA', 'descarga' => 'SDQ'],
        'EC' => ['pais' => 'ECUADOR', 'ciudad' => 'QUITO', 'impc' => 'ECUIOA', 'descarga' => 'UIO'],
        'SV' => ['pais' => 'EL SALVADOR', 'ciudad' => 'SAN SALVADOR', 'impc' => 'SVSALA', 'descarga' => 'SAL'],
        'GT' => ['pais' => 'GUATEMALA', 'ciudad' => 'GUATEMALA', 'impc' => 'GTGUAA', 'descarga' => 'GUA'],
        'HN' => ['pais' => 'HONDURAS', 'ciudad' => 'TEGUCIGALPA', 'impc' => 'HNTGUA', 'descarga' => 'TGU'],
        'MX' => ['pais' => 'MEXICO', 'ciudad' => 'CIUDAD DE MEXICO', 'impc' => 'MXMEXA', 'descarga' => 'MEX'],
        'NI' => ['pais' => 'NICARAGUA', 'ciudad' => 'MANAGUA', 'impc' => 'NIMGAA', 'descarga' => 'MGA'],
        'PA' => ['pais' => 'PANAMA', 'ciudad' => 'PANAMA', 'impc' => 'PAPTYA', 'descarga' => 'PTY'],
        'PY' => ['pais' => 'PARAGUAY', 'ciudad' => 'ASUNCION', 'impc' => 'PYASUA', 'descarga' => 'ASU'],
        'PE' => ['pais' => 'PERU', 'ciudad' => 'LIMA', 'impc' => 'PELIMA', 'descarga' => 'LIM'],
        'PR' => ['pais' => 'PUERTO RICO', 'ciudad' => 'SAN JUAN', 'impc' => 'PRSJUA', 'descarga' => 'SJU'],
        'UY' => ['pais' => 'URUGUAY', 'ciudad' => 'MONTEVIDEO', 'impc' => 'UYMVDA', 'descarga' => 'MVD'],
        'VE' => ['pais' => 'VENEZUELA', 'ciudad' => 'CARACAS', 'impc' => 'VECCSA', 'descarga' => 'CCS'],
        'CA' => ['pais' => 'CANADA', 'ciudad' => 'TORONTO', 'impc' => 'CAYTOA', 'descarga' => 'YTO'],
        'US' => ['pais' => 'ESTADOS UNIDOS', 'ciudad' => 'NEW YORK', 'impc' => 'USNYCA', 'descarga' => 'NYC'],
        'DE' => ['pais' => 'ALEMANIA', 'ciudad' => 'FRANKFURT', 'impc' => 'DEFRAA', 'descarga' => 'FRA'],
        'BE' => ['pais' => 'BELGICA', 'ciudad' => 'BRUSELAS', 'impc' => 'BEBRUA', 'descarga' => 'BRU'],
        'ES' => ['pais' => 'ESPANA', 'ciudad' => 'MADRID', 'impc' => 'ESMADA', 'descarga' => 'MAD'],
        'FR' => ['pais' => 'FRANCIA', 'ciudad' => 'PARIS', 'impc' => 'FRPARA', 'descarga' => 'PAR'],
        'IT' => ['pais' => 'ITALIA', 'ciudad' => 'MILAN', 'impc' => 'ITMXPA', 'descarga' => 'MXP'],
        'NL' => ['pais' => 'PAISES BAJOS', 'ciudad' => 'LA HAYA', 'impc' => 'NLHAGA', 'descarga' => 'HAG'],
        'PT' => ['pais' => 'PORTUGAL', 'ciudad' => 'LISBOA', 'impc' => 'PTLISA', 'descarga' => 'LIS'],
        'GB' => ['pais' => 'REINO UNIDO', 'ciudad' => 'LONDRES', 'impc' => 'GBLONA', 'descarga' => 'LON'],
        'CH' => ['pais' => 'SUIZA', 'ciudad' => 'ZURICH', 'impc' => 'CHZRHA', 'descarga' => 'ZRH'],
        'CN' => ['pais' => 'CHINA', 'ciudad' => 'PEKIN', 'impc' => 'CNBJSA', 'descarga' => 'BJS'],
        'KR' => ['pais' => 'COREA DEL SUR', 'ciudad' => 'SEUL', 'impc' => 'KRSELA', 'descarga' => 'SEL'],
        'IN' => ['pais' => 'INDIA', 'ciudad' => 'NUEVA DELHI', 'impc' => 'INDELA', 'descarga' => 'DEL'],
        'JP' => ['pais' => 'JAPON', 'ciudad' => 'TOKIO', 'impc' => 'JPTYOA', 'descarga' => 'TYO'],
        'AU' => ['pais' => 'AUSTRALIA', 'ciudad' => 'SYDNEY', 'impc' => 'AUSYDA', 'descarga' => 'SYD'],
        'NZ' => ['pais' => 'NUEVA ZELANDA', 'ciudad' => 'AUCKLAND', 'impc' => 'NZAKLA', 'descarga' => 'AKL'],
    ];

    /** @var array<string, string> */
    private const MAIL_SUBCLASSES = [
        'UN' => 'UN - Certificados internacionales',
        'UR' => 'UR - Correspondencia registrada',
        'UA' => 'UA - Cartas',
        'MN' => 'MN - Mixto',
    ];

    public function index()
    {
        return view('marbetes.index', [
            'defaultDate' => now()->format('Y-m-d'),
            'mailSubclasses' => self::MAIL_SUBCLASSES,
            'destinations' => self::DESTINATIONS,
        ]);
    }

    public function pdf(Request $request)
    {
        $validated = $request->validate([
            'fecha' => ['required', 'date'],
            'origen_impc' => ['required', 'string', 'size:6', 'regex:/^[A-Za-z]{6}$/'],
            'pais_codigo' => ['required', Rule::in(array_keys(self::DESTINATIONS))],
            'subclase' => ['required', Rule::in(array_keys(self::MAIL_SUBCLASSES))],
            'numero_despacho' => ['required', 'integer', 'min:1', 'max:9999'],
            'numero_receptaculo' => ['required', 'integer', 'min:1', 'max:999'],
            'ultimo_receptaculo' => ['required', 'boolean'],
            'registrado_asegurado' => ['required', 'boolean'],
            'cantidad_envios' => ['required', 'integer', 'min:0', 'max:999'],
            'peso' => ['required', 'numeric', 'min:0.1', 'max:999.9'],
            'tipo_correo' => ['required', 'string', 'max:60'],
            'vuelo' => ['nullable', 'string', 'max:30'],
            'tren' => ['nullable', 'string', 'max:30'],
        ], [
            'origen_impc.size' => 'El IMPC de origen debe tener exactamente 6 letras.',
            'origen_impc.regex' => 'El IMPC de origen solo puede contener letras.',
            'pais_codigo.required' => 'Selecciona el pais de destino.',
            'numero_despacho.max' => 'El numero de despacho no puede superar 9999.',
            'numero_receptaculo.max' => 'El numero de receptaculo no puede superar 999.',
            'peso.max' => 'El peso maximo permitido por el identificador UPU es 999,9 kg.',
        ]);

        $data = $this->buildLabelData($validated);
        $data['barcodePng'] = (new DNS1D())->getBarcodePNG(
            $data['receptaculo'],
            'C128',
            2,
            70
        );

        $pdf = Pdf::loadView('marbetes.pdf', $data)->setPaper('a5', 'landscape');

        return response()->streamDownload(
            fn () => print ($pdf->output()),
            'marbete-cn35-'.$data['receptaculo'].'.pdf',
            ['Content-Type' => 'application/pdf']
        );
    }

    /** @param array<string, mixed> $values */
    private function buildLabelData(array $values): array
    {
        $fecha = \Illuminate\Support\Carbon::parse($values['fecha']);
        $peso = round((float) $values['peso'], 1);
        $pesoCodificado = str_pad((string) round($peso * 10), 4, '0', STR_PAD_LEFT);
        $destination = self::DESTINATIONS[$values['pais_codigo']];

        $values['origen_impc'] = strtoupper($values['origen_impc']);
        $values['destino_impc'] = $destination['impc'];
        $values['ciudad_destino'] = $destination['ciudad'];
        $values['pais_destino'] = $destination['pais'];
        $values['tipo_correo'] = strtoupper(trim($values['tipo_correo']));
        $values['vuelo'] = strtoupper(trim((string) ($values['vuelo'] ?? '')));
        $values['tren'] = strtoupper(trim((string) ($values['tren'] ?? '')));
        $values['descarga'] = $destination['descarga'];
        $values['categoria'] = self::MAIL_CATEGORY;
        $values['anio_despacho'] = substr($fecha->format('Y'), -1);
        $values['numero_despacho_formateado'] = str_pad((string) $values['numero_despacho'], 4, '0', STR_PAD_LEFT);
        $values['numero_receptaculo_formateado'] = str_pad((string) $values['numero_receptaculo'], 3, '0', STR_PAD_LEFT);
        $values['cantidad_envios_formateada'] = str_pad((string) $values['cantidad_envios'], 3, '0', STR_PAD_LEFT);
        $values['peso'] = $peso;
        $values['receptaculo'] = implode('', [
            $values['origen_impc'],
            $values['destino_impc'],
            self::MAIL_CATEGORY,
            $values['subclase'],
            $values['anio_despacho'],
            $values['numero_despacho_formateado'],
            $values['numero_receptaculo_formateado'],
            (string) (int) $values['ultimo_receptaculo'],
            (string) (int) $values['registrado_asegurado'],
            $pesoCodificado,
        ]);

        return $values;
    }
}

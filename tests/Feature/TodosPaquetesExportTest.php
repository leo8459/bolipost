<?php

namespace Tests\Feature;

use App\Exports\TodosPaquetesExport;
use Carbon\Carbon;
use Maatwebsite\Excel\Excel as ExcelWriter;
use Maatwebsite\Excel\Facades\Excel;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Tests\TestCase;

class TodosPaquetesExportTest extends TestCase
{
    public function test_filtered_packages_export_contains_data_filters_and_excel_formatting(): void
    {
        $rows = collect([
            (object) [
                'tipo' => 'CONTRATO',
                'codigo' => 'C0061A51965BO',
                'cod_especial' => 'LPZ00530',
                'origen' => 'LA PAZ',
                'destino' => 'TARIJA',
                'empresa' => '67373420',
                'remitente' => 'LUIS ALBERTO ANTI CALLATA',
                'destinatario' => 'MINISTERIO DE SALUD',
                'telefono' => '76543210',
                'peso' => '585.000',
                'precio' => '',
                'estado_nombre' => 'ENTREGADO',
                'justificacion' => '',
                'updated_at' => '2026-07-21 08:43:00',
            ],
            (object) [
                'tipo' => 'CONTRATO',
                'codigo' => 'C0032A81935BO',
                'cod_especial' => 'LPZ00528',
                'origen' => 'LA PAZ',
                'destino' => 'SUCRE',
                'empresa' => '72870056',
                'remitente' => 'AREZZU SADAFI',
                'destinatario' => 'ENTEL',
                'telefono' => '70000000',
                'peso' => '120.000',
                'precio' => null,
                'estado_nombre' => 'ENTREGADO',
                'justificacion' => '',
                'updated_at' => '2026-07-21 07:15:00',
            ],
        ]);
        $filters = [
            'search' => '',
            'type_label' => 'TODOS',
            'estado_label' => 'TODOS',
            'peso_min' => 100,
            'peso_max' => 1000000,
            'generated_at' => Carbon::parse('2026-08-10 10:41:00'),
        ];

        $contents = Excel::raw(new TodosPaquetesExport($rows, $filters), ExcelWriter::XLSX);
        $temporaryPath = tempnam(sys_get_temp_dir(), 'todos-paquetes-export-');
        file_put_contents($temporaryPath, $contents);

        try {
            $sheet = IOFactory::load($temporaryPath)->getActiveSheet();

            $this->assertSame('REPORTE DE PAQUETES FILTRADOS', $sheet->getCell('A1')->getValue());
            $this->assertStringContainsString('Peso mínimo: 100', (string) $sheet->getCell('A2')->getValue());
            $this->assertStringContainsString('Total de registros: 2', (string) $sheet->getCell('A3')->getValue());
            $this->assertSame('CÓDIGO', $sheet->getCell('B5')->getValue());
            $this->assertSame('C0061A51965BO', $sheet->getCell('B6')->getValue());
            $this->assertSame(585.0, $sheet->getCell('J6')->getValue());
            $this->assertSame('A5:N7', $sheet->getAutoFilter()->getRange());
            $this->assertSame('A6', $sheet->getFreezePane());
        } finally {
            @unlink($temporaryPath);
        }
    }
}

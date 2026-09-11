<?php

namespace Tests\Feature;

use App\Exports\PaquetesCertiAlmacenExport;
use Carbon\Carbon;
use Maatwebsite\Excel\Excel as ExcelWriter;
use Maatwebsite\Excel\Facades\Excel;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Tests\TestCase;

class PaquetesCertiAlmacenExportTest extends TestCase
{
    public function test_export_matches_the_ventanilla_certificados_report_format(): void
    {
        $rows = collect([
            (object) [
                'codigo' => 'RR000123456BO',
                'destinatario' => 'DESTINATARIO DE PRUEBA',
                'zona' => 'SOPOCACHI',
                'telefono' => '76543210',
                'cuidad' => 'LA PAZ',
                'ventanilla' => 'DD',
                'ventanillaRef' => (object) ['nombre_ventanilla' => 'DD'],
                'peso' => '0.125',
                'estado' => (object) ['nombre_estado' => 'VENTANILLA'],
                'created_at' => Carbon::parse('2026-09-11 11:34:00'),
            ],
        ]);

        $contents = Excel::raw(new PaquetesCertiAlmacenExport($rows), ExcelWriter::XLSX);
        $temporaryPath = tempnam(sys_get_temp_dir(), 'certi-almacen-export-');
        file_put_contents($temporaryPath, $contents);

        try {
            $sheet = IOFactory::load($temporaryPath)->getActiveSheet();

            $this->assertSame('Ventanilla Certificados', $sheet->getTitle());
            $this->assertSame(
                ['CODIGO', 'DESTINATARIO', 'BANDEJA', 'TELEFONO', 'CUIDAD', 'VENTANILLA', 'PESO', 'ESTADO', 'FECHA'],
                $sheet->rangeToArray('A1:I1', null, true, true, false)[0]
            );
            $this->assertSame('RR000123456BO', $sheet->getCell('A2')->getValue());
            $this->assertSame('SOPOCACHI', $sheet->getCell('C2')->getValue());
            $this->assertSame('76543210', (string) $sheet->getCell('D2')->getValue());
            $this->assertSame(0.125, $sheet->getCell('G2')->getValue());
            $this->assertSame('VENTANILLA', $sheet->getCell('H2')->getValue());
            $this->assertSame('A1:I2', $sheet->getAutoFilter()->getRange());
            $this->assertSame('A2', $sheet->getFreezePane());
            $this->assertTrue($sheet->getStyle('A1')->getFont()->getBold());
            $this->assertSame(30.0, $sheet->getColumnDimension('C')->getWidth());
            $this->assertSame('yyyy-mm-dd hh:mm', $sheet->getStyle('I2')->getNumberFormat()->getFormatCode());
        } finally {
            @unlink($temporaryPath);
        }
    }
}

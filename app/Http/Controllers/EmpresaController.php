<?php

namespace App\Http\Controllers;

<<<<<<< HEAD
use App\Models\Empresa;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

=======
>>>>>>> a41ccfb (Uchazara)
class EmpresaController extends Controller
{
    public function index()
    {
        return view('empresa.index');
    }
<<<<<<< HEAD

    public function importForm()
    {
        return view('empresa.import');
    }

    public function import(Request $request): RedirectResponse
    {
        $request->validate([
            'archivo' => ['required', 'file', 'mimes:xlsx,xls'],
        ], [
            'archivo.required' => 'Debes seleccionar un archivo Excel.',
            'archivo.mimes' => 'El archivo debe ser Excel (.xlsx o .xls).',
        ]);

        $sheet = IOFactory::load($request->file('archivo')->getRealPath())->getActiveSheet();
        $highestRow = $sheet->getHighestDataRow();
        $created = 0;
        $updated = 0;
        $errors = [];

        DB::transaction(function () use ($sheet, $highestRow, &$created, &$updated, &$errors) {
            for ($row = 2; $row <= $highestRow; $row++) {
                $nombre = $this->normalizeExcelText($sheet->getCell("A{$row}")->getValue());
                $sigla = $this->normalizeExcelText($sheet->getCell("B{$row}")->getValue());
                $codigoCliente = $this->normalizeExcelText($sheet->getCell("C{$row}")->getValue());

                if ($nombre === '' && $sigla === '' && $codigoCliente === '') {
                    continue;
                }

                if ($nombre === '' || $sigla === '' || $codigoCliente === '') {
                    $errors[] = "Fila {$row}: nombre, sigla y codigo_cliente son obligatorios.";
                    continue;
                }

                $payload = [
                    'nombre' => $nombre,
                    'sigla' => $sigla,
                    'codigo_cliente' => $codigoCliente,
                ];

                $empresa = Empresa::query()->where('codigo_cliente', $codigoCliente)->first();

                if ($empresa) {
                    $empresa->update($payload);
                    $updated++;
                    continue;
                }

                Empresa::query()->create($payload);
                $created++;
            }
        });

        $message = "Importacion completada. Creadas: {$created}. Actualizadas: {$updated}.";
        if ($errors !== []) {
            $message .= ' Filas con error: ' . count($errors) . '.';
        }

        $redirect = redirect()
            ->route('empresas.index')
            ->with('success', $message);

        if ($errors !== []) {
            $redirect->with('import_errors', array_slice($errors, 0, 20));
        }

        return $redirect;
    }

    public function downloadTemplateExcel(): BinaryFileResponse
    {
        $spreadsheet = new Spreadsheet();

        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Empresas');
        $this->buildDataSheet($sheet);

        $instructions = new Worksheet($spreadsheet, 'Instrucciones');
        $spreadsheet->addSheet($instructions, 0);
        $this->buildInstructionsSheet($instructions);
        $spreadsheet->setActiveSheetIndexByName('Empresas');

        $tempPath = tempnam(sys_get_temp_dir(), 'empresa_template_');
        if ($tempPath === false) {
            abort(500, 'No se pudo generar la plantilla temporal.');
        }

        $xlsxPath = $tempPath . '.xlsx';
        @rename($tempPath, $xlsxPath);

        $writer = new Xlsx($spreadsheet);
        $writer->save($xlsxPath);

        return response()->download($xlsxPath, 'plantilla_empresas.xlsx')->deleteFileAfterSend(true);
    }

    private function normalizeExcelText(mixed $value): string
    {
        if ($value === null) {
            return '';
        }

        return strtoupper(trim((string) $value));
    }

    private function buildDataSheet(Worksheet $sheet): void
    {
        $sheet->fromArray([
            ['NOMBRE', 'SIGLA', 'CODIGO_CLIENTE'],
            ['EMPRESA DEMO S.R.L.', 'EDS', 'CLI001'],
        ]);

        $sheet->freezePane('A2');
        $sheet->getRowDimension(1)->setRowHeight(24);

        foreach (['A' => 38, 'B' => 20, 'C' => 24] as $column => $width) {
            $sheet->getColumnDimension($column)->setWidth($width);
        }

        $sheet->getStyle('A1:C1')->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['rgb' => '20539A'],
            ],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_CENTER,
                'vertical' => Alignment::VERTICAL_CENTER,
            ],
            'borders' => [
                'allBorders' => [
                    'borderStyle' => Border::BORDER_THIN,
                    'color' => ['rgb' => 'D1D5DB'],
                ],
            ],
        ]);

        $sheet->getStyle('A2:C2000')->applyFromArray([
            'borders' => [
                'allBorders' => [
                    'borderStyle' => Border::BORDER_THIN,
                    'color' => ['rgb' => 'E5E7EB'],
                ],
            ],
        ]);
    }

    private function buildInstructionsSheet(Worksheet $sheet): void
    {
        $sheet->setCellValue('A1', 'IMPORTACION DE EMPRESAS');
        $sheet->setCellValue('A3', '1) Llena la hoja "Empresas".');
        $sheet->setCellValue('A4', '2) Las columnas obligatorias son: nombre, sigla y codigo_cliente.');
        $sheet->setCellValue('A5', '3) Si el codigo_cliente ya existe, el sistema actualiza la empresa.');
        $sheet->setCellValue('A6', '4) Si el codigo_cliente no existe, el sistema crea una nueva empresa.');
        $sheet->setCellValue('A7', '5) No cambies los nombres de las columnas.');

        $sheet->mergeCells('A1:D1');
        $sheet->getStyle('A1')->applyFromArray([
            'font' => ['bold' => true, 'size' => 14, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['rgb' => '20539A'],
            ],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_CENTER,
                'vertical' => Alignment::VERTICAL_CENTER,
            ],
        ]);

        $sheet->getRowDimension(1)->setRowHeight(26);
        $sheet->getColumnDimension('A')->setWidth(90);
    }
}
=======
}

>>>>>>> a41ccfb (Uchazara)

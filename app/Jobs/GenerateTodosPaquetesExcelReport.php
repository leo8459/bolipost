<?php

namespace App\Jobs;

use App\Models\PackageReportExport;
use App\Services\TodosPaquetesReportQuery;
use App\Services\TodosPaquetesXlsxWriter;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Throwable;

class GenerateTodosPaquetesExcelReport implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $timeout = 3600;

    public int $tries = 1;

    public function __construct(public readonly string $exportId) {}

    public function handle(TodosPaquetesReportQuery $reportQuery, TodosPaquetesXlsxWriter $writer): void
    {
        $report = PackageReportExport::query()->findOrFail($this->exportId);
        $disk = Storage::disk('local');
        $directory = 'todos-paquetes/reportes';
        $relativePath = $directory.'/'.$report->id.'.xlsx';
        $xlsxPath = $disk->path($relativePath);
        $sheetPaths = [];
        $handle = false;
        $disk->makeDirectory($directory);

        try {
            $sheetNumber = 1;
            $sheetRows = 0;
            $sheetPath = $disk->path($directory.'/'.$report->id.'.sheet'.$sheetNumber.'.xml');
            $sheetPaths[] = $sheetPath;
            $handle = $writer->openSheet($sheetPath);

            $report->forceFill([
                'status' => 'processing',
                'file_path' => $relativePath,
                'error_message' => null,
                'progress' => 1,
                'progress_message' => 'Contando los paquetes que coinciden con los filtros...',
            ])->save();

            $totalRows = $reportQuery->count($report->filters);
            $report->forceFill([
                'total_rows' => $totalRows,
                'progress' => $totalRows > 0 ? 3 : 90,
                'progress_message' => $totalRows > 0 ? 'Preparando los datos del Excel...' : 'Creando el archivo Excel...',
            ])->save();

            $rowsWritten = 0;
            $reportQuery->chunked($report->filters, function (Collection $rows) use (&$handle, &$sheetNumber, &$sheetRows, &$sheetPaths, $directory, $disk, $report, &$rowsWritten, $totalRows, $writer): void {
                foreach ($rows as $row) {
                    if ($sheetRows >= $writer->dataRowsPerSheet()) {
                        $writer->finishSheet($handle, $sheetRows);
                        $handle = false;
                        $sheetNumber++;
                        $sheetRows = 0;
                        $sheetPath = $disk->path($directory.'/'.$report->id.'.sheet'.$sheetNumber.'.xml');
                        $sheetPaths[] = $sheetPath;
                        $handle = $writer->openSheet($sheetPath);
                    }

                    $writer->writeRow($handle, [
                        $row->tipo ?? '',
                        $row->codigo ?? '',
                        $row->cod_especial ?? '',
                        $row->origen ?? '',
                        $row->destino ?? '',
                        $row->empresa ?? '',
                        $row->remitente ?? '',
                        $row->destinatario ?? '',
                        $row->telefono ?? '',
                        $this->numericXlsxValue($row->peso ?? null, 3),
                        $this->numericXlsxValue($row->precio ?? null, 2),
                        $row->estado_nombre ?? 'SIN ESTADO',
                        $row->justificacion ?? '',
                        $row->fecha_recojo ?? '',
                    ], $sheetRows + 2, [9 => true, 10 => true]);
                    $rowsWritten++;
                    $sheetRows++;
                }

                $progress = $totalRows > 0
                    ? min(90, 3 + (int) floor(87 * min(1, $rowsWritten / $totalRows)))
                    : 90;

                $report->forceFill([
                    'row_count' => $rowsWritten,
                    'progress' => $progress,
                    'progress_message' => 'Escribiendo el Excel: '.$rowsWritten.' de '.$totalRows.' paquetes...',
                ])->save();
            });

            $report->forceFill([
                'progress' => 92,
                'progress_message' => 'Cerrando las hojas del Excel...',
            ])->save();
            $writer->finishSheet($handle, $sheetRows);
            $handle = false;
            $report->forceFill([
                'progress' => 97,
                'progress_message' => 'Finalizando el archivo Excel...',
            ])->save();
            $writer->package($sheetPaths, $xlsxPath);

            $report->forceFill([
                'status' => 'completed',
                'row_count' => $rowsWritten,
                'total_rows' => $rowsWritten,
                'progress' => 100,
                'progress_message' => 'Excel listo para descargar.',
                'file_path' => $relativePath,
                'expires_at' => now()->addDays(7),
            ])->save();
        } catch (Throwable $exception) {
            if (is_resource($handle)) {
                fclose($handle);
            }

            $disk->delete($relativePath);

            throw $exception;
        } finally {
            foreach ($sheetPaths as $sheetPath) {
                if (is_file($sheetPath)) {
                    @unlink($sheetPath);
                }
            }
        }
    }

    public function failed(?Throwable $exception): void
    {
        $report = PackageReportExport::query()->find($this->exportId);
        if (! $report) {
            return;
        }

        if ($report->file_path) {
            Storage::disk('local')->delete($report->file_path);
        }

        $report->forceFill([
            'status' => 'failed',
            'error_message' => 'No se pudo generar el reporte. Vuelve a solicitarlo.',
            'progress_message' => 'No se pudo generar el reporte. Vuelve a solicitarlo.',
            'file_path' => null,
            'expires_at' => now()->addDays(7),
        ])->save();
    }

    private function numericXlsxValue(mixed $value, int $decimals): string
    {
        $text = trim((string) ($value ?? ''));

        if ($text === '') {
            return '';
        }

        if (! is_numeric($text)) {
            throw new RuntimeException('El reporte contiene un valor numérico inválido.');
        }

        return number_format((float) $text, $decimals, '.', '');
    }
}

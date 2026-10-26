<?php

namespace App\Jobs;

use App\Models\PackageReportExport;
use App\Models\User;
use App\Services\TodosPaquetesHistoryReportService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Throwable;

class GenerateTodosPaquetesHistoryPdfReport implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $timeout = 3600;

    public int $tries = 1;

    public function __construct(public readonly string $exportId) {}

    public function handle(TodosPaquetesHistoryReportService $historyReportService): void
    {
        $report = PackageReportExport::query()->findOrFail($this->exportId);
        $user = User::query()->findOrFail($report->user_id);
        $disk = Storage::disk('local');
        $relativePath = 'todos-paquetes/reportes/'.$report->id.'.pdf';
        $disk->makeDirectory('todos-paquetes/reportes');
        $codes = (array) data_get($report->filters, 'codes', []);

        try {
            $report->forceFill([
                'status' => 'processing',
                'file_path' => $relativePath,
                'error_message' => null,
                'total_rows' => count($codes),
                'progress' => 3,
                'progress_message' => 'Preparando las guías del reporte...',
            ])->save();

            $result = $historyReportService->generate(
                $codes,
                $user,
                function (int $progress, string $message) use ($report): void {
                    $report->forceFill([
                        'progress' => min(95, max(3, $progress)),
                        'progress_message' => $message,
                    ])->save();
                },
            );

            if (! $disk->put($relativePath, $result['contents'])) {
                throw new RuntimeException('No se pudo guardar el reporte PDF.');
            }

            $report->forceFill([
                'status' => 'completed',
                'row_count' => $result['package_count'],
                'total_rows' => count($codes),
                'progress' => 100,
                'progress_message' => 'PDF listo para descargar.',
                'file_path' => $relativePath,
                'expires_at' => now()->addDays(7),
            ])->save();
        } catch (Throwable $exception) {
            $disk->delete($relativePath);

            throw $exception;
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
            'error_message' => 'No se pudo generar el reporte PDF. Vuelve a solicitarlo.',
            'progress_message' => 'No se pudo generar el reporte PDF. Vuelve a solicitarlo.',
            'file_path' => null,
            'expires_at' => now()->addDays(7),
        ])->save();
    }
}

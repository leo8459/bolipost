<?php

namespace App\Services;

use App\Models\Empresa;
use App\Models\EmpresaHistorial;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

class EmpresaHistoryService
{
    public function archiveAndRenew(Empresa $empresa, array $payload, ?UploadedFile $newPdf, ?int $userId): EmpresaHistorial
    {
        $disk = Storage::disk('public');
        $newPdfPath = $newPdf?->store('empresa-documentos', 'public');
        $historyPdfPath = null;
        $oldPdfPath = null;

        try {
            [$history, $oldPdfPath] = DB::transaction(function () use (
                $empresa,
                $payload,
                $newPdfPath,
                $userId,
                $disk,
                &$historyPdfPath
            ): array {
                $current = Empresa::query()->lockForUpdate()->findOrFail($empresa->id);
                $oldPdfPath = $current->documento_pdf_path;

                if ($oldPdfPath && $disk->exists($oldPdfPath)) {
                    $extension = pathinfo($oldPdfPath, PATHINFO_EXTENSION) ?: 'pdf';
                    $historyPdfPath = 'empresa-historial/'.Str::uuid().'.'.$extension;

                    if (! $disk->copy($oldPdfPath, $historyPdfPath)) {
                        throw new \RuntimeException('No se pudo respaldar el PDF vigente de la empresa.');
                    }
                }

                $history = EmpresaHistorial::query()->create([
                    'empresa_id' => $current->id,
                    'archivado_por' => $userId,
                    'nombre' => $current->nombre,
                    'sigla' => $current->sigla,
                    'codigo_cliente' => $current->codigo_cliente,
                    'clasificacion' => $current->clasificacion,
                    'documentacion_legal' => $current->documentacion_legal,
                    'inicio_contrato' => $current->inicio_contrato,
                    'fin_contrato' => $current->fin_contrato,
                    'cobertura' => $current->cobertura,
                    'presupuesto' => $current->presupuesto,
                    'documento_pdf_path' => $historyPdfPath,
                    'datos_completos' => $current->getAttributes(),
                ]);

                if ($newPdfPath) {
                    $payload['documento_pdf_path'] = $newPdfPath;
                }

                $current->update($payload);

                return [$history, $oldPdfPath];
            });
        } catch (Throwable $exception) {
            if ($newPdfPath) {
                $disk->delete($newPdfPath);
            }
            if ($historyPdfPath) {
                $disk->delete($historyPdfPath);
            }

            throw $exception;
        }

        if ($newPdfPath && $oldPdfPath) {
            $disk->delete($oldPdfPath);
        }

        app(EmpresaContractUserSyncService::class)->syncCompanyById((int) $empresa->id);

        return $history;
    }
}

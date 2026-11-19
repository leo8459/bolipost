<?php

namespace App\Services;

use App\Models\Empresa;
use App\Models\TarifarioContrato;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class TarifarioContratoService
{
    public function aprobar(int $tarifarioId): void
    {
        DB::transaction(function () use ($tarifarioId) {
            $tarifario = TarifarioContrato::query()->lockForUpdate()->findOrFail($tarifarioId);
            if ($tarifario->publicado_en) {
                return;
            }

            if (! $tarifario->tarifas()->exists()) {
                throw ValidationException::withMessages(['tarifario_contrato_id' => 'Carga al menos una tarifa antes de aprobar el uso del tarifario.']);
            }

            if ($tarifario->empresa_titular_id) {
                $this->activarParaEmpresas($tarifario->id, [$tarifario->empresa_titular_id]);
            }
            $tarifario->update(['publicado_en' => now()]);
        });
    }

    public function asignar(int $tarifarioId, array $empresaIds): void
    {
        DB::transaction(function () use ($tarifarioId, $empresaIds) {
            $tarifario = TarifarioContrato::query()->lockForUpdate()->findOrFail($tarifarioId);
            if (! $tarifario->tarifas()->exists()) {
                throw ValidationException::withMessages(['empresas' => 'Agrega tarifas antes de asignar este tarifario.']);
            }
            if (! $tarifario->publicado_en && $tarifario->empresa_titular_id) {
                $empresaIds[] = $tarifario->empresa_titular_id;
            }
            $this->activarParaEmpresas($tarifarioId, $empresaIds);
            if (! $tarifario->publicado_en) {
                $tarifario->update(['publicado_en' => now()]);
            }
        });
    }

    private function activarParaEmpresas(int $tarifarioId, array $empresaIds): void
    {
        $empresas = Empresa::query()->whereIn('id', $empresaIds)->orderBy('id')->lockForUpdate()->get();
        $fecha = now();
        foreach ($empresas as $empresa) {
            $asignaciones = DB::table('tarifario_contrato_empresa')->where('empresa_id', $empresa->id);
            $activas = (clone $asignaciones)->where('activo', true);
            $activas->where('tarifario_contrato_id', '<>', $tarifarioId)
                ->update(['activo' => false, 'finalizado_en' => $fecha]);
            if ((clone $asignaciones)->where('tarifario_contrato_id', $tarifarioId)->where('activo', true)->exists()) {
                continue;
            }
            DB::table('tarifario_contrato_empresa')->updateOrInsert(
                ['empresa_id' => $empresa->id, 'tarifario_contrato_id' => $tarifarioId],
                ['activo' => true, 'asignado_en' => $fecha, 'finalizado_en' => null]
            );
        }
    }

    public function editable(int $id): TarifarioContrato
    {
        $tarifario = TarifarioContrato::query()->lockForUpdate()->findOrFail($id);
        if ($tarifario->publicado_en) {
            throw ValidationException::withMessages(['tarifario_contrato_id' => 'Este tarifario ya fue publicado y conserva sus precios. Cópialo a un nuevo año para modificarlos.']);
        }

        return $tarifario;
    }
}

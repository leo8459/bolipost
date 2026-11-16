<?php

namespace App\Services;

use App\Models\Empresa;
use App\Models\TarifarioContrato;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class TarifarioContratoService
{
    public function asignar(int $tarifarioId, array $empresaIds): void
    {
        DB::transaction(function () use ($tarifarioId, $empresaIds) {
            $tarifario = TarifarioContrato::query()->lockForUpdate()->findOrFail($tarifarioId);
            if (! $tarifario->tarifas()->exists()) {
                throw ValidationException::withMessages(['empresas' => 'Agrega tarifas antes de asignar este tarifario.']);
            }
            $empresas = Empresa::query()->whereIn('id', $empresaIds)->orderBy('id')->lockForUpdate()->get();
            foreach ($empresas as $empresa) {
                $actual = DB::table('tarifario_contrato_empresa')->where('empresa_id', $empresa->id)->where('activo', true)->first();
                if ($actual && (int) $actual->tarifario_contrato_id === $tarifarioId) {
                    continue;
                }
                DB::table('tarifario_contrato_empresa')->where('empresa_id', $empresa->id)->where('activo', true)
                    ->update(['activo' => false, 'finalizado_en' => now()]);
                DB::table('tarifario_contrato_empresa')->updateOrInsert(
                    ['empresa_id' => $empresa->id, 'tarifario_contrato_id' => $tarifarioId],
                    ['activo' => true, 'asignado_en' => now(), 'finalizado_en' => null]
                );
            }
            if (! $tarifario->publicado_en) {
                $tarifario->update(['publicado_en' => now()]);
            }
        });
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

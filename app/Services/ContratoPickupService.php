<?php

namespace App\Services;

use App\Models\Estado;
use App\Models\Recojo;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class ContratoPickupService
{
    public const EVENTO_ID_CONTRATO_RECOGIDO = 295;

    /**
     * @param  array<int, int|string>  $identificadores
     * @return array{actualizados: int, codigos: array<int, string>, no_procesados: array<int, int|string>}
     */
    public function recogerPorIds(User $actor, array $identificadores): array
    {
        return $this->recoger($actor, $identificadores, 'id');
    }

    /**
     * @param  array<int, string>  $identificadores
     * @return array{actualizados: int, codigos: array<int, string>, no_procesados: array<int, string>}
     */
    public function recogerPorCodigos(User $actor, array $identificadores): array
    {
        return $this->recoger($actor, $identificadores, 'codigo');
    }

    /**
     * @param  array<int, int|string>  $identificadores
     * @return array{actualizados: int, codigos: array<int, string>, no_procesados: array<int, int|string>}
     */
    private function recoger(User $actor, array $identificadores, string $campo): array
    {
        $solicitudId = $this->estadoId('SOLICITUD');
        $almacenId = $this->estadoId('ALMACEN');

        if (! DB::table('eventos')->where('id', self::EVENTO_ID_CONTRATO_RECOGIDO)->exists()) {
            throw new RuntimeException('No existe el evento con ID '.self::EVENTO_ID_CONTRATO_RECOGIDO.' en la tabla eventos.');
        }

        $valores = collect($identificadores)
            ->map(fn ($valor) => $campo === 'id'
                ? (int) $valor
                : strtoupper(trim((string) $valor)))
            ->filter(fn ($valor) => $campo === 'id' ? $valor > 0 : $valor !== '')
            ->unique()
            ->values();

        if ($valores->isEmpty()) {
            return ['actualizados' => 0, 'codigos' => [], 'no_procesados' => []];
        }

        $userCity = strtoupper(trim((string) $actor->ciudad));
        $hasGlobalDepartmentAccess = $actor->hasGlobalDepartmentAccess();

        if (! $hasGlobalDepartmentAccess && $userCity === '') {
            throw new RuntimeException('El usuario propietario de la credencial API no tiene una ciudad asignada.');
        }

        return DB::transaction(function () use (
            $actor,
            $almacenId,
            $campo,
            $hasGlobalDepartmentAccess,
            $solicitudId,
            $userCity,
            $valores
        ): array {
            $query = Recojo::query()
                ->where('estados_id', $solicitudId)
                ->when(! $hasGlobalDepartmentAccess, fn (Builder $query) => $query
                    ->whereRaw('trim(upper(origen)) = ?', [$userCity]));

            if ($campo === 'id') {
                $query->whereIn('id', $valores->all());
            } else {
                $query->whereIn(DB::raw('upper(trim(codigo))'), $valores->all());
            }

            $recojos = $query->lockForUpdate()->get(['id', 'codigo']);
            $now = now();

            if ($recojos->isNotEmpty()) {
                Recojo::query()
                    ->whereIn('id', $recojos->pluck('id')->all())
                    ->update([
                        'estados_id' => $almacenId,
                        'fecha_recojo' => $now,
                        'updated_at' => $now,
                    ]);

                DB::table('eventos_contrato')->insert($recojos
                    ->map(fn (Recojo $recojo): array => [
                        'codigo' => trim((string) $recojo->codigo),
                        'evento_id' => self::EVENTO_ID_CONTRATO_RECOGIDO,
                        'user_id' => (int) $actor->id,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ])
                    ->all());
            }

            $procesados = $campo === 'id'
                ? $recojos->pluck('id')->map(fn ($id) => (int) $id)
                : $recojos->pluck('codigo')->map(fn ($codigo) => strtoupper(trim((string) $codigo)));

            return [
                'actualizados' => $recojos->count(),
                'codigos' => $recojos->pluck('codigo')->map(fn ($codigo) => (string) $codigo)->values()->all(),
                'no_procesados' => $valores->diff($procesados)->values()->all(),
            ];
        });
    }

    private function estadoId(string $nombre): int
    {
        $id = (int) (Estado::query()
            ->whereRaw('trim(upper(nombre_estado)) = ?', [$nombre])
            ->value('id') ?? 0);

        if ($id <= 0) {
            throw new RuntimeException("No existe el estado {$nombre} en la tabla estados.");
        }

        return $id;
    }
}

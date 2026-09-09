<?php

namespace App\Services;

use App\Models\Estado;
use App\Models\Recojo;
use App\Models\SolicitudCliente;
use App\Models\User;
use App\Support\TiktokerEvent;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class ContratoPickupService
{
    public const EVENTO_ID_CONTRATO_RECOGIDO = 295;

    public const PESO_MAXIMO_KG = 700.000;

    /**
     * @param  array<int, int|string>  $identificadores
     * @return array{actualizados: int, actualizados_por_tipo: array{contrato: int, solicitud: int}, codigos: array<int, string>, no_procesados: array<int, int|string>}
     */
    public function recogerPorIds(User $actor, array $identificadores): array
    {
        return $this->recoger($actor, $identificadores, 'id');
    }

    /**
     * @param  array<int, int|string>  $identificadores
     * @param  array<int|string, int|float|string>  $pesosPorId
     * @return array{actualizados: int, actualizados_por_tipo: array{contrato: int, solicitud: int}, codigos: array<int, string>, no_procesados: array<int, int|string>}
     */
    public function recogerPorIdsConPesos(User $actor, array $identificadores, array $pesosPorId): array
    {
        return $this->recoger($actor, $identificadores, 'id', $pesosPorId);
    }

    /**
     * @param  array<int, string>  $identificadores
     * @return array{actualizados: int, actualizados_por_tipo: array{contrato: int, solicitud: int}, codigos: array<int, string>, no_procesados: array<int, string>}
     */
    public function recogerPorCodigos(User $actor, array $identificadores): array
    {
        return $this->recoger($actor, $identificadores, 'codigo');
    }

    /**
     * @param  array<int, string>  $identificadores
     * @param  array<string, int|float|string>  $pesosPorCodigo
     * @return array{actualizados: int, actualizados_por_tipo: array{contrato: int, solicitud: int}, codigos: array<int, string>, no_procesados: array<int, string>}
     */
    public function recogerPorCodigosConPesos(User $actor, array $identificadores, array $pesosPorCodigo): array
    {
        $pesosNormalizados = collect($pesosPorCodigo)
            ->mapWithKeys(fn ($peso, $codigo) => [strtoupper(trim((string) $codigo)) => $peso])
            ->all();

        return $this->recoger($actor, $identificadores, 'codigo', $pesosNormalizados);
    }

    /**
     * @param  array<int, int|string>  $identificadores
     * @return array{actualizados: int, actualizados_por_tipo: array{contrato: int, solicitud: int}, codigos: array<int, string>, no_procesados: array<int, int|string>}
     */
    private function recoger(User $actor, array $identificadores, string $campo, ?array $pesosPorIdentificador = null): array
    {
        $solicitudId = $this->estadoId('SOLICITUD');
        $almacenId = $this->estadoId('ALMACEN');

        $valores = collect($identificadores)
            ->map(fn ($valor) => $campo === 'id'
                ? (int) $valor
                : strtoupper(trim((string) $valor)))
            ->filter(fn ($valor) => $campo === 'id' ? $valor > 0 : $valor !== '')
            ->unique()
            ->values();

        if ($valores->isEmpty()) {
            return [
                'actualizados' => 0,
                'actualizados_por_tipo' => ['contrato' => 0, 'solicitud' => 0],
                'codigos' => [],
                'no_procesados' => [],
            ];
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
            $valores,
            $pesosPorIdentificador
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

            $solicitudes = collect();

            // Los IDs numericos no son globales entre tablas. El flujo web de
            // contratos usa IDs, por lo que Delivery Express solo se incorpora
            // cuando la integracion externa identifica los envios por codigo.
            if ($campo === 'codigo') {
                $solicitudesQuery = SolicitudCliente::query()
                    ->where('estado_id', $solicitudId)
                    ->when(! $hasGlobalDepartmentAccess, fn (Builder $query) => $query
                        ->whereRaw('trim(upper(origen)) = ?', [$userCity]));

                $solicitudesQuery->where(function (Builder $query) use ($valores): void {
                    $query
                        ->whereIn(DB::raw('upper(trim(codigo_solicitud))'), $valores->all())
                        ->orWhereIn(DB::raw('upper(trim(barcode))'), $valores->all());
                });

                $solicitudes = $solicitudesQuery
                    ->lockForUpdate()
                    ->get(['id', 'cliente_id', 'codigo_solicitud', 'barcode']);
            }

            if ($pesosPorIdentificador !== null) {
                $pesosRecojos = $recojos->mapWithKeys(function (Recojo $recojo) use ($campo, $pesosPorIdentificador): array {
                    $llave = $campo === 'id' ? $recojo->id : strtoupper(trim((string) $recojo->codigo));

                    return [$recojo->id => $this->normalizarPeso($pesosPorIdentificador[$llave] ?? null)];
                });
                $pesosSolicitudes = $solicitudes->mapWithKeys(function (SolicitudCliente $solicitud) use ($pesosPorIdentificador): array {
                    $codigoSolicitud = strtoupper(trim((string) $solicitud->codigo_solicitud));
                    $barcode = strtoupper(trim((string) $solicitud->barcode));
                    $peso = $pesosPorIdentificador[$codigoSolicitud]
                        ?? $pesosPorIdentificador[$barcode]
                        ?? null;

                    return [$solicitud->id => $this->normalizarPeso($peso)];
                });
                $codigosPesoInvalido = $recojos
                    ->filter(fn (Recojo $recojo) => ! $this->pesoValido($pesosRecojos->get($recojo->id)))
                    ->pluck('codigo')
                    ->map(fn ($codigo) => (string) $codigo)
                    ->values();

                if ($codigosPesoInvalido->isNotEmpty()) {
                    throw new RuntimeException(
                        'Por favor ingrese un peso entre 0,001 y 700,000 kg para los paquetes: '
                        .$codigosPesoInvalido->implode(', ').'.'
                    );
                }

                foreach ($recojos as $recojo) {
                    $recojo->forceFill(['peso' => $pesosRecojos->get($recojo->id)])->save();
                }

                foreach ($solicitudes as $solicitud) {
                    $pesoSolicitud = $pesosSolicitudes->get($solicitud->id);
                    if ($this->pesoValido($pesoSolicitud)) {
                        $solicitud->forceFill(['peso' => $pesoSolicitud])->save();
                    }
                }
            }

            $now = now();

            if ($recojos->isNotEmpty()) {
                if (! DB::table('eventos')->where('id', self::EVENTO_ID_CONTRATO_RECOGIDO)->exists()) {
                    throw new RuntimeException('No existe el evento con ID '.self::EVENTO_ID_CONTRATO_RECOGIDO.' en la tabla eventos.');
                }

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

            if ($solicitudes->isNotEmpty()) {
                SolicitudCliente::query()
                    ->whereIn('id', $solicitudes->pluck('id')->all())
                    ->update([
                        'estado_id' => $almacenId,
                        'updated_at' => $now,
                    ]);

                $eventoTiktokerId = TiktokerEvent::resolveId(TiktokerEvent::RECIBIDA_ALMACEN);

                DB::table('eventos_tiktoker')->insert($solicitudes
                    ->map(function (SolicitudCliente $solicitud) use ($actor, $eventoTiktokerId, $now): array {
                        return [
                            'codigo' => $this->codigoSolicitud($solicitud),
                            'evento_id' => $eventoTiktokerId,
                            'user_id' => (int) $actor->id,
                            'cliente_id' => null,
                            'created_at' => $now,
                            'updated_at' => $now,
                        ];
                    })
                    ->all());
            }

            $procesados = $campo === 'id'
                ? $recojos->pluck('id')
                    ->concat($solicitudes->pluck('id'))
                    ->map(fn ($id) => (int) $id)
                    ->unique()
                : $recojos->pluck('codigo')
                    ->concat($solicitudes->pluck('codigo_solicitud'))
                    ->concat($solicitudes->pluck('barcode'))
                    ->map(fn ($codigo) => strtoupper(trim((string) $codigo)))
                    ->filter(fn ($codigo) => $codigo !== '')
                    ->unique();

            $codigos = $recojos->pluck('codigo')
                ->map(fn ($codigo) => (string) $codigo)
                ->concat($solicitudes->map(fn (SolicitudCliente $solicitud) => $this->codigoSolicitud($solicitud)))
                ->values()
                ->all();

            return [
                'actualizados' => $recojos->count() + $solicitudes->count(),
                'actualizados_por_tipo' => [
                    'contrato' => $recojos->count(),
                    'solicitud' => $solicitudes->count(),
                ],
                'codigos' => $codigos,
                'no_procesados' => $valores->diff($procesados)->values()->all(),
            ];
        });
    }

    private function codigoSolicitud(SolicitudCliente $solicitud): string
    {
        $codigo = trim((string) $solicitud->codigo_solicitud);

        return $codigo !== '' ? $codigo : trim((string) $solicitud->barcode);
    }

    private function normalizarPeso(mixed $peso): ?float
    {
        $valor = str_replace(',', '.', trim((string) $peso));

        return is_numeric($valor) ? round((float) $valor, 3) : null;
    }

    private function pesoValido(?float $peso): bool
    {
        return $peso !== null && $peso >= 0.001 && $peso <= self::PESO_MAXIMO_KG;
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

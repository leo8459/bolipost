<?php

use App\Support\CarteroEvent;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public $withinTransaction = true;

    private const TRACKING_TABLES = [
        'eventos_ems',
        'eventos_certi',
        'eventos_ordi',
        'eventos_contrato',
        'eventos_tiktoker',
    ];

    public function up(): void
    {
        if (! Schema::hasTable('eventos')) {
            return;
        }

        $patterns = [
            'Paquete en camino para entrega fisica. Asignado a CARTERO por %' => $this->findOrCreateEvent(CarteroEvent::ASIGNADO),
            'Intento de entrega registrado por %. Devuelto a ventanilla: %.' => $this->findOrCreateEvent(CarteroEvent::INTENTO_VENTANILLA),
            'Cambio de cartero realizado por %' => $this->findOrCreateEvent(CarteroEvent::CAMBIADO),
            'Quitar cartero. Paquete devuelto a estado anterior desde Carteros Asignados. Ejecutado por %.' => $this->findOrCreateEvent(CarteroEvent::QUITADO),
        ];

        foreach ($patterns as $pattern => $targetId) {
            $this->consolidatePattern($pattern, $targetId);
        }

        $this->consolidateExactDuplicates();

        if (DB::getDriverName() === 'pgsql') {
            DB::statement(
                'CREATE UNIQUE INDEX IF NOT EXISTS eventos_nombre_normalizado_unique '
                .'ON eventos ((UPPER(BTRIM(nombre_evento))))'
            );
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('DROP INDEX IF EXISTS eventos_nombre_normalizado_unique');
        }
    }

    private function findOrCreateEvent(string $name): int
    {
        $id = (int) DB::table('eventos')
            ->whereRaw('TRIM(UPPER(nombre_evento)) = ?', [mb_strtoupper(trim($name))])
            ->value('id');

        return $id > 0
            ? $id
            : (int) DB::table('eventos')->insertGetId([
                'nombre_evento' => $name,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
    }

    private function consolidatePattern(string $pattern, int $targetId): void
    {
        $legacyEvents = DB::table('eventos')
            ->where('nombre_evento', 'like', $pattern)
            ->where('id', '<>', $targetId)
            ->get(['id', 'nombre_evento']);

        foreach ($legacyEvents as $event) {
            foreach (self::TRACKING_TABLES as $table) {
                if (! Schema::hasTable($table)) {
                    continue;
                }

                DB::table($table)->where('evento_id', $event->id)->update([
                    'evento_id' => $targetId,
                    'detalle_evento' => $event->nombre_evento,
                    'updated_at' => now(),
                ]);
            }

            $this->replaceAuxiliaryReferences((int) $event->id, $targetId);
            DB::table('eventos')->where('id', $event->id)->delete();
        }
    }

    private function consolidateExactDuplicates(): void
    {
        $groups = DB::table('eventos')
            ->selectRaw('TRIM(UPPER(nombre_evento)) as normalized_name')
            ->selectRaw('MIN(id) as canonical_id')
            ->selectRaw('COUNT(*) as total')
            ->groupByRaw('TRIM(UPPER(nombre_evento))')
            ->havingRaw('COUNT(*) > 1')
            ->get();

        foreach ($groups as $group) {
            $duplicateIds = DB::table('eventos')
                ->whereRaw('TRIM(UPPER(nombre_evento)) = ?', [$group->normalized_name])
                ->where('id', '<>', (int) $group->canonical_id)
                ->pluck('id');

            foreach ($duplicateIds as $duplicateId) {
                foreach (self::TRACKING_TABLES as $table) {
                    if (Schema::hasTable($table)) {
                        DB::table($table)->where('evento_id', $duplicateId)->update([
                            'evento_id' => (int) $group->canonical_id,
                            'updated_at' => now(),
                        ]);
                    }
                }

                $this->replaceAuxiliaryReferences((int) $duplicateId, (int) $group->canonical_id);
                DB::table('eventos')->where('id', $duplicateId)->delete();
            }
        }
    }

    private function replaceAuxiliaryReferences(int $legacyId, int $targetId): void
    {
        foreach (['eventos_despacho', 'bastion_eventos'] as $table) {
            if (Schema::hasTable($table)) {
                DB::table($table)->where('evento_id', $legacyId)->update(['evento_id' => $targetId]);
            }
        }

        if (Schema::hasTable('eventos_auditoria')) {
            DB::table('eventos_auditoria')->where('auditoria_id', $legacyId)->update(['auditoria_id' => $targetId]);
        }
    }
};

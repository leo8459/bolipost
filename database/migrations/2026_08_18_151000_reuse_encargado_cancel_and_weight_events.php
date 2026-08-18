<?php

use App\Support\EncargadoEvent;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
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

    private const PESO_LEGACY = [
        'Peso de envio actualizado desde encargado.',
        'Peso de solicitud actualizado desde encargado.',
    ];

    public function up(): void
    {
        foreach (self::TRACKING_TABLES as $table) {
            if (Schema::hasTable($table) && ! Schema::hasColumn($table, 'detalle_evento')) {
                Schema::table($table, function (Blueprint $blueprint) {
                    $blueprint->text('detalle_evento')->nullable();
                });
            }
        }

        if (! Schema::hasTable('eventos')) {
            return;
        }

        $canceladoId = $this->findOrCreateEvent(EncargadoEvent::CANCELADO);
        $pesoId = $this->findOrCreateEvent(EncargadoEvent::PESO_ACTUALIZADO);
        $additionalPatterns = [
            'Devuelto a almacen origen por %.' => $this->findOrCreateEvent(EncargadoEvent::DEVUELTO_ORIGEN),
            'Devuelto a almacen destino por %.' => $this->findOrCreateEvent(EncargadoEvent::DEVUELTO_DESTINO),
            'Envio devuelto a ventanilla por %.' => $this->findOrCreateEvent(EncargadoEvent::DEVUELTO_VENTANILLA),
            'El usuario % cambio el cartero asignado desde Encargado EMS.%' => $this->findOrCreateEvent(EncargadoEvent::CARTERO_CAMBIADO),
            'Cambio de cartero realizado por %' => $this->findOrCreateEvent(EncargadoEvent::CARTERO_CAMBIADO),
            'El usuario % quito el cartero asignado desde Encargado EMS.%' => $this->findOrCreateEvent(EncargadoEvent::CARTERO_QUITADO),
        ];

        if (DB::getDriverName() === 'pgsql') {
            $this->migratePostgres($canceladoId, $pesoId);
            $this->migrateAdditionalPostgres($additionalPatterns);

            return;
        }

        $legacyEvents = DB::table('eventos')
            ->where('nombre_evento', 'like', 'Envio cancelado por %.')
            ->orWhereIn('nombre_evento', self::PESO_LEGACY)
            ->get(['id', 'nombre_evento']);

        foreach ($legacyEvents as $event) {
            $isCancel = str_starts_with((string) $event->nombre_evento, 'Envio cancelado por ');
            $targetId = $isCancel ? $canceladoId : $pesoId;

            foreach (self::TRACKING_TABLES as $table) {
                if (! Schema::hasTable($table)) {
                    continue;
                }

                $payload = ['evento_id' => $targetId, 'updated_at' => now()];
                if ($isCancel) {
                    $payload['detalle_evento'] = $event->nombre_evento;
                }

                DB::table($table)->where('evento_id', $event->id)->update($payload);
            }

            $this->replaceAuxiliaryReferences((int) $event->id, $targetId);
            DB::table('eventos')->where('id', $event->id)->delete();
        }

        foreach ($additionalPatterns as $pattern => $targetId) {
            $events = DB::table('eventos')->where('nombre_evento', 'like', $pattern)->get(['id', 'nombre_evento']);

            foreach ($events as $event) {
                foreach (self::TRACKING_TABLES as $table) {
                    if (Schema::hasTable($table)) {
                        DB::table($table)->where('evento_id', $event->id)->update([
                            'evento_id' => $targetId,
                            'detalle_evento' => $event->nombre_evento,
                            'updated_at' => now(),
                        ]);
                    }
                }

                $this->replaceAuxiliaryReferences((int) $event->id, $targetId);
                DB::table('eventos')->where('id', $event->id)->delete();
            }
        }
    }

    public function down(): void
    {
        foreach (self::TRACKING_TABLES as $table) {
            if (Schema::hasTable($table) && Schema::hasColumn($table, 'detalle_evento')) {
                Schema::table($table, function (Blueprint $blueprint) {
                    $blueprint->dropColumn('detalle_evento');
                });
            }
        }
    }

    private function findOrCreateEvent(string $name): int
    {
        $id = (int) DB::table('eventos')->where('nombre_evento', $name)->value('id');

        return $id > 0
            ? $id
            : (int) DB::table('eventos')->insertGetId([
                'nombre_evento' => $name,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
    }

    private function migratePostgres(int $canceladoId, int $pesoId): void
    {
        $cancelLike = 'Envio cancelado por %.';

        foreach (self::TRACKING_TABLES as $table) {
            if (! Schema::hasTable($table)) {
                continue;
            }

            DB::statement(<<<SQL
                UPDATE {$table} AS tracking
                SET evento_id = CASE
                        WHEN evento.nombre_evento LIKE ? THEN CAST(? AS BIGINT)
                        ELSE CAST(? AS BIGINT)
                    END,
                    detalle_evento = CASE
                        WHEN evento.nombre_evento LIKE ? THEN evento.nombre_evento
                        ELSE tracking.detalle_evento
                    END,
                    updated_at = CURRENT_TIMESTAMP
                FROM eventos AS evento
                WHERE tracking.evento_id = evento.id
                  AND (evento.nombre_evento LIKE ? OR evento.nombre_evento IN (?, ?))
                SQL, [
                $cancelLike,
                $canceladoId,
                $pesoId,
                $cancelLike,
                $cancelLike,
                ...self::PESO_LEGACY,
            ]);
        }

        foreach (['eventos_despacho', 'bastion_eventos'] as $table) {
            if (! Schema::hasTable($table)) {
                continue;
            }

            DB::statement(<<<SQL
                UPDATE {$table} AS tracking
                SET evento_id = CASE
                    WHEN evento.nombre_evento LIKE ? THEN CAST(? AS BIGINT)
                    ELSE CAST(? AS BIGINT)
                END
                FROM eventos AS evento
                WHERE tracking.evento_id = evento.id
                  AND (evento.nombre_evento LIKE ? OR evento.nombre_evento IN (?, ?))
                SQL, [$cancelLike, $canceladoId, $pesoId, $cancelLike, ...self::PESO_LEGACY]);
        }

        if (Schema::hasTable('eventos_auditoria')) {
            DB::statement(<<<SQL
                UPDATE eventos_auditoria AS tracking
                SET auditoria_id = CASE
                    WHEN evento.nombre_evento LIKE ? THEN CAST(? AS BIGINT)
                    ELSE CAST(? AS BIGINT)
                END
                FROM eventos AS evento
                WHERE tracking.auditoria_id = evento.id
                  AND (evento.nombre_evento LIKE ? OR evento.nombre_evento IN (?, ?))
                SQL, [$cancelLike, $canceladoId, $pesoId, $cancelLike, ...self::PESO_LEGACY]);
        }

        DB::table('eventos')
            ->where('nombre_evento', 'like', $cancelLike)
            ->orWhereIn('nombre_evento', self::PESO_LEGACY)
            ->delete();
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

    private function migrateAdditionalPostgres(array $patterns): void
    {
        foreach ($patterns as $pattern => $targetId) {
            foreach (self::TRACKING_TABLES as $table) {
                if (! Schema::hasTable($table)) {
                    continue;
                }

                DB::statement(<<<SQL
                    UPDATE {$table} AS tracking
                    SET evento_id = CAST(? AS BIGINT),
                        detalle_evento = evento.nombre_evento,
                        updated_at = CURRENT_TIMESTAMP
                    FROM eventos AS evento
                    WHERE tracking.evento_id = evento.id
                      AND evento.nombre_evento LIKE ?
                    SQL, [$targetId, $pattern]);
            }

            foreach (['eventos_despacho', 'bastion_eventos'] as $table) {
                if (! Schema::hasTable($table)) {
                    continue;
                }

                DB::statement(<<<SQL
                    UPDATE {$table} AS tracking
                    SET evento_id = CAST(? AS BIGINT)
                    FROM eventos AS evento
                    WHERE tracking.evento_id = evento.id
                      AND evento.nombre_evento LIKE ?
                    SQL, [$targetId, $pattern]);
            }

            if (Schema::hasTable('eventos_auditoria')) {
                DB::statement(<<<SQL
                    UPDATE eventos_auditoria AS tracking
                    SET auditoria_id = CAST(? AS BIGINT)
                    FROM eventos AS evento
                    WHERE tracking.auditoria_id = evento.id
                      AND evento.nombre_evento LIKE ?
                    SQL, [$targetId, $pattern]);
            }

            DB::table('eventos')->where('nombre_evento', 'like', $pattern)->delete();
        }
    }
};

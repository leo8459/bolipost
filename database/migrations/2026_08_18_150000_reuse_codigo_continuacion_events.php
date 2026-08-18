<?php

use App\Support\CodigoContinuacionEvent;
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
    ];

    public function up(): void
    {
        foreach (self::TRACKING_TABLES as $table) {
            if (Schema::hasTable($table) && ! Schema::hasColumn($table, 'codigo_relacionado')) {
                Schema::table($table, function (Blueprint $blueprint) {
                    $blueprint->string('codigo_relacionado', 255)->nullable()->index();
                });
            }
        }

        if (! Schema::hasTable('eventos')) {
            return;
        }

        $eventoMadreId = $this->findOrCreateEvent(CodigoContinuacionEvent::MADRE);
        $eventoHijoId = $this->findOrCreateEvent(CodigoContinuacionEvent::HIJO);

        if (DB::getDriverName() === 'pgsql') {
            $this->migratePostgresLegacyEvents($eventoMadreId, $eventoHijoId);

            return;
        }

        DB::table('eventos')
            ->where('nombre_evento', 'like', 'Se genero el codigo hijo % como continuacion de este codigo madre.')
            ->orderBy('id')
            ->get(['id', 'nombre_evento'])
            ->each(function (object $evento) use ($eventoMadreId) {
                if (preg_match('/^Se genero el codigo hijo (.+) como continuacion de este codigo madre\.$/u', $evento->nombre_evento, $matches) !== 1) {
                    return;
                }

                $this->replaceLegacyEvent((int) $evento->id, $eventoMadreId, strtoupper(trim($matches[1])));
            });

        DB::table('eventos')
            ->where('nombre_evento', 'like', 'Este codigo es la continuacion del codigo madre %.')
            ->orderBy('id')
            ->get(['id', 'nombre_evento'])
            ->each(function (object $evento) use ($eventoHijoId) {
                if (preg_match('/^Este codigo es la continuacion del codigo madre (.+)\.$/u', $evento->nombre_evento, $matches) !== 1) {
                    return;
                }

                $this->replaceLegacyEvent((int) $evento->id, $eventoHijoId, strtoupper(trim($matches[1])));
            });
    }

    public function down(): void
    {
        foreach (self::TRACKING_TABLES as $table) {
            if (Schema::hasTable($table) && Schema::hasColumn($table, 'codigo_relacionado')) {
                Schema::table($table, function (Blueprint $blueprint) {
                    $blueprint->dropColumn('codigo_relacionado');
                });
            }
        }
    }

    private function findOrCreateEvent(string $name): int
    {
        $id = (int) DB::table('eventos')->where('nombre_evento', $name)->value('id');

        if ($id > 0) {
            return $id;
        }

        return (int) DB::table('eventos')->insertGetId([
            'nombre_evento' => $name,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function migratePostgresLegacyEvents(int $eventoMadreId, int $eventoHijoId): void
    {
        $madreLike = 'Se genero el codigo hijo % como continuacion de este codigo madre.';
        $hijoLike = 'Este codigo es la continuacion del codigo madre %.';

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
                    codigo_relacionado = UPPER(CASE
                        WHEN evento.nombre_evento LIKE ?
                            THEN substring(evento.nombre_evento from '^Se genero el codigo hijo (.+) como continuacion de este codigo madre\\.$')
                        ELSE substring(evento.nombre_evento from '^Este codigo es la continuacion del codigo madre (.+)\\.$')
                    END),
                    updated_at = CURRENT_TIMESTAMP
                FROM eventos AS evento
                WHERE tracking.evento_id = evento.id
                  AND (evento.nombre_evento LIKE ? OR evento.nombre_evento LIKE ?)
                SQL, [
                $madreLike,
                $eventoMadreId,
                $eventoHijoId,
                $madreLike,
                $madreLike,
                $hijoLike,
            ]);
        }

        foreach (['eventos_despacho', 'eventos_tiktoker', 'bastion_eventos'] as $table) {
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
                  AND (evento.nombre_evento LIKE ? OR evento.nombre_evento LIKE ?)
                SQL, [$madreLike, $eventoMadreId, $eventoHijoId, $madreLike, $hijoLike]);
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
                  AND (evento.nombre_evento LIKE ? OR evento.nombre_evento LIKE ?)
                SQL, [$madreLike, $eventoMadreId, $eventoHijoId, $madreLike, $hijoLike]);
        }

        DB::table('eventos')
            ->where(function ($query) use ($madreLike, $hijoLike) {
                $query->where('nombre_evento', 'like', $madreLike)
                    ->orWhere('nombre_evento', 'like', $hijoLike);
            })
            ->delete();
    }

    private function replaceLegacyEvent(int $legacyId, int $templateId, string $relatedCode): void
    {
        if ($legacyId === $templateId || $relatedCode === '') {
            return;
        }

        foreach (self::TRACKING_TABLES as $table) {
            if (! Schema::hasTable($table)) {
                continue;
            }

            DB::table($table)
                ->where('evento_id', $legacyId)
                ->update([
                    'evento_id' => $templateId,
                    'codigo_relacionado' => $relatedCode,
                    'updated_at' => now(),
                ]);
        }

        foreach (['eventos_despacho', 'eventos_tiktoker'] as $table) {
            if (Schema::hasTable($table)) {
                DB::table($table)->where('evento_id', $legacyId)->update(['evento_id' => $templateId]);
            }
        }

        if (Schema::hasTable('bastion_eventos')) {
            DB::table('bastion_eventos')->where('evento_id', $legacyId)->update(['evento_id' => $templateId]);
        }

        if (Schema::hasTable('eventos_auditoria')) {
            DB::table('eventos_auditoria')->where('auditoria_id', $legacyId)->update(['auditoria_id' => $templateId]);
        }

        DB::table('eventos')->where('id', $legacyId)->delete();
    }
};

<?php

namespace App\Http\Controllers;

use Carbon\Carbon;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class EventosAdministradorController extends Controller
{
    public function index(Request $request): View
    {
        $filters = $request->validate([
            'buscar' => ['nullable', 'string', 'max:120'],
            'tipo' => ['nullable', 'string', 'max:20'],
            'desde' => ['nullable', 'date_format:Y-m-d'],
            'hasta' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:desde'],
        ]);

        $search = trim((string) ($filters['buscar'] ?? ''));
        $requestedType = (string) ($filters['tipo'] ?? 'todos');
        $type = in_array($requestedType, ['todos', 'creacion', 'edicion', 'eliminacion'], true)
            ? $requestedType
            : 'todos';
        $from = $filters['desde'] ?? null;
        $until = $filters['hasta'] ?? null;

        $visibleEvents = static fn (): Builder => DB::table('system_audit_logs')
            ->where(function (Builder $query): void {
                $query->whereRaw("left(table_name, 9) = 'paquetes_'")
                    ->orWhereIn('operation', ['DELETE', 'TRUNCATE']);
            });

        $auditEvents = $visibleEvents()
            ->when($type === 'creacion', fn (Builder $query) => $query
                ->whereRaw("left(table_name, 9) = 'paquetes_'")
                ->where('operation', 'INSERT'))
            ->when($type === 'edicion', fn (Builder $query) => $query
                ->whereRaw("left(table_name, 9) = 'paquetes_'")
                ->where('operation', 'UPDATE'))
            ->when($type === 'eliminacion', fn (Builder $query) => $query->whereIn('operation', ['DELETE', 'TRUNCATE']))
            ->selectRaw(<<<'SQL'
                ('EVENTO-' || id::text) AS event_id,
                'CAMBIO'::text AS source,
                operation::text AS operation,
                occurred_at AS happened_at,
                CASE
                    WHEN operation IN ('DELETE', 'TRUNCATE') THEN 'ELIMINACION'
                    WHEN operation = 'INSERT' THEN 'CREACION'
                    WHEN EXISTS (
                        SELECT 1
                          FROM jsonb_array_elements_text(changed_fields) AS changed_field(field_name)
                         WHERE lower(changed_field.field_name) ~ '(estado|status|cancel|anul|baja|active|activo|deleted_at|eliminad)'
                    ) THEN 'CAMBIO DE ESTADO'
                    ELSE 'EDICION'
                END::text AS action,
                table_name::text AS table_name,
                COALESCE(record_identifier, '-')::text AS record_identifier,
                COALESCE(NULLIF(request_user_name, ''), NULLIF(request_user_alias, ''), database_user, database_session_user, 'Conexion de base de datos')::text AS actor,
                NULLIF(request_user_alias, '')::text AS actor_alias,
                request_user_id::text AS actor_id,
                COALESCE(request_ip, database_client_ip)::text AS ip_address,
                request_ip::text AS request_ip,
                database_client_ip::text AS database_client_ip,
                user_agent::text AS user_agent,
                application_name::text AS application_name,
                database_user::text AS database_user,
                database_session_user::text AS session_role,
                database_pid::text AS database_pid,
                changed_fields::text AS changed_fields,
                COALESCE(old_values::text, '{}')::text AS old_values,
                COALESCE(new_values::text, '{}')::text AS new_values
            SQL);

        $eventsQuery = DB::query()->fromSub($auditEvents, 'audit_events')
            ->when($from, fn (Builder $query) => $query->where('happened_at', '>=', Carbon::parse($from)->startOfDay()))
            ->when($until, fn (Builder $query) => $query->where('happened_at', '<=', Carbon::parse($until)->endOfDay()))
            ->when($search !== '', function (Builder $query) use ($search): void {
                $like = '%'.$search.'%';
                $query->where(function (Builder $query) use ($like): void {
                    foreach (['actor', 'actor_alias', 'actor_id', 'ip_address', 'table_name', 'record_identifier', 'user_agent', 'application_name', 'database_user', 'session_role'] as $column) {
                        $query->orWhere($column, 'ILIKE', $like);
                    }
                    $query->orWhere('old_values', 'ILIKE', $like)
                        ->orWhere('new_values', 'ILIKE', $like);
                });
            })
            ->orderByDesc('happened_at')
            ->orderByDesc('event_id');

        $events = $eventsQuery->paginate(30)->withQueryString();
        $events->getCollection()->transform(function (object $event): object {
            $event->changed_fields = json_decode((string) $event->changed_fields, true) ?: [];
            $oldValues = json_decode((string) $event->old_values, true) ?: [];
            $newValues = json_decode((string) $event->new_values, true) ?: [];

            $event->target_label = (string) ($event->record_identifier ?: '—');
            if ($event->operation === 'TRUNCATE') {
                $event->target_label = 'Todos los registros';
            }

            $labelFields = $event->table_name === 'users'
                ? ['name', 'username', 'usuario', 'alias', 'email']
                : ['codigo', 'codigo_guia', 'codigo_paquete', 'guia', 'tracking_number', 'name', 'nombre', 'username', 'usuario', 'alias', 'email'];

            foreach ($labelFields as $labelField) {
                $label = $newValues[$labelField] ?? $oldValues[$labelField] ?? null;
                if (is_scalar($label) && trim((string) $label) !== '') {
                    $event->target_label = (string) $label;
                    break;
                }
            }

            if ($event->table_name === 'users') {
                $alias = trim((string) ($oldValues['alias'] ?? $newValues['alias'] ?? ''));
                $userId = $oldValues['id'] ?? $event->record_identifier;
                if ($alias !== '' && $alias !== $event->target_label) {
                    $event->target_label .= ' ('.$alias.')';
                }
                $event->target_label .= ' · ID '.$userId;
            }

            $event->changes = collect($event->changed_fields)
                ->map(fn (string $field): array => [
                    'field' => $field,
                    'before' => $oldValues[$field] ?? null,
                    'after' => $newValues[$field] ?? null,
                ])
                ->all();

            return $event;
        });

        return view('eventos_administrador.index', [
            'events' => $events,
            'search' => $search,
            'type' => $type,
            'from' => $from,
            'until' => $until,
            'visibleEventCount' => $visibleEvents()->count(),
        ]);
    }
}

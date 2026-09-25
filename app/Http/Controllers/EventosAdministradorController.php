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
            'tipo' => ['nullable', 'in:todos,accesos,cambios'],
            'desde' => ['nullable', 'date_format:Y-m-d'],
            'hasta' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:desde'],
        ]);

        $search = trim((string) ($filters['buscar'] ?? ''));
        $type = (string) ($filters['tipo'] ?? 'todos');
        $from = $filters['desde'] ?? null;
        $until = $filters['hasta'] ?? null;

        $accessEvents = DB::table('user_login_logs')->selectRaw(<<<'SQL'
            ('INGRESO-' || id::text) AS event_id,
            'ACCESO'::text AS source,
            logged_in_at AS happened_at,
            'INGRESO'::text AS action,
            'users'::text AS table_name,
            user_id::text AS record_identifier,
            COALESCE(NULLIF(user_name, ''), NULLIF(user_alias, ''), 'Usuario sin nombre')::text AS actor,
            NULLIF(user_alias, '')::text AS actor_alias,
            user_id::text AS actor_id,
            ip_address::text AS ip_address,
            ip_address::text AS request_ip,
            NULL::text AS database_client_ip,
            user_agent::text AS user_agent,
            NULL::text AS application_name,
            NULL::text AS database_user,
            NULL::text AS session_role,
            NULL::text AS database_pid,
            '[]'::text AS changed_fields,
            '{}'::text AS old_values,
            '{}'::text AS new_values
        SQL);

        $logoutEvents = DB::table('user_login_logs')
            ->whereNotNull('logged_out_at')
            ->selectRaw(<<<'SQL'
                ('SALIDA-' || id::text) AS event_id,
                'ACCESO'::text AS source,
                logged_out_at AS happened_at,
                'SALIDA'::text AS action,
                'users'::text AS table_name,
                user_id::text AS record_identifier,
                COALESCE(NULLIF(user_name, ''), NULLIF(user_alias, ''), 'Usuario sin nombre')::text AS actor,
                NULLIF(user_alias, '')::text AS actor_alias,
                user_id::text AS actor_id,
                ip_address::text AS ip_address,
                ip_address::text AS request_ip,
                NULL::text AS database_client_ip,
                user_agent::text AS user_agent,
                NULL::text AS application_name,
                NULL::text AS database_user,
                NULL::text AS session_role,
                NULL::text AS database_pid,
                '[]'::text AS changed_fields,
                '{}'::text AS old_values,
                '{}'::text AS new_values
            SQL);

        $mutationEvents = DB::table('system_audit_logs')->selectRaw(<<<'SQL'
            ('CAMBIO-' || id::text) AS event_id,
            'CAMBIO'::text AS source,
            occurred_at AS happened_at,
            CASE
                WHEN operation = 'DELETE' THEN 'ELIMINACION'
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
            COALESCE(NULLIF(request_user_name, ''), NULLIF(request_user_alias, ''), database_user, database_session_user, 'Conexión de base de datos')::text AS actor,
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

        $allEvents = $accessEvents
            ->unionAll($logoutEvents)
            ->unionAll($mutationEvents);

        $eventsQuery = DB::query()->fromSub($allEvents, 'system_events')
            ->when($type === 'accesos', fn (Builder $query) => $query->where('source', 'ACCESO'))
            ->when($type === 'cambios', fn (Builder $query) => $query->where('source', 'CAMBIO'))
            ->when($from, fn (Builder $query) => $query->where('happened_at', '>=', Carbon::parse($from)->startOfDay()))
            ->when($until, fn (Builder $query) => $query->where('happened_at', '<=', Carbon::parse($until)->endOfDay()))
            ->when($search !== '', function (Builder $query) use ($search): void {
                $like = '%'.$search.'%';
                $query->where(function (Builder $query) use ($like): void {
                    foreach (['actor', 'actor_alias', 'actor_id', 'ip_address', 'table_name', 'record_identifier', 'user_agent', 'application_name', 'database_user', 'session_role'] as $column) {
                        $query->orWhere($column, 'ILIKE', $like);
                    }
                });
            })
            ->orderByDesc('happened_at')
            ->orderByDesc('event_id');

        $events = $eventsQuery->paginate(30)->withQueryString();
        $targetUserIds = $events->getCollection()
            ->filter(fn (object $event): bool => $event->source === 'CAMBIO'
                && $event->table_name === 'users'
                && ctype_digit((string) $event->record_identifier))
            ->pluck('record_identifier')
            ->map(fn ($id): int => (int) $id)
            ->unique()
            ->values();
        $targetUsers = $targetUserIds->isEmpty()
            ? collect()
            : DB::table('users')->whereIn('id', $targetUserIds)->get(['id', 'name', 'alias', 'email'])->keyBy('id');

        $events->getCollection()->transform(function (object $event) use ($targetUsers): object {
            $event->changed_fields = json_decode((string) $event->changed_fields, true) ?: [];
            $oldValues = json_decode((string) $event->old_values, true) ?: [];
            $newValues = json_decode((string) $event->new_values, true) ?: [];

            $event->target_label = (string) ($event->record_identifier ?: '—');

            if ($event->source === 'CAMBIO' && $event->table_name === 'users') {
                $targetId = (int) $event->record_identifier;
                $currentUser = $targetUsers->get($targetId);
                $name = $newValues['name'] ?? $oldValues['name'] ?? $currentUser?->name;
                $alias = $newValues['alias'] ?? $oldValues['alias'] ?? $currentUser?->alias;
                $email = $newValues['email'] ?? $oldValues['email'] ?? $currentUser?->email;
                $label = trim((string) $name) ?: trim((string) $email) ?: 'Usuario';

                if (trim((string) $alias) !== '' && trim((string) $alias) !== $label) {
                    $label .= ' ('.$alias.')';
                }

                $event->target_label = $label.' · ID '.$targetId;
            } elseif ($event->source === 'CAMBIO' && str_starts_with((string) $event->table_name, 'paquetes_')) {
                foreach (['codigo', 'codigo_guia', 'codigo_paquete', 'guia', 'tracking_number'] as $codeField) {
                    $code = $newValues[$codeField] ?? $oldValues[$codeField] ?? null;
                    if (is_scalar($code) && trim((string) $code) !== '') {
                        $event->target_label = (string) $code;
                        break;
                    }
                }
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
            'loginCount' => DB::table('user_login_logs')->count(),
            'mutationCount' => DB::table('system_audit_logs')->count(),
        ]);
    }
}

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        $applicationDatabaseRole = (string) (DB::selectOne('SELECT session_user AS audit_role')->audit_role ?? '');
        $applicationRoleLiteral = DB::connection()->getPdo()->quote($applicationDatabaseRole);

        DB::unprepared(str_replace('__AUDIT_APPLICATION_ROLE__', $applicationRoleLiteral, <<<'SQL'
CREATE OR REPLACE FUNCTION public.capture_system_audit_truncate()
RETURNS trigger
LANGUAGE plpgsql
SECURITY DEFINER
SET search_path = pg_catalog, public
AS $$
DECLARE
    v_row_count bigint;
    v_application_connection boolean;
BEGIN
    v_application_connection := session_user = __AUDIT_APPLICATION_ROLE__;

    EXECUTE format('SELECT count(*) FROM %I.%I', TG_TABLE_SCHEMA, TG_TABLE_NAME)
       INTO v_row_count;

    INSERT INTO public.system_audit_logs (
        occurred_at,
        table_name,
        operation,
        record_identifier,
        request_user_id,
        request_user_name,
        request_user_alias,
        request_ip,
        user_agent,
        database_user,
        database_session_user,
        database_client_ip,
        application_name,
        database_pid,
        changed_fields,
        old_values,
        new_values
    ) VALUES (
        clock_timestamp(),
        TG_TABLE_NAME,
        'TRUNCATE',
        NULL,
        CASE
            WHEN v_application_connection
             AND NULLIF(current_setting('app.audit_user_id', true), '') ~ '^[0-9]+$'
                THEN current_setting('app.audit_user_id', true)::bigint
            ELSE NULL
        END,
        CASE WHEN v_application_connection THEN NULLIF(current_setting('app.audit_user_name', true), '') ELSE NULL END,
        CASE WHEN v_application_connection THEN NULLIF(current_setting('app.audit_user_alias', true), '') ELSE NULL END,
        CASE WHEN v_application_connection THEN NULLIF(current_setting('app.audit_request_ip', true), '') ELSE NULL END,
        CASE WHEN v_application_connection THEN NULLIF(current_setting('app.audit_user_agent', true), '') ELSE NULL END,
        COALESCE(NULLIF(current_setting('role', true), 'none'), session_user),
        session_user,
        inet_client_addr()::text,
        NULLIF(current_setting('application_name', true), ''),
        pg_backend_pid(),
        jsonb_build_array('filas_eliminadas'),
        jsonb_build_object('filas_eliminadas', v_row_count),
        '{}'::jsonb
    );

    RETURN NULL;
END;
$$;

DO $$
DECLARE
    v_table record;
BEGIN
    FOR v_table IN
        SELECT n.nspname AS schema_name, c.relname AS table_name
          FROM pg_class c
          JOIN pg_namespace n ON n.oid = c.relnamespace
         WHERE n.nspname = 'public'
           AND c.relkind = 'r'
           AND c.relname NOT IN (
               'system_audit_logs', 'migrations', 'sessions', 'cache', 'cache_locks',
               'jobs', 'job_batches', 'failed_jobs', 'password_reset_tokens',
               'user_login_logs', 'auditoria', 'eventos_auditoria', 'activity_logs',
               'pulse_values', 'pulse_entries', 'pulse_aggregates'
           )
    LOOP
        EXECUTE format('DROP TRIGGER IF EXISTS system_audit_truncate ON %I.%I', v_table.schema_name, v_table.table_name);
        EXECUTE format(
            'CREATE TRIGGER system_audit_truncate BEFORE TRUNCATE ON %I.%I FOR EACH STATEMENT EXECUTE FUNCTION public.capture_system_audit_truncate()',
            v_table.schema_name,
            v_table.table_name
        );
    END LOOP;
END;
$$;
SQL));
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        DB::unprepared(<<<'SQL'
DO $$
DECLARE
    v_trigger record;
BEGIN
    FOR v_trigger IN
        SELECT n.nspname AS schema_name, c.relname AS table_name, t.tgname AS trigger_name
          FROM pg_trigger t
          JOIN pg_class c ON c.oid = t.tgrelid
          JOIN pg_namespace n ON n.oid = c.relnamespace
         WHERE NOT t.tgisinternal
           AND t.tgfoid = 'public.capture_system_audit_truncate()'::regprocedure
    LOOP
        EXECUTE format('DROP TRIGGER IF EXISTS %I ON %I.%I', v_trigger.trigger_name, v_trigger.schema_name, v_trigger.table_name);
    END LOOP;
END;
$$;
DROP FUNCTION IF EXISTS public.capture_system_audit_truncate();
SQL);
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('system_audit_logs', function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->timestampTz('occurred_at')->useCurrent();
            $table->string('table_name', 190);
            $table->string('operation', 12);
            $table->text('record_identifier')->nullable();
            $table->unsignedBigInteger('request_user_id')->nullable();
            $table->text('request_user_name')->nullable();
            $table->text('request_user_alias')->nullable();
            $table->string('request_ip', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->string('database_user', 190)->nullable();
            $table->string('database_session_user', 190)->nullable();
            $table->string('database_client_ip', 64)->nullable();
            $table->text('application_name')->nullable();
            $table->integer('database_pid')->nullable();
            $table->jsonb('changed_fields');
            $table->jsonb('old_values')->nullable();
            $table->jsonb('new_values')->nullable();

            $table->index(['occurred_at', 'operation']);
            $table->index(['table_name', 'record_identifier']);
            $table->index('request_user_id');
            $table->index('request_ip');
            $table->index('database_client_ip');
        });

        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        $applicationDatabaseRole = (string) (DB::selectOne('SELECT session_user AS audit_role')->audit_role ?? '');
        $applicationRoleLiteral = DB::connection()->getPdo()->quote($applicationDatabaseRole);
        $auditSql = str_replace('__AUDIT_APPLICATION_ROLE__', $applicationRoleLiteral, <<<'SQL'
CREATE OR REPLACE FUNCTION public.capture_system_audit_change()
RETURNS trigger
LANGUAGE plpgsql
SECURITY DEFINER
SET search_path = pg_catalog, public
AS $$
DECLARE
    v_old jsonb;
    v_new jsonb;
    v_row jsonb;
    v_key text;
    v_old_value jsonb;
    v_new_value jsonb;
    v_old_diff jsonb := '{}'::jsonb;
    v_new_diff jsonb := '{}'::jsonb;
    v_changed_fields jsonb := '[]'::jsonb;
    v_record_identifier text;
    v_operation text := TG_OP;
    v_application_connection boolean;
BEGIN
    v_application_connection := session_user = __AUDIT_APPLICATION_ROLE__;

    IF TG_OP = 'INSERT' THEN
        v_old := NULL;
        v_new := to_jsonb(NEW);
        v_row := v_new;
    ELSIF TG_OP = 'DELETE' THEN
        v_old := to_jsonb(OLD);
        v_new := NULL;
        v_row := v_old;
    ELSE
        v_old := to_jsonb(OLD);
        v_new := to_jsonb(NEW);
        v_row := v_new;
    END IF;

    IF TG_OP = 'DELETE' THEN
        SELECT COALESCE(jsonb_agg(k), '[]'::jsonb)
          INTO v_changed_fields
          FROM jsonb_object_keys(v_old) AS keys(k);
        SELECT COALESCE(jsonb_object_agg(k, CASE
                   WHEN lower(k) ~ '(password|token|secret|credential|private.?key|api.?key|imagen|fotografia|archivo|documento|^foto)'
                       THEN '"[oculto]"'::jsonb
                   ELSE v_old -> k
               END), '{}'::jsonb)
          INTO v_old_diff
          FROM jsonb_object_keys(v_old) AS keys(k);
    ELSE
        FOR v_key IN
            SELECT key_name
              FROM jsonb_object_keys(COALESCE(v_old, '{}'::jsonb) || COALESCE(v_new, '{}'::jsonb)) AS keys(key_name)
        LOOP
            v_old_value := v_old -> v_key;
            v_new_value := v_new -> v_key;

            IF v_old_value IS DISTINCT FROM v_new_value THEN
                v_changed_fields := v_changed_fields || to_jsonb(v_key);

                IF lower(v_key) ~ '(password|token|secret|credential|private.?key|api.?key|imagen|fotografia|archivo|documento|^foto)' THEN
                    v_old_diff := v_old_diff || jsonb_build_object(v_key, CASE WHEN v_old_value IS NULL THEN NULL ELSE '"[oculto]"'::jsonb END);
                    v_new_diff := v_new_diff || jsonb_build_object(v_key, CASE WHEN v_new_value IS NULL THEN NULL ELSE '"[oculto]"'::jsonb END);
                ELSE
                    v_old_diff := v_old_diff || jsonb_build_object(v_key, v_old_value);
                    v_new_diff := v_new_diff || jsonb_build_object(v_key, v_new_value);
                END IF;
            END IF;
        END LOOP;
    END IF;

    IF TG_OP = 'UPDATE' AND jsonb_array_length(v_changed_fields) = 0 THEN
        RETURN NEW;
    END IF;

    v_record_identifier := left(COALESCE(v_row ->> 'codigo', v_row ->> 'codigo_guia', v_row ->> 'id'), 255);

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
        v_operation,
        v_record_identifier,
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
        v_changed_fields,
        CASE WHEN TG_OP = 'INSERT' THEN NULL ELSE v_old_diff END,
        CASE WHEN TG_OP = 'DELETE' THEN NULL ELSE v_new_diff END
    );

    IF TG_OP = 'DELETE' THEN
        RETURN OLD;
    END IF;
    RETURN NEW;
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
        EXECUTE format('DROP TRIGGER IF EXISTS system_audit_row_change ON %I.%I', v_table.schema_name, v_table.table_name);
        EXECUTE format(
            'CREATE TRIGGER system_audit_row_change AFTER INSERT OR UPDATE OR DELETE ON %I.%I FOR EACH ROW EXECUTE FUNCTION public.capture_system_audit_change()',
            v_table.schema_name,
            v_table.table_name
        );
    END LOOP;
END;
$$;
SQL);
        DB::unprepared($auditSql);
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'pgsql') {
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
           AND t.tgfoid = 'public.capture_system_audit_change()'::regprocedure
    LOOP
        EXECUTE format('DROP TRIGGER IF EXISTS %I ON %I.%I', v_trigger.trigger_name, v_trigger.schema_name, v_trigger.table_name);
    END LOOP;
END;
$$;
DROP FUNCTION IF EXISTS public.capture_system_audit_change();
SQL);
        }

        Schema::dropIfExists('system_audit_logs');
    }
};

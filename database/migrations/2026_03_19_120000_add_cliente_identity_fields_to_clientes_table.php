<?php

use App\Models\Cliente;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('clientes', function (Blueprint $table) {
            if (! Schema::hasColumn('clientes', 'codigo_cliente')) {
                $table->string('codigo_cliente', 9)->nullable()->after('id');
            }

            if (! Schema::hasColumn('clientes', 'tipodocumentoidentidad')) {
                $table->string('tipodocumentoidentidad', 50)->nullable()->after('name');
            }

            if (! Schema::hasColumn('clientes', 'numero_carnet')) {
                $table->string('numero_carnet', 50)->nullable()->after('tipodocumentoidentidad');
            }

            if (! Schema::hasColumn('clientes', 'razon_social')) {
                $table->string('razon_social')->nullable()->after('numero_carnet');
            }
        });

        Cliente::query()
            ->where(function ($query): void {
                $query->whereNull('codigo_cliente')
                    ->orWhere('codigo_cliente', '');
            })
            ->orderBy('id')
            ->get()
            ->each(function (Cliente $cliente): void {
                $cliente->forceFill([
                    'codigo_cliente' => Cliente::nextCodigoCliente(),
                ])->save();
            });

        if (! Schema::hasTable('clientes')) {
            return;
        }

        $uniqueExists = DB::table('pg_indexes')
            ->where('schemaname', 'public')
            ->where('tablename', 'clientes')
            ->where('indexname', 'clientes_codigo_cliente_unique')
            ->exists();

        if (! $uniqueExists) {
            Schema::table('clientes', function (Blueprint $table) {
                $table->unique('codigo_cliente');
            });
        }
    }

    public function down(): void
    {
        Schema::table('clientes', function (Blueprint $table) {
            $table->dropUnique(['codigo_cliente']);

            if (Schema::hasColumn('clientes', 'razon_social')) {
                $table->dropColumn('razon_social');
            }

            if (Schema::hasColumn('clientes', 'numero_carnet')) {
                $table->dropColumn('numero_carnet');
            }

            if (Schema::hasColumn('clientes', 'tipodocumentoidentidad')) {
                $table->dropColumn('tipodocumentoidentidad');
            }

            if (Schema::hasColumn('clientes', 'codigo_cliente')) {
                $table->dropColumn('codigo_cliente');
            }
        });
    }
};

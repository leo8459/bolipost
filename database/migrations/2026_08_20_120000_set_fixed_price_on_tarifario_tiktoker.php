<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tarifario_tiktoker', function (Blueprint $table) {
            $table->decimal('peso2', 10, 2)->nullable()->change();
            $table->decimal('peso3', 10, 2)->nullable()->change();
            $table->decimal('peso_extra', 10, 2)->nullable()->change();
        });

        DB::table('tarifario_tiktoker')->update([
            'peso1' => 20.00,
            'peso2' => null,
            'peso3' => null,
            'peso_extra' => null,
        ]);
    }

    public function down(): void
    {
        DB::table('tarifario_tiktoker')->update([
            'peso2' => 0,
            'peso_extra' => 0,
        ]);

        Schema::table('tarifario_tiktoker', function (Blueprint $table) {
            $table->decimal('peso2', 10, 2)->nullable(false)->change();
            $table->decimal('peso_extra', 10, 2)->nullable(false)->change();
        });
    }
};

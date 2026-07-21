<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('users', 'provincia_origen')) {
            Schema::table('users', function (Blueprint $table) {
                $table->string('provincia_origen')->nullable()->after('ciudad');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('users', 'provincia_origen')) {
            Schema::table('users', function (Blueprint $table) {
                $table->dropColumn('provincia_origen');
            });
        }
    }
};

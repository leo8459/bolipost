<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('vehicles')) {
            return;
        }

        Schema::table('vehicles', function (Blueprint $table) {
            if (!Schema::hasColumn('vehicles', 'chasis')) {
                $table->string('chasis', 80)->nullable()->after('modelo');
            }

            if (!Schema::hasColumn('vehicles', 'motor')) {
                $table->string('motor', 80)->nullable()->after('chasis');
            }
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('vehicles')) {
            return;
        }

        Schema::table('vehicles', function (Blueprint $table) {
            if (Schema::hasColumn('vehicles', 'motor')) {
                $table->dropColumn('motor');
            }

            if (Schema::hasColumn('vehicles', 'chasis')) {
                $table->dropColumn('chasis');
            }
        });
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('users', 'regionales')) {
            Schema::table('users', function (Blueprint $table) {
                $table->json('regionales')->nullable()->after('ciudad');
            });
        }

        DB::table('users')
            ->whereNotNull('ciudad')
            ->whereRaw("trim(coalesce(ciudad, '')) <> ''")
            ->whereNull('regionales')
            ->orderBy('id')
            ->chunkById(200, function ($users) {
                foreach ($users as $user) {
                    DB::table('users')
                        ->where('id', $user->id)
                        ->update([
                            'regionales' => json_encode([strtoupper(trim((string) $user->ciudad))]),
                        ]);
                }
            });
    }

    public function down(): void
    {
        if (Schema::hasColumn('users', 'regionales')) {
            Schema::table('users', function (Blueprint $table) {
                $table->dropColumn('regionales');
            });
        }
    }
};

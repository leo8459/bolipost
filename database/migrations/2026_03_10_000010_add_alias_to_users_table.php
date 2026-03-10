<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (! Schema::hasColumn('users', 'alias')) {
            Schema::table('users', function (Blueprint $table) {
                $table->string('alias')->nullable()->after('name');
            });
        }

        $users = DB::table('users')
            ->select(['id', 'name', 'email', 'alias'])
            ->orderBy('id')
            ->get();

        foreach ($users as $user) {
            if (! empty($user->alias)) {
                continue;
            }

            $base = Str::slug((string) Str::before((string) $user->email, '@'), '_');

            if ($base === '') {
                $base = Str::slug((string) $user->name, '_');
            }

            if ($base === '') {
                $base = 'usuario';
            }

            $alias = $base;
            $suffix = 1;

            while (
                DB::table('users')
                    ->where('id', '!=', $user->id)
                    ->where('alias', $alias)
                    ->exists()
            ) {
                $alias = $base.'_'.$suffix;
                $suffix++;
            }

            DB::table('users')
                ->where('id', $user->id)
                ->update(['alias' => $alias]);
        }

        Schema::table('users', function (Blueprint $table) {
            $table->unique('alias');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasColumn('users', 'alias')) {
            Schema::table('users', function (Blueprint $table) {
                $table->dropUnique('users_alias_unique');
                $table->dropColumn('alias');
            });
        }
    }
};

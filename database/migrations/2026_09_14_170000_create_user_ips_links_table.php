<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_ips_links', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnUpdate()->cascadeOnDelete();
            $table->unsignedSmallInteger('ips_user_pid');
            $table->string('ips_user_domain', 20)->default('');
            $table->string('ips_user_fid');
            $table->string('ips_user_name');
            $table->unsignedSmallInteger('ips_office_cd')->nullable();
            $table->string('ips_office_fcd', 25)->nullable();
            $table->string('ips_office_name', 64)->nullable();
            $table->boolean('ipsweb')->default(false);
            $table->boolean('restrict_user_offices')->default(false);
            $table->boolean('active')->default(true);
            $table->timestamp('last_verified_at')->nullable();
            $table->json('last_snapshot')->nullable();
            $table->timestamps();

            $table->unique('user_id');
            $table->unique('ips_user_pid');
            $table->index(['active', 'ips_user_domain', 'ips_user_fid']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_ips_links');
    }
};

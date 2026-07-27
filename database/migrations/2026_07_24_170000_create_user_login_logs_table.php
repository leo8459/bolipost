<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('user_login_logs')) {
            Schema::create('user_login_logs', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->string('user_name')->nullable();
                $table->string('user_alias')->nullable();
                $table->string('ip_address', 45)->nullable();
                $table->text('user_agent')->nullable();
                $table->string('session_id')->nullable();
                $table->timestamp('logged_in_at')->useCurrent();
                $table->timestamps();

                $table->index(['logged_in_at', 'user_id']);
                $table->index('ip_address');
                $table->index('user_alias');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('user_login_logs');
    }
};

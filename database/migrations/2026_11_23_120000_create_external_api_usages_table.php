<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('external_api_usages', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('external_api_token_id')->constrained()->cascadeOnDelete();
            $table->char('fingerprint', 64);
            $table->string('method', 16);
            $table->text('endpoint_url');
            $table->string('source_app', 64)->nullable();
            $table->string('source_origin')->nullable();
            $table->ipAddress('source_ip')->nullable();
            $table->unsignedBigInteger('request_count');
            $table->timestamp('last_used_at');

            $table->unique(['external_api_token_id', 'fingerprint']);
            $table->index(['external_api_token_id', 'last_used_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('external_api_usages');
    }
};

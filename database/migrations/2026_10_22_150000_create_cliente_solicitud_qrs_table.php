<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cliente_solicitud_qrs', function (Blueprint $table): void {
            $table->id();
            $table->uuid('attempt_id')->unique();
            $table->foreignId('cliente_id')->constrained('clientes');
            $table->foreignId('solicitud_id')->nullable()->unique()->constrained('solicitud_clientes');
            $table->unsignedBigInteger('cart_id');
            $table->string('internal_code');
            $table->longText('qr_image');
            $table->json('solicitud_data');
            $table->decimal('amount', 12, 2);
            $table->timestamp('generated_at');
            $table->timestamp('expires_at');
            $table->string('status', 20)->default('pending');
            $table->timestamp('checked_at')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamps();
            $table->index(['status', 'expires_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cliente_solicitud_qrs');
    }
};

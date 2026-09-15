<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('factura_ticket_snapshots', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('cart_id')->unique();
            $table->json('data');
            $table->timestamp('captured_at');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('factura_ticket_snapshots');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tracking_local_event_rules', function (Blueprint $table) {
            $table->id();
            $table->string('source_table', 80)->default('*');
            $table->unsignedBigInteger('event_id')->nullable();
            $table->string('raw_name', 255)->default('');
            $table->string('display_name', 255)->nullable();
            $table->boolean('is_visible')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['source_table', 'event_id']);
            $table->index(['source_table', 'raw_name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tracking_local_event_rules');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('mobile_db_snapshots')) {
            return;
        }

        Schema::create('mobile_db_snapshots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('snapshot_key', 120)->index();
            $table->dateTime('sent_at')->nullable()->index();
            $table->string('action', 120)->index();
            $table->string('model', 190)->index();
            $table->string('table_name', 120)->nullable()->index();
            $table->unsignedBigInteger('record_id')->nullable();
            $table->unsignedInteger('page')->nullable();
            $table->unsignedInteger('total_pages')->nullable();
            $table->longText('payload_json')->nullable();
            $table->unsignedInteger('payload_size')->default(0);
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mobile_db_snapshots');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('registros_apis', function (Blueprint $table): void {
            $table->id();
            $table->string('method', 10)->index();
            $table->text('url');
            $table->longText('token');
            $table->text('description');
            $table->longText('json_body')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('registros_apis');
    }
};

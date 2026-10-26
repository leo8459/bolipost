<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('package_report_exports', function (Blueprint $table): void {
            $table->unsignedBigInteger('total_rows')->default(0);
            $table->unsignedTinyInteger('progress')->default(0);
            $table->string('progress_message')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('package_report_exports', function (Blueprint $table): void {
            $table->dropColumn(['total_rows', 'progress', 'progress_message']);
        });
    }
};

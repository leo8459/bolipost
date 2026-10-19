<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ips_verification_notes', function (Blueprint $table): void {
            $table->id();
            $table->string('receptacle_code', 35);
            $table->uuid('receptacle_pid')->nullable();
            $table->string('dispatch_code', 35)->nullable();
            $table->unsignedSmallInteger('office_cd');
            $table->string('document_type', 5);
            $table->string('incident_type', 40);
            $table->unsignedSmallInteger('summary_code')->nullable();
            $table->string('package_code', 35)->nullable();
            $table->string('comparison_source', 20)->nullable();
            $table->string('comparison_reference', 120)->nullable();
            $table->boolean('basis_confirmed')->default(false);
            $table->unsignedInteger('declared_count')->nullable();
            $table->unsignedInteger('observed_count')->nullable();
            $table->text('description');
            $table->text('outer_condition')->nullable();
            $table->text('contents_condition')->nullable();
            $table->boolean('contents_at_risk')->default(false);
            $table->boolean('cn24_required')->default(false);
            $table->json('evidence_paths')->nullable();
            $table->string('status', 24)->default('pending_transmission');
            $table->string('external_vn_number', 40)->nullable();
            $table->string('transmission_method', 24)->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->text('resolution')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->foreignId('reported_by')->nullable()->constrained('users')->nullOnDelete();
            $table->json('ips_snapshot')->nullable();
            $table->timestamps();

            $table->index(['receptacle_code', 'office_cd', 'created_at'], 'ips_vn_receptacle_office_created_idx');
            $table->index(['status', 'office_cd'], 'ips_vn_status_office_idx');
            $table->index('package_code');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ips_verification_notes');
    }
};

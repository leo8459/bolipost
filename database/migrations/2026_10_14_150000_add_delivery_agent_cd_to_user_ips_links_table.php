<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('user_ips_links', function (Blueprint $table) {
            $table->string('delivery_agent_cd', 2)->nullable()->after('ips_office_name');
            $table->string('delivery_agent_full_cd', 25)->nullable()->after('delivery_agent_cd');
            $table->index(['ips_office_cd', 'delivery_agent_cd'], 'user_ips_links_office_agent_index');
            $table->index('delivery_agent_full_cd', 'user_ips_links_agent_full_cd_index');
        });
    }

    public function down(): void
    {
        Schema::table('user_ips_links', function (Blueprint $table) {
            $table->dropIndex('user_ips_links_office_agent_index');
            $table->dropIndex('user_ips_links_agent_full_cd_index');
            $table->dropColumn(['delivery_agent_cd', 'delivery_agent_full_cd']);
        });
    }
};

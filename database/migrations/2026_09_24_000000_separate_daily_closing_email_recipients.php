<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const CONTRACT_RECIPIENTS = 'contracts.expiration_email_recipients';

    private const DAILY_CLOSING_RECIPIENTS = 'operations.daily_closing_email_recipients';

    public function up(): void
    {
        if (DB::table('app_settings')->where('key', self::DAILY_CLOSING_RECIPIENTS)->exists()) {
            return;
        }

        $recipients = DB::table('app_settings')
            ->where('key', self::CONTRACT_RECIPIENTS)
            ->value('value') ?? '[]';

        DB::table('app_settings')->insert([
            'key' => self::DAILY_CLOSING_RECIPIENTS,
            'value' => $recipients,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        DB::table('app_settings')->where('key', self::DAILY_CLOSING_RECIPIENTS)->delete();
    }
};

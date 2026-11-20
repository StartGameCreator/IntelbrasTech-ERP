<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('portal_site_settings', function (Blueprint $table) {
            $table->string('google_analytics_id', 40)->nullable()->after('google_site_verification');
            $table->boolean('google_analytics_ativo')->default(false)->after('google_analytics_id');
            $table->string('google_tag_manager_id', 40)->nullable()->after('google_analytics_ativo');
            $table->boolean('google_tag_manager_ativo')->default(false)->after('google_tag_manager_id');
            $table->string('microsoft_clarity_id', 80)->nullable()->after('google_tag_manager_ativo');
            $table->boolean('microsoft_clarity_ativo')->default(false)->after('microsoft_clarity_id');
        });
    }

    public function down(): void
    {
        Schema::table('portal_site_settings', function (Blueprint $table) {
            $table->dropColumn(['google_analytics_id', 'google_analytics_ativo', 'google_tag_manager_id', 'google_tag_manager_ativo', 'microsoft_clarity_id', 'microsoft_clarity_ativo']);
        });
    }
};

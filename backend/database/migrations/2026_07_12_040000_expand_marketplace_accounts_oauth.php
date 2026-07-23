<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::table('marketplace_accounts', function (Blueprint $table) {
            if (!Schema::hasColumn('marketplace_accounts','external_user_id')) $table->string('external_user_id',100)->nullable()->index();
            if (!Schema::hasColumn('marketplace_accounts','nickname')) $table->string('nickname',150)->nullable();
            if (!Schema::hasColumn('marketplace_accounts','site_id')) $table->string('site_id',20)->nullable();
            if (!Schema::hasColumn('marketplace_accounts','oauth_state')) $table->string('oauth_state',150)->nullable()->index();
            if (!Schema::hasColumn('marketplace_accounts','oauth_state_expires_at')) $table->timestamp('oauth_state_expires_at')->nullable();
            if (!Schema::hasColumn('marketplace_accounts','erro_conexao')) $table->text('erro_conexao')->nullable();
        });
    }
    public function down(): void {}
};

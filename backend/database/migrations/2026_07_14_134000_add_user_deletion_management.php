<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('platform_cadastros')) {
            Schema::table('platform_cadastros', function (Blueprint $table) {
                if (!Schema::hasColumn('platform_cadastros', 'deleted_by')) {
                    $table->unsignedBigInteger('deleted_by')->nullable()->index();
                }
                if (!Schema::hasColumn('platform_cadastros', 'delete_reason')) {
                    $table->text('delete_reason')->nullable();
                }
            });
        }

        if (!Schema::hasTable('platform_user_deletion_logs')) {
            Schema::create('platform_user_deletion_logs', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('cadastro_id')->index();
                $table->unsignedBigInteger('usuario_id')->nullable()->index();
                $table->string('acao', 40)->index();
                $table->text('motivo')->nullable();
                $table->unsignedBigInteger('executado_por')->nullable()->index();
                $table->string('ip', 64)->nullable();
                $table->string('user_agent', 500)->nullable();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('platform_user_deletion_logs');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('empresas', function (Blueprint $table): void {
            if (!Schema::hasColumn('empresas', 'perfil_fiscal')) {
                $table->string('perfil_fiscal', 40)->nullable();
            }

            if (!Schema::hasColumn('empresas', 'mei_csosn_padrao')) {
                $table->string('mei_csosn_padrao', 3)->default('102');
            }
        });
    }

    public function down(): void
    {
        Schema::table('empresas', function (Blueprint $table): void {
            if (Schema::hasColumn('empresas', 'mei_csosn_padrao')) {
                $table->dropColumn('mei_csosn_padrao');
            }

            if (Schema::hasColumn('empresas', 'perfil_fiscal')) {
                $table->dropColumn('perfil_fiscal');
            }
        });
    }
};

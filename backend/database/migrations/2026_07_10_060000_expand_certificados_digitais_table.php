<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('certificados_digitais', function (Blueprint $table) {
            if (! Schema::hasColumn('certificados_digitais', 'nome')) $table->string('nome')->nullable();
            if (! Schema::hasColumn('certificados_digitais', 'titular')) $table->string('titular')->nullable();
            if (! Schema::hasColumn('certificados_digitais', 'documento')) $table->string('documento', 32)->nullable();
            if (! Schema::hasColumn('certificados_digitais', 'emissor')) $table->string('emissor')->nullable();
            if (! Schema::hasColumn('certificados_digitais', 'validade_inicio')) $table->date('validade_inicio')->nullable();
            if (! Schema::hasColumn('certificados_digitais', 'thumbprint')) $table->string('thumbprint', 128)->nullable();
            if (! Schema::hasColumn('certificados_digitais', 'armazenamento')) $table->string('armazenamento', 30)->default('servidor');
            if (! Schema::hasColumn('certificados_digitais', 'observacoes')) $table->text('observacoes')->nullable();
            if (! Schema::hasColumn('certificados_digitais', 'updated_at')) $table->timestamp('updated_at')->nullable();
            if (! Schema::hasColumn('certificados_digitais', 'deleted_at')) $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::table('certificados_digitais', function (Blueprint $table) {
            $columns = ['nome','titular','documento','emissor','validade_inicio','thumbprint','armazenamento','observacoes','updated_at','deleted_at'];
            foreach ($columns as $column) {
                if (Schema::hasColumn('certificados_digitais', $column)) $table->dropColumn($column);
            }
        });
    }
};

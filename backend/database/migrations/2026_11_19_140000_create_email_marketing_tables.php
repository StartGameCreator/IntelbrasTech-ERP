<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('email_marketing_settings', function (Blueprint $t) {
            $t->id(); $t->foreignId('empresa_id')->unique()->constrained('empresas')->cascadeOnDelete();
            $t->string('driver', 30)->default('smtp'); $t->string('host')->nullable(); $t->unsignedSmallInteger('port')->default(587);
            $t->string('encryption', 20)->default('tls'); $t->text('username_encrypted')->nullable(); $t->text('password_encrypted')->nullable();
            $t->string('from_email', 180)->nullable(); $t->string('from_name', 180)->nullable(); $t->string('reply_to', 180)->nullable();
            $t->unsignedSmallInteger('rate_per_minute')->default(30); $t->boolean('active')->default(false); $t->timestamps();
        });
        Schema::create('email_marketing_contacts', function (Blueprint $t) {
            $t->id(); $t->foreignId('empresa_id')->constrained('empresas')->cascadeOnDelete(); $t->uuid('uuid')->unique();
            $t->string('email',180); $t->string('nome',180)->nullable(); $t->string('empresa',180)->nullable(); $t->string('cidade',120)->nullable();
            $t->json('tags')->nullable(); $t->boolean('consentimento')->default(false); $t->timestamp('consentimento_em')->nullable();
            $t->string('origem_consentimento',120)->nullable(); $t->boolean('ativo')->default(true); $t->timestamp('descadastrado_em')->nullable();
            $t->string('unsubscribe_token',64)->unique(); $t->timestamps(); $t->unique(['empresa_id','email']);
        });
        Schema::create('email_marketing_templates', function (Blueprint $t) {
            $t->id(); $t->foreignId('empresa_id')->constrained('empresas')->cascadeOnDelete(); $t->uuid('uuid')->unique();
            $t->string('nome',180); $t->string('assunto',240); $t->longText('html'); $t->boolean('ativo')->default(true); $t->timestamps();
        });
        Schema::create('email_marketing_campaigns', function (Blueprint $t) {
            $t->id(); $t->foreignId('empresa_id')->constrained('empresas')->cascadeOnDelete(); $t->foreignId('template_id')->nullable()->constrained('email_marketing_templates')->nullOnDelete();
            $t->uuid('uuid')->unique(); $t->string('nome',180); $t->string('assunto',240); $t->longText('html'); $t->string('status',30)->default('rascunho')->index();
            $t->timestamp('agendada_para')->nullable(); $t->timestamp('iniciada_em')->nullable(); $t->timestamp('concluida_em')->nullable();
            $t->unsignedInteger('total')->default(0); $t->unsignedInteger('enviados')->default(0); $t->unsignedInteger('falhas')->default(0); $t->timestamps();
        });
        Schema::create('email_marketing_messages', function (Blueprint $t) {
            $t->id(); $t->foreignId('campaign_id')->constrained('email_marketing_campaigns')->cascadeOnDelete(); $t->foreignId('contact_id')->constrained('email_marketing_contacts')->cascadeOnDelete();
            $t->string('status',30)->default('pendente')->index(); $t->unsignedSmallInteger('tentativas')->default(0); $t->text('erro')->nullable();
            $t->timestamp('enviado_em')->nullable(); $t->timestamps(); $t->unique(['campaign_id','contact_id']);
        });
    }
    public function down(): void { Schema::dropIfExists('email_marketing_messages'); Schema::dropIfExists('email_marketing_campaigns'); Schema::dropIfExists('email_marketing_templates'); Schema::dropIfExists('email_marketing_contacts'); Schema::dropIfExists('email_marketing_settings'); }
};

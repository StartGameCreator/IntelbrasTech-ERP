<?php

namespace App\Models;

use App\Traits\HasEmpresa;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Crypt;

class ApiIntegration extends Model
{
    use HasEmpresa;

    protected $table = 'api_integrations';

    protected $fillable = [
        'empresa_id', 'provider', 'name', 'category', 'credentials_encrypted',
        'settings', 'enabled', 'is_primary', 'is_fallback', 'status',
        'last_tested_at', 'last_test_message',
    ];

    protected $casts = [
        'settings' => 'array',
        'enabled' => 'boolean',
        'is_primary' => 'boolean',
        'is_fallback' => 'boolean',
        'last_tested_at' => 'datetime',
    ];

    protected $hidden = ['credentials_encrypted'];

    public function setCredentials(array $credentials): void
    {
        $this->credentials_encrypted = Crypt::encryptString(json_encode($credentials, JSON_UNESCAPED_UNICODE));
    }

    public function getCredentials(): array
    {
        if (blank($this->credentials_encrypted)) return [];
        try {
            return json_decode(Crypt::decryptString($this->credentials_encrypted), true) ?: [];
        } catch (\Throwable) {
            return [];
        }
    }

    public function maskedCredentials(): array
    {
        return collect($this->getCredentials())->map(function ($value) {
            $text = (string) $value;
            if ($text === '') return '';
            if (strlen($text) <= 8) return str_repeat('•', strlen($text));
            return substr($text, 0, 4) . str_repeat('•', max(4, strlen($text) - 8)) . substr($text, -4);
        })->all();
    }
}

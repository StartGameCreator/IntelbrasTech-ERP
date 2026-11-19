<?php

namespace Tests\Unit;

use App\Models\MarketplaceAccount;
use Tests\TestCase;

class CredentialEncryptionTest extends TestCase
{
    public function test_tokens_de_marketplace_sao_criptografados_no_modelo(): void
    {
        $conta = new MarketplaceAccount();
        $conta->access_token = 'token-sensivel-de-teste';
        $conta->refresh_token = 'refresh-sensivel-de-teste';

        self::assertNotSame('token-sensivel-de-teste', $conta->getRawOriginal('access_token'));
        self::assertNotSame('refresh-sensivel-de-teste', $conta->getRawOriginal('refresh_token'));
        self::assertSame('token-sensivel-de-teste', $conta->access_token);
        self::assertSame('refresh-sensivel-de-teste', $conta->refresh_token);
        self::assertArrayNotHasKey('access_token', $conta->toArray());
        self::assertArrayNotHasKey('refresh_token', $conta->toArray());
    }
}

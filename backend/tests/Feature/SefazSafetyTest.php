<?php

namespace Tests\Feature;

use App\Services\Fiscal\SefazService;
use Tests\TestCase;

class SefazSafetyTest extends TestCase
{
    public function test_driver_fake_e_permitido_no_ambiente_de_testes(): void
    {
        config()->set('services.sefaz.driver', 'fake');
        config()->set('services.sefaz.allow_fake_in_production', false);

        $service = app(SefazService::class);
        $service->assertDisponivel();

        self::assertSame('fake', $service->modo());
    }
}

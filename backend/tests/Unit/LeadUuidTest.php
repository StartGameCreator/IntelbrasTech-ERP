<?php

namespace Tests\Unit;

use App\Models\Lead;
use ReflectionMethod;
use Tests\TestCase;

class LeadUuidTest extends TestCase
{
    public function test_lead_receives_uuid_before_insert(): void
    {
        $lead = new Lead();
        $fireModelEvent = new ReflectionMethod($lead, 'fireModelEvent');
        $fireModelEvent->invoke($lead, 'creating', false);

        $this->assertMatchesRegularExpression(
            '/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i',
            (string) $lead->uuid,
        );
    }
}

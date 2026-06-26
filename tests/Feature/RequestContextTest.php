<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Context;
use Tests\TestCase;

class RequestContextTest extends TestCase
{
    public function test_response_carries_a_request_id_header(): void
    {
        $response = $this->get('/welcome');

        $response->assertHeader('X-Request-Id');
        $this->assertNotEmpty($response->headers->get('X-Request-Id'));
    }

    public function test_incoming_request_id_is_echoed_back_and_added_to_log_context(): void
    {
        $response = $this->withHeaders(['X-Request-Id' => 'test-correlation-id'])->get('/welcome');

        $response->assertHeader('X-Request-Id', 'test-correlation-id');
        $this->assertSame('test-correlation-id', Context::get('request_id'));
    }
}

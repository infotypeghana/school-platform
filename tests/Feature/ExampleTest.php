<?php

namespace Tests\Feature;

use Tests\TestCase;

class ExampleTest extends TestCase
{
    /**
     * The root URL returns 404 in our multi-tenant app (requires a tenant subdomain).
     * This test verifies the app boots without crashing.
     */
    public function test_the_application_boots_without_error(): void
    {
        // In our multi-tenant setup, / has no tenant and returns 404 — that's correct.
        $response = $this->get('/');
        $this->assertContains($response->status(), [200, 404]);
    }
}

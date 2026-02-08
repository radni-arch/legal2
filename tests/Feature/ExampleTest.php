<?php

namespace Tests\Feature;

// use Tests\UsesTestDatabase;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    /**
     * A basic test example.
     */
    public function test_the_application_returns_a_successful_response(): void
    {
        // Test that the application redirects unauthenticated users to login
        $response = $this->get('/');

        // Expect redirect to login (302) since home route is protected
        $response->assertRedirect('/login');
    }
}

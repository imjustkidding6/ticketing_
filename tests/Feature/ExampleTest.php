<?php

namespace Tests\Feature;

// use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    /**
     * A basic test example.
     */
    public function test_the_application_returns_a_successful_response(): void
    {
        $response = $this->get('/');

        // Guests hitting the root route are redirected to the login page
        // (HomeController redirects based on auth/tenant context).
        $response->assertRedirect(route('login'));
    }
}

<?php

namespace Tests\Feature;

use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * Tenant routes mount under a wildcard {slug} prefix, so any static top-level
 * path competes with a tenant of the same name. A tenant that registered such a
 * slug would find its whole workspace shadowed and unreachable.
 */
class TenantSlugReservationTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Prefixes that cannot collide: framework internals and paths that already
     * sit under a reserved segment.
     */
    private const IGNORED = ['_debugbar', '_ignition', 'sanctum', 'livewire', 'storage', 'build'];

    public function test_every_static_top_level_route_prefix_is_reserved(): void
    {
        $prefixes = collect(Route::getRoutes()->getRoutes())
            ->map(fn ($route) => $route->uri())
            ->reject(fn (string $uri) => $uri === '/' || $uri === '')
            // The tenant group itself is the wildcard we are protecting.
            ->reject(fn (string $uri) => str_starts_with($uri, '{slug}'))
            ->map(fn (string $uri) => explode('/', $uri)[0])
            ->reject(fn (string $segment) => str_starts_with($segment, '{'))
            ->reject(fn (string $segment) => in_array($segment, self::IGNORED, true))
            ->unique()
            ->values();

        $missing = $prefixes
            ->reject(fn (string $p) => in_array($p, Tenant::RESERVED_SLUGS, true))
            ->all();

        $this->assertSame([], $missing, sprintf(
            'These top-level route prefixes would shadow a tenant of the same name '.
            'and must be added to Tenant::RESERVED_SLUGS: %s',
            implode(', ', $missing),
        ));
    }

    public function test_the_availability_check_agrees_with_registration(): void
    {
        // These are two separate code paths asking the same question. When they
        // disagree, the form says a slug is free and then registration rejects
        // it — which reads as a bug in registration.
        foreach (['connect-jude', 'webhooks', 'password', 'admin'] as $slug) {
            $this->getJson('/register/check-slug?slug='.$slug)
                ->assertOk()
                ->assertJson(['available' => false], "'{$slug}' is reserved but the checker offered it");
        }

        $this->getJson('/register/check-slug?slug=acme-support')
            ->assertOk()
            ->assertJson(['available' => true]);
    }

    public function test_registration_rejects_a_reserved_slug(): void
    {
        foreach (['connect-jude', 'webhooks', 'auth', 'admin'] as $slug) {
            $this->assertContains($slug, Tenant::RESERVED_SLUGS);
        }
    }
}

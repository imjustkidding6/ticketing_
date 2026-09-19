<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class SessionLoginFixTest extends TestCase
{
    use RefreshDatabase;

    /**
     * SESSION_DOMAIN=null in a .env file is the literal string "null", which used
     * to reach the cookie domain verbatim and produce a 419 on every login. The
     * rule lives in config/session.php; assert the rule rather than whatever the
     * developer happens to have set locally.
     *
     * @return array<string, array{0: string|null, 1: string|null}>
     */
    public static function sessionDomainProvider(): array
    {
        return [
            'unset' => [null, null],
            'empty string' => ['', null],
            'literal "null"' => ['null', null],
            'a real domain passes through' => ['example.test', 'example.test'],
        ];
    }

    #[DataProvider('sessionDomainProvider')]
    public function test_session_domain_coerces_placeholder_values_to_null(?string $env, ?string $expected): void
    {
        $original = getenv('SESSION_DOMAIN');

        try {
            if ($env === null) {
                putenv('SESSION_DOMAIN');
                unset($_ENV['SESSION_DOMAIN'], $_SERVER['SESSION_DOMAIN']);
            } else {
                putenv("SESSION_DOMAIN={$env}");
                $_ENV['SESSION_DOMAIN'] = $env;
                $_SERVER['SESSION_DOMAIN'] = $env;
            }

            $config = require base_path('config/session.php');

            $this->assertSame($expected, $config['domain']);
        } finally {
            if ($original === false) {
                putenv('SESSION_DOMAIN');
                unset($_ENV['SESSION_DOMAIN'], $_SERVER['SESSION_DOMAIN']);
            } else {
                putenv("SESSION_DOMAIN={$original}");
                $_ENV['SESSION_DOMAIN'] = $original;
                $_SERVER['SESSION_DOMAIN'] = $original;
            }
        }
    }

    public function test_user_can_login_and_redirect_without_419_session_expired(): void
    {
        $user = User::factory()->create([
            'password' => 'password123',
        ]);

        $tenant = Tenant::factory()->create(['is_active' => true]);
        $user->tenants()->attach($tenant->id, ['role' => 'owner']);

        $response = $this->post('/login', [
            'email' => $user->email,
            'password' => 'password123',
        ]);

        $response->assertStatus(302);
        $this->assertAuthenticatedAs($user);
    }

    public function test_logout_invalidates_session(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post('/logout');

        $response->assertStatus(302);
        $this->assertGuest();
    }
}

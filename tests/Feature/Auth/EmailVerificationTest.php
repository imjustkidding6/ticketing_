<?php

namespace Tests\Feature\Auth;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Auth\Events\Verified;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class EmailVerificationTest extends TestCase
{
    use RefreshDatabase;

    private function createUserWithTenant(bool $verified = true): User
    {
        $user = $verified
            ? User::factory()->create()
            : User::factory()->unverified()->create();

        $tenant = Tenant::factory()->create();
        $tenant->addUser($user, 'member');
        $user->setCurrentTenant($tenant);

        return $user;
    }

    public function test_email_verification_screen_can_be_rendered(): void
    {
        $user = $this->createUserWithTenant(verified: false);

        $response = $this->actingAs($user)
            ->withSession(['current_tenant_id' => $user->tenants()->first()->id])
            ->get('/verify-email');

        $response->assertStatus(200);
    }

    public function test_email_can_be_verified(): void
    {
        $user = $this->createUserWithTenant(verified: false);
        $tenant = $user->tenants()->first();

        Event::fake();

        $verificationUrl = URL::temporarySignedRoute(
            'verification.verify',
            now()->addMinutes(60),
            ['id' => $user->id, 'hash' => sha1($user->email)]
        );

        $response = $this->actingAs($user)
            ->withSession(['current_tenant_id' => $tenant->id])
            ->get($verificationUrl);

        Event::assertDispatched(Verified::class);
        $this->assertTrue($user->fresh()->hasVerifiedEmail());
        $response->assertRedirect(route('dashboard', ['slug' => $tenant->slug], false).'?verified=1');
    }

    public function test_email_is_not_verified_with_invalid_hash(): void
    {
        $user = $this->createUserWithTenant(verified: false);

        $verificationUrl = URL::temporarySignedRoute(
            'verification.verify',
            now()->addMinutes(60),
            ['id' => $user->id, 'hash' => sha1('wrong-email')]
        );

        $this->actingAs($user)
            ->withSession(['current_tenant_id' => $user->tenants()->first()->id])
            ->get($verificationUrl);

        $this->assertFalse($user->fresh()->hasVerifiedEmail());
    }
}

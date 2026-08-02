<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminManualDownloadTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    public function test_admin_can_download_user_manual_pdf(): void
    {
        $admin = User::where('email', 'admin@example.com')->first()
            ?? User::factory()->create(['is_admin' => true]);

        $response = $this->actingAs($admin)
            ->get(route('admin.help.download-manual'));

        $response->assertOk()
            ->assertHeader('Content-Type', 'application/pdf');

        $this->assertGreaterThan(1000, filesize($response->getFile()->getPathname()));
    }
}

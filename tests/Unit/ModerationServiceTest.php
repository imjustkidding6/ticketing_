<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\ModerationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ModerationServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_detects_prohibited_content(): void
    {
        $service = app(ModerationService::class);

        $this->assertTrue($service->isFlagged('This message contains hate speech against people'));
        $this->assertFalse($service->isFlagged('Can you help me reset my account password?'));
    }
}

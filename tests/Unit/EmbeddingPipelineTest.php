<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Jobs\GenerateKnowledgeBaseEmbeddingJob;
use App\Jobs\GenerateSnippetEmbeddingJob;
use App\Jobs\GenerateTicketEmbeddingJob;
use App\Models\KbArticle;
use App\Models\LearnedSnippet;
use App\Models\Tenant;
use App\Models\Ticket;
use App\Services\EmbeddingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class EmbeddingPipelineTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Config::set('services.openai.api_key', 'sk-test-key-12345');
        Config::set('services.openai.model', 'gpt-5');
    }

    public function test_embedding_service_embeds_kb_article(): void
    {
        Http::fake([
            'https://api.openai.com/v1/embeddings' => Http::response([
                'data' => [
                    ['embedding' => [0.1, 0.2, 0.3, 0.4]],
                ],
            ], 200),
        ]);

        $tenant = Tenant::factory()->create();
        $article = KbArticle::factory()->create([
            'tenant_id' => $tenant->id,
            'is_published' => true,
            'title' => 'How to reset your portal password',
            'content' => 'Click on forgot password and follow the email instructions.',
        ]);

        $service = app(EmbeddingService::class);
        $result = $service->embedKbArticle($article);

        $this->assertTrue($result);
        $article->refresh();
        $this->assertNotNull($article->embedded_at);
        $this->assertEquals([0.1, 0.2, 0.3, 0.4], $article->embedding);
    }

    public function test_embedding_service_embeds_closed_ticket(): void
    {
        Http::fake([
            'https://api.openai.com/v1/embeddings' => Http::response([
                'data' => [
                    ['embedding' => [0.5, 0.6, 0.7, 0.8]],
                ],
            ], 200),
        ]);

        $tenant = Tenant::factory()->create();
        $ticket = Ticket::factory()->create([
            'tenant_id' => $tenant->id,
            'status' => 'closed',
            'subject' => 'Cannot log into VPN',
            'description' => 'VPN hangs at connecting phase',
            'closing_remarks' => 'Reissued MFA token and updated VPN config',
        ]);

        $service = app(EmbeddingService::class);
        $result = $service->embedTicket($ticket);

        $this->assertTrue($result);
        $ticket->refresh();
        $this->assertNotNull($ticket->solution_embedded_at);
        $this->assertEquals([0.5, 0.6, 0.7, 0.8], $ticket->solution_embedding);
    }

    public function test_cosine_similarity_calculation(): void
    {
        $service = app(EmbeddingService::class);

        $a = [1.0, 0.0, 0.0];
        $b = [1.0, 0.0, 0.0];
        $c = [0.0, 1.0, 0.0];

        $this->assertEquals(1.0, $service->cosineSimilarity($a, $b));
        $this->assertEquals(0.0, $service->cosineSimilarity($a, $c));
    }

    public function test_vector_search_returns_ranked_similar_tickets(): void
    {
        Http::fake([
            'https://api.openai.com/v1/embeddings' => Http::response([
                'data' => [
                    ['embedding' => [1.0, 0.0, 0.0]],
                ],
            ], 200),
        ]);

        $tenant = Tenant::factory()->create();

        $ticket1 = Ticket::factory()->create([
            'tenant_id' => $tenant->id,
            'status' => 'closed',
            'solution_embedding' => [1.0, 0.0, 0.0],
            'solution_embedded_at' => now(),
        ]);

        $ticket2 = Ticket::factory()->create([
            'tenant_id' => $tenant->id,
            'status' => 'closed',
            'solution_embedding' => [0.0, 1.0, 0.0],
            'solution_embedded_at' => now(),
        ]);

        $service = app(EmbeddingService::class);
        $matches = $service->searchSimilarResolvedTickets($tenant->id, 'VPN login issues', limit: 5, threshold: 0.5);

        $this->assertCount(1, $matches);
        $this->assertEquals($ticket1->id, $matches[0]['ticket']->id);
        $this->assertEquals(1.0, $matches[0]['score']);
    }

    public function test_observers_dispatch_queued_jobs_on_events(): void
    {
        Queue::fake();

        $tenant = Tenant::factory()->create();

        $article = KbArticle::factory()->create([
            'tenant_id' => $tenant->id,
            'is_published' => true,
        ]);

        Queue::assertPushed(GenerateKnowledgeBaseEmbeddingJob::class);

        $ticket = Ticket::factory()->create([
            'tenant_id' => $tenant->id,
            'status' => 'closed',
            'closing_remarks' => 'Resolved issue',
        ]);

        Queue::assertPushed(GenerateTicketEmbeddingJob::class);

        $snippet = LearnedSnippet::factory()->create([
            'tenant_id' => $tenant->id,
            'question' => 'Sample Q',
            'answer' => 'Sample A',
        ]);

        Queue::assertPushed(GenerateSnippetEmbeddingJob::class);
    }

    public function test_artisan_commands_execute_successfully(): void
    {
        Http::fake([
            'https://api.openai.com/v1/embeddings' => Http::response([
                'data' => [
                    ['embedding' => [0.1, 0.2, 0.3]],
                ],
            ], 200),
        ]);

        $this->artisan('ai:embed:all')
            ->assertExitCode(0);
    }
}

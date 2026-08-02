<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Exceptions\OpenAiException;
use App\Services\OpenAiService;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class OpenAiServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Config::set('services.openai.api_key', 'sk-test-key-12345');
        Config::set('services.openai.model', 'gpt-5');
        Config::set('services.openai.embed_model', 'text-embedding-3-small');
        Config::set('services.openai.timeout', 60);
        Config::set('services.openai.base_url', 'https://api.openai.com/v1');
    }

    public function test_is_configured_returns_false_when_api_key_is_empty(): void
    {
        Config::set('services.openai.api_key', null);
        $service = new OpenAiService;

        $this->assertFalse($service->isConfigured());
    }

    public function test_is_configured_returns_true_when_api_key_is_present(): void
    {
        $service = new OpenAiService;

        $this->assertTrue($service->isConfigured());
    }

    public function test_chat_throws_exception_if_not_configured(): void
    {
        Config::set('services.openai.api_key', null);
        $service = new OpenAiService;

        $this->expectException(OpenAiException::class);
        $this->expectExceptionMessage('OpenAI API key is not configured.');

        $service->chat([['role' => 'user', 'content' => 'Hello']]);
    }

    public function test_chat_sends_correct_payload_and_returns_decoded_response(): void
    {
        Http::fake([
            'https://api.openai.com/v1/chat/completions' => Http::response([
                'id' => 'chatcmpl-123',
                'object' => 'chat.completion',
                'choices' => [
                    [
                        'index' => 0,
                        'message' => [
                            'role' => 'assistant',
                            'content' => 'Hello there!',
                        ],
                        'finish_reason' => 'stop',
                    ],
                ],
                'usage' => [
                    'prompt_tokens' => 10,
                    'completion_tokens' => 5,
                    'total_tokens' => 15,
                ],
            ], 200),
        ]);

        $service = new OpenAiService(model: 'gpt-4o-mini');

        $messages = [
            ['role' => 'system', 'content' => 'You are a helpful bot.'],
            ['role' => 'user', 'content' => 'Hello'],
        ];

        $result = $service->chat($messages);

        $this->assertArrayHasKey('choices', $result);
        $this->assertEquals('Hello there!', $result['choices'][0]['message']['content']);

        Http::assertSent(function ($request) use ($messages) {
            return $request->url() === 'https://api.openai.com/v1/chat/completions' &&
                $request->hasHeader('Authorization', 'Bearer sk-test-key-12345') &&
                $request->hasHeader('Accept', 'application/json') &&
                $request['model'] === 'gpt-4o-mini' &&
                $request['messages'] === $messages &&
                $request['temperature'] === 0.3 &&
                ! isset($request['tools']);
        });
    }

    public function test_chat_includes_tools_and_tool_choice_when_provided(): void
    {
        Http::fake([
            'https://api.openai.com/v1/chat/completions' => Http::response([
                'choices' => [
                    [
                        'message' => [
                            'role' => 'assistant',
                            'tool_calls' => [
                                [
                                    'id' => 'call_1',
                                    'type' => 'function',
                                    'function' => ['name' => 'search_kb', 'arguments' => '{"query":"reset password"}'],
                                ],
                            ],
                        ],
                    ],
                ],
            ], 200),
        ]);

        $service = new OpenAiService(model: 'gpt-4o-mini');

        $tools = [
            [
                'type' => 'function',
                'function' => [
                    'name' => 'search_kb',
                    'description' => 'Search knowledge base articles',
                    'parameters' => ['type' => 'object', 'properties' => ['query' => ['type' => 'string']]],
                ],
            ],
        ];

        $result = $service->chat([['role' => 'user', 'content' => 'How do I reset password?']], $tools);

        $this->assertArrayHasKey('choices', $result);
        $this->assertEquals('call_1', $result['choices'][0]['message']['tool_calls'][0]['id']);

        Http::assertSent(function ($request) use ($tools) {
            return $request['tools'] === $tools &&
                $request['tool_choice'] === 'auto';
        });
    }

    public function test_chat_handles_reasoning_models_correctly(): void
    {
        Http::fake([
            'https://api.openai.com/v1/chat/completions' => Http::response([
                'choices' => [
                    ['message' => ['role' => 'assistant', 'content' => 'Reasoning result']],
                ],
            ], 200),
        ]);

        $service = new OpenAiService(model: 'gpt-5', reasoningEffort: 'low', maxOutputTokens: 6000);

        $result = $service->chat([['role' => 'user', 'content' => 'Solve logic puzzle']]);

        $this->assertEquals('Reasoning result', $result['choices'][0]['message']['content']);

        Http::assertSent(function ($request) {
            return ! isset($request['temperature']) &&
                ! isset($request['max_tokens']) &&
                $request['max_completion_tokens'] === 6000 &&
                $request['reasoning_effort'] === 'low';
        });
    }

    public function test_chat_throws_openai_exception_on_http_failure(): void
    {
        Http::fake([
            'https://api.openai.com/v1/chat/completions' => Http::response([
                'error' => ['message' => 'Rate limit exceeded'],
            ], 429),
        ]);

        $service = new OpenAiService;

        $this->expectException(OpenAiException::class);
        $this->expectExceptionMessage('failed with status 429');

        $service->chat([['role' => 'user', 'content' => 'Hi']]);
    }

    public function test_chat_throws_openai_exception_on_connection_timeout(): void
    {
        Http::fake([
            'https://api.openai.com/v1/chat/completions' => fn () => throw new ConnectionException('cURL error 28: Operation timed out'),
        ]);

        $service = new OpenAiService(timeout: 10);

        $this->expectException(OpenAiException::class);
        $this->expectExceptionMessage('timed out after 10s');

        $service->chat([['role' => 'user', 'content' => 'Hi']]);
    }

    public function test_web_search_sends_correct_payload_and_returns_response(): void
    {
        Http::fake([
            'https://api.openai.com/v1/chat/completions' => Http::response([
                'choices' => [
                    ['message' => ['role' => 'assistant', 'content' => 'Search results content']],
                ],
            ], 200),
        ]);

        $service = new OpenAiService(searchModel: 'gpt-4o-mini-search-preview');

        $result = $service->webSearch('latest php features');

        $this->assertEquals('Search results content', $result['choices'][0]['message']['content']);

        Http::assertSent(function ($request) {
            return $request['model'] === 'gpt-4o-mini-search-preview' &&
                $request['messages'][0]['content'] === 'latest php features';
        });
    }

    public function test_embed_returns_vector_array(): void
    {
        $fakeVector = [0.123, -0.456, 0.789];

        Http::fake([
            'https://api.openai.com/v1/embeddings' => Http::response([
                'data' => [
                    [
                        'object' => 'embedding',
                        'embedding' => $fakeVector,
                        'index' => 0,
                    ],
                ],
            ], 200),
        ]);

        $service = new OpenAiService(embeddingModel: 'text-embedding-3-small');

        $vector = $service->embed('Sample text for vector embedding');

        $this->assertSame($fakeVector, $vector);

        Http::assertSent(function ($request) {
            return $request->url() === 'https://api.openai.com/v1/embeddings' &&
                $request['model'] === 'text-embedding-3-small' &&
                $request['input'] === 'Sample text for vector embedding';
        });
    }

    public function test_embed_throws_exception_on_invalid_response_structure(): void
    {
        Http::fake([
            'https://api.openai.com/v1/embeddings' => Http::response([
                'data' => [],
            ], 200),
        ]);

        $service = new OpenAiService;

        $this->expectException(OpenAiException::class);
        $this->expectExceptionMessage('missing vector data');

        $service->embed('Sample text');
    }
}

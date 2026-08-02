<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AiUsageLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'tenant_id',
        'user_id',
        'conversation_id',
        'chat_message_id',
        'model',
        'prompt_tokens',
        'completion_tokens',
        'total_tokens',
        'estimated_cost',
        'latency_ms',
        'response_status',
        'error_message',
        'feature',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'prompt_tokens' => 'integer',
            'completion_tokens' => 'integer',
            'total_tokens' => 'integer',
            'estimated_cost' => 'decimal:6',
            'latency_ms' => 'integer',
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(ChatConversation::class, 'conversation_id');
    }

    public function chatMessage(): BelongsTo
    {
        return $this->belongsTo(ChatMessage::class, 'chat_message_id');
    }
}

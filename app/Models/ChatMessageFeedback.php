<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ChatMessageFeedback extends Model
{
    use HasFactory;

    public const RATING_THUMBS_UP = 'thumbs_up';

    public const RATING_THUMBS_DOWN = 'thumbs_down';

    protected $table = 'chat_message_feedbacks';

    protected $fillable = [
        'chat_message_id',
        'tenant_id',
        'user_id',
        'rating',
        'comment',
        'question',
        'response',
    ];

    public function chatMessage(): BelongsTo
    {
        return $this->belongsTo(ChatMessage::class);
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}

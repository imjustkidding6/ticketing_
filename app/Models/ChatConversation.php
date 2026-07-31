<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Model representing an AI Chat Conversation.
 *
 * @property int $id
 * @property int $tenant_id
 * @property int|null $user_id
 * @property int|null $client_id
 * @property int|null $ticket_id
 * @property string $channel
 * @property string|null $session_token
 * @property string|null $title
 * @property string $status
 * @property Carbon|null $last_message_at
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read Tenant $tenant
 * @property-read User|null $user
 * @property-read Client|null $client
 * @property-read Ticket|null $ticket
 * @property-read Collection<int, ChatMessage> $messages
 */
class ChatConversation extends Model
{
    use BelongsToTenant, HasFactory;

    public const CHANNEL_PORTAL = 'portal';

    public const CHANNEL_AGENT = 'agent';

    public const STATUS_ACTIVE = 'active';

    public const STATUS_CLOSED = 'closed';

    /** @var list<string> */
    protected $fillable = [
        'tenant_id',
        'channel',
        'user_id',
        'client_id',
        'ticket_id',
        'session_token',
        'title',
        'status',
        'last_message_at',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'last_message_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Tenant, $this>
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<Client, $this>
     */
    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    /**
     * @return BelongsTo<Ticket, $this>
     */
    public function ticket(): BelongsTo
    {
        return $this->belongsTo(Ticket::class);
    }

    /**
     * @return HasMany<ChatMessage, $this>
     */
    public function messages(): HasMany
    {
        return $this->hasMany(ChatMessage::class, 'chat_conversation_id')->orderBy('id');
    }

    /**
     * Mark the conversation as active.
     */
    public function markActive(): static
    {
        $this->status = self::STATUS_ACTIVE;
        $this->save();

        return $this;
    }

    /**
     * Mark the conversation as closed.
     */
    public function markClosed(): static
    {
        $this->status = self::STATUS_CLOSED;
        $this->save();

        return $this;
    }

    /**
     * Update the last message timestamp to now.
     */
    public function touchLastMessage(): static
    {
        $this->last_message_at = now();
        $this->save();

        return $this;
    }

    /**
     * Retrieve the model for a bound value without global scopes.
     *
     * @param  mixed  $value
     * @param  string|null  $field
     * @return \Illuminate\Database\Eloquent\Model
     */
    public function resolveRouteBinding($value, $field = null)
    {
        return $this->withoutGlobalScopes()
            ->where($field ?? $this->getRouteKeyName(), $value)
            ->firstOrFail();
    }
}

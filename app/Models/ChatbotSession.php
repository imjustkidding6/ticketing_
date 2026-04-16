<?php

namespace App\Models;

use App\Models\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ChatbotSession extends Model
{
    /** @use HasFactory<\Database\Factories\ChatbotSessionFactory> */
    use BelongsToTenant, HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'tenant_id',
        'session_id',
        'status',
        'contact_name',
        'contact_email',
        'contact_phone',
        'escalated_ticket_id',
        'escalated_at',
        'last_seen_at',
        'metadata',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'metadata' => 'array',
            'escalated_at' => 'datetime',
            'last_seen_at' => 'datetime',
        ];
    }

    /**
     * Get the messages for the session.
     */
    public function messages(): HasMany
    {
        return $this->hasMany(ChatbotMessage::class);
    }
}

<?php

namespace App\Models;

use App\Models\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A question/answer pair an agent confirmed as helpful in the AI chat, kept so the
 * assistant can reuse it for similar future questions. Opt-in (ai_learn_from_chat)
 * and reviewable under Settings → AI → Learned answers.
 */
class LearnedSnippet extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'tenant_id',
        'created_by',
        'question',
        'answer',
        'embedding',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}

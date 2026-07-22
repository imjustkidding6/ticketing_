<?php

namespace App\Models;

use App\Models\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TenantFeedback extends Model
{
    use BelongsToTenant;

    protected $table = 'tenant_feedbacks';

    public const TYPES = ['bug', 'suggestion', 'compliment', 'other'];

    public const STATUSES = ['new', 'read', 'resolved'];

    protected $fillable = [
        'tenant_id',
        'user_id',
        'type',
        'subject',
        'body',
        'status',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}

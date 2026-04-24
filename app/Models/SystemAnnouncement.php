<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SystemAnnouncement extends Model
{
    public const SEVERITIES = ['info', 'update', 'maintenance', 'warning'];

    /**
     * @var list<string>
     */
    protected $fillable = [
        'title',
        'body',
        'severity',
        'published_by',
        'published_at',
        'recipient_count',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'published_at' => 'datetime',
        ];
    }

    public function publisher(): BelongsTo
    {
        return $this->belongsTo(User::class, 'published_by');
    }
}

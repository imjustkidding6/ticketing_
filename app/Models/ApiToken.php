<?php

namespace App\Models;

use App\Models\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class ApiToken extends Model
{
    use BelongsToTenant;
    use HasFactory;

    protected $fillable = [
        'tenant_id',
        'name',
        'token',
        'last_used_at',
        'expires_at',
    ];

    protected function casts(): array
    {
        return [
            'last_used_at' => 'datetime',
            'expires_at' => 'datetime',
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function isExpired(): bool
    {
        return $this->expires_at !== null && $this->expires_at->isPast();
    }

    public function touchLastUsed(): void
    {
        $this->forceFill(['last_used_at' => now()])->save();
    }

    /**
     * @return array{plain: string, hash: string}
     */
    public static function generateToken(): array
    {
        $plain = 'tk_'.Str::random(48);

        return [
            'plain' => $plain,
            'hash' => hash('sha256', $plain),
        ];
    }

    public static function findByPlainToken(string $plainToken): ?self
    {
        return self::withoutGlobalScopes()
            ->where('token', hash('sha256', $plainToken))
            ->first();
    }
}

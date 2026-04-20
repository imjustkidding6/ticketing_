<?php

namespace App\Models;

use App\Models\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;

class AppSetting extends Model
{
    use BelongsToTenant;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'tenant_id',
        'key',
        'value',
        'type',
        'group',
    ];

    /**
     * Get a setting value for the current tenant.
     */
    public static function get(string $key, mixed $default = null): mixed
    {
        $tenantId = session('current_tenant_id');

        if (! $tenantId) {
            return $default;
        }

        $cacheKey = "app_settings.{$tenantId}.{$key}";

        return Cache::remember($cacheKey, 300, function () use ($tenantId, $key, $default) {
            $setting = static::withoutGlobalScopes()
                ->where('tenant_id', $tenantId)
                ->where('key', $key)
                ->first();

            if (! $setting) {
                return $default;
            }

            return $setting->getTypedValue();
        });
    }

    /**
     * Set a setting value for the current tenant.
     */
    public static function set(string $key, mixed $value, string $type = 'string', string $group = 'general'): void
    {
        $tenantId = session('current_tenant_id');

        if (! $tenantId) {
            return;
        }

        $storedValue = match ($type) {
            'boolean' => $value ? '1' : '0',
            'encrypted' => Crypt::encryptString((string) $value),
            'json' => json_encode($value),
            default => (string) $value,
        };

        static::withoutGlobalScopes()->updateOrCreate(
            ['tenant_id' => $tenantId, 'key' => $key],
            ['value' => $storedValue, 'type' => $type, 'group' => $group]
        );

        Cache::forget("app_settings.{$tenantId}.{$key}");
        Cache::forget("app_settings.{$tenantId}.group.{$group}");
    }

    /**
     * Get all settings for a group.
     *
     * @return array<string, mixed>
     */
    public static function getByGroup(string $group): array
    {
        $tenantId = session('current_tenant_id');

        if (! $tenantId) {
            return [];
        }

        $cacheKey = "app_settings.{$tenantId}.group.{$group}";

        return Cache::remember($cacheKey, 300, function () use ($tenantId, $group) {
            return static::withoutGlobalScopes()
                ->where('tenant_id', $tenantId)
                ->where('group', $group)
                ->get()
                ->mapWithKeys(fn (AppSetting $setting) => [$setting->key => $setting->getTypedValue()])
                ->toArray();
        });
    }

    /**
     * Currency symbol for the tenant's configured billing currency.
     */
    public static function currencySymbol(): string
    {
        return match (static::get('currency', 'USD')) {
            'PHP' => '₱',
            default => '$',
        };
    }

    /**
     * Format a numeric amount with the tenant's currency symbol.
     */
    public static function formatCurrency(float|int|string|null $amount): string
    {
        return static::currencySymbol().number_format((float) ($amount ?? 0), 2);
    }

    /**
     * Get the value cast to its proper type.
     */
    public function getTypedValue(): mixed
    {
        return match ($this->type) {
            'boolean' => (bool) $this->value,
            'encrypted' => $this->value ? Crypt::decryptString($this->value) : null,
            'json' => $this->value ? json_decode($this->value, true) : null,
            default => $this->value,
        };
    }
}

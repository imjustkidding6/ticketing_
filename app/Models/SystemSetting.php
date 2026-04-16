<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;

class SystemSetting extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'key',
        'value',
        'type',
        'group',
    ];

    /**
     * Get a setting value.
     */
    public static function get(string $key, mixed $default = null): mixed
    {
        $cacheKey = "system_settings.{$key}";

        return Cache::remember($cacheKey, 300, function () use ($key, $default) {
            $setting = static::query()
                ->where('key', $key)
                ->first();

            if (! $setting) {
                return $default;
            }

            return $setting->getTypedValue();
        });
    }

    /**
     * Set a setting value.
     */
    public static function set(string $key, mixed $value, string $type = 'string', string $group = 'general'): void
    {
        $storedValue = match ($type) {
            'boolean' => $value ? '1' : '0',
            'encrypted' => Crypt::encryptString((string) $value),
            'json' => json_encode($value),
            default => (string) $value,
        };

        static::query()->updateOrCreate(
            ['key' => $key],
            ['value' => $storedValue, 'type' => $type, 'group' => $group]
        );

        Cache::forget("system_settings.{$key}");
        Cache::forget("system_settings.group.{$group}");
    }

    /**
     * Get all settings for a group.
     *
     * @return array<string, mixed>
     */
    public static function getByGroup(string $group): array
    {
        $cacheKey = "system_settings.group.{$group}";

        return Cache::remember($cacheKey, 300, function () use ($group) {
            return static::query()
                ->where('group', $group)
                ->get()
                ->mapWithKeys(fn (SystemSetting $setting) => [$setting->key => $setting->getTypedValue()])
                ->toArray();
        });
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

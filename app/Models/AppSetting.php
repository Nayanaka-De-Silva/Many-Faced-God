<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class AppSetting extends Model
{
    protected $fillable = ['key', 'value'];

    private const CACHE_PREFIX = 'app_settings.';
    private const CACHE_TTL    = 3600; // 1 hour in seconds

    /**
     * Retrieve a setting value, with optional default.
     * Caches the result (including null) to avoid repeated DB lookups.
     */
    public static function get(string $key, ?string $default = null): ?string
    {
        $cacheKey = self::CACHE_PREFIX . $key;

        // Laravel's Cache::has() is `!is_null(get())`, so a cached null looks like a
        // miss. Wrap the value in ['value' => ...] so "no row" can itself be cached.
        $cached = Cache::get($cacheKey);
        if (is_array($cached) && array_key_exists('value', $cached)) {
            return $cached['value'] ?? $default;
        }

        $value = static::where('key', $key)->value('value');
        Cache::put($cacheKey, ['value' => $value], self::CACHE_TTL);

        return $value ?? $default;
    }

    /**
     * Persist a setting value and bust its cache entry.
     * Passing null clears the override (stores NULL in the DB).
     */
    public static function set(string $key, ?string $value): void
    {
        static::updateOrCreate(['key' => $key], ['value' => $value]);
        Cache::forget(self::CACHE_PREFIX . $key);
    }
}

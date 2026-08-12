<?php

namespace App\Services\SpellLibrary;

use App\Exceptions\SpellLibraryUnavailableException;
use App\Models\AppSetting;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class SpellLibraryClient
{
    private string $baseUrl;
    /** Short hash of the base URL, used to namespace cache keys. */
    private string $urlHash;

    /** Public so the browser-facing proxy controller can reuse the same allowlist. */
    public const ALLOWED_SEARCH_PARAMS = ['page', 'pageSize', 'sort', 'direction', 'search', 'source'];


    public function __construct()
    {
        // DB override wins; config default is the fallback.
        $override       = AppSetting::get('spell_library.base_url');
        $this->baseUrl  = $override ?? config('services.netheril.base_url');
        $this->urlHash  = md5($this->baseUrl);
    }

    // --- Public API ---

    /**
     * Returns true if the service is reachable and healthy.
     * Returns false on any non-2xx HTTP response.
     * Throws SpellLibraryUnavailableException on transport-level failure.
     */
    public function health(): bool
    {
        try {
            $response = $this->http()->get('/health');
            return $response->successful() && $response->json('status') === 'ok';
        } catch (ConnectionException $e) {
            throw new SpellLibraryUnavailableException('Cannot reach the spell library.', 0, $e);
        }
    }

    /**
     * Returns the library metadata (schools, classes, levels, sources).
     * Cached for 24 hours per base URL.
     * Throws SpellLibraryUnavailableException on transport-level failure.
     */
    public function metadata(): array
    {
        return Cache::remember(
            "netheril.metadata.{$this->urlHash}",
            now()->addHours(24),
            function () {
                try {
                    $response = $this->http()->get('/metadata');
                    $body     = $response->successful() ? $response->json() : null;

                    if (is_null($body)) {
                        throw new SpellLibraryUnavailableException('Unexpected response from the spell library.');
                    }

                    return $body;
                } catch (ConnectionException $e) {
                    throw new SpellLibraryUnavailableException('Cannot reach the spell library.', 0, $e);
                }
            }
        );
    }

    /**
     * Search spells with whitelisted query parameters.
     * Cached for 10 minutes, keyed by base URL + params.
     * Throws SpellLibraryUnavailableException on transport-level failure.
     */
    public function searchSpells(array $params): array
    {
        $filtered = array_intersect_key($params, array_flip(self::ALLOWED_SEARCH_PARAMS));
        // Sort so the same params in a different order share one cache entry.
        ksort($filtered);
        $cacheKey = "netheril.spells.{$this->urlHash}." . md5(http_build_query($filtered));

        return Cache::remember(
            $cacheKey,
            now()->addMinutes(10),
            function () use ($filtered) {
                try {
                    $response = $this->http()->get('/spells', $filtered);
                    $body     = $response->successful() ? $response->json() : null;

                    if (is_null($body)) {
                        throw new SpellLibraryUnavailableException('Unexpected response from the spell library.');
                    }

                    return $body;
                } catch (ConnectionException $e) {
                    throw new SpellLibraryUnavailableException('Cannot reach the spell library.', 0, $e);
                }
            }
        );
    }

    /**
     * Fetch a single spell by ID.
     * Returns null on 404. Caches both hits and misses for 24 hours.
     * Throws SpellLibraryUnavailableException on transport-level failure.
     */
    public function getSpell(string $id): ?array
    {
        $cacheKey = "netheril.spell.{$this->urlHash}.{$id}";

        // Laravel's Cache::get() treats a stored null as a miss (returns default).
        // Wrap the result in ['value' => ...] so a cached null (404) is stored as a non-null
        // array and can be retrieved without triggering an extra HTTP request.
        $cached = Cache::get($cacheKey);
        if (!is_null($cached)) {
            return $cached['value']; // may be null for a previously cached 404
        }

        try {
            $response = $this->http()->get("/spells/{$id}");

            if ($response->status() === 404) {
                Cache::put($cacheKey, ['value' => null], now()->addHours(24));
                return null;
            }

            if (!$response->successful()) {
                // Don't cache transient upstream errors (5xx, bad gateway, etc.) as spell data.
                throw new SpellLibraryUnavailableException('Unexpected response from the spell library.');
            }

            $result = $response->json();
            Cache::put($cacheKey, ['value' => $result], now()->addHours(24));
            return $result;
        } catch (ConnectionException $e) {
            throw new SpellLibraryUnavailableException('Cannot reach the spell library.', 0, $e);
        }
    }

    // --- Private helpers ---

    private function http(): PendingRequest
    {
        return Http::baseUrl(rtrim($this->baseUrl, '/') . '/api/v1')
            ->timeout(config('services.netheril.timeout', 5))
            ->acceptJson();
    }
}

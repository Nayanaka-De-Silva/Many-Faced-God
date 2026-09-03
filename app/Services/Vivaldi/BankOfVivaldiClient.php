<?php

namespace App\Services\Vivaldi;

use App\Exceptions\BankOfVivaldiRequestException;
use App\Exceptions\BankOfVivaldiUnavailableException;
use App\Models\AppSetting;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use InvalidArgumentException;

/**
 * Transport for the Bank of Vivaldi v1 JSON API (issue #71).
 *
 * Mirrors App\Services\SpellLibrary\SpellLibraryClient: DB override wins over the
 * config default, cache keys are namespaced by a hash of the base URL, and a
 * transport-level failure becomes BankOfVivaldiUnavailableException. Upstream
 * client errors (4xx) surface as BankOfVivaldiRequestException so the caller can
 * relay validation details; upstream 5xx is treated as "unavailable".
 */
class BankOfVivaldiClient
{
    private string $baseUrl;

    /** Short hash of the base URL, used to namespace cache keys. */
    private string $urlHash;

    /**
     * Query params the compendium browse endpoint understands. Anything else a
     * caller passes is dropped before the request is made.
     */
    public const ALLOWED_COMPENDIUM_PARAMS = [
        'q', 'category', 'rarity', 'minWeightLb', 'maxWeightLb', 'minValueGp', 'maxValueGp',
        'magical', 'attunement', 'sort', 'page', 'pageSize',
        'armorCategory', 'armorDexBehavior', 'armorMinAc', 'armorMaxAc', 'armorStealth',
        'weaponCategory', 'weaponDamageType', 'weaponProperty',
        'containerMinCapacityLb', 'containerMaxCapacityLb',
        'toolCategory', 'mountType', 'mountMinSpeed', 'mountMaxSpeed',
        'vehicleType', 'vehicleMinSpeed', 'vehicleMaxSpeed', 'treasureKind',
    ];

    public function __construct()
    {
        // DB override wins; config default is the fallback.
        $override      = AppSetting::get('vivaldi.base_url');
        $this->baseUrl = $override ?? config('services.vivaldi.base_url');
        $this->urlHash = md5($this->baseUrl);
    }

    // --- Public API ---

    /**
     * True when the service is reachable and reports itself healthy.
     * Throws BankOfVivaldiUnavailableException on transport-level failure.
     */
    public function health(): bool
    {
        $response = $this->send(fn () => $this->http()->get('/health'));

        return $response->successful() && $response->json('status') === 'ok';
    }

    /**
     * Browse/filter the master item compendium. Cached for 10 minutes, keyed by
     * base URL + the whitelisted params (order-independent).
     */
    public function searchCompendium(array $params): array
    {
        $filtered = $this->clampPaging(
            array_intersect_key($params, array_flip(self::ALLOWED_COMPENDIUM_PARAMS))
        );
        // Sort so the same params in a different order share one cache entry.
        ksort($filtered);
        $cacheKey = "vivaldi.compendium.{$this->urlHash}." . md5(http_build_query($filtered));

        return Cache::remember(
            $cacheKey,
            now()->addMinutes(10),
            fn () => $this->decode($this->send(fn () => $this->http()->get('/compendium/items', $filtered)))
        );
    }

    /**
     * Full vault detail ({ summary, rootItems, links }). Returns null when the
     * vault id is unknown upstream. Not cached — vault contents change.
     */
    public function getVault(string $id): ?array
    {
        $this->guardUuid($id);

        $response = $this->send(fn () => $this->http()->get("/vaults/{$id}"));

        if ($response->status() === 404) {
            return null;
        }

        return $this->decode($response);
    }

    /**
     * Create a vault. Returns the vault object (including its new id).
     */
    public function createVault(array $payload): array
    {
        return $this->decode($this->send(fn () => $this->http()->post('/vaults', $payload)));
    }

    /**
     * Register (upsert) an external reference against a vault. Idempotent upstream.
     */
    public function linkVault(string $id, string $externalRef, ?string $label = null): array
    {
        $this->guardUuid($id);

        $body = array_filter(
            ['externalRef' => $externalRef, 'label' => $label],
            static fn ($value) => ! is_null($value)
        );

        return $this->decode($this->send(fn () => $this->http()->post("/vaults/{$id}/link", $body)));
    }

    /**
     * Remove one external reference from a vault. Never deletes the vault itself.
     */
    public function unlinkVault(string $id, string $externalRef): void
    {
        $this->guardUuid($id);

        // The API expects externalRef as a query param, not a body — Laravel's
        // HTTP client would otherwise send it as a JSON body on a DELETE.
        $query = http_build_query(['externalRef' => $externalRef]);
        $response = $this->send(fn () => $this->http()->delete("/vaults/{$id}/link?{$query}"));

        $this->raiseForError($response);
    }

    /**
     * Add an item to a vault — either a transfer ({ sourceItemId, mode }) or an
     * inline definition. Returns the created item object.
     */
    public function addItem(string $id, array $payload): array
    {
        $this->guardUuid($id);

        return $this->decode($this->send(fn () => $this->http()->post("/vaults/{$id}/items", $payload)));
    }

    // --- Private helpers ---

    private function http(): PendingRequest
    {
        return Http::baseUrl(rtrim($this->baseUrl, '/') . '/api/v1')
            ->timeout(config('services.vivaldi.timeout', 5))
            ->acceptJson();
    }

    /**
     * Run an HTTP call, converting a transport failure into "unavailable".
     */
    private function send(callable $call): Response
    {
        try {
            return $call();
        } catch (ConnectionException $e) {
            throw new BankOfVivaldiUnavailableException('Cannot reach the Bank of Vivaldi.', 0, $e);
        }
    }

    /**
     * Decode a successful response body, or raise:
     * - BankOfVivaldiUnavailableException for a 5xx (transient upstream fault)
     * - BankOfVivaldiRequestException for a 4xx (carries the error envelope)
     */
    private function decode(Response $response): array
    {
        if ($response->successful()) {
            return $response->json() ?? [];
        }

        if ($response->serverError()) {
            throw new BankOfVivaldiUnavailableException('Unexpected response from the Bank of Vivaldi.');
        }

        $body = $response->json();

        throw new BankOfVivaldiRequestException(
            $response->status(),
            is_array($body) ? $body : ['error' => ['code' => 'HTTP_ERROR', 'message' => 'Request rejected.']],
        );
    }

    private function guardUuid(string $id): void
    {
        if (! Str::isUuid($id)) {
            throw new InvalidArgumentException("Not a valid Bank of Vivaldi vault id: {$id}");
        }
    }

    /**
     * Raise the appropriate exception for a non-successful response; no-op on 2xx.
     * Used where the response body isn't needed (e.g. a 204 DELETE).
     */
    private function raiseForError(Response $response): void
    {
        if (! $response->successful()) {
            $this->decode($response); // decode always throws for a non-successful response
        }
    }

    /**
     * Keep page/pageSize within the API's documented bounds so a hostile value
     * can't drive an oversized upstream response into the 10-minute cache.
     */
    private function clampPaging(array $params): array
    {
        if (isset($params['page'])) {
            $params['page'] = max(1, (int) $params['page']);
        }

        if (isset($params['pageSize'])) {
            $params['pageSize'] = min(200, max(1, (int) $params['pageSize']));
        }

        return $params;
    }
}

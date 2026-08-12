<?php

namespace Tests\Unit;

use App\Exceptions\SpellLibraryUnavailableException;
use App\Models\AppSetting;
use App\Services\SpellLibrary\SpellLibraryClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class SpellLibraryClientTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
    }

    // --- health() ---

    public function test_health_returns_true_when_service_responds_ok(): void
    {
        Http::fake(['*' => Http::response(['status' => 'ok'], 200)]);

        $this->assertTrue((new SpellLibraryClient())->health());
    }

    public function test_health_returns_false_when_status_is_not_ok(): void
    {
        Http::fake(['*' => Http::response(['status' => 'degraded'], 200)]);

        $this->assertFalse((new SpellLibraryClient())->health());
    }

    public function test_health_returns_false_on_http_error_status(): void
    {
        Http::fake(['*' => Http::response([], 500)]);

        $this->assertFalse((new SpellLibraryClient())->health());
    }

    public function test_health_throws_unavailable_exception_on_connection_failure(): void
    {
        Http::fake(fn() => throw new ConnectionException('Connection refused'));

        $this->expectException(SpellLibraryUnavailableException::class);

        (new SpellLibraryClient())->health();
    }

    // --- searchSpells() ---

    public function test_search_spells_passes_only_whitelisted_params(): void
    {
        Http::fake(['*' => Http::response(['data' => [], 'meta' => []], 200)]);

        (new SpellLibraryClient())->searchSpells([
            'search'     => 'fire',
            'source'     => 'phb',
            'page'       => 1,
            'pageSize'   => 10,
            'sort'       => 'name',
            'direction'  => 'asc',
            'evil_param' => 'injected',
            'other_bad'  => 'value',
        ]);

        Http::assertSent(function ($request) {
            parse_str(parse_url($request->url(), PHP_URL_QUERY), $params);

            return isset($params['search'], $params['source'], $params['page'], $params['pageSize'])
                && !array_key_exists('evil_param', $params)
                && !array_key_exists('other_bad', $params);
        });
    }

    public function test_search_spells_throws_unavailable_exception_on_connection_failure(): void
    {
        Http::fake(fn() => throw new ConnectionException('Connection refused'));

        $this->expectException(SpellLibraryUnavailableException::class);

        (new SpellLibraryClient())->searchSpells(['search' => 'fire']);
    }

    public function test_search_spells_throws_unavailable_exception_on_error_status_instead_of_type_error(): void
    {
        Http::fake(['*' => Http::response('not json', 500)]);

        $this->expectException(SpellLibraryUnavailableException::class);

        (new SpellLibraryClient())->searchSpells(['search' => 'fire']);
    }

    public function test_search_spells_cache_key_is_stable_regardless_of_param_order(): void
    {
        Http::fake(['*' => Http::response(['data' => [], 'meta' => []], 200)]);

        $client = new SpellLibraryClient();
        $client->searchSpells(['search' => 'fire', 'page' => 1]);
        $client->searchSpells(['page' => 1, 'search' => 'fire']);

        Http::assertSentCount(1);
    }

    // --- getSpell() ---

    public function test_get_spell_returns_array_on_success(): void
    {
        $spellData = ['id' => 'phb-fireball', 'name' => 'Fireball', 'level' => 3];
        Http::fake(['*' => Http::response($spellData, 200)]);

        $result = (new SpellLibraryClient())->getSpell('phb-fireball');

        $this->assertEquals($spellData, $result);
    }

    public function test_get_spell_returns_null_on_404(): void
    {
        Http::fake(['*' => Http::response(['error' => 'not found'], 404)]);

        $result = (new SpellLibraryClient())->getSpell('phb-nonexistent');

        $this->assertNull($result);
    }

    public function test_get_spell_throws_unavailable_exception_on_connection_failure(): void
    {
        Http::fake(fn() => throw new ConnectionException('Connection refused'));

        $this->expectException(SpellLibraryUnavailableException::class);

        (new SpellLibraryClient())->getSpell('phb-fireball');
    }

    public function test_get_spell_throws_unavailable_exception_on_non_404_error_status_without_caching(): void
    {
        // A transient 502 must not be cached as spell data — a retry should still hit HTTP
        // and succeed once the upstream recovers.
        Http::fake(['*' => Http::sequence()
            ->push('<html>Bad Gateway</html>', 502)
            ->push(['id' => 'phb-fireball', 'name' => 'Fireball'], 200),
        ]);

        $client = new SpellLibraryClient();

        try {
            $client->getSpell('phb-fireball');
            $this->fail('Expected SpellLibraryUnavailableException was not thrown.');
        } catch (SpellLibraryUnavailableException) {
            // expected
        }

        $this->assertSame(['id' => 'phb-fireball', 'name' => 'Fireball'], $client->getSpell('phb-fireball'));
        Http::assertSentCount(2);
    }

    // --- caching ---

    public function test_get_spell_caches_result_and_skips_http_on_second_call(): void
    {
        Http::fake(['*' => Http::response(['id' => 'phb-fireball', 'name' => 'Fireball'], 200)]);

        $client = new SpellLibraryClient();
        $client->getSpell('phb-fireball');
        $client->getSpell('phb-fireball');

        Http::assertSentCount(1);
    }

    public function test_get_spell_caches_null_on_404_and_skips_http_on_second_call(): void
    {
        Http::fake(['*' => Http::response([], 404)]);

        $client = new SpellLibraryClient();
        $result1 = $client->getSpell('phb-nonexistent');
        $result2 = $client->getSpell('phb-nonexistent');

        Http::assertSentCount(1);
        $this->assertNull($result1);
        $this->assertNull($result2);
    }

    // --- metadata() ---

    public function test_metadata_throws_unavailable_exception_on_error_status_instead_of_type_error(): void
    {
        Http::fake(['*' => Http::response('', 500)]);

        $this->expectException(SpellLibraryUnavailableException::class);

        (new SpellLibraryClient())->metadata();
    }

    // --- base URL resolution ---

    public function test_resolves_base_url_from_app_setting_over_config_default(): void
    {
        AppSetting::set('spell_library.base_url', 'http://custom-library:9000');

        Http::fake(['http://custom-library:9000/*' => Http::response(['status' => 'ok'], 200)]);

        $result = (new SpellLibraryClient())->health();

        $this->assertTrue($result);
        Http::assertSent(fn($req) => str_starts_with($req->url(), 'http://custom-library:9000/'));
    }

    public function test_falls_back_to_config_default_when_no_app_setting_override(): void
    {
        $defaultUrl = config('services.netheril.base_url');

        Http::fake(['*' => Http::response(['status' => 'ok'], 200)]);

        (new SpellLibraryClient())->health();

        Http::assertSent(fn($req) => str_starts_with($req->url(), rtrim($defaultUrl, '/') . '/'));
    }
}

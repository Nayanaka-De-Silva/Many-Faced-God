<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class SpellLibraryProxyTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
    }

    // --- GET /api/spell-library/spells ---

    public function test_index_proxies_whitelisted_query_params_and_returns_payload(): void
    {
        $fakePayload = ['data' => [['id' => 'phb-fireball', 'name' => 'Fireball']], 'meta' => ['totalItems' => 1]];
        Http::fake(['*' => Http::response($fakePayload, 200)]);

        $response = $this->get(route('spell-library.index', [
            'search'     => 'fire',
            'page'       => 1,
            'evil_param' => 'injected',
        ]));

        $response->assertStatus(200);
        $response->assertJson($fakePayload);

        Http::assertSent(function ($request) {
            parse_str(parse_url($request->url(), PHP_URL_QUERY), $params);
            return isset($params['search'], $params['page'])
                && !array_key_exists('evil_param', $params);
        });
    }

    public function test_index_returns_503_library_unreachable_on_connection_failure(): void
    {
        Http::fake(fn() => throw new ConnectionException('Connection refused'));

        $response = $this->get(route('spell-library.index'));

        $response->assertStatus(503);
        $response->assertJson(['error' => ['code' => 'LIBRARY_UNREACHABLE']]);
    }

    public function test_index_returns_503_library_unreachable_on_upstream_error_status(): void
    {
        Http::fake(['*' => Http::response('<html>Bad Gateway</html>', 502)]);

        $response = $this->get(route('spell-library.index'));

        $response->assertStatus(503);
        $response->assertJson(['error' => ['code' => 'LIBRARY_UNREACHABLE']]);
    }

    // --- GET /api/spell-library/spells/{id} ---

    public function test_show_returns_spell_json_on_success(): void
    {
        $spellData = ['id' => 'phb-fireball', 'name' => 'Fireball', 'level' => 3];
        Http::fake(['*' => Http::response($spellData, 200)]);

        $response = $this->get(route('spell-library.show', ['id' => 'phb-fireball']));

        $response->assertStatus(200);
        $response->assertJson($spellData);
    }

    public function test_show_returns_404_json_when_spell_not_found(): void
    {
        Http::fake(['*' => Http::response([], 404)]);

        $response = $this->get(route('spell-library.show', ['id' => 'phb-nonexistent']));

        $response->assertStatus(404);
        $response->assertJson(['error' => ['code' => 'NOT_FOUND']]);
    }

    public function test_show_returns_503_library_unreachable_on_connection_failure(): void
    {
        Http::fake(fn() => throw new ConnectionException('Connection refused'));

        $response = $this->get(route('spell-library.show', ['id' => 'phb-fireball']));

        $response->assertStatus(503);
        $response->assertJson(['error' => ['code' => 'LIBRARY_UNREACHABLE']]);
    }

    public function test_show_returns_503_instead_of_caching_upstream_error_as_spell_data(): void
    {
        Http::fake(['*' => Http::response('<html>Bad Gateway</html>', 502)]);

        $response = $this->get(route('spell-library.show', ['id' => 'phb-fireball']));

        $response->assertStatus(503);
        $response->assertJson(['error' => ['code' => 'LIBRARY_UNREACHABLE']]);
    }

    // --- GET /api/spell-library/health ---

    public function test_health_returns_ok_status_when_library_is_healthy(): void
    {
        Http::fake(['*' => Http::response(['status' => 'ok'], 200)]);

        $response = $this->get(route('spell-library.health'));

        $response->assertStatus(200);
        $response->assertJson(['status' => 'ok']);
    }

    public function test_health_returns_unreachable_status_when_library_fails(): void
    {
        Http::fake(['*' => Http::response([], 500)]);

        $response = $this->get(route('spell-library.health'));

        $response->assertStatus(200);
        $response->assertJson(['status' => 'unreachable']);
    }

    public function test_health_returns_503_on_connection_failure(): void
    {
        Http::fake(fn() => throw new ConnectionException('Connection refused'));

        $response = $this->get(route('spell-library.health'));

        $response->assertStatus(503);
        $response->assertJson(['error' => ['code' => 'LIBRARY_UNREACHABLE']]);
    }
}

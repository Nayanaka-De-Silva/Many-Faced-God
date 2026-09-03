<?php

namespace Tests\Unit;

use App\Exceptions\BankOfVivaldiRequestException;
use App\Exceptions\BankOfVivaldiUnavailableException;
use App\Models\AppSetting;
use App\Services\Vivaldi\BankOfVivaldiClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Tests\TestCase;

class BankOfVivaldiClientTest extends TestCase
{
    use RefreshDatabase;

    private string $vaultId;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
        $this->vaultId = (string) Str::uuid();
    }

    // --- health() ---

    public function test_health_returns_true_when_service_responds_ok(): void
    {
        Http::fake(['*' => Http::response(['status' => 'ok'], 200)]);

        $this->assertTrue((new BankOfVivaldiClient())->health());
    }

    public function test_health_returns_false_on_http_error_status(): void
    {
        Http::fake(['*' => Http::response([], 500)]);

        $this->assertFalse((new BankOfVivaldiClient())->health());
    }

    public function test_health_throws_unavailable_exception_on_connection_failure(): void
    {
        Http::fake(fn () => throw new ConnectionException('Connection refused'));

        $this->expectException(BankOfVivaldiUnavailableException::class);

        (new BankOfVivaldiClient())->health();
    }

    public function test_requests_are_prefixed_with_api_v1(): void
    {
        Http::fake(['*' => Http::response(['status' => 'ok'], 200)]);

        (new BankOfVivaldiClient())->health();

        Http::assertSent(fn ($request) => str_contains($request->url(), '/api/v1/health'));
    }

    // --- searchCompendium() ---

    public function test_search_compendium_passes_only_whitelisted_params(): void
    {
        Http::fake(['*' => Http::response(['data' => [], 'meta' => []], 200)]);

        (new BankOfVivaldiClient())->searchCompendium([
            'q'          => 'sword',
            'category'   => 'weapon',
            'page'       => 2,
            'pageSize'   => 10,
            'evil_param' => 'injected',
        ]);

        Http::assertSent(function ($request) {
            parse_str(parse_url($request->url(), PHP_URL_QUERY), $params);

            return isset($params['q'], $params['category'], $params['page'], $params['pageSize'])
                && !array_key_exists('evil_param', $params);
        });
    }

    public function test_search_compendium_clamps_page_and_page_size_to_the_api_bounds(): void
    {
        Http::fake(['*' => Http::response(['data' => [], 'meta' => []], 200)]);

        (new BankOfVivaldiClient())->searchCompendium(['page' => 0, 'pageSize' => 9999]);

        Http::assertSent(function ($request) {
            parse_str(parse_url($request->url(), PHP_URL_QUERY), $params);

            return $params['page'] === '1' && $params['pageSize'] === '200';
        });
    }

    public function test_search_compendium_cache_key_is_stable_regardless_of_param_order(): void
    {
        Http::fake(['*' => Http::response(['data' => [], 'meta' => []], 200)]);

        $client = new BankOfVivaldiClient();
        $client->searchCompendium(['q' => 'sword', 'category' => 'weapon']);
        $client->searchCompendium(['category' => 'weapon', 'q' => 'sword']);

        Http::assertSentCount(1);
    }

    public function test_search_compendium_throws_unavailable_exception_on_error_status(): void
    {
        Http::fake(['*' => Http::response('not json', 500)]);

        $this->expectException(BankOfVivaldiUnavailableException::class);

        (new BankOfVivaldiClient())->searchCompendium(['q' => 'sword']);
    }

    // --- getVault() ---

    public function test_get_vault_returns_array_on_success(): void
    {
        $detail = ['summary' => ['vault' => ['id' => $this->vaultId]], 'rootItems' => [], 'links' => []];
        Http::fake(['*' => Http::response($detail, 200)]);

        $this->assertEquals($detail, (new BankOfVivaldiClient())->getVault($this->vaultId));
    }

    public function test_get_vault_returns_null_on_404(): void
    {
        Http::fake(['*' => Http::response(['error' => ['code' => 'NOT_FOUND']], 404)]);

        $this->assertNull((new BankOfVivaldiClient())->getVault($this->vaultId));
    }

    public function test_get_vault_is_not_cached(): void
    {
        Http::fake(['*' => Http::response(['summary' => [], 'rootItems' => [], 'links' => []], 200)]);

        $client = new BankOfVivaldiClient();
        $client->getVault($this->vaultId);
        $client->getVault($this->vaultId);

        Http::assertSentCount(2);
    }

    public function test_get_vault_rejects_a_non_uuid_id_before_any_http_call(): void
    {
        Http::fake();

        try {
            (new BankOfVivaldiClient())->getVault('not-a-uuid');
            $this->fail('Expected InvalidArgumentException.');
        } catch (InvalidArgumentException) {
            // expected
        }

        Http::assertNothingSent();
    }

    public function test_get_vault_throws_unavailable_exception_on_5xx(): void
    {
        Http::fake(['*' => Http::response('<html>Bad Gateway</html>', 502)]);

        $this->expectException(BankOfVivaldiUnavailableException::class);

        (new BankOfVivaldiClient())->getVault($this->vaultId);
    }

    // --- createVault() / linkVault() / addItem() ---

    public function test_create_vault_posts_the_payload_and_returns_the_vault(): void
    {
        $vault = ['id' => $this->vaultId, 'characterName' => 'Goblin Boss', 'kind' => 'npc'];
        Http::fake(['*' => Http::response($vault, 201)]);

        $result = (new BankOfVivaldiClient())->createVault([
            'characterName' => 'Goblin Boss',
            'strengthScore' => 10,
            'kind'          => 'npc',
        ]);

        $this->assertEquals($vault, $result);
        Http::assertSent(function ($request) {
            return $request->method() === 'POST'
                && str_ends_with($request->url(), '/api/v1/vaults')
                && $request['characterName'] === 'Goblin Boss'
                && $request['kind'] === 'npc';
        });
    }

    public function test_link_vault_posts_external_ref_to_the_link_endpoint(): void
    {
        Http::fake(['*' => Http::response(['id' => 'link-1', 'externalRef' => 'many-faced-god:npc-7'], 201)]);

        (new BankOfVivaldiClient())->linkVault($this->vaultId, 'many-faced-god:npc-7', 'Goblin Boss');

        Http::assertSent(function ($request) {
            return $request->method() === 'POST'
                && str_ends_with($request->url(), "/api/v1/vaults/{$this->vaultId}/link")
                && $request['externalRef'] === 'many-faced-god:npc-7'
                && $request['label'] === 'Goblin Boss';
        });
    }

    public function test_unlink_vault_sends_a_delete_with_the_external_ref(): void
    {
        Http::fake(['*' => Http::response('', 204)]);

        (new BankOfVivaldiClient())->unlinkVault($this->vaultId, 'many-faced-god:npc-7');

        Http::assertSent(function ($request) {
            parse_str(parse_url($request->url(), PHP_URL_QUERY), $params);

            return $request->method() === 'DELETE'
                && $params['externalRef'] === 'many-faced-god:npc-7';
        });
    }

    public function test_add_item_posts_to_the_vault_items_endpoint(): void
    {
        Http::fake(['*' => Http::response(['id' => 'item-1', 'name' => 'Rusty Dagger'], 201)]);

        (new BankOfVivaldiClient())->addItem($this->vaultId, ['name' => 'Rusty Dagger', 'category' => 'weapon']);

        Http::assertSent(function ($request) {
            return $request->method() === 'POST'
                && str_ends_with($request->url(), "/api/v1/vaults/{$this->vaultId}/items")
                && $request['name'] === 'Rusty Dagger';
        });
    }

    public function test_add_item_raises_request_exception_carrying_the_error_envelope_on_400(): void
    {
        $envelope = ['error' => ['code' => 'INVALID_INPUT', 'message' => 'Bad item', 'details' => [['field' => 'category', 'message' => 'unknown']]]];
        Http::fake(['*' => Http::response($envelope, 400)]);

        try {
            (new BankOfVivaldiClient())->addItem($this->vaultId, ['name' => 'x', 'category' => 'bogus']);
            $this->fail('Expected BankOfVivaldiRequestException.');
        } catch (BankOfVivaldiRequestException $e) {
            $this->assertSame(400, $e->status);
            $this->assertSame($envelope, $e->errorBody);
        }
    }

    public function test_add_item_raises_unavailable_exception_on_upstream_5xx(): void
    {
        Http::fake(['*' => Http::response('boom', 500)]);

        $this->expectException(BankOfVivaldiUnavailableException::class);

        (new BankOfVivaldiClient())->addItem($this->vaultId, ['name' => 'x', 'category' => 'weapon']);
    }

    // --- base URL resolution ---

    public function test_resolves_base_url_from_app_setting_over_config_default(): void
    {
        AppSetting::set('vivaldi.base_url', 'http://custom-bank:9999');
        Http::fake(['http://custom-bank:9999/*' => Http::response(['status' => 'ok'], 200)]);

        $this->assertTrue((new BankOfVivaldiClient())->health());
        Http::assertSent(fn ($request) => str_starts_with($request->url(), 'http://custom-bank:9999/'));
    }

    public function test_falls_back_to_config_default_when_no_override(): void
    {
        $default = config('services.vivaldi.base_url');
        Http::fake(['*' => Http::response(['status' => 'ok'], 200)]);

        (new BankOfVivaldiClient())->health();

        Http::assertSent(fn ($request) => str_starts_with($request->url(), rtrim($default, '/') . '/'));
    }
}

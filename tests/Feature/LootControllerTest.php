<?php

namespace Tests\Feature;

use App\Models\Npc;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\TestCase;

class LootControllerTest extends TestCase
{
    use RefreshDatabase;

    private string $vaultId;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
        $this->vaultId = (string) Str::uuid();
    }

    private function npcWithVault(): Npc
    {
        return Npc::factory()->create(['vivaldi_vault_id' => $this->vaultId]);
    }

    // --- GET /npcs/{npc}/loot ---

    public function test_show_returns_null_vault_when_npc_has_none(): void
    {
        Http::fake();
        $npc = Npc::factory()->create();

        $this->getJson(route('npcs.loot.show', $npc))
            ->assertOk()
            ->assertExactJson(['vault' => null]);

        Http::assertNothingSent();
    }

    public function test_show_returns_vault_detail_from_the_bank(): void
    {
        $detail = ['summary' => ['vault' => ['id' => $this->vaultId]], 'rootItems' => [], 'links' => []];
        Http::fake(['*' => Http::response($detail, 200)]);

        $this->getJson(route('npcs.loot.show', $this->npcWithVault()))
            ->assertOk()
            ->assertJson($detail);
    }

    public function test_show_reports_null_vault_without_mutating_state_when_the_bank_returns_404(): void
    {
        Http::fake(['*' => Http::response(['error' => ['code' => 'NOT_FOUND']], 404)]);
        $npc = $this->npcWithVault();

        $this->getJson(route('npcs.loot.show', $npc))
            ->assertOk()
            ->assertExactJson(['vault' => null]);

        // A GET must not write — the dangling id is reconciled by the next create().
        $this->assertSame($this->vaultId, $npc->fresh()->vivaldi_vault_id);
    }

    public function test_show_returns_503_when_the_bank_is_unreachable(): void
    {
        Http::fake(fn () => throw new ConnectionException('refused'));

        $this->getJson(route('npcs.loot.show', $this->npcWithVault()))
            ->assertStatus(503)
            ->assertJson(['error' => ['code' => 'BANK_UNREACHABLE']]);
    }

    // --- POST /npcs/{npc}/loot ---

    public function test_create_makes_a_vault_links_it_and_stores_the_id(): void
    {
        Http::fake([
            '*/vaults'        => Http::response(['id' => $this->vaultId, 'kind' => 'npc'], 201),
            '*/vaults/*/link' => Http::response(['id' => 'link-1'], 201),
        ]);
        $npc = Npc::factory()->create(['name' => 'Goblin Boss']);

        $this->postJson(route('npcs.loot.create', $npc))
            ->assertStatus(201)
            ->assertJson(['id' => $this->vaultId]);

        $this->assertSame($this->vaultId, $npc->fresh()->vivaldi_vault_id);
        Http::assertSent(fn ($request) => str_ends_with($request->url(), '/link')
            && $request['externalRef'] === "many-faced-god:npc-{$npc->id}");
    }

    public function test_create_is_a_conflict_when_the_recorded_vault_still_exists(): void
    {
        Http::fake(['*' => Http::response(['summary' => [], 'rootItems' => [], 'links' => []], 200)]);

        $this->postJson(route('npcs.loot.create', $this->npcWithVault()))
            ->assertStatus(409)
            ->assertJson(['error' => ['code' => 'CONFLICT']]);

        Http::assertSent(fn ($request) => $request->method() === 'GET'
            && str_contains($request->url(), "/vaults/{$this->vaultId}"));
    }

    public function test_create_replaces_a_dangling_vault_id_when_the_recorded_vault_is_gone(): void
    {
        $newVaultId = (string) Str::uuid();
        Http::fake([
            "*/vaults/{$this->vaultId}" => Http::response(['error' => ['code' => 'NOT_FOUND']], 404),
            '*/vaults/*/link'           => Http::response(['id' => 'link-1'], 201),
            '*/vaults'                  => Http::response(['id' => $newVaultId, 'kind' => 'npc'], 201),
        ]);
        $npc = $this->npcWithVault();

        $this->postJson(route('npcs.loot.create', $npc))
            ->assertStatus(201)
            ->assertJson(['id' => $newVaultId]);

        $this->assertSame($newVaultId, $npc->fresh()->vivaldi_vault_id);
    }

    public function test_create_returns_502_when_the_bank_returns_a_vault_without_a_valid_id(): void
    {
        Http::fake(['*/vaults' => Http::response(['id' => 'not-a-uuid'], 201)]);
        $npc = Npc::factory()->create();

        $this->postJson(route('npcs.loot.create', $npc))
            ->assertStatus(502)
            ->assertJson(['error' => ['code' => 'BAD_UPSTREAM']]);

        $this->assertNull($npc->fresh()->vivaldi_vault_id);
    }

    public function test_create_still_returns_201_when_the_cross_link_fails(): void
    {
        Http::fake([
            '*/vaults/*/link' => Http::response(['error' => ['code' => 'INTERNAL_ERROR']], 500),
            '*/vaults'        => Http::response(['id' => $this->vaultId, 'kind' => 'npc'], 201),
        ]);
        $npc = Npc::factory()->create();

        $this->postJson(route('npcs.loot.create', $npc))->assertStatus(201);

        $this->assertSame($this->vaultId, $npc->fresh()->vivaldi_vault_id);
    }

    public function test_create_returns_503_when_the_bank_is_unreachable(): void
    {
        Http::fake(fn () => throw new ConnectionException('refused'));
        $npc = Npc::factory()->create();

        $this->postJson(route('npcs.loot.create', $npc))->assertStatus(503);

        $this->assertNull($npc->fresh()->vivaldi_vault_id);
    }

    // --- POST /npcs/{npc}/loot/items ---

    public function test_add_item_forwards_an_inline_definition_to_the_bank(): void
    {
        Http::fake(['*' => Http::response(['id' => 'item-1', 'name' => 'Rusty Dagger'], 201)]);

        $this->postJson(route('npcs.loot.items.store', $this->npcWithVault()), [
            'name'                 => 'Rusty Dagger',
            'category'             => 'weapon',
            'weight_hundredths_lb' => 100,
            'base_value_cp'        => 200,
        ])->assertStatus(201);

        Http::assertSent(fn ($request) => str_ends_with($request->url(), "/vaults/{$this->vaultId}/items")
            && $request['name'] === 'Rusty Dagger'
            && $request['weightHundredthsLb'] === 100);
    }

    public function test_add_item_forwards_a_compendium_transfer(): void
    {
        Http::fake(['*' => Http::response(['id' => 'item-2'], 201)]);
        $sourceId = (string) Str::uuid();

        $this->postJson(route('npcs.loot.items.store', $this->npcWithVault()), [
            'source_item_id' => $sourceId,
            'mode'           => 'copy',
        ])->assertStatus(201);

        Http::assertSent(fn ($request) => $request['sourceItemId'] === $sourceId && $request['mode'] === 'copy');
    }

    public function test_add_item_rejects_an_unknown_category_before_calling_the_bank(): void
    {
        Http::fake();

        $this->postJson(route('npcs.loot.items.store', $this->npcWithVault()), [
            'name'     => 'Weird Thing',
            'category' => 'not-a-category',
        ])->assertStatus(422)->assertJsonValidationErrors('category');

        Http::assertNothingSent();
    }

    public function test_add_item_maps_upstream_invalid_input_to_422_with_details(): void
    {
        $envelope = ['error' => [
            'code'    => 'INVALID_INPUT',
            'message' => 'Bad item',
            'details' => [['field' => 'containerId', 'message' => 'not a container']],
        ]];
        Http::fake(['*' => Http::response($envelope, 400)]);

        $this->postJson(route('npcs.loot.items.store', $this->npcWithVault()), [
            'name'     => 'Torch',
            'category' => 'adventuring-gear',
        ])->assertStatus(422)->assertJson($envelope);
    }

    public function test_add_item_relays_a_non_validation_upstream_error_with_its_status(): void
    {
        $envelope = ['error' => ['code' => 'NOT_FOUND', 'message' => 'Container not in this vault']];
        Http::fake(['*' => Http::response($envelope, 404)]);

        $this->postJson(route('npcs.loot.items.store', $this->npcWithVault()), [
            'name'     => 'Torch',
            'category' => 'adventuring-gear',
        ])->assertStatus(404)->assertJson($envelope);
    }

    public function test_add_item_returns_404_when_the_npc_has_no_vault(): void
    {
        Http::fake();

        $this->postJson(route('npcs.loot.items.store', Npc::factory()->create()), [
            'name'     => 'Torch',
            'category' => 'adventuring-gear',
        ])->assertStatus(404);

        Http::assertNothingSent();
    }

    public function test_add_item_returns_503_on_upstream_5xx(): void
    {
        Http::fake(['*' => Http::response('boom', 500)]);

        $this->postJson(route('npcs.loot.items.store', $this->npcWithVault()), [
            'name'     => 'Torch',
            'category' => 'adventuring-gear',
        ])->assertStatus(503);
    }

    // --- DELETE /npcs/{npc}/loot ---

    public function test_detach_unlinks_and_clears_the_column(): void
    {
        Http::fake(['*' => Http::response('', 204)]);
        $npc = $this->npcWithVault();

        $this->deleteJson(route('npcs.loot.detach', $npc))->assertStatus(204);

        $this->assertNull($npc->fresh()->vivaldi_vault_id);
        Http::assertSent(fn ($request) => $request->method() === 'DELETE'
            && str_contains($request->url(), 'externalRef=many-faced-god'));
    }

    public function test_detach_is_a_noop_204_when_there_is_no_vault(): void
    {
        Http::fake();

        $this->deleteJson(route('npcs.loot.detach', Npc::factory()->create()))->assertStatus(204);

        Http::assertNothingSent();
    }

    public function test_detach_clears_the_column_when_the_bank_says_the_link_is_already_gone(): void
    {
        Http::fake(['*' => Http::response(['error' => ['code' => 'NOT_FOUND']], 404)]);
        $npc = $this->npcWithVault();

        $this->deleteJson(route('npcs.loot.detach', $npc))->assertStatus(204);

        $this->assertNull($npc->fresh()->vivaldi_vault_id);
    }

    public function test_detach_returns_503_and_keeps_the_column_when_the_bank_is_unreachable(): void
    {
        Http::fake(fn () => throw new ConnectionException('refused'));
        $npc = $this->npcWithVault();

        $this->deleteJson(route('npcs.loot.detach', $npc))->assertStatus(503);

        $this->assertSame($this->vaultId, $npc->fresh()->vivaldi_vault_id);
    }

    // --- GET /api/loot/compendium ---

    public function test_compendium_proxies_the_search_and_returns_the_payload(): void
    {
        $payload = ['data' => [['item' => ['id' => 'x', 'name' => 'Longsword']]], 'meta' => ['totalItems' => 1]];
        Http::fake(['*' => Http::response($payload, 200)]);

        $this->getJson(route('loot.compendium', ['q' => 'sword', 'evil' => 'x']))
            ->assertOk()
            ->assertJson($payload);

        Http::assertSent(function ($request) {
            parse_str(parse_url($request->url(), PHP_URL_QUERY), $params);

            return ($params['q'] ?? null) === 'sword' && !array_key_exists('evil', $params);
        });
    }

    public function test_compendium_returns_503_when_the_bank_is_unreachable(): void
    {
        Http::fake(fn () => throw new ConnectionException('refused'));

        $this->getJson(route('loot.compendium'))->assertStatus(503);
    }

    // --- GET /api/loot/health ---

    public function test_health_reports_ok_when_the_bank_is_healthy(): void
    {
        Http::fake(['*' => Http::response(['status' => 'ok'], 200)]);

        $this->getJson(route('loot.health'))->assertOk()->assertJson(['status' => 'ok']);
    }

    public function test_health_reports_unreachable_on_an_upstream_error_status(): void
    {
        Http::fake(['*' => Http::response([], 500)]);

        $this->getJson(route('loot.health'))->assertOk()->assertJson(['status' => 'unreachable']);
    }

    public function test_health_returns_503_on_a_connection_failure(): void
    {
        Http::fake(fn () => throw new ConnectionException('refused'));

        $this->getJson(route('loot.health'))->assertStatus(503);
    }
}

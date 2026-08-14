<?php

namespace Tests\Feature\Api\V1;

use App\Models\Npc;
use App\Models\NpcTrait;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ApiCacheHeadersTest extends TestCase
{
    use RefreshDatabase;

    public function test_get_npc_returns_a_non_empty_etag(): void
    {
        $npc = Npc::factory()->create(['is_template' => false]);

        $response = $this->getJson("/api/v1/npcs/{$npc->id}");

        $response->assertStatus(200);
        $this->assertNotEmpty($response->headers->get('ETag'));
    }

    public function test_if_none_match_with_the_current_etag_returns_304_with_empty_body(): void
    {
        $npc = Npc::factory()->create(['is_template' => false]);

        $first = $this->getJson("/api/v1/npcs/{$npc->id}");
        $etag  = $first->headers->get('ETag');

        $second = $this->get("/api/v1/npcs/{$npc->id}", [
            'Accept'         => 'application/json',
            'If-None-Match'  => $etag,
        ]);

        $second->assertStatus(304);
        $this->assertEmpty($second->getContent());
    }

    /**
     * Proves the no-$touches gap (landmine #4) is actually handled: editing
     * only a child trait must change the parent NPC's ETag, even though
     * saving the trait does NOT bump npcs.updated_at in the database.
     */
    public function test_editing_only_a_child_trait_changes_the_npc_etag(): void
    {
        $npc = Npc::factory()->create(['is_template' => false]);
        $trait = NpcTrait::factory()->for($npc, 'npc')->create();

        $before = $this->getJson("/api/v1/npcs/{$npc->id}");
        $beforeEtag = $before->headers->get('ETag');

        // Editing the trait alone — deliberately not touching/saving $npc itself.
        sleep(1); // ensure a distinct updated_at second (timestamp-resolution ETag)
        $trait->update(['description' => 'A freshly rewritten description.']);

        $after = $this->getJson("/api/v1/npcs/{$npc->id}");
        $afterEtag = $after->headers->get('ETag');

        $this->assertNotSame($beforeEtag, $afterEtag, 'ETag must change when a child trait is edited.');
    }

    public function test_access_control_expose_headers_includes_etag_with_no_origin_sent(): void
    {
        $npc = Npc::factory()->create(['is_template' => false]);

        // Deliberately no Origin header — mirrors the acceptance suite's
        // "Get NPC" request, which never sends one. Laravel's own CORS
        // middleware only fires when Origin is present, so this header must
        // be set unconditionally by our own middleware.
        $response = $this->getJson("/api/v1/npcs/{$npc->id}");

        $exposed = strtolower($response->headers->get('Access-Control-Expose-Headers', ''));
        $this->assertStringContainsString('etag', $exposed);
    }

    public function test_repeat_fetch_body_is_byte_identical_regardless_of_etag_machinery(): void
    {
        $npc = Npc::factory()->create(['is_template' => false]);

        $first  = $this->getJson("/api/v1/npcs/{$npc->id}");
        $second = $this->getJson("/api/v1/npcs/{$npc->id}");

        $this->assertSame($first->getContent(), $second->getContent());
    }
}

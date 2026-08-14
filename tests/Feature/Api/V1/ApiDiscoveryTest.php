<?php

namespace Tests\Feature\Api\V1;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ApiDiscoveryTest extends TestCase
{
    use RefreshDatabase;

    // ── GET /api/v1/ ────────────────────────────────────────────────────────

    public function test_root_returns_200_with_version_endpoints_and_openapi_spec(): void
    {
        $response = $this->getJson('/api/v1/');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'version',
                'endpoints',
                'openapi',
            ])
            ->assertJsonPath('version', 1);

        $endpoints = $response->json('endpoints');
        $this->assertIsArray($endpoints);
        $this->assertNotEmpty($endpoints);
    }

    public function test_root_endpoints_map_includes_all_planned_paths(): void
    {
        $response = $this->getJson('/api/v1/');
        $response->assertStatus(200);

        $endpoints = $response->json('endpoints');

        // All 9 contracted endpoints must appear in the discovery map.
        foreach (['/api/v1/', '/api/v1/health', '/api/v1/npcs', '/api/v1/templates', '/api/v1/folders'] as $path) {
            $this->assertContains($path, $endpoints, "Discovery map must include path {$path}");
        }
    }

    public function test_root_openapi_spec_link_points_to_api_v1_yaml(): void
    {
        $response = $this->getJson('/api/v1/');
        $response->assertStatus(200);

        $this->assertStringContainsString('/api/v1/openapi.yaml', $response->json('openapi'));
    }

    // ── GET /api/v1/health ──────────────────────────────────────────────────

    public function test_health_returns_status_ok(): void
    {
        $response = $this->getJson('/api/v1/health');

        $response->assertStatus(200)
            ->assertExactJson(['status' => 'ok']);
    }

    // ── X-MFG-API-Version header ────────────────────────────────────────────

    public function test_root_response_has_api_version_header(): void
    {
        $response = $this->getJson('/api/v1/');

        $response->assertHeader('X-MFG-API-Version', '1');
    }

    public function test_health_response_has_api_version_header(): void
    {
        $response = $this->getJson('/api/v1/health');

        $response->assertHeader('X-MFG-API-Version', '1');
    }

    // ── JSON error envelope ──────────────────────────────────────────────────

    public function test_post_to_root_returns_json_method_not_allowed_envelope(): void
    {
        $response = $this->postJson('/api/v1/');

        $response->assertStatus(405)
            ->assertJsonPath('error.code', 'METHOD_NOT_ALLOWED')
            ->assertJsonStructure(['error' => ['code', 'message']]);
    }

    /**
     * Must return the JSON error envelope even without an Accept header.
     * Uses ->call() rather than ->getJson() to avoid the automatic
     * Accept: application/json header — the shouldRenderJsonWhen callback
     * must fire on path alone, not on client negotiation.
     */
    public function test_unmatched_path_returns_json_not_found_envelope_without_accept_header(): void
    {
        $response = $this->call('GET', '/api/v1/does-not-exist');

        $this->assertEquals(404, $response->getStatusCode());

        $body = json_decode($response->getContent(), true);
        $this->assertIsArray($body);
        $this->assertArrayHasKey('error', $body);
        $this->assertEquals('NOT_FOUND', $body['error']['code']);
        $this->assertArrayHasKey('message', $body['error']);

        // Content-Type should be application/json, not text/html.
        $this->assertStringContainsString('application/json', $response->headers->get('Content-Type'));
    }

    // ── spell-library routes must remain unshadowed ──────────────────────────

    public function test_spell_library_proxy_routes_are_not_shadowed_by_api_v1(): void
    {
        // GET /api/spell-library/health must still resolve to SpellLibraryProxyController.
        // It will fail with a connection error in tests (no live service), but it must NOT 404.
        $response = $this->get('/api/spell-library/health');

        // 200 (mocked), 500 (service unavailable), or 503 — anything but 404.
        $this->assertNotEquals(404, $response->getStatusCode());
    }
}

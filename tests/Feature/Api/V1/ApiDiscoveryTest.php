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

    // ── GET /api/v1/metadata ────────────────────────────────────────────────

    public function test_metadata_publishes_every_vocabulary(): void
    {
        $response = $this->getJson('/api/v1/metadata');

        $response->assertStatus(200)->assertJsonStructure([
            'alignments', 'skills', 'damageTypes', 'conditions',
            'senseCategories', 'actionTypes', 'castingTypes', 'challengeRatings',
        ]);
    }

    /**
     * The DMG CR table (manticore-arena-v2.2.md:157-174, the canonical source
     * cited by issue #65) has 34 distinct CRs: CR 0, three fractional CRs
     * (1/8, 1/4, 1/2), and integers 1 through 30. The pre-authored Postman
     * collection asserts 31 ("CR 0 through 30"), undercounting the three
     * fractions — its own CR-sort-order test elsewhere lists all 34 values
     * in its ORDER array, so this is a miscount in that one assertion, not
     * a second valid contract. Documented in the PR per the issue's own
     * instruction to amend the collection only when the contract itself is
     * deliberately amended.
     */
    public function test_metadata_challenge_ratings_cover_all_34_crs(): void
    {
        $response = $this->getJson('/api/v1/metadata');

        $crs = $response->json('challengeRatings');
        $this->assertCount(34, $crs);
    }

    public function test_metadata_challenge_rating_xp_matches_dmg_table(): void
    {
        $response = $this->getJson('/api/v1/metadata');

        $crs = $response->json('challengeRatings');
        $this->assertSame(25, $crs['1/8']['xp']);
        $this->assertSame(50, $crs['1/4']['xp']);
        $this->assertSame(100, $crs['1/2']['xp']);
        $this->assertSame(200, $crs['1']['xp']);
        $this->assertSame(700, $crs['3']['xp']);
        $this->assertSame(25000, $crs['20']['xp']);
        $this->assertSame(155000, $crs['30']['xp']);
        $this->assertSame(0, $crs['0']['xp']);
        $this->assertSame(10, $crs['0']['xpIfDangerous']);
    }

    public function test_metadata_action_types_keep_snake_case_enum_values(): void
    {
        $response = $this->getJson('/api/v1/metadata');

        $json = $response->getContent();
        $this->assertStringContainsString('attack_action', $json);
        $this->assertStringContainsString('legendary_action', $json);
    }

    public function test_metadata_response_has_api_version_header(): void
    {
        $response = $this->getJson('/api/v1/metadata');

        $response->assertHeader('X-MFG-API-Version', '1');
    }

    // ── GET /api/v1/openapi.yaml ────────────────────────────────────────────

    public function test_openapi_yaml_is_served_as_yaml(): void
    {
        $response = $this->get('/api/v1/openapi.yaml', ['Accept' => 'text/yaml']);

        $response->assertStatus(200);
        $this->assertMatchesRegularExpression('/ya?ml/i', $response->headers->get('Content-Type'));
    }

    public function test_openapi_yaml_declares_version_3_1(): void
    {
        $response = $this->get('/api/v1/openapi.yaml');

        $this->assertMatchesRegularExpression('/openapi:\s*["\']?3\.1/', $response->getContent());
    }

    public function test_openapi_yaml_documents_core_paths(): void
    {
        $response = $this->get('/api/v1/openapi.yaml');
        $body = $response->getContent();

        foreach (['/npcs', '/templates', '/folders', '/health', '/metadata'] as $path) {
            $this->assertStringContainsString($path, $body, "spec omits {$path}");
        }
    }

    /**
     * Keeps the hand-written spec honest: every registered api/v1 GET route
     * must have a matching `paths` entry in openapi/v1.yaml, and vice versa.
     */
    public function test_openapi_yaml_paths_match_registered_routes_bidirectionally(): void
    {
        $spec = \Symfony\Component\Yaml\Yaml::parseFile(base_path('openapi/v1.yaml'));
        $specPaths = array_keys($spec['paths'] ?? []);

        $registeredPaths = collect(\Illuminate\Support\Facades\Route::getRoutes())
            ->filter(fn ($route) => str_starts_with($route->uri(), 'api/v1') && in_array('GET', $route->methods(), true))
            ->map(function ($route) {
                $uri = '/' . preg_replace('#^api/v1/?#', '', $route->uri());
                $uri = preg_replace('/\{[^}]+\}/', '{id}', $uri);
                return rtrim($uri, '/') === '' ? '/' : rtrim($uri, '/');
            })
            ->unique()
            ->values()
            ->all();

        $normalizedSpecPaths = collect($specPaths)
            ->map(function (string $p) {
                $p = preg_replace('/\{[^}]+\}/', '{id}', $p);
                return rtrim($p, '/') === '' ? '/' : rtrim($p, '/');
            })
            ->unique()
            ->values()
            ->all();

        sort($registeredPaths);
        sort($normalizedSpecPaths);

        $this->assertEquals(
            $registeredPaths,
            $normalizedSpecPaths,
            'openapi/v1.yaml paths must exactly match registered api/v1 GET routes'
        );
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

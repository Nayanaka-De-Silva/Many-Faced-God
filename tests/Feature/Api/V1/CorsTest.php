<?php

namespace Tests\Feature\Api\V1;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CorsTest extends TestCase
{
    use RefreshDatabase;

    private const ORIGIN = 'https://arena.example.com';

    /**
     * OPTIONS preflight for a GET /api/v1/health request must return
     * Access-Control-Allow-Origin (the CORS preflight response).
     */
    public function test_options_preflight_returns_allow_origin_header(): void
    {
        $response = $this->call('OPTIONS', '/api/v1/health', [], [], [], [
            'HTTP_ORIGIN'                        => self::ORIGIN,
            'HTTP_ACCESS_CONTROL_REQUEST_METHOD' => 'GET',
        ]);

        $response->assertHeader('Access-Control-Allow-Origin');
    }

    /**
     * A real GET response to an api/v1/* path with an Origin header must
     * include Access-Control-Expose-Headers listing both ETag and
     * X-MFG-API-Version so browser JS can read them.
     *
     * This is the most important CORS setting in the whole feature: without
     * exposed_headers, CORS strips those headers from JS visibility even
     * though they are present on the wire, silently defeating Arena's
     * cache-revalidation design.
     */
    public function test_get_response_exposes_etag_and_api_version_headers(): void
    {
        $response = $this->withHeaders(['Origin' => self::ORIGIN])
            ->get('/api/v1/health');

        $response->assertStatus(200);
        $response->assertHeader('Access-Control-Expose-Headers');

        $exposed = $response->headers->get('Access-Control-Expose-Headers');

        $this->assertStringContainsStringIgnoringCase('ETag', $exposed);
        $this->assertStringContainsStringIgnoringCase('X-MFG-API-Version', $exposed);
    }

    /**
     * The CORS configuration must apply to all api/* paths, not just api/v1/*.
     * Verify that spell-library paths also get the CORS origin header.
     */
    public function test_cors_applies_to_spell_library_paths(): void
    {
        $response = $this->call('OPTIONS', '/api/spell-library/health', [], [], [], [
            'HTTP_ORIGIN'                        => self::ORIGIN,
            'HTTP_ACCESS_CONTROL_REQUEST_METHOD' => 'GET',
        ]);

        $response->assertHeader('Access-Control-Allow-Origin');
    }
}

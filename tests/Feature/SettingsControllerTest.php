<?php

namespace Tests\Feature;

use App\Models\AppSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class SettingsControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
    }

    // --- GET /settings ---

    public function test_edit_returns_200_and_shows_current_effective_url(): void
    {
        $response = $this->get(route('settings.edit'));

        $response->assertStatus(200);
        $response->assertSee(config('services.netheril.base_url'));
    }

    public function test_edit_shows_db_override_url_when_one_is_set(): void
    {
        AppSetting::set('spell_library.base_url', 'http://override-library:8888');

        $response = $this->get(route('settings.edit'));

        $response->assertStatus(200);
        $response->assertSee('http://override-library:8888');
    }

    public function test_edit_shows_the_vivaldi_effective_url(): void
    {
        AppSetting::set('vivaldi.base_url', 'http://override-bank:7777');

        $response = $this->get(route('settings.edit'));

        $response->assertStatus(200);
        $response->assertSee(config('services.vivaldi.base_url'));
        $response->assertSee('http://override-bank:7777');
    }

    // --- PUT /settings ---

    public function test_update_persists_valid_url_to_app_settings(): void
    {
        $response = $this->put(route('settings.update'), [
            'spell_library_base_url' => 'http://new-library:4000',
        ]);

        $response->assertRedirect(route('settings.edit'));
        $this->assertEquals('http://new-library:4000', AppSetting::get('spell_library.base_url'));
    }

    public function test_update_returns_422_on_invalid_url(): void
    {
        // putJson sends Accept: application/json, so Laravel returns 422 instead of redirecting.
        $response = $this->putJson(route('settings.update'), [
            'spell_library_base_url' => 'not-a-valid-url',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('spell_library_base_url');
    }

    public function test_update_clears_db_override_when_empty_string_submitted(): void
    {
        AppSetting::set('spell_library.base_url', 'http://old-library:9000');

        $response = $this->put(route('settings.update'), [
            'spell_library_base_url' => '',
        ]);

        $response->assertRedirect(route('settings.edit'));
        $this->assertNull(AppSetting::get('spell_library.base_url'));
    }

    public function test_update_persists_the_vivaldi_url(): void
    {
        $response = $this->put(route('settings.update'), [
            'vivaldi_base_url' => 'http://new-bank:4100',
        ]);

        $response->assertRedirect(route('settings.edit'));
        $this->assertEquals('http://new-bank:4100', AppSetting::get('vivaldi.base_url'));
    }

    public function test_update_of_one_card_leaves_the_other_cards_override_untouched(): void
    {
        AppSetting::set('vivaldi.base_url', 'http://bank-kept:9000');

        // The spell-library card submits without a vivaldi field.
        $this->put(route('settings.update'), ['spell_library_base_url' => 'http://lib:5000']);

        $this->assertEquals('http://bank-kept:9000', AppSetting::get('vivaldi.base_url'));
    }

    public function test_update_returns_422_on_invalid_vivaldi_url(): void
    {
        $this->putJson(route('settings.update'), ['vivaldi_base_url' => 'not-a-url'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('vivaldi_base_url');
    }

    // --- POST /settings/spell-library/test ---

    public function test_test_connection_returns_ok_true_when_library_is_healthy(): void
    {
        Http::fake(['*' => Http::response(['status' => 'ok'], 200)]);

        $response = $this->post(route('settings.test-spell-library'));

        $response->assertStatus(200);
        $response->assertJson(['ok' => true]);
    }

    public function test_test_connection_returns_ok_false_on_connection_exception(): void
    {
        Http::fake(fn() => throw new ConnectionException('Connection refused'));

        $response = $this->post(route('settings.test-spell-library'));

        $response->assertStatus(200);
        $response->assertJson(['ok' => false]);
    }

    public function test_test_connection_does_not_return_500_on_connection_failure(): void
    {
        Http::fake(fn() => throw new ConnectionException('Connection refused'));

        $response = $this->post(route('settings.test-spell-library'));

        $response->assertStatus(200);
    }

    // --- POST /settings/vivaldi/test ---

    public function test_vivaldi_test_connection_returns_ok_true_when_healthy(): void
    {
        Http::fake(['*' => Http::response(['status' => 'ok'], 200)]);

        $this->post(route('settings.test-vivaldi'))
            ->assertStatus(200)
            ->assertJson(['ok' => true]);
    }

    public function test_vivaldi_test_connection_returns_ok_false_on_connection_exception(): void
    {
        Http::fake(fn() => throw new ConnectionException('Connection refused'));

        $this->post(route('settings.test-vivaldi'))
            ->assertStatus(200)
            ->assertJson(['ok' => false]);
    }
}

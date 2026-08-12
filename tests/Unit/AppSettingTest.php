<?php

namespace Tests\Unit;

use App\Models\AppSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AppSettingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
    }

    public function test_get_returns_default_when_no_row_exists(): void
    {
        $this->assertSame('fallback', AppSetting::get('does.not.exist', 'fallback'));
        $this->assertNull(AppSetting::get('does.not.exist'));
    }

    public function test_get_caches_the_absence_of_a_row_and_skips_the_second_db_query(): void
    {
        DB::enableQueryLog();

        AppSetting::get('does.not.exist');
        AppSetting::get('does.not.exist');

        $queries = collect(DB::getQueryLog())
            ->filter(fn ($q) => str_contains($q['query'], 'app_settings'));

        $this->assertCount(1, $queries, 'Expected the no-row case to be served from cache on the second call.');
    }

    public function test_set_then_get_returns_the_persisted_value(): void
    {
        AppSetting::set('spell_library.base_url', 'http://custom-library:9000');

        $this->assertSame('http://custom-library:9000', AppSetting::get('spell_library.base_url'));
    }

    public function test_set_null_clears_a_previously_cached_value(): void
    {
        AppSetting::set('spell_library.base_url', 'http://custom-library:9000');
        AppSetting::get('spell_library.base_url'); // warm the cache

        AppSetting::set('spell_library.base_url', null);

        $this->assertNull(AppSetting::get('spell_library.base_url'));
    }
}

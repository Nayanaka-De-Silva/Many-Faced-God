<?php

namespace Tests\Unit;

use Tests\TestCase;

/**
 * Regression coverage for a real deployment bug: env()'s default argument
 * only applies when a key is absent, not when it's present-but-empty — which
 * is exactly the state `cp .env.example .env` produced before this was fixed.
 */
class NetherilConfigTest extends TestCase
{
    public function test_base_url_falls_back_to_default_when_env_var_is_empty_string(): void
    {
        putenv('NETHERIL_BASE_URL=');
        $_ENV['NETHERIL_BASE_URL']    = '';
        $_SERVER['NETHERIL_BASE_URL'] = '';

        try {
            $config = require base_path('config/services.php');

            $this->assertSame('http://library-of-netheril:3000', $config['netheril']['base_url']);
        } finally {
            putenv('NETHERIL_BASE_URL');
            unset($_ENV['NETHERIL_BASE_URL'], $_SERVER['NETHERIL_BASE_URL']);
        }
    }

    public function test_env_example_does_not_ship_an_empty_netheril_base_url(): void
    {
        $envExample = file_get_contents(base_path('.env.example'));

        $this->assertMatchesRegularExpression(
            '/^NETHERIL_BASE_URL=\S+/m',
            $envExample,
            'NETHERIL_BASE_URL in .env.example must not be blank — env() only falls back '
                . 'to its default for an absent key, not a present-but-empty one.'
        );
    }
}

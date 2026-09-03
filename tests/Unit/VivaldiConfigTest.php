<?php

namespace Tests\Unit;

use Tests\TestCase;

/**
 * Same deployment trap NetherilConfigTest guards against: env()'s default
 * argument only applies when a key is absent, not when it's present-but-empty —
 * exactly the state `cp .env.example .env` produces.
 */
class VivaldiConfigTest extends TestCase
{
    public function test_base_url_falls_back_to_default_when_env_var_is_empty_string(): void
    {
        putenv('VIVALDI_BASE_URL=');
        $_ENV['VIVALDI_BASE_URL']    = '';
        $_SERVER['VIVALDI_BASE_URL'] = '';

        try {
            $config = require base_path('config/services.php');

            $this->assertSame('http://bank-of-vivaldi:8080', $config['vivaldi']['base_url']);
        } finally {
            putenv('VIVALDI_BASE_URL');
            unset($_ENV['VIVALDI_BASE_URL'], $_SERVER['VIVALDI_BASE_URL']);
        }
    }

    public function test_env_example_does_not_ship_an_empty_vivaldi_base_url(): void
    {
        $envExample = file_get_contents(base_path('.env.example'));

        $this->assertMatchesRegularExpression(
            '/^VIVALDI_BASE_URL=\S+/m',
            $envExample,
            'VIVALDI_BASE_URL in .env.example must not be blank — env() only falls back '
                . 'to its default for an absent key, not a present-but-empty one.'
        );
    }
}

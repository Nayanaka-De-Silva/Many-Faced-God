<?php

namespace Tests\Unit;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Yaml\Yaml;

/**
 * Guards the fix for issue #74.
 *
 * The app container joins Docker networks owned by other stacks
 * (netheril-integration, vivaldi-integration). Docker's embedded DNS answers
 * from every joined network, so the bare Compose service name "db" is not
 * unique and resolved non-deterministically once a foreign stack shipped its
 * own "db". The stack now addresses the database through a dedicated alias.
 *
 * These assertions are cheap and catch the compose files and .env.example
 * drifting apart again — which is the failure mode that reached production.
 */
class DatabaseHostConfigurationTest extends TestCase
{
    private const DATABASE_ALIAS = 'mfg-db';

    public static function composeFileProvider(): array
    {
        return [
            'production' => ['docker-compose.prod.yml', 'many-faced-god-network'],
            'development' => ['docker-compose.yml', 'mfg-network'],
        ];
    }

    #[DataProvider('composeFileProvider')]
    public function test_db_service_declares_the_shared_alias(string $composeFile, string $network): void
    {
        $networks = $this->parseComposeFile($composeFile)['services']['db']['networks'];

        $this->assertArrayHasKey(
            $network,
            $networks,
            "{$composeFile}: the db service must attach to {$network} in long form so it can declare aliases."
        );
        $this->assertContains(
            self::DATABASE_ALIAS,
            $networks[$network]['aliases'] ?? [],
            "{$composeFile}: the db service must expose the '".self::DATABASE_ALIAS."' alias that DB_HOST points at."
        );
    }

    #[DataProvider('composeFileProvider')]
    public function test_app_waits_for_a_healthy_database(string $composeFile): void
    {
        $dependencies = $this->parseComposeFile($composeFile)['services']['app']['depends_on'];

        $this->assertSame(
            'service_healthy',
            $dependencies['db']['condition'] ?? null,
            "{$composeFile}: app must wait for db to report healthy, not merely to be created."
        );
    }

    public function test_env_example_points_at_the_alias(): void
    {
        $example = file_get_contents($this->projectPath('.env.example'));

        $this->assertMatchesRegularExpression(
            '/^DB_HOST='.preg_quote(self::DATABASE_ALIAS, '/').'$/m',
            $example,
            '.env.example must set DB_HOST to the '.self::DATABASE_ALIAS.' alias declared by the compose files.'
        );
    }

    private function parseComposeFile(string $composeFile): array
    {
        return Yaml::parseFile($this->projectPath($composeFile));
    }

    private function projectPath(string $relativePath): string
    {
        return dirname(__DIR__, 2).'/'.$relativePath;
    }
}

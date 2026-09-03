<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Yaml\Yaml;

/**
 * Guards against a class of bug that broke three deploys in a row while
 * hardening the deploy step for issue #74: the readiness probe is written as
 * a single-quoted argument to `sh -lc` inside a Woodpecker command string
 * (`... exec -T app sh -lc '<script>'`). That whole string is itself parsed
 * by the *outer* shell running the pipeline step, so a literal apostrophe
 * anywhere inside it -- including inside a comment -- closes the quoting
 * early and corrupts everything after it. This exact mistake ("Laravel's
 * own config" in a comment) passed local testing because extracting the
 * script to a file and running it directly never exercises the outer
 * single-quote wrapping at all.
 */
class WoodpeckerDeployScriptTest extends TestCase
{
    public function test_deploy_probe_script_has_no_unescaped_apostrophe(): void
    {
        $script = $this->extractSingleQuotedProbeScript();

        $this->assertStringNotContainsString(
            "'",
            $script,
            "The deploy step's `sh -lc '...'` body must not contain a literal apostrophe "
            .'(straight or curly), including in comments -- it terminates the single-quoted '
            .'string early in the outer shell and corrupts everything after it.'
        );
        $this->assertStringNotContainsString(
            "\u{2019}",
            $script,
            'The same applies to a curly apostrophe (\u{2019}).'
        );
    }

    public function test_smoke_script_is_copied_after_the_container_recreate(): void
    {
        $commands = $this->findDeployCommands(Yaml::parseFile(dirname(__DIR__, 2).'/.woodpecker.yml'));

        $recreateIndex = $this->findCommandIndex($commands, 'up --force-recreate');
        $copyIndex = $this->findCommandIndex($commands, 'cp ./scripts/deploy-smoke.sh');

        $this->assertGreaterThan(
            $recreateIndex,
            $copyIndex,
            'deploy-smoke.sh must be copied into the app container after `up --force-recreate`, '
            .'not before it -- --force-recreate replaces the container, discarding anything '
            .'copied into the one it replaces.'
        );
    }

    private function findCommandIndex(array $commands, string $needle): int
    {
        foreach ($commands as $index => $command) {
            if (str_contains($command, $needle)) {
                return $index;
            }
        }

        $this->fail("Could not find a deploy command containing \"{$needle}\".");
    }

    private function extractSingleQuotedProbeScript(): string
    {
        $config = Yaml::parseFile(dirname(__DIR__, 2).'/.woodpecker.yml');
        $commands = $this->findDeployCommands($config);

        foreach ($commands as $command) {
            if (str_contains($command, "sh -lc '")) {
                $start = strpos($command, "sh -lc '") + strlen("sh -lc '");

                return substr($command, $start, strrpos($command, "'") - $start);
            }
        }

        $this->fail("Could not find the `sh -lc '...'` probe command in the deploy step.");
    }

    private function findDeployCommands(array $config): array
    {
        foreach ($config['steps'] as $step) {
            if ($step['name'] === 'deploy') {
                return $step['commands'];
            }
        }

        $this->fail('No step named "deploy" found in .woodpecker.yml.');
    }
}

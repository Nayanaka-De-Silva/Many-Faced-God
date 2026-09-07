<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Yaml\Yaml;

/**
 * Guards the deploy step against a class of bug that broke several deploys in a
 * row (issues #74, #77, #78): shell logic written inline in .woodpecker.yml as a
 * single-quoted argument to `sh -lc` is parsed twice -- once by Woodpecker's own
 * script generator, once by the outer pipeline shell -- and a stray quote
 * anywhere in it, even in a comment, corrupts everything after it.
 *
 * The deploy step now runs every non-trivial shell block from a real script file
 * inside the container (scripts/deploy-migrate.sh, scripts/deploy-smoke.sh) and
 * keeps every .woodpecker.yml command on a single line. These tests assert that
 * structure holds.
 */
class WoodpeckerDeployScriptTest extends TestCase
{
    public function test_deploy_step_has_no_inline_sh_lc(): void
    {
        foreach ($this->deployCommands() as $command) {
            $this->assertStringNotContainsString(
                'sh -lc',
                $command,
                'Deploy commands must not embed inline shell via `sh -lc` -- put the '
                .'logic in a script file under scripts/ and run it with `sh /tmp/<file>`.'
            );
        }
    }

    public function test_deploy_commands_are_each_a_single_line(): void
    {
        foreach ($this->deployCommands() as $command) {
            $this->assertStringNotContainsString(
                "\n",
                $command,
                "Every deploy command must be a single line so Woodpecker's script "
                .'generator and the pipeline shell agree on where it ends.'
            );
        }
    }

    public function test_migrate_and_smoke_scripts_run_after_the_container_recreate(): void
    {
        $commands = $this->deployCommands();

        $recreateIndex = $this->findCommandIndex($commands, 'up --force-recreate');

        foreach (['deploy-migrate.sh', 'deploy-smoke.sh'] as $script) {
            $copyIndex = $this->findCommandIndex($commands, "cp ./scripts/{$script}");
            $execIndex = $this->findCommandIndex($commands, "sh /tmp/{$script}");

            $this->assertGreaterThan(
                $recreateIndex,
                $copyIndex,
                "{$script} must be copied into the app container after `up --force-recreate` "
                .'-- --force-recreate replaces the container, discarding anything copied before.'
            );
            $this->assertGreaterThan(
                $copyIndex,
                $execIndex,
                "{$script} must be executed after it is copied in."
            );
        }
    }

    public function test_referenced_deploy_scripts_exist(): void
    {
        foreach (['deploy-migrate.sh', 'deploy-smoke.sh'] as $script) {
            $this->assertFileExists(dirname(__DIR__, 2)."/scripts/{$script}");
        }
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

    private function deployCommands(): array
    {
        $config = Yaml::parseFile(dirname(__DIR__, 2).'/.woodpecker.yml');

        foreach ($config['steps'] as $step) {
            if ($step['name'] === 'deploy') {
                return $step['commands'];
            }
        }

        $this->fail('No step named "deploy" found in .woodpecker.yml.');
    }
}

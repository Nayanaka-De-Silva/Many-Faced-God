<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Yaml\Yaml;

/**
 * Guards the production stack definition against the mismatch that broke deploy
 * #175: docker-compose.prod.yml declared the webserver with `build.context:
 * ./docker/nginx`, that directory had no Dockerfile, and the CI build-image step
 * tagged `many-faced-god-nginx:latest` from the repo root instead -- so the
 * webserver container ran php-fpm and nothing served HTTP on port 80.
 *
 * The deployed stack only ever has the compose file and .env beside it, so the
 * prod compose file must not lean on any part of the repo tree: no build
 * contexts, no bind mounts of source paths. Every locally-built image it names
 * must be one the build-image step actually builds.
 */
class DockerComposeProdTest extends TestCase
{
    private const LOCAL_IMAGE_PREFIX = 'many-faced-god-';

    public function test_prod_compose_declares_no_build_contexts(): void
    {
        foreach ($this->prodServices() as $name => $service) {
            $this->assertArrayNotHasKey(
                'build',
                $service,
                "Service \"{$name}\" declares a build context. The deploy dir has no "
                .'repo tree -- ship a prebuilt image instead and build it in CI.'
            );
        }
    }

    public function test_prod_compose_bind_mounts_no_source_paths(): void
    {
        foreach ($this->prodServices() as $name => $service) {
            foreach ($service['volumes'] ?? [] as $volume) {
                $source = is_array($volume) ? ($volume['source'] ?? '') : explode(':', $volume)[0];
                $this->assertFalse(
                    str_starts_with($source, '.'),
                    "Service \"{$name}\" bind-mounts a relative path (\"{$source}\"); it will "
                    .'not exist in the deploy dir. Bake the content into the image.'
                );
            }
        }
    }

    public function test_every_locally_built_image_is_built_by_ci(): void
    {
        $builtImages = $this->imagesBuiltByCi();

        foreach ($this->prodServices() as $name => $service) {
            $image = $service['image'] ?? '';

            if (! str_starts_with($image, self::LOCAL_IMAGE_PREFIX)) {
                continue;
            }

            $this->assertContains(
                $image,
                $builtImages,
                "Service \"{$name}\" uses locally-built image \"{$image}\", but no "
                .'build-image command in .woodpecker.yml builds that tag.'
            );
        }
    }

    public function test_nginx_image_is_built_from_its_own_context(): void
    {
        $nginxBuild = null;
        foreach ($this->buildImageCommands() as $command) {
            if (str_contains($command, 'many-faced-god-nginx:latest')) {
                $nginxBuild = $command;
            }
        }

        $this->assertNotNull($nginxBuild, 'No command builds many-faced-god-nginx:latest.');
        $this->assertStringContainsString(
            './docker/nginx',
            $nginxBuild,
            'The nginx image must build from ./docker/nginx, not the repo root '
            .'(building from "." produces another php-fpm app image -- deploy #175).'
        );
        $this->assertFileExists(dirname(__DIR__, 2).'/docker/nginx/Dockerfile');
    }

    private function prodServices(): array
    {
        $config = Yaml::parseFile(dirname(__DIR__, 2).'/docker-compose.prod.yml');

        return $config['services'] ?? [];
    }

    private function buildImageCommands(): array
    {
        $config = Yaml::parseFile(dirname(__DIR__, 2).'/.woodpecker.yml');

        foreach ($config['steps'] as $step) {
            if ($step['name'] === 'build-image') {
                return $step['commands'];
            }
        }

        $this->fail('No step named "build-image" found in .woodpecker.yml.');
    }

    private function imagesBuiltByCi(): array
    {
        $images = [];
        foreach ($this->buildImageCommands() as $command) {
            if (preg_match('/docker build\s+-t\s+(\S+)/', $command, $matches)) {
                $images[] = $matches[1];
            }
        }

        return $images;
    }
}

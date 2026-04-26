<?php

namespace Stokoe\AiGateway\Tests\Property;

use Mockery;
use PHPUnit\Framework\Attributes\Test;
use Statamic\Contracts\Assets\AssetContainer as AssetContainerContract;
use Statamic\Contracts\Assets\AssetContainerRepository;
use Stokoe\AiGateway\Tests\TestCase;
use Stokoe\AiGateway\Tools\AssetListTool;

/**
 * Feature: gateway-content-expansion, Property 3: Asset list path prefix filtering
 *
 * For any set of assets in a container and any path prefix string, the AssetListTool
 * SHALL return only assets whose path starts with the given prefix, and SHALL not
 * exclude any asset whose path does start with the prefix.
 *
 * **Validates: Requirements 2.2**
 */
class AssetListPrefixPropertyTest extends TestCase
{
    private const PATH_SEGMENTS = [
        'images', 'docs', 'uploads', 'media', 'files', 'assets',
        'photos', 'videos', 'audio', 'pdfs', 'icons', 'logos',
        'banners', 'thumbnails', 'avatars', 'backgrounds',
    ];

    private const FILENAMES = [
        'hero.jpg', 'logo.png', 'document.pdf', 'photo.webp',
        'readme.md', 'data.csv', 'report.xlsx', 'slide.pptx',
        'icon.svg', 'video.mp4', 'track.mp3', 'archive.zip',
    ];

    private const ITERATIONS = 100;

    /**
     * The current container mock, updated each iteration.
     */
    private ?AssetContainerContract $currentContainer = null;

    protected function setUp(): void
    {
        parent::setUp();

        // Bind a mock repository that delegates findByHandle to $this->currentContainer
        $repo = Mockery::mock(AssetContainerRepository::class);
        $repo->shouldReceive('findByHandle')
            ->with('test-container')
            ->andReturnUsing(fn () => $this->currentContainer);

        // Allow any other calls to pass through gracefully
        $repo->shouldReceive('findByHandle')
            ->withAnyArgs()
            ->andReturnNull();

        $this->app->instance(AssetContainerRepository::class, $repo);
    }

    /**
     * Property 3a: When a path prefix is provided, every returned asset's path
     * starts with that prefix.
     *
     * Strategy: Generate random asset paths and a random prefix across 100
     * iterations. Verify every returned asset has a path starting with the prefix.
     */
    #[Test]
    public function returned_assets_all_match_the_given_prefix(): void
    {
        for ($i = 0; $i < self::ITERATIONS; $i++) {
            $assetCount = random_int(1, 20);
            $prefixDir = self::PATH_SEGMENTS[array_rand(self::PATH_SEGMENTS)];
            $trailSlash = random_int(0, 1) ? '/' : '';
            $pathPrefix = $prefixDir . $trailSlash;

            $assetPaths = $this->generateAssetPaths($assetCount);
            $this->currentContainer = $this->buildContainerMock($assetPaths);

            $tool = new AssetListTool();
            $response = $tool->execute([
                'container' => 'test-container',
                'path' => $pathPrefix,
                'limit' => 100,
                'offset' => 0,
            ]);

            $data = json_decode($response->toJsonResponse()->getContent(), true);

            $this->assertTrue($data['ok'], "Iteration {$i}: response should be ok");

            foreach ($data['result']['assets'] as $asset) {
                $this->assertStringStartsWith(
                    $pathPrefix,
                    $asset['path'],
                    "Iteration {$i}: Asset path '{$asset['path']}' should start with prefix '{$pathPrefix}'"
                );
            }
        }
    }

    /**
     * Property 3b: When a path prefix is provided, no asset whose path starts
     * with the prefix is excluded from the results (within pagination bounds).
     *
     * Strategy: Generate assets, compute the expected matching set manually,
     * then verify the tool's total count and returned paths match.
     */
    #[Test]
    public function no_matching_assets_are_excluded(): void
    {
        for ($i = 0; $i < self::ITERATIONS; $i++) {
            $assetCount = random_int(1, 20);
            $prefixDir = self::PATH_SEGMENTS[array_rand(self::PATH_SEGMENTS)];
            $pathPrefix = $prefixDir . '/';

            $assetPaths = $this->generateAssetPaths($assetCount);

            $expectedMatches = array_values(array_filter(
                $assetPaths,
                fn (string $path) => str_starts_with($path, $pathPrefix)
            ));

            $this->currentContainer = $this->buildContainerMock($assetPaths);

            $tool = new AssetListTool();
            $response = $tool->execute([
                'container' => 'test-container',
                'path' => $pathPrefix,
                'limit' => 100,
                'offset' => 0,
            ]);

            $data = json_decode($response->toJsonResponse()->getContent(), true);

            $this->assertTrue($data['ok'], "Iteration {$i}: response should be ok");
            $this->assertSame(
                count($expectedMatches),
                $data['result']['pagination']['total'],
                "Iteration {$i}: Total count should match expected for prefix '{$pathPrefix}'"
            );

            $returnedPaths = array_column($data['result']['assets'], 'path');
            foreach ($expectedMatches as $expectedPath) {
                $this->assertContains(
                    $expectedPath,
                    $returnedPaths,
                    "Iteration {$i}: Asset '{$expectedPath}' matches prefix but was excluded"
                );
            }
        }
    }

    /**
     * Property 3c: When no path prefix is provided, all assets are returned.
     */
    #[Test]
    public function all_assets_returned_when_no_prefix(): void
    {
        for ($i = 0; $i < self::ITERATIONS; $i++) {
            $assetCount = random_int(1, 15);
            $assetPaths = $this->generateAssetPaths($assetCount);

            $this->currentContainer = $this->buildContainerMock($assetPaths);

            $tool = new AssetListTool();
            $response = $tool->execute([
                'container' => 'test-container',
                'limit' => 100,
                'offset' => 0,
            ]);

            $data = json_decode($response->toJsonResponse()->getContent(), true);

            $this->assertTrue($data['ok'], "Iteration {$i}: response should be ok");
            $this->assertSame(
                count($assetPaths),
                $data['result']['pagination']['total'],
                "Iteration {$i}: All assets should be returned when no prefix is given"
            );
        }
    }

    /**
     * @return string[]
     */
    private function generateAssetPaths(int $count): array
    {
        $paths = [];
        for ($i = 0; $i < $count; $i++) {
            $depth = random_int(0, 2);
            $segments = [];
            for ($d = 0; $d < $depth; $d++) {
                $segments[] = self::PATH_SEGMENTS[array_rand(self::PATH_SEGMENTS)];
            }
            $segments[] = self::FILENAMES[array_rand(self::FILENAMES)];
            $paths[] = implode('/', $segments);
        }

        return $paths;
    }

    private function buildContainerMock(array $assetPaths): AssetContainerContract
    {
        $now = now();

        $assets = collect($assetPaths)->map(function (string $path, int $idx) use ($now) {
            $asset = Mockery::mock(\Statamic\Assets\Asset::class);
            $asset->shouldReceive('id')->andReturn("test-container::{$path}");
            $asset->shouldReceive('path')->andReturn($path);
            $asset->shouldReceive('url')->andReturn("/assets/{$path}");
            $asset->shouldReceive('size')->andReturn(random_int(1024, 1048576));
            $asset->shouldReceive('lastModified')->andReturn($now);

            return $asset;
        });

        $container = Mockery::mock(AssetContainerContract::class);
        $container->shouldReceive('assets')->andReturn($assets);

        return $container;
    }
}

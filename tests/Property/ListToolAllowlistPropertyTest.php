<?php

namespace Stokoe\AiGateway\Tests\Property;

use Mockery;
use PHPUnit\Framework\Attributes\Test;
use Statamic\Contracts\Entries\CollectionRepository;
use Statamic\Contracts\Forms\FormRepository;
use Stokoe\AiGateway\Tests\TestCase;
use Stokoe\AiGateway\Tools\CollectionListTool;
use Stokoe\AiGateway\Tools\FormListTool;

/**
 * Feature: gateway-content-expansion, Property 5: List tools return only allowlisted resources
 *
 * For any list tool (CollectionListTool, FormListTool, TaxonomyListTool, NavigationListTool)
 * and any allowlist configuration, every resource in the response SHALL be present in the
 * corresponding allowlist, and every allowlisted resource that exists SHALL appear in the response.
 *
 * **Validates: Requirements 5.1, 9.2, 20.1, 20.5**
 */
class ListToolAllowlistPropertyTest extends TestCase
{
    private const ITERATIONS = 100;

    private const COLLECTION_HANDLES = [
        'pages', 'articles', 'blog', 'products', 'events',
        'news', 'docs', 'faq', 'team', 'portfolio',
        'services', 'testimonials', 'gallery', 'jobs', 'press',
    ];

    private const FORM_HANDLES = [
        'contact', 'newsletter', 'feedback', 'support', 'survey',
        'registration', 'application', 'inquiry', 'booking', 'quote',
        'subscribe', 'report', 'complaint', 'suggestion', 'review',
    ];

    // ---------------------------------------------------------------
    // CollectionListTool
    // ---------------------------------------------------------------

    /**
     * Property 5a (Collections): Every collection returned by CollectionListTool
     * is present in the allowed_collections allowlist.
     */
    #[Test]
    public function every_returned_collection_is_in_the_allowlist(): void
    {
        for ($i = 0; $i < self::ITERATIONS; $i++) {
            $existingCount = random_int(1, 10);
            $existingHandles = $this->randomSubset(self::COLLECTION_HANDLES, $existingCount);

            $allowlistSize = random_int(0, count(self::COLLECTION_HANDLES));
            $allowlist = $this->randomSubset(self::COLLECTION_HANDLES, $allowlistSize);

            config(['ai_gateway.allowed_collections' => $allowlist]);
            $this->bindCollectionMocks($existingHandles);

            $tool = new CollectionListTool();
            $response = $tool->execute([]);

            $data = json_decode($response->toJsonResponse()->getContent(), true);
            $this->assertTrue($data['ok'], "Iteration {$i}: response should be ok");

            foreach ($data['result']['collections'] as $collection) {
                $this->assertContains(
                    $collection['handle'],
                    $allowlist,
                    "Iteration {$i}: Returned collection '{$collection['handle']}' is not in allowlist "
                    . json_encode($allowlist)
                );
            }
        }
    }

    /**
     * Property 5b (Collections): Every allowlisted collection that exists
     * appears in the response.
     */
    #[Test]
    public function every_allowlisted_collection_that_exists_appears_in_response(): void
    {
        for ($i = 0; $i < self::ITERATIONS; $i++) {
            $existingCount = random_int(1, 10);
            $existingHandles = $this->randomSubset(self::COLLECTION_HANDLES, $existingCount);

            $allowlistSize = random_int(0, count(self::COLLECTION_HANDLES));
            $allowlist = $this->randomSubset(self::COLLECTION_HANDLES, $allowlistSize);

            $expectedHandles = array_values(array_intersect($allowlist, $existingHandles));

            config(['ai_gateway.allowed_collections' => $allowlist]);
            $this->bindCollectionMocks($existingHandles);

            $tool = new CollectionListTool();
            $response = $tool->execute([]);

            $data = json_decode($response->toJsonResponse()->getContent(), true);
            $this->assertTrue($data['ok'], "Iteration {$i}: response should be ok");

            $returnedHandles = array_column($data['result']['collections'], 'handle');

            foreach ($expectedHandles as $handle) {
                $this->assertContains(
                    $handle,
                    $returnedHandles,
                    "Iteration {$i}: Allowlisted collection '{$handle}' exists but was not returned. "
                    . "Allowlist: " . json_encode($allowlist) . ", Existing: " . json_encode($existingHandles)
                );
            }

            $this->assertCount(
                count($expectedHandles),
                $data['result']['collections'],
                "Iteration {$i}: Expected " . count($expectedHandles) . " collections but got "
                . count($data['result']['collections'])
            );
        }
    }

    // ---------------------------------------------------------------
    // FormListTool
    // ---------------------------------------------------------------

    /**
     * Property 5c (Forms): Every form returned by FormListTool
     * is present in the allowed_forms allowlist.
     */
    #[Test]
    public function every_returned_form_is_in_the_allowlist(): void
    {
        for ($i = 0; $i < self::ITERATIONS; $i++) {
            $existingCount = random_int(1, 10);
            $existingHandles = $this->randomSubset(self::FORM_HANDLES, $existingCount);

            $allowlistSize = random_int(0, count(self::FORM_HANDLES));
            $allowlist = $this->randomSubset(self::FORM_HANDLES, $allowlistSize);

            config(['ai_gateway.allowed_forms' => $allowlist]);
            $this->bindFormMocks($existingHandles);

            $tool = new FormListTool();
            $response = $tool->execute([]);

            $data = json_decode($response->toJsonResponse()->getContent(), true);
            $this->assertTrue($data['ok'], "Iteration {$i}: response should be ok");

            foreach ($data['result']['forms'] as $form) {
                $this->assertContains(
                    $form['handle'],
                    $allowlist,
                    "Iteration {$i}: Returned form '{$form['handle']}' is not in allowlist "
                    . json_encode($allowlist)
                );
            }
        }
    }

    /**
     * Property 5d (Forms): Every allowlisted form that exists
     * appears in the response.
     */
    #[Test]
    public function every_allowlisted_form_that_exists_appears_in_response(): void
    {
        for ($i = 0; $i < self::ITERATIONS; $i++) {
            $existingCount = random_int(1, 10);
            $existingHandles = $this->randomSubset(self::FORM_HANDLES, $existingCount);

            $allowlistSize = random_int(0, count(self::FORM_HANDLES));
            $allowlist = $this->randomSubset(self::FORM_HANDLES, $allowlistSize);

            $expectedHandles = array_values(array_intersect($allowlist, $existingHandles));

            config(['ai_gateway.allowed_forms' => $allowlist]);
            $this->bindFormMocks($existingHandles);

            $tool = new FormListTool();
            $response = $tool->execute([]);

            $data = json_decode($response->toJsonResponse()->getContent(), true);
            $this->assertTrue($data['ok'], "Iteration {$i}: response should be ok");

            $returnedHandles = array_column($data['result']['forms'], 'handle');

            foreach ($expectedHandles as $handle) {
                $this->assertContains(
                    $handle,
                    $returnedHandles,
                    "Iteration {$i}: Allowlisted form '{$handle}' exists but was not returned. "
                    . "Allowlist: " . json_encode($allowlist) . ", Existing: " . json_encode($existingHandles)
                );
            }

            $this->assertCount(
                count($expectedHandles),
                $data['result']['forms'],
                "Iteration {$i}: Expected " . count($expectedHandles) . " forms but got "
                . count($data['result']['forms'])
            );
        }
    }

    // ---------------------------------------------------------------
    // Helpers
    // ---------------------------------------------------------------

    /**
     * Bind mock CollectionRepository returning collections for the given handles.
     */
    private function bindCollectionMocks(array $handles): void
    {
        \Statamic\Facades\Collection::clearResolvedInstances();

        $collections = collect($handles)->map(function (string $handle) {
            $collection = Mockery::mock(\Statamic\Entries\Collection::class);
            $collection->shouldReceive('handle')->andReturn($handle);
            $collection->shouldReceive('title')->andReturn(ucfirst($handle));
            $collection->shouldReceive('route')->andReturn("/{$handle}/{slug}");

            $structure = null;
            if (random_int(0, 1)) {
                $structure = Mockery::mock();
                $structure->shouldReceive('maxDepth')->andReturn(random_int(1, 5));
            }
            $collection->shouldReceive('structure')->andReturn($structure);

            $collection->shouldReceive('taxonomies')->andReturn(collect());

            return $collection;
        });

        $repo = Mockery::mock(CollectionRepository::class);
        $repo->shouldReceive('all')->andReturn($collections);

        $this->app->instance(CollectionRepository::class, $repo);
    }

    /**
     * Bind mock FormRepository returning forms for the given handles.
     */
    private function bindFormMocks(array $handles): void
    {
        \Statamic\Facades\Form::clearResolvedInstances();

        $forms = collect($handles)->map(function (string $handle) {
            $submissions = Mockery::mock(\Illuminate\Support\Collection::class);
            $submissions->shouldReceive('count')->andReturn(random_int(0, 100));

            $form = Mockery::mock(\Statamic\Forms\Form::class);
            $form->shouldReceive('handle')->andReturn($handle);
            $form->shouldReceive('title')->andReturn(ucfirst($handle) . ' Form');
            $form->shouldReceive('submissions')->andReturn($submissions);

            return $form;
        });

        $repo = Mockery::mock(FormRepository::class);
        $repo->shouldReceive('all')->andReturn($forms);

        $this->app->instance(FormRepository::class, $repo);
    }

    /**
     * @return string[]
     */
    private function randomSubset(array $items, int $count): array
    {
        if ($count === 0 || empty($items)) {
            return [];
        }

        $count = min($count, count($items));
        $keys = array_rand($items, $count);
        if (! is_array($keys)) {
            $keys = [$keys];
        }

        return array_map(fn ($k) => $items[$k], $keys);
    }
}

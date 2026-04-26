<?php

namespace Stokoe\AiGateway\Tests\Property;

use Mockery;
use PHPUnit\Framework\Attributes\Test;
use Statamic\Contracts\Entries\EntryRepository;
use Statamic\Contracts\Entries\QueryBuilder as EntryQueryBuilder;
use Stokoe\AiGateway\Tests\TestCase;
use Stokoe\AiGateway\Tools\EntrySearchTool;

/**
 * Feature: gateway-content-expansion, Property 6: Entry search filter returns only matching entries
 *
 * For any set of entries in a collection and any filter object with field-value pairs,
 * every entry returned by the EntrySearchTool SHALL have field values matching all
 * specified filter criteria.
 *
 * **Validates: Requirements 8.1**
 */
class EntrySearchFilterPropertyTest extends TestCase
{
    private const ITERATIONS = 100;

    private const FIELD_POOL = ['author', 'category', 'color', 'region', 'priority'];

    private const VALUE_POOL = [
        'author'   => ['alice', 'bob', 'charlie', 'diana', 'eve'],
        'category' => ['news', 'tutorial', 'review', 'opinion', 'guide'],
        'color'    => ['red', 'blue', 'green', 'yellow', 'purple'],
        'region'   => ['north', 'south', 'east', 'west', 'central'],
        'priority' => ['low', 'medium', 'high', 'critical', 'urgent'],
    ];

    protected function defineEnvironment($app): void
    {
        $app['config']->set('ai_gateway.enabled', true);
        $app['config']->set('ai_gateway.token', 'test-token');
        $app['config']->set('ai_gateway.tools.entry.search', true);
        $app['config']->set('ai_gateway.allowed_collections', ['test-collection']);
    }

    /**
     * Property 6a: Every returned entry matches ALL filter criteria.
     *
     * Strategy: Generate random entries with random field values, build a mock
     * query builder that applies where() filters in-memory, pick random filter
     * criteria, and verify every returned entry matches all filter fields.
     */
    #[Test]
    public function every_returned_entry_matches_all_filter_criteria(): void
    {
        for ($i = 0; $i < self::ITERATIONS; $i++) {
            $entryCount = random_int(2, 12);
            $rawEntries = $this->generateRandomEntries($entryCount);

            $filterFieldCount = random_int(1, 3);
            $filterFields = $this->randomSubset(self::FIELD_POOL, $filterFieldCount);
            $filter = [];
            foreach ($filterFields as $field) {
                $values = self::VALUE_POOL[$field];
                $filter[$field] = $values[array_rand($values)];
            }

            $this->bindMocks($rawEntries);

            $tool = new EntrySearchTool();
            $response = $tool->execute([
                'collection' => 'test-collection',
                'filter'     => $filter,
                'limit'      => 100,
                'offset'     => 0,
            ]);

            $data = json_decode($response->toJsonResponse()->getContent(), true);
            $this->assertTrue($data['ok'], "Iteration {$i}: response should be ok");

            foreach ($data['result']['entries'] as $entry) {
                foreach ($filter as $field => $expectedValue) {
                    $this->assertSame(
                        $expectedValue,
                        $entry['data'][$field] ?? null,
                        "Iteration {$i}: Entry '{$entry['slug']}' field '{$field}' "
                        . "should be '{$expectedValue}' but got '"
                        . ($entry['data'][$field] ?? 'null') . "'. "
                        . "Filter: " . json_encode($filter)
                    );
                }
            }
        }
    }

    /**
     * Property 6b: No matching entry is excluded from results (within pagination bounds).
     *
     * Strategy: Generate entries, compute expected matches manually, then verify
     * the tool's total count and returned entries match the expected set.
     */
    #[Test]
    public function no_matching_entry_is_excluded(): void
    {
        for ($i = 0; $i < self::ITERATIONS; $i++) {
            $entryCount = random_int(2, 12);
            $rawEntries = $this->generateRandomEntries($entryCount);

            $filterFieldCount = random_int(1, 2);
            $filterFields = $this->randomSubset(self::FIELD_POOL, $filterFieldCount);
            $filter = [];
            foreach ($filterFields as $field) {
                $values = self::VALUE_POOL[$field];
                $filter[$field] = $values[array_rand($values)];
            }

            // Compute expected matches manually
            $expectedSlugs = [];
            foreach ($rawEntries as $entry) {
                $matches = true;
                foreach ($filter as $field => $expectedValue) {
                    if (($entry['data'][$field] ?? null) !== $expectedValue) {
                        $matches = false;
                        break;
                    }
                }
                if ($matches) {
                    $expectedSlugs[] = $entry['slug'];
                }
            }

            $this->bindMocks($rawEntries);

            $tool = new EntrySearchTool();
            $response = $tool->execute([
                'collection' => 'test-collection',
                'filter'     => $filter,
                'limit'      => 100,
                'offset'     => 0,
            ]);

            $data = json_decode($response->toJsonResponse()->getContent(), true);
            $this->assertTrue($data['ok'], "Iteration {$i}: response should be ok");

            $this->assertSame(
                count($expectedSlugs),
                $data['result']['pagination']['total'],
                "Iteration {$i}: Expected " . count($expectedSlugs) . " matching entries but got "
                . "{$data['result']['pagination']['total']}. "
                . "Filter: " . json_encode($filter)
            );

            $returnedSlugs = array_column($data['result']['entries'], 'slug');
            foreach ($expectedSlugs as $slug) {
                $this->assertContains(
                    $slug,
                    $returnedSlugs,
                    "Iteration {$i}: Entry '{$slug}' matches filter but was excluded. "
                    . "Filter: " . json_encode($filter)
                );
            }
        }
    }

    /**
     * Property 6c: When filter is empty, all entries are returned.
     */
    #[Test]
    public function empty_filter_returns_all_entries(): void
    {
        for ($i = 0; $i < self::ITERATIONS; $i++) {
            $entryCount = random_int(1, 10);
            $rawEntries = $this->generateRandomEntries($entryCount);

            $this->bindMocks($rawEntries);

            $tool = new EntrySearchTool();
            $response = $tool->execute([
                'collection' => 'test-collection',
                'filter'     => [],
                'limit'      => 100,
                'offset'     => 0,
            ]);

            $data = json_decode($response->toJsonResponse()->getContent(), true);
            $this->assertTrue($data['ok'], "Iteration {$i}: response should be ok");

            $this->assertSame(
                $entryCount,
                $data['result']['pagination']['total'],
                "Iteration {$i}: Empty filter should return all {$entryCount} entries"
            );
        }
    }

    /**
     * Generate random entry data arrays.
     *
     * @return array<int, array{id: string, slug: string, published: bool, data: array<string, string>}>
     */
    private function generateRandomEntries(int $count): array
    {
        $entries = [];

        for ($j = 0; $j < $count; $j++) {
            $data = ['title' => "Entry {$j}"];
            foreach (self::FIELD_POOL as $field) {
                $values = self::VALUE_POOL[$field];
                $data[$field] = $values[array_rand($values)];
            }

            $entries[] = [
                'id'        => "entry-id-{$j}",
                'slug'      => "entry-{$j}",
                'published' => (bool) random_int(0, 1),
                'data'      => $data,
            ];
        }

        return $entries;
    }

    /**
     * Bind mock Collection and Entry repositories for a single iteration.
     *
     * Creates a mock query builder that applies where() filters in-memory
     * against the provided entry data, simulating Statamic's query behaviour.
     *
     * @param array<int, array{id: string, slug: string, published: bool, data: array}> $rawEntries
     */
    private function bindMocks(array $rawEntries): void
    {
        // Clear facade caches so fresh mocks are resolved
        \Statamic\Facades\Entry::clearResolvedInstances();
        \Statamic\Facades\Collection::clearResolvedInstances();

        // Build entry mock objects
        $entryMocks = [];
        foreach ($rawEntries as $item) {
            $entry = Mockery::mock(\Statamic\Entries\Entry::class);
            $entry->shouldReceive('id')->andReturn($item['id']);
            $entry->shouldReceive('slug')->andReturn($item['slug']);
            $entry->shouldReceive('published')->andReturn($item['published']);
            $entry->shouldReceive('data')->andReturn(collect($item['data']));
            $entry->shouldReceive('get')->andReturnUsing(fn ($key) => $item['data'][$key] ?? null);
            $entry->shouldReceive('value')->andReturnUsing(fn ($key) => $item['data'][$key] ?? null);

            $entryMocks[] = ['mock' => $entry, 'raw' => $item];
        }

        // Build a mock query builder with in-memory filtering
        $allEntryMocks = $entryMocks;

        $queryBuilder = Mockery::mock(EntryQueryBuilder::class);

        // Track filters applied via where() — use an object to avoid closure reference issues
        $filterState = new \stdClass();
        $filterState->filters = [];

        $queryBuilder->shouldReceive('where')->andReturnUsing(
            function (string $field, $operatorOrValue = null, $value = null) use ($queryBuilder, $filterState) {
                if ($value === null) {
                    $filterState->filters[] = ['field' => $field, 'op' => '=', 'value' => $operatorOrValue];
                } else {
                    $filterState->filters[] = ['field' => $field, 'op' => $operatorOrValue, 'value' => $value];
                }

                return $queryBuilder;
            }
        );

        $queryBuilder->shouldReceive('offset')->andReturnSelf();
        $queryBuilder->shouldReceive('limit')->andReturnSelf();

        $applyFilters = function () use ($allEntryMocks, $filterState) {
            return collect($allEntryMocks)->filter(function ($item) use ($filterState) {
                foreach ($filterState->filters as $f) {
                    $field = $f['field'];
                    $op = $f['op'];
                    $val = $f['value'];

                    // Resolve the actual value from the entry
                    if ($field === 'collection') {
                        $actual = 'test-collection';
                    } elseif ($field === 'locale') {
                        $actual = 'default';
                    } else {
                        $actual = $item['raw']['data'][$field] ?? null;
                    }

                    if ($op === '=') {
                        if ($actual !== $val) {
                            return false;
                        }
                    } elseif ($op === 'like') {
                        // Convert SQL LIKE pattern to regex
                        $pattern = preg_quote($val, '/');
                        $pattern = str_replace(preg_quote('%', '/'), '.*', $pattern);
                        if (! preg_match("/^{$pattern}$/i", (string) $actual)) {
                            return false;
                        }
                    }
                }

                return true;
            });
        };

        $queryBuilder->shouldReceive('count')->andReturnUsing(
            fn () => $applyFilters()->count()
        );

        $queryBuilder->shouldReceive('get')->andReturnUsing(
            fn () => $applyFilters()->pluck('mock')
        );

        // Bind mock entry repository
        $repo = Mockery::mock(EntryRepository::class);
        $repo->shouldReceive('query')->andReturn($queryBuilder);
        $this->app->instance(EntryRepository::class, $repo);

        // Bind mock collection repository
        $collectionMock = Mockery::mock(\Statamic\Entries\Collection::class);
        $collectionMock->shouldReceive('handle')->andReturn('test-collection');

        $collectionRepo = Mockery::mock(\Statamic\Contracts\Entries\CollectionRepository::class);
        $collectionRepo->shouldReceive('findByHandle')
            ->with('test-collection')
            ->andReturn($collectionMock);
        $collectionRepo->shouldReceive('findByHandle')
            ->withAnyArgs()
            ->andReturnNull();

        $this->app->instance(\Statamic\Contracts\Entries\CollectionRepository::class, $collectionRepo);
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

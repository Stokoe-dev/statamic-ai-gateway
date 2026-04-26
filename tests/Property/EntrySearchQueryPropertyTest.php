<?php

namespace Stokoe\AiGateway\Tests\Property;

use Mockery;
use PHPUnit\Framework\Attributes\Test;
use Statamic\Contracts\Entries\EntryRepository;
use Statamic\Contracts\Entries\QueryBuilder as EntryQueryBuilder;
use Stokoe\AiGateway\Tests\TestCase;
use Stokoe\AiGateway\Tools\EntrySearchTool;

/**
 * Feature: gateway-content-expansion, Property 7: Entry search query returns only title-matching entries
 *
 * For any set of entries in a collection and any query string, every entry returned
 * by the EntrySearchTool SHALL have a title that contains the query string as a
 * case-insensitive substring.
 *
 * **Validates: Requirements 8.2**
 */
class EntrySearchQueryPropertyTest extends TestCase
{
    private const ITERATIONS = 100;

    private const TITLE_WORDS = [
        'Alpha', 'Bravo', 'Charlie', 'Delta', 'Echo',
        'Foxtrot', 'Golf', 'Hotel', 'India', 'Juliet',
        'Kilo', 'Lima', 'Mike', 'November', 'Oscar',
    ];

    protected function defineEnvironment($app): void
    {
        $app['config']->set('ai_gateway.enabled', true);
        $app['config']->set('ai_gateway.token', 'test-token');
        $app['config']->set('ai_gateway.tools.entry.search', true);
        $app['config']->set('ai_gateway.allowed_collections', ['test-collection']);
    }

    /**
     * Property 7a: Every returned entry has a title containing the query (case-insensitive).
     *
     * Strategy: Generate random entries with random titles, pick a random query
     * substring, and verify every returned entry's title contains the query
     * as a case-insensitive substring.
     */
    #[Test]
    public function every_returned_entry_title_contains_query_string(): void
    {
        for ($i = 0; $i < self::ITERATIONS; $i++) {
            $entryCount = random_int(3, 15);
            $rawEntries = $this->generateRandomEntries($entryCount);

            // Pick a query string: either a substring from a random entry's title
            // or a completely random short string
            $queryString = $this->generateQueryString($rawEntries);

            $this->bindMocks($rawEntries);

            $tool = new EntrySearchTool();
            $response = $tool->execute([
                'collection' => 'test-collection',
                'query'      => $queryString,
                'limit'      => 100,
                'offset'     => 0,
            ]);

            $data = json_decode($response->toJsonResponse()->getContent(), true);
            $this->assertTrue($data['ok'], "Iteration {$i}: response should be ok");

            foreach ($data['result']['entries'] as $entry) {
                $title = $entry['data']['title'] ?? '';
                $this->assertTrue(
                    str_contains(mb_strtolower($title), mb_strtolower($queryString)),
                    "Iteration {$i}: Entry '{$entry['slug']}' title '{$title}' "
                    . "should contain query '{$queryString}' (case-insensitive)."
                );
            }
        }
    }

    /**
     * Property 7b: No matching entry is excluded from results (within pagination bounds).
     *
     * Strategy: Generate entries, compute expected matches manually using
     * case-insensitive substring matching, then verify the tool returns
     * exactly those entries.
     */
    #[Test]
    public function no_matching_entry_is_excluded(): void
    {
        for ($i = 0; $i < self::ITERATIONS; $i++) {
            $entryCount = random_int(3, 15);
            $rawEntries = $this->generateRandomEntries($entryCount);

            $queryString = $this->generateQueryString($rawEntries);

            // Compute expected matches manually
            $expectedSlugs = [];
            foreach ($rawEntries as $entry) {
                $title = $entry['data']['title'] ?? '';
                if (str_contains(mb_strtolower($title), mb_strtolower($queryString))) {
                    $expectedSlugs[] = $entry['slug'];
                }
            }

            $this->bindMocks($rawEntries);

            $tool = new EntrySearchTool();
            $response = $tool->execute([
                'collection' => 'test-collection',
                'query'      => $queryString,
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
                . "Query: '{$queryString}'"
            );

            $returnedSlugs = array_column($data['result']['entries'], 'slug');
            foreach ($expectedSlugs as $slug) {
                $this->assertContains(
                    $slug,
                    $returnedSlugs,
                    "Iteration {$i}: Entry '{$slug}' matches query but was excluded. "
                    . "Query: '{$queryString}'"
                );
            }
        }
    }

    /**
     * Property 7c: When query is empty or null, all entries are returned.
     */
    #[Test]
    public function empty_query_returns_all_entries(): void
    {
        for ($i = 0; $i < self::ITERATIONS; $i++) {
            $entryCount = random_int(1, 10);
            $rawEntries = $this->generateRandomEntries($entryCount);

            $this->bindMocks($rawEntries);

            // Test with empty string
            $tool = new EntrySearchTool();
            $response = $tool->execute([
                'collection' => 'test-collection',
                'query'      => '',
                'limit'      => 100,
                'offset'     => 0,
            ]);

            $data = json_decode($response->toJsonResponse()->getContent(), true);
            $this->assertTrue($data['ok'], "Iteration {$i}: response should be ok");

            $this->assertSame(
                $entryCount,
                $data['result']['pagination']['total'],
                "Iteration {$i}: Empty query should return all {$entryCount} entries"
            );
        }
    }

    /**
     * Generate a random query string. Uses one of several strategies:
     * - Extract a substring from a random entry's title (most common, ensures some matches)
     * - Use a random word that may or may not match
     * - Use a mixed-case variant of a title substring
     */
    private function generateQueryString(array $rawEntries): string
    {
        $strategy = random_int(1, 4);

        if ($strategy <= 2 && ! empty($rawEntries)) {
            // Extract a substring from a random entry's title
            $entry = $rawEntries[array_rand($rawEntries)];
            $title = $entry['data']['title'];
            $len = mb_strlen($title);
            if ($len > 1) {
                $start = random_int(0, max(0, $len - 2));
                $subLen = random_int(1, min(6, $len - $start));
                $sub = mb_substr($title, $start, $subLen);

                // Randomly change case to test case-insensitivity
                if (random_int(0, 1)) {
                    $sub = mb_strtoupper($sub);
                } elseif (random_int(0, 1)) {
                    $sub = mb_strtolower($sub);
                }

                return $sub;
            }
        }

        if ($strategy === 3) {
            // Use a random word from the pool (may or may not match)
            return self::TITLE_WORDS[array_rand(self::TITLE_WORDS)];
        }

        // Use a short random alphabetic string (unlikely to match)
        $chars = 'abcdefghijklmnopqrstuvwxyz';
        $len = random_int(2, 4);
        $str = '';
        for ($j = 0; $j < $len; $j++) {
            $str .= $chars[random_int(0, 25)];
        }

        return $str;
    }

    /**
     * Generate random entry data arrays with randomized titles.
     *
     * @return array<int, array{id: string, slug: string, published: bool, data: array<string, string>}>
     */
    private function generateRandomEntries(int $count): array
    {
        $entries = [];

        for ($j = 0; $j < $count; $j++) {
            // Build a random title from 1-3 words
            $wordCount = random_int(1, 3);
            $titleWords = [];
            for ($w = 0; $w < $wordCount; $w++) {
                $titleWords[] = self::TITLE_WORDS[array_rand(self::TITLE_WORDS)];
            }
            $title = implode(' ', $titleWords);

            $entries[] = [
                'id'        => "entry-id-{$j}",
                'slug'      => "entry-{$j}",
                'published' => (bool) random_int(0, 1),
                'data'      => ['title' => $title],
            ];
        }

        return $entries;
    }

    /**
     * Bind mock Collection and Entry repositories for a single iteration.
     *
     * Creates a mock query builder that applies where() filters in-memory
     * against the provided entry data, simulating Statamic's query behaviour.
     * Handles the 'like' operator with case-insensitive matching for title queries.
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
                        // Convert SQL LIKE pattern to regex for case-insensitive matching
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
}

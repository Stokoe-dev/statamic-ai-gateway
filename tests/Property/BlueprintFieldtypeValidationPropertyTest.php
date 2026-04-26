<?php

namespace Stokoe\AiGateway\Tests\Property;

use Facades\Statamic\Fields\FieldtypeRepository;
use PHPUnit\Framework\Attributes\Test;
use Stokoe\AiGateway\Tests\TestCase;
use Stokoe\AiGateway\Tools\BlueprintCreateTool;
use Stokoe\AiGateway\Tools\BlueprintUpdateTool;

/**
 * Feature: gateway-content-expansion, Property 13: Blueprint fieldtype validation
 *
 * For any field definition array provided to BlueprintCreateTool or BlueprintUpdateTool,
 * if any field's type value is not in Statamic's set of recognized fieldtypes, the tool
 * SHALL reject the request with validation_failed listing the invalid fieldtypes.
 *
 * **Validates: Requirements 18.2**
 */
class BlueprintFieldtypeValidationPropertyTest extends TestCase
{
    private const ITERATIONS = 100;

    private const RESOURCE_TYPES = ['collection', 'global', 'taxonomy'];

    private const RESOURCE_TYPE_TO_CONFIG_KEY = [
        'collection' => 'ai_gateway.allowed_collections',
        'global'     => 'ai_gateway.allowed_globals',
        'taxonomy'   => 'ai_gateway.allowed_taxonomies',
    ];

    /**
     * A pool of valid Statamic fieldtypes that we'll tell the mock to recognize.
     */
    private const VALID_FIELDTYPES = [
        'text', 'textarea', 'integer', 'toggle', 'select', 'assets',
        'bard', 'markdown', 'code', 'color', 'date', 'entries',
        'grid', 'hidden', 'html', 'link', 'list', 'radio',
        'range', 'replicator', 'slug', 'structures', 'table',
        'tags', 'template', 'terms', 'time', 'users', 'video',
        'yaml', 'button_group', 'checkboxes', 'revealer', 'section',
    ];

    /**
     * A pool of invalid fieldtype names that should never be recognized.
     */
    private const INVALID_FIELDTYPES = [
        'superwidget', 'foobar', 'mega_field', 'custom_xyz', 'nonexistent',
        'imaginary', 'fake_type', 'unknown_field', 'broken', 'nope',
        'widget', 'gadget', 'thingamajig', 'doohickey', 'whatchamacallit',
        'zzzz_invalid', 'not_a_type', 'random_junk', 'bad_field', 'missing',
    ];

    private const HANDLE_POOL = [
        'pages', 'articles', 'blog', 'products', 'events', 'team',
        'settings', 'seo', 'social', 'footer', 'header', 'contact',
    ];

    /**
     * Property 13a: When ALL field types are invalid, both tools reject with validation_failed
     * listing every invalid fieldtype.
     */
    #[Test]
    public function all_invalid_fieldtypes_are_rejected(): void
    {
        for ($i = 0; $i < self::ITERATIONS; $i++) {
            $resourceType = self::RESOURCE_TYPES[array_rand(self::RESOURCE_TYPES)];
            $handle = self::HANDLE_POOL[array_rand(self::HANDLE_POOL)];

            // Allow the handle through the allowlist
            $configKey = self::RESOURCE_TYPE_TO_CONFIG_KEY[$resourceType];
            config([$configKey => [$handle]]);

            // Pick 1-3 invalid fieldtypes
            $invalidCount = random_int(1, 3);
            $invalidTypes = $this->randomSubset(self::INVALID_FIELDTYPES, $invalidCount);

            // Build field definitions using only invalid types
            $fields = [];
            foreach ($invalidTypes as $idx => $type) {
                $fields[] = [
                    'handle' => 'field_' . $idx,
                    'type'   => $type,
                ];
            }

            // Mock FieldtypeRepository to return only our valid set
            $this->mockFieldtypeRepository();

            // Pick a random tool (create or update)
            $toolClass = random_int(0, 1) === 0 ? BlueprintCreateTool::class : BlueprintUpdateTool::class;
            $tool = new $toolClass();

            $response = $tool->execute([
                'resource_type' => $resourceType,
                'handle'        => $handle,
                'fields'        => $fields,
            ]);

            $data = json_decode($response->toJsonResponse()->getContent(), true);

            $this->assertFalse(
                $data['ok'],
                "Iteration {$i}: Fields with invalid types [" . implode(', ', $invalidTypes)
                . "] should be rejected (tool: " . $tool->name() . ")"
            );

            $this->assertSame(
                'validation_failed',
                $data['error']['code'],
                "Iteration {$i}: Error code should be validation_failed"
            );

            // Verify the invalid fieldtypes are listed in the error details
            $reportedInvalid = $data['error']['details']['invalid_fieldtypes'] ?? [];
            foreach ($invalidTypes as $invalidType) {
                $this->assertContains(
                    $invalidType,
                    $reportedInvalid,
                    "Iteration {$i}: Invalid fieldtype '{$invalidType}' should be listed in error details"
                );
            }
        }
    }

    /**
     * Property 13b: When ALL field types are valid, the tools pass fieldtype validation.
     * They may fail at resource existence (resource_not_found), which proves they passed
     * fieldtype validation.
     */
    #[Test]
    public function all_valid_fieldtypes_pass_validation(): void
    {
        for ($i = 0; $i < self::ITERATIONS; $i++) {
            $resourceType = self::RESOURCE_TYPES[array_rand(self::RESOURCE_TYPES)];
            $handle = self::HANDLE_POOL[array_rand(self::HANDLE_POOL)];

            // Allow the handle through the allowlist
            $configKey = self::RESOURCE_TYPE_TO_CONFIG_KEY[$resourceType];
            config([$configKey => [$handle]]);

            // Pick 1-4 valid fieldtypes
            $validCount = random_int(1, 4);
            $validTypes = $this->randomSubset(self::VALID_FIELDTYPES, $validCount);

            // Build field definitions using only valid types
            $fields = [];
            foreach ($validTypes as $idx => $type) {
                $fields[] = [
                    'handle' => 'field_' . $idx,
                    'type'   => $type,
                ];
            }

            // Mock FieldtypeRepository to return our valid set
            $this->mockFieldtypeRepository();

            // Test BlueprintCreateTool
            $tool = new BlueprintCreateTool();
            $response = $tool->execute([
                'resource_type' => $resourceType,
                'handle'        => $handle,
                'fields'        => $fields,
            ]);

            $data = json_decode($response->toJsonResponse()->getContent(), true);

            // Should NOT be validation_failed — may be resource_not_found which is fine
            if (! $data['ok']) {
                $this->assertNotSame(
                    'validation_failed',
                    $data['error']['code'],
                    "Iteration {$i}: Fields with valid types [" . implode(', ', $validTypes)
                    . "] should not be rejected for fieldtype validation (BlueprintCreateTool)"
                );
            }
        }
    }

    /**
     * Property 13c: When a mix of valid and invalid fieldtypes are provided,
     * the tools reject with validation_failed listing ONLY the invalid ones.
     */
    #[Test]
    public function mixed_fieldtypes_rejects_listing_only_invalid(): void
    {
        for ($i = 0; $i < self::ITERATIONS; $i++) {
            $resourceType = self::RESOURCE_TYPES[array_rand(self::RESOURCE_TYPES)];
            $handle = self::HANDLE_POOL[array_rand(self::HANDLE_POOL)];

            // Allow the handle through the allowlist
            $configKey = self::RESOURCE_TYPE_TO_CONFIG_KEY[$resourceType];
            config([$configKey => [$handle]]);

            // Pick 1-2 valid and 1-2 invalid fieldtypes
            $validCount = random_int(1, 2);
            $invalidCount = random_int(1, 2);
            $validTypes = $this->randomSubset(self::VALID_FIELDTYPES, $validCount);
            $invalidTypes = $this->randomSubset(self::INVALID_FIELDTYPES, $invalidCount);

            // Build mixed field definitions
            $fields = [];
            $fieldIdx = 0;
            foreach ($validTypes as $type) {
                $fields[] = ['handle' => 'valid_' . $fieldIdx, 'type' => $type];
                $fieldIdx++;
            }
            foreach ($invalidTypes as $type) {
                $fields[] = ['handle' => 'invalid_' . $fieldIdx, 'type' => $type];
                $fieldIdx++;
            }

            // Shuffle to randomize order
            shuffle($fields);

            // Mock FieldtypeRepository
            $this->mockFieldtypeRepository();

            // Pick a random tool
            $toolClass = random_int(0, 1) === 0 ? BlueprintCreateTool::class : BlueprintUpdateTool::class;
            $tool = new $toolClass();

            $response = $tool->execute([
                'resource_type' => $resourceType,
                'handle'        => $handle,
                'fields'        => $fields,
            ]);

            $data = json_decode($response->toJsonResponse()->getContent(), true);

            $this->assertFalse(
                $data['ok'],
                "Iteration {$i}: Mixed fields should be rejected when invalid types present"
            );

            $this->assertSame(
                'validation_failed',
                $data['error']['code'],
                "Iteration {$i}: Error code should be validation_failed"
            );

            $reportedInvalid = $data['error']['details']['invalid_fieldtypes'] ?? [];

            // All invalid types should be reported
            foreach ($invalidTypes as $invalidType) {
                $this->assertContains(
                    $invalidType,
                    $reportedInvalid,
                    "Iteration {$i}: Invalid fieldtype '{$invalidType}' should be listed"
                );
            }

            // No valid types should be reported as invalid
            foreach ($validTypes as $validType) {
                $this->assertNotContains(
                    $validType,
                    $reportedInvalid,
                    "Iteration {$i}: Valid fieldtype '{$validType}' should NOT be listed as invalid"
                );
            }
        }
    }

    /**
     * Property 13d: BlueprintUpdateTool also rejects invalid fieldtypes
     * (dedicated test to ensure both tools are covered).
     */
    #[Test]
    public function update_tool_rejects_invalid_fieldtypes(): void
    {
        for ($i = 0; $i < self::ITERATIONS; $i++) {
            $resourceType = self::RESOURCE_TYPES[array_rand(self::RESOURCE_TYPES)];
            $handle = self::HANDLE_POOL[array_rand(self::HANDLE_POOL)];

            $configKey = self::RESOURCE_TYPE_TO_CONFIG_KEY[$resourceType];
            config([$configKey => [$handle]]);

            // Pick 1-3 invalid fieldtypes
            $invalidCount = random_int(1, 3);
            $invalidTypes = $this->randomSubset(self::INVALID_FIELDTYPES, $invalidCount);

            $fields = [];
            foreach ($invalidTypes as $idx => $type) {
                $fields[] = [
                    'handle' => 'field_' . $idx,
                    'type'   => $type,
                ];
            }

            $this->mockFieldtypeRepository();

            $tool = new BlueprintUpdateTool();
            $response = $tool->execute([
                'resource_type' => $resourceType,
                'handle'        => $handle,
                'fields'        => $fields,
            ]);

            $data = json_decode($response->toJsonResponse()->getContent(), true);

            $this->assertFalse(
                $data['ok'],
                "Iteration {$i}: BlueprintUpdateTool should reject invalid fieldtypes ["
                . implode(', ', $invalidTypes) . "]"
            );

            $this->assertSame(
                'validation_failed',
                $data['error']['code'],
                "Iteration {$i}: Error code should be validation_failed for BlueprintUpdateTool"
            );
        }
    }

    /**
     * Mock the FieldtypeRepository facade to return our known set of valid fieldtypes.
     */
    private function mockFieldtypeRepository(): void
    {
        FieldtypeRepository::shouldReceive('handles')
            ->andReturn(collect(self::VALID_FIELDTYPES)->mapWithKeys(fn ($type) => [$type => $type]));
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

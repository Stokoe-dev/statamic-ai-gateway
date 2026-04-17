<?php

namespace Stokoe\AiGateway\Tests\Property;

use Eris\Generators;
use Eris\TestTrait;
use Illuminate\Support\Facades\Validator;
use PHPUnit\Framework\Attributes\Test;
use Stokoe\AiGateway\Exceptions\ToolValidationException;
use Stokoe\AiGateway\Tests\TestCase;
use Stokoe\AiGateway\Tools\CacheClearTool;
use Stokoe\AiGateway\Tools\EntryCreateTool;
use Stokoe\AiGateway\Tools\EntryUpdateTool;
use Stokoe\AiGateway\Tools\EntryUpsertTool;
use Stokoe\AiGateway\Tools\GlobalUpdateTool;
use Stokoe\AiGateway\Tools\NavigationUpdateTool;
use Stokoe\AiGateway\Tools\TermUpsertTool;

/**
 * Feature: ai-gateway, Property 7: Tool argument validation
 *
 * For any tool and for any arguments object, the tool's validation passes if and only if
 * all required fields are present with correct types, no unknown keys are present, and
 * type-specific constraints are met (e.g., `data` must be an object for content mutation
 * tools, `target` must be one of the allowed enum values for cache.clear).
 *
 * **Validates: Requirements 9.4, 10.3, 11.3, 12.3, 13.3, 14.3, 15.2, 15.3, 20.3, 20.4**
 */
class ToolArgumentValidationPropertyTest extends TestCase
{
    use TestTrait;

    /**
     * Property 7a: Valid arguments for entry tools pass validation rules.
     */
    #[Test]
    public function valid_entry_tool_arguments_pass_validation(): void
    {
        $tools = [new EntryCreateTool(), new EntryUpdateTool(), new EntryUpsertTool()];

        $this->forAll(
            Generators::elements($tools),
            Generators::suchThat(
                fn ($s) => is_string($s) && strlen($s) > 0 && strlen($s) <= 30,
                Generators::string()
            ),
            Generators::suchThat(
                fn ($s) => is_string($s) && strlen($s) > 0 && strlen($s) <= 30,
                Generators::string()
            ),
        )
            ->withMaxSize(50)
            ->__invoke(function ($tool, string $collection, string $slug): void {
                $arguments = [
                    'collection' => $collection,
                    'slug'       => $slug,
                    'data'       => ['title' => 'Test'],
                ];

                $validator = Validator::make($arguments, $tool->validationRules());

                $this->assertTrue(
                    $validator->passes(),
                    "Valid arguments should pass validation for {$tool->name()}: " . json_encode($validator->errors()->toArray())
                );
            });
    }

    /**
     * Property 7b: Missing required fields for entry tools fail validation.
     */
    #[Test]
    public function missing_required_fields_fail_entry_tool_validation(): void
    {
        $tools = [new EntryCreateTool(), new EntryUpdateTool(), new EntryUpsertTool()];

        $this->forAll(
            Generators::elements($tools),
            Generators::elements(['collection', 'slug', 'data']),
        )
            ->withMaxSize(50)
            ->__invoke(function ($tool, string $missingField): void {
                $arguments = [
                    'collection' => 'pages',
                    'slug'       => 'test',
                    'data'       => ['title' => 'Test'],
                ];

                unset($arguments[$missingField]);

                $validator = Validator::make($arguments, $tool->validationRules());

                $this->assertTrue(
                    $validator->fails(),
                    "Missing '{$missingField}' should fail validation for {$tool->name()}"
                );
            });
    }

    /**
     * Property 7c: Valid arguments for global update tool pass validation.
     */
    #[Test]
    public function valid_global_tool_arguments_pass_validation(): void
    {
        $tool = new GlobalUpdateTool();

        $this->forAll(
            Generators::suchThat(
                fn ($s) => is_string($s) && strlen($s) > 0 && strlen($s) <= 30,
                Generators::string()
            ),
        )
            ->withMaxSize(50)
            ->__invoke(function (string $handle) use ($tool): void {
                $arguments = [
                    'handle' => $handle,
                    'data'   => ['phone' => '555-1234'],
                ];

                $validator = Validator::make($arguments, $tool->validationRules());

                $this->assertTrue(
                    $validator->passes(),
                    "Valid arguments should pass validation for global.update"
                );
            });
    }

    /**
     * Property 7d: Missing required fields for global update tool fail validation.
     */
    #[Test]
    public function missing_required_fields_fail_global_tool_validation(): void
    {
        $tool = new GlobalUpdateTool();

        $this->forAll(
            Generators::elements(['handle', 'data']),
        )
            ->withMaxSize(50)
            ->__invoke(function (string $missingField) use ($tool): void {
                $arguments = [
                    'handle' => 'contact',
                    'data'   => ['phone' => '555-1234'],
                ];

                unset($arguments[$missingField]);

                $validator = Validator::make($arguments, $tool->validationRules());

                $this->assertTrue(
                    $validator->fails(),
                    "Missing '{$missingField}' should fail validation for global.update"
                );
            });
    }

    /**
     * Property 7e: Valid arguments for navigation update tool pass validation.
     */
    #[Test]
    public function valid_navigation_tool_arguments_pass_validation(): void
    {
        $tool = new NavigationUpdateTool();

        $this->forAll(
            Generators::suchThat(
                fn ($s) => is_string($s) && strlen($s) > 0 && strlen($s) <= 30,
                Generators::string()
            ),
        )
            ->withMaxSize(50)
            ->__invoke(function (string $handle) use ($tool): void {
                $arguments = [
                    'handle' => $handle,
                    'tree'   => [['url' => '/', 'title' => 'Home']],
                ];

                $validator = Validator::make($arguments, $tool->validationRules());

                $this->assertTrue(
                    $validator->passes(),
                    "Valid arguments should pass validation for navigation.update"
                );
            });
    }

    /**
     * Property 7f: Missing required fields for navigation update tool fail validation.
     */
    #[Test]
    public function missing_required_fields_fail_navigation_tool_validation(): void
    {
        $tool = new NavigationUpdateTool();

        $this->forAll(
            Generators::elements(['handle', 'tree']),
        )
            ->withMaxSize(50)
            ->__invoke(function (string $missingField) use ($tool): void {
                $arguments = [
                    'handle' => 'main_nav',
                    'tree'   => [['url' => '/', 'title' => 'Home']],
                ];

                unset($arguments[$missingField]);

                $validator = Validator::make($arguments, $tool->validationRules());

                $this->assertTrue(
                    $validator->fails(),
                    "Missing '{$missingField}' should fail validation for navigation.update"
                );
            });
    }

    /**
     * Property 7g: Valid arguments for term upsert tool pass validation.
     */
    #[Test]
    public function valid_term_tool_arguments_pass_validation(): void
    {
        $tool = new TermUpsertTool();

        $this->forAll(
            Generators::suchThat(
                fn ($s) => is_string($s) && strlen($s) > 0 && strlen($s) <= 30,
                Generators::string()
            ),
            Generators::suchThat(
                fn ($s) => is_string($s) && strlen($s) > 0 && strlen($s) <= 30,
                Generators::string()
            ),
        )
            ->withMaxSize(50)
            ->__invoke(function (string $taxonomy, string $slug) use ($tool): void {
                $arguments = [
                    'taxonomy' => $taxonomy,
                    'slug'     => $slug,
                    'data'     => ['title' => 'Test Tag'],
                ];

                $validator = Validator::make($arguments, $tool->validationRules());

                $this->assertTrue(
                    $validator->passes(),
                    "Valid arguments should pass validation for term.upsert"
                );
            });
    }

    /**
     * Property 7h: Missing required fields for term upsert tool fail validation.
     */
    #[Test]
    public function missing_required_fields_fail_term_tool_validation(): void
    {
        $tool = new TermUpsertTool();

        $this->forAll(
            Generators::elements(['taxonomy', 'slug', 'data']),
        )
            ->withMaxSize(50)
            ->__invoke(function (string $missingField) use ($tool): void {
                $arguments = [
                    'taxonomy' => 'tags',
                    'slug'     => 'test',
                    'data'     => ['title' => 'Test'],
                ];

                unset($arguments[$missingField]);

                $validator = Validator::make($arguments, $tool->validationRules());

                $this->assertTrue(
                    $validator->fails(),
                    "Missing '{$missingField}' should fail validation for term.upsert"
                );
            });
    }

    /**
     * Property 7i: Cache clear tool only accepts valid target values.
     */
    #[Test]
    public function cache_clear_valid_targets_pass_validation(): void
    {
        $tool = new CacheClearTool();

        $this->forAll(
            Generators::elements(['application', 'static', 'stache', 'glide']),
        )
            ->withMaxSize(50)
            ->__invoke(function (string $target) use ($tool): void {
                $arguments = ['target' => $target];

                $validator = Validator::make($arguments, $tool->validationRules());

                $this->assertTrue(
                    $validator->passes(),
                    "Valid target '{$target}' should pass validation for cache.clear"
                );
            });
    }

    /**
     * Property 7j: Cache clear tool rejects invalid target values.
     */
    #[Test]
    public function cache_clear_invalid_targets_fail_validation(): void
    {
        $validTargets = ['application', 'static', 'stache', 'glide'];
        $tool = new CacheClearTool();

        $this->forAll(
            Generators::suchThat(
                fn ($s) => is_string($s) && strlen($s) > 0 && ! in_array($s, $validTargets, true),
                Generators::string()
            ),
        )
            ->withMaxSize(50)
            ->__invoke(function (string $target) use ($tool): void {
                $arguments = ['target' => $target];

                $validator = Validator::make($arguments, $tool->validationRules());

                $this->assertTrue(
                    $validator->fails(),
                    "Invalid target '{$target}' should fail validation for cache.clear"
                );
            });
    }

    /**
     * Property 7k: Unknown keys are rejected by all tools via execute().
     */
    #[Test]
    public function unknown_keys_are_rejected_by_all_tools(): void
    {
        $tools = [
            new EntryCreateTool(),
            new EntryUpdateTool(),
            new EntryUpsertTool(),
            new GlobalUpdateTool(),
            new NavigationUpdateTool(),
            new TermUpsertTool(),
            new CacheClearTool(),
        ];

        $this->forAll(
            Generators::elements($tools),
            Generators::suchThat(
                fn ($s) => is_string($s) && strlen($s) > 0 && strlen($s) <= 20
                    && ! in_array($s, ['collection', 'slug', 'site', 'published', 'data', 'handle', 'tree', 'taxonomy', 'target'], true),
                Generators::string()
            ),
        )
            ->withMaxSize(50)
            ->__invoke(function ($tool, string $unknownKey): void {
                // Build valid base arguments for each tool, then add the unknown key
                $arguments = $this->validArgumentsFor($tool);
                $arguments[$unknownKey] = 'unexpected_value';

                try {
                    $tool->execute($arguments);
                    $this->fail("Expected ToolValidationException for unknown key '{$unknownKey}' on {$tool->name()}");
                } catch (ToolValidationException $e) {
                    $this->assertStringContainsString('Unknown argument keys', $e->getMessage());
                }
            });
    }

    /**
     * Property 7l: Non-associative data arrays are rejected by content mutation tools.
     */
    #[Test]
    public function non_associative_data_rejected_by_content_tools(): void
    {
        $tools = [
            new EntryCreateTool(),
            new EntryUpdateTool(),
            new EntryUpsertTool(),
            new GlobalUpdateTool(),
            new TermUpsertTool(),
        ];

        $this->forAll(
            Generators::elements($tools),
        )
            ->withMaxSize(50)
            ->__invoke(function ($tool): void {
                $arguments = $this->validArgumentsFor($tool);
                // Replace data with a sequential (non-associative) array
                $arguments['data'] = ['value1', 'value2', 'value3'];

                try {
                    $tool->execute($arguments);
                    $this->fail("Expected ToolValidationException for non-associative data on {$tool->name()}");
                } catch (ToolValidationException $e) {
                    $this->assertStringContainsString('associative array', $e->getMessage());
                }
            });
    }

    /**
     * Build valid base arguments for a given tool.
     */
    private function validArgumentsFor($tool): array
    {
        return match ($tool->name()) {
            'entry.create', 'entry.update', 'entry.upsert' => [
                'collection' => 'pages',
                'slug'       => 'test',
                'data'       => ['title' => 'Test'],
            ],
            'global.update' => [
                'handle' => 'contact',
                'data'   => ['phone' => '555-1234'],
            ],
            'navigation.update' => [
                'handle' => 'main_nav',
                'tree'   => [['url' => '/', 'title' => 'Home']],
            ],
            'term.upsert' => [
                'taxonomy' => 'tags',
                'slug'     => 'test',
                'data'     => ['title' => 'Test'],
            ],
            'cache.clear' => [
                'target' => 'application',
            ],
        };
    }
}

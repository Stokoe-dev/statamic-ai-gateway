<?php

namespace Stokoe\AiGateway\Tests\Property;

use Eris\Generators;
use Eris\TestTrait;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Stokoe\AiGateway\Support\FieldFilter;

/**
 * Feature: ai-gateway, Property 6: Field filtering preserves allowed fields and removes denied fields
 *
 * For any data object (associative array) and for any deny list of field names,
 * the FieldFilter output contains every key from the original data that is not on
 * the deny list, and contains no key that is on the deny list.
 *
 * **Validates: Requirements 8.2**
 */
class FieldFilterPropertyTest extends TestCase
{
    use TestTrait;

    /**
     * Property 6: Filtering preserves allowed keys and removes denied keys.
     */
    #[Test]
    public function filter_preserves_allowed_and_removes_denied(): void
    {
        $fieldNameGen = Generators::suchThat(
            fn ($s) => is_string($s) && strlen($s) > 0 && strlen($s) <= 20,
            Generators::string()
        );

        $this->forAll(
            Generators::associative([
                'title'       => Generators::string(),
                'slug'        => Generators::string(),
                'content'     => Generators::string(),
                'author'      => Generators::string(),
                'published'   => Generators::bool(),
                'date'        => Generators::string(),
                'description' => Generators::string(),
            ]),
            Generators::subset(['title', 'slug', 'content', 'author', 'published', 'date', 'description']),
        )
            ->withMaxSize(50)
            ->__invoke(function (array $data, array $deniedFields): void {
                $filter = new FieldFilter();
                $result = $filter->filter($data, $deniedFields);

                $denySet = array_flip($deniedFields);

                // Every key NOT on the deny list must be present in the result
                foreach ($data as $key => $value) {
                    if (! isset($denySet[$key])) {
                        $this->assertArrayHasKey($key, $result, "Allowed key '{$key}' was removed");
                        $this->assertSame($value, $result[$key], "Value for allowed key '{$key}' was changed");
                    }
                }

                // No key ON the deny list should be present in the result
                foreach ($deniedFields as $denied) {
                    $this->assertArrayNotHasKey($denied, $result, "Denied key '{$denied}' was not removed");
                }
            });
    }
}

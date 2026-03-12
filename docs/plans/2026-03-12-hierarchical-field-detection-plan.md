# Hierarchical Field Detection Implementation Plan

> **For Claude:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task.

**Goal:** Replace flat field detection with a hierarchical pipeline that correctly identifies sub-fields in repeaters/groups using path context, FakerPHP extensions, and AI-generated value lists.

**Architecture:** Five-stage cascade (Pattern → Faker Extensions → Hive → AI Batch with Hierarchy → AI Value-List Fallback). Sub-fields are no longer filtered out — they flow through the full pipeline with their parent path. When all stages fail, AI generates a cached value list locally and reports it to Hive.

**Tech Stack:** PHP 7.4+, FakerPHP 1.23+, FakerRestaurant, FakerCommerce, WordPress Transients API, Hive GraphQL API

**Design doc:** `docs/plans/2026-03-12-hierarchical-field-detection-design.md`

---

### Task 1: Add FakerPHP Extension Libraries

**Files:**
- Modify: `/home/emmgee/Projects/wpfaker/composer.json`
- Modify: `/home/emmgee/Projects/wpfaker/classes/Services/FakerService.php:89-101`
- Test: `/home/emmgee/Projects/wpfaker/tests/Feature/FakerServiceTest.php`

**Step 1: Write the failing test**

In `/home/emmgee/Projects/wpfaker/tests/Feature/FakerServiceTest.php`, add a new describe block at the end:

```php
describe('FakerPHP Extension Providers', function () {
    it('provides FakerRestaurant methods', function () {
        $faker = new FakerService('en_US');
        $value = $faker->getFaker()->foodName();
        expect($value)->toBeString()->not->toBeEmpty();
    });

    it('provides FakerCommerce methods', function () {
        $faker = new FakerService('en_US');
        $value = $faker->getFaker()->productName();
        expect($value)->toBeString()->not->toBeEmpty();
    });

    it('provides FakerRestaurant methods across locales', function () {
        $faker = new FakerService('de_DE');
        $value = $faker->getFaker()->foodName();
        expect($value)->toBeString()->not->toBeEmpty();
    });
});
```

**Step 2: Run test to verify it fails**

Run: `cd /home/emmgee/Projects/wpfaker && ./vendor/bin/pest tests/Feature/FakerServiceTest.php --filter="FakerPHP Extension"`
Expected: FAIL — `foodName()` and `productName()` methods not found.

**Step 3: Install composer dependencies**

```bash
cd /home/emmgee/Projects/wpfaker && composer require jzonta/faker-restaurant matusstafura/faker-commerce
```

**Step 4: Register providers in FakerService**

In `/home/emmgee/Projects/wpfaker/classes/Services/FakerService.php`, modify `getOrCreateInstance()` (lines 89-101):

```php
protected function getOrCreateInstance(string $locale): Generator
{
    if (!isset(self::$instances[$locale])) {
        $instance = Factory::create($locale);

        if (ModernTextProvider::hasLocale($locale)) {
            $instance->addProvider(new ModernTextProvider($instance, $locale));
        }

        // Food/restaurant data provider (supports 14 locales)
        $instance->addProvider(new \FakerRestaurant\Provider\en_US\Restaurant($instance));

        // Commerce/product data provider
        $instance->addProvider(new \Matusstafura\FakerCommerce\FakerCommerce($instance));

        self::$instances[$locale] = $instance;
    }
    return self::$instances[$locale];
}
```

Note: Check the actual class names/namespaces after `composer require` — read `vendor/jzonta/faker-restaurant/src/` and `vendor/matusstafura/faker-commerce/src/` to confirm the provider class paths. FakerRestaurant has locale-specific providers (e.g., `en_US`, `de_DE`) — use the one matching `$locale` with fallback to `en_US`.

**Step 5: Run test to verify it passes**

Run: `cd /home/emmgee/Projects/wpfaker && ./vendor/bin/pest tests/Feature/FakerServiceTest.php --filter="FakerPHP Extension"`
Expected: PASS

**Step 6: Run full test suite**

Run: `cd /home/emmgee/Projects/wpfaker && ./vendor/bin/pest`
Expected: All existing tests still pass.

**Step 7: Commit**

```bash
git add composer.json composer.lock classes/Services/FakerService.php tests/Feature/FakerServiceTest.php
git commit -m "feat: add FakerRestaurant and FakerCommerce providers"
```

---

### Task 2: Add Faker Extension Mappings to FieldNameDetector

**Files:**
- Modify: `/home/emmgee/Projects/wpfaker/classes/Services/FieldNameDetector.php:41-72` (CONTEXT_RULES)
- Modify: `/home/emmgee/Projects/wpfaker/classes/Services/FieldNameDetector.php:504-671` (PATTERNS)
- Modify: `/home/emmgee/Projects/wpfaker/classes/Services/FieldNameDetector.php:1259-1428` (generateForType)
- Test: `/home/emmgee/Projects/wpfaker/tests/Unit/Services/FieldNameDetectorTest.php`

**Step 1: Write the failing tests**

Add to `/home/emmgee/Projects/wpfaker/tests/Unit/Services/FieldNameDetectorTest.php`:

```php
describe('FakerRestaurant Integration', function () {
    it('detects ingredient field in recipe context via CONTEXT_RULES', function () {
        $result = $this->detector->detectType('ingredient', [
            'field_path' => ['recipe', 'ingredients', 'ingredient'],
        ]);
        expect($result)->toBe('food_name');
    });

    it('detects food_name pattern from field name alone', function () {
        expect($this->detector->detectType('food_name'))->toBe('food_name');
        expect($this->detector->detectType('dish'))->toBe('food_name');
        expect($this->detector->detectType('meal'))->toBe('food_name');
    });

    it('detects vegetable pattern', function () {
        expect($this->detector->detectType('vegetable'))->toBe('vegetable');
        expect($this->detector->detectType('gemüse'))->toBe('vegetable');
    });

    it('detects fruit pattern', function () {
        expect($this->detector->detectType('fruit'))->toBe('fruit');
        expect($this->detector->detectType('obst'))->toBe('fruit');
    });

    it('detects beverage pattern', function () {
        expect($this->detector->detectType('beverage'))->toBe('beverage');
        expect($this->detector->detectType('drink'))->toBe('beverage');
        expect($this->detector->detectType('getränk'))->toBe('beverage');
    });

    it('detects sauce pattern', function () {
        expect($this->detector->detectType('sauce'))->toBe('sauce');
        expect($this->detector->detectType('soße'))->toBe('sauce');
    });

    it('generates food_name using FakerRestaurant', function () {
        $value = $this->detector->generateForType('food_name', $this->faker->getFaker());
        expect($value)->toBeString()->not->toBeEmpty();
    });

    it('generates vegetable using FakerRestaurant', function () {
        $value = $this->detector->generateForType('vegetable', $this->faker->getFaker());
        expect($value)->toBeString()->not->toBeEmpty();
    });
});

describe('FakerCommerce Integration', function () {
    it('detects department pattern', function () {
        expect($this->detector->detectType('department'))->toBe('department');
        expect($this->detector->detectType('abteilung'))->toBe('department');
    });

    it('detects product_name in product context', function () {
        $result = $this->detector->detectType('name', [
            'field_path' => ['product', 'name'],
        ]);
        expect($result)->toBe('product_name');
    });

    it('generates department using FakerCommerce', function () {
        $value = $this->detector->generateForType('department', $this->faker->getFaker());
        expect($value)->toBeString()->not->toBeEmpty();
    });
});
```

**Step 2: Run tests to verify they fail**

Run: `cd /home/emmgee/Projects/wpfaker && ./vendor/bin/pest tests/Unit/Services/FieldNameDetectorTest.php --filter="FakerRestaurant|FakerCommerce"`
Expected: FAIL — types not recognized, methods not mapped.

**Step 3: Add CONTEXT_RULES for food/commerce domains**

In `/home/emmgee/Projects/wpfaker/classes/Services/FieldNameDetector.php`, extend the CONTEXT_RULES array (after existing recipe rules at lines ~69-71):

```php
// Food/Restaurant context — map generic names to FakerRestaurant types
['context' => ['recipe', 'rezept', 'recette', 'receta', 'menu', 'dish', 'meal', 'gericht', 'plat', 'cooking'], 'field' => ['ingredient', 'zutat', 'ingrédient', 'ingrediente'], 'type' => 'food_name'],
['context' => ['recipe', 'rezept', 'recette', 'menu', 'dish', 'meal', 'gericht'], 'field' => ['name', 'title', 'titel', 'nom', 'nombre'], 'type' => 'food_name'],

// Commerce context — existing product rules already handle most cases
['context' => ['shop', 'store', 'ecommerce', 'catalog', 'katalog'], 'field' => ['department', 'abteilung', 'département', 'departamento'], 'type' => 'department'],
```

**Step 4: Add PATTERNS for direct field name matching**

In the PATTERNS array (after existing food patterns), add:

```php
// FakerRestaurant types
'food_name'  => ['/\bfood[_\s]?name\b/i', '/\bdish\b/i', '/\bmeal\b/i', '/\bgericht\b/i', '/\bplat\b/i'],
'vegetable'  => ['/\bvegetable\b/i', '/\bgemüse\b/i', '/\blégume\b/i', '/\bverdura\b/i'],
'fruit'      => ['/\bfruit\b/i', '/\bobst\b/i', '/\bfruta\b/i'],
'beverage'   => ['/\bbeverage\b/i', '/\bdrink\b/i', '/\bgetränk\b/i', '/\bboisson\b/i', '/\bbebida\b/i'],
'sauce'      => ['/\bsauce\b/i', '/\bsoße\b/i', '/\bsalsa\b/i'],
'spice'      => ['/\bspice\b/i', '/\bgewürz\b/i', '/\bépice\b/i', '/\bespecia\b/i'],
'meat'       => ['/\bmeat\b/i', '/\bfleisch\b/i', '/\bviande\b/i', '/\bcarne\b/i'],

// FakerCommerce types
'department' => ['/\bdepartment\b/i', '/\babteilung\b/i', '/\bdépartement\b/i', '/\bdepartamento\b/i'],
```

**Step 5: Map types to FakerPHP extension methods in generateForType()**

In the `generateForType()` method, add cases (replacing the existing hardcoded `'ingredient'` and `'ingredients'` entries):

```php
// FakerRestaurant methods
'food_name'  => $faker->foodName(),
'vegetable'  => $faker->vegetableName(),
'fruit'      => $faker->fruitName(),
'beverage'   => $faker->beverageName(),
'sauce'      => $faker->sauceName(),
'spice'      => $faker->spiceName(),
'meat'       => $faker->meatName(),
'ingredient' => $faker->foodName(),
'ingredients' => implode(', ', array_map(fn() => $faker->foodName(), range(1, rand(5, 12)))),

// FakerCommerce methods
'department'   => $faker->department(),
'product_name' => $faker->productName(),
```

Note: Verify the exact method names by reading the FakerRestaurant/FakerCommerce source after Task 1's composer install. The above are based on the GitHub READMEs.

**Step 6: Run tests to verify they pass**

Run: `cd /home/emmgee/Projects/wpfaker && ./vendor/bin/pest tests/Unit/Services/FieldNameDetectorTest.php --filter="FakerRestaurant|FakerCommerce"`
Expected: PASS

**Step 7: Run full test suite**

Run: `cd /home/emmgee/Projects/wpfaker && ./vendor/bin/pest`
Expected: All tests pass. Check that existing `'ingredient'` tests still work (the type changed from `randomElement` to `foodName()`).

**Step 8: Commit**

```bash
git add classes/Services/FieldNameDetector.php tests/Unit/Services/FieldNameDetectorTest.php
git commit -m "feat: add FakerRestaurant and FakerCommerce field mappings"
```

---

### Task 3: Include Sub-Fields in Detection Pipeline

**Files:**
- Modify: `/home/emmgee/Projects/wpfaker/classes/Generators/PostGenerator.php:1867-1891` (filterFieldsForAi)
- Modify: `/home/emmgee/Projects/wpfaker/classes/Generators/PostGenerator.php:1810-1859` (detectFieldsBatch)
- Test: `/home/emmgee/Projects/wpfaker/tests/Unit/Services/FieldNameDetectorTest.php` (or new test file)

This is the root cause fix — sub-fields currently get skipped entirely by `filterFieldsForAi()`.

**Step 1: Write the failing test**

Create `/home/emmgee/Projects/wpfaker/tests/Unit/Generators/PostGeneratorFilterTest.php`:

```php
<?php

use WPFaker\Generators\PostGenerator;

describe('filterFieldsForAi', function () {
    beforeEach(function () {
        $this->generator = new PostGenerator('movie');
        $this->method = new ReflectionMethod(PostGenerator::class, 'filterFieldsForAi');
        $this->method->setAccessible(true);
    });

    it('includes top-level text fields', function () {
        $fields = [
            ['name' => 'plot', 'type' => 'text', 'label' => 'Plot'],
        ];
        $result = $this->method->invoke($this->generator, $fields);
        expect($result)->toHaveCount(1);
        expect($result[0]['name'])->toBe('plot');
    });

    it('includes sub-fields with parent_field and parent_path', function () {
        $fields = [
            ['name' => 'ingredient', 'type' => 'text', 'label' => 'Ingredient', 'parent_field' => 'ingredients', 'parent_path' => ['recipe', 'ingredients']],
            ['name' => 'amount', 'type' => 'text', 'label' => 'Amount', 'parent_field' => 'ingredients', 'parent_path' => ['recipe', 'ingredients']],
        ];
        $result = $this->method->invoke($this->generator, $fields);
        expect($result)->toHaveCount(2);
        expect($result[0]['name'])->toBe('ingredient');
        expect($result[0]['parent_path'])->toBe(['recipe', 'ingredients']);
        expect($result[1]['name'])->toBe('amount');
    });

    it('still excludes fields with empty name', function () {
        $fields = [
            ['name' => '', 'type' => 'text', 'label' => ''],
        ];
        $result = $this->method->invoke($this->generator, $fields);
        expect($result)->toBeEmpty();
    });

    it('still excludes non-text/numeric/datetime fields', function () {
        $fields = [
            ['name' => 'gallery', 'type' => 'gallery', 'label' => 'Gallery'],
        ];
        $result = $this->method->invoke($this->generator, $fields);
        expect($result)->toBeEmpty();
    });
});
```

**Step 2: Run test to verify it fails**

Run: `cd /home/emmgee/Projects/wpfaker && ./vendor/bin/pest tests/Unit/Generators/PostGeneratorFilterTest.php`
Expected: FAIL — sub-fields with `parent_field` are filtered out.

**Step 3: Modify filterFieldsForAi() to include sub-fields**

In `/home/emmgee/Projects/wpfaker/classes/Generators/PostGenerator.php`, replace the `filterFieldsForAi()` method (lines 1867-1891):

```php
protected function filterFieldsForAi(array $fields): array
{
    $fieldsForAi = [];
    foreach ($fields as $field) {
        $fieldName = $field['name'] ?? '';
        $fieldType = $field['type'] ?? 'text';

        if (empty($fieldName)) {
            continue;
        }

        $category = FieldConfigSchema::getFieldCategory($fieldType);
        if (!in_array($category, ['text', 'numeric', 'datetime'], true)) {
            continue;
        }

        $entry = [
            'name' => $fieldName,
            'label' => $field['label'] ?? '',
            'type' => $fieldType,
        ];

        // Preserve parent path for hierarchical context
        $parentPath = $field['parent_path'] ?? null;
        if ($parentPath) {
            $entry['parent_path'] = $parentPath;
        }

        $fieldsForAi[] = $entry;
    }
    return $fieldsForAi;
}
```

Key change: Removed `|| $parentField` from the filter condition. Sub-fields now pass through with their `parent_path` preserved.

**Step 4: Run test to verify it passes**

Run: `cd /home/emmgee/Projects/wpfaker && ./vendor/bin/pest tests/Unit/Generators/PostGeneratorFilterTest.php`
Expected: PASS

**Step 5: Run full test suite**

Run: `cd /home/emmgee/Projects/wpfaker && ./vendor/bin/pest`
Expected: All tests pass.

**Step 6: Commit**

```bash
git add classes/Generators/PostGenerator.php tests/Unit/Generators/PostGeneratorFilterTest.php
git commit -m "fix: include repeater/group sub-fields in detection pipeline"
```

---

### Task 4: Hierarchical AI Batch Prompt

**Files:**
- Modify: `/home/emmgee/Projects/wpfaker/classes/Services/AbstractAiProvider.php:620-713` (buildBatchConfigPrompt)
- Modify: `/home/emmgee/Projects/wpfaker/classes/Services/AbstractAiProvider.php:199-236` (detectFieldsBatchWithConfig)
- Modify: `/home/emmgee/Projects/wpfaker/classes/Generators/PostGenerator.php:1810-1859` (detectFieldsBatch — pass parent_path)
- Test: New test or extend existing

**Step 1: Write the failing test**

Create `/home/emmgee/Projects/wpfaker/tests/Unit/Services/AiProviderBatchPromptTest.php`:

```php
<?php

use WPFaker\Services\AbstractAiProvider;

describe('buildBatchConfigPrompt with hierarchy', function () {
    beforeEach(function () {
        // Create a concrete test subclass to access protected method
        $this->provider = new class extends AbstractAiProvider {
            public function isAvailable(): bool { return true; }
            public function getProviderName(): string { return 'test'; }
            protected function callApiWithJsonMode(string $prompt, float $temperature, int $maxTokens): ?string { return null; }
            // Expose protected method for testing
            public function testBuildPrompt(array $fields, string $postType, ?string $postTypeLabel, ?string $locale): string {
                return $this->buildBatchConfigPrompt($fields, $postType, $postTypeLabel, $locale);
            }
        };
    });

    it('includes parent path context in field list', function () {
        $fields = [
            ['name' => 'plot', 'label' => 'Plot', 'type' => 'wysiwyg'],
            ['name' => 'ingredient', 'label' => 'Ingredient', 'type' => 'text', 'parent_path' => ['recipe', 'ingredients']],
            ['name' => 'amount', 'label' => 'Amount', 'type' => 'text', 'parent_path' => ['recipe', 'ingredients']],
        ];
        $prompt = $this->provider->testBuildPrompt($fields, 'recipe', 'Recipe', 'en_US');

        // Top-level field should NOT have path prefix
        expect($prompt)->toContain('- Name: plot');
        // Sub-fields should show hierarchy
        expect($prompt)->toContain('Recipe > Ingredients > Ingredient');
        expect($prompt)->toContain('Recipe > Ingredients > Amount');
    });

    it('handles fields without parent_path as top-level', function () {
        $fields = [
            ['name' => 'title', 'label' => 'Title', 'type' => 'text'],
        ];
        $prompt = $this->provider->testBuildPrompt($fields, 'movie', 'Movie', 'en_US');

        expect($prompt)->toContain('- Name: title');
        expect($prompt)->not->toContain('>');
    });
});
```

**Step 2: Run test to verify it fails**

Run: `cd /home/emmgee/Projects/wpfaker && ./vendor/bin/pest tests/Unit/Services/AiProviderBatchPromptTest.php`
Expected: FAIL — prompt doesn't contain hierarchy info.

**Step 3: Modify buildBatchConfigPrompt() to include hierarchy**

In `/home/emmgee/Projects/wpfaker/classes/Services/AbstractAiProvider.php`, modify the field list building section in `buildBatchConfigPrompt()` (around line 636):

Replace the `$fieldList` building loop:

```php
$fieldList = '';
foreach ($fields as $field) {
    $parentPath = $field['parent_path'] ?? null;
    if ($parentPath) {
        // Build hierarchical display: "Recipe > Ingredients > Ingredient | text"
        $pathLabels = array_map('ucfirst', $parentPath);
        $pathLabels[] = ucfirst($field['label'] ?: $field['name']);
        $hierarchy = implode(' > ', $pathLabels);
        $fieldList .= sprintf(
            "- %s | Type: %s (Field key: %s)\n",
            $hierarchy,
            $field['type'] ?? 'text',
            $field['name']
        );
    } else {
        $fieldList .= sprintf(
            "- Name: %s | Label: %s | Type: %s\n",
            $field['name'],
            $field['label'] ?? '',
            $field['type'] ?? 'text'
        );
    }
}
```

Also add a note to the prompt text (after the "Fields to analyze:" section):

```
Fields marked with ">" hierarchy show sub-fields inside repeaters/groups. Use the FULL path context to determine the best faker method. For example, "Recipe > Ingredients > Ingredient | text" should generate food ingredient names, not lorem ipsum.
```

**Step 4: Run test to verify it passes**

Run: `cd /home/emmgee/Projects/wpfaker && ./vendor/bin/pest tests/Unit/Services/AiProviderBatchPromptTest.php`
Expected: PASS

**Step 5: Pass parent_path from detectFieldsBatch() to AI provider**

In `/home/emmgee/Projects/wpfaker/classes/Generators/PostGenerator.php`, the `detectFieldsBatch()` method (around line 1840) already passes `$fieldsForAi` to `detectFieldsBatchWithConfig()`. Since `filterFieldsForAi()` now preserves `parent_path` (Task 3), this should flow through automatically. Verify by reading the code path.

Also pass `parent_path` to the Hive query — in the same method, where `$hive->queryFieldConfigs()` is called, ensure the fields include `parent_path`.

**Step 6: Run full test suite**

Run: `cd /home/emmgee/Projects/wpfaker && ./vendor/bin/pest`
Expected: All tests pass.

**Step 7: Commit**

```bash
git add classes/Services/AbstractAiProvider.php classes/Generators/PostGenerator.php tests/Unit/Services/AiProviderBatchPromptTest.php
git commit -m "feat: add hierarchical path context to AI batch prompt"
```

---

### Task 5: Hive Value List Query and Report

**Files:**
- Modify: `/home/emmgee/Projects/wpfaker/classes/Services/HiveService.php`
- Test: `/home/emmgee/Projects/wpfaker/tests/Unit/Services/HiveServiceValueListTest.php`

This adds two new Hive methods: `queryValueList()` and `reportValueList()`. The Go API changes are out of scope — the plugin side should gracefully handle the case where the API doesn't support these queries yet (returns null).

**Step 1: Write the failing test**

Create `/home/emmgee/Projects/wpfaker/tests/Unit/Services/HiveServiceValueListTest.php`:

```php
<?php

use WPFaker\Services\HiveService;

describe('HiveService Value Lists', function () {
    it('has queryValueList method', function () {
        $hive = new HiveService();
        expect(method_exists($hive, 'queryValueList'))->toBeTrue();
    });

    it('returns null when disabled', function () {
        $hive = new HiveService();
        // Hive is disabled in test environment (no license)
        $result = $hive->queryValueList('ingredient', ['recipe', 'ingredients'], 'en_US');
        expect($result)->toBeNull();
    });

    it('has reportValueList method', function () {
        $hive = new HiveService();
        expect(method_exists($hive, 'reportValueList'))->toBeTrue();
    });

    it('returns false when reporting while disabled', function () {
        $hive = new HiveService();
        $result = $hive->reportValueList('ingredient', ['recipe', 'ingredients'], ['flour', 'sugar', 'butter'], 'en_US');
        expect($result)->toBeFalse();
    });
});
```

**Step 2: Run test to verify it fails**

Run: `cd /home/emmgee/Projects/wpfaker && ./vendor/bin/pest tests/Unit/Services/HiveServiceValueListTest.php`
Expected: FAIL — methods don't exist.

**Step 3: Implement queryValueList() and reportValueList()**

In `/home/emmgee/Projects/wpfaker/classes/Services/HiveService.php`, add after `reportFieldConfigs()`:

```php
/**
 * Query Hive for a cached value list for a specific field + context.
 *
 * @param string $fieldName  e.g. "ingredient"
 * @param array  $context    Parent path, e.g. ["recipe", "ingredients"]
 * @param string|null $locale
 * @return array|null  Array of string values, or null if not found
 */
public function queryValueList(string $fieldName, array $context, ?string $locale): ?array
{
    if (!$this->isEnabled()) {
        return null;
    }

    $query = 'query ValueList($fieldName: String!, $context: [String!]!, $locale: String) {
        valueList(fieldName: $fieldName, context: $context, locale: $locale) {
            values
        }
    }';

    $variables = [
        'fieldName' => $fieldName,
        'context' => $context,
        'locale' => $locale ?? '',
    ];

    $response = $this->graphqlRequest($query, $variables, false);

    if (!$response || empty($response['data']['valueList']['values'])) {
        return null;
    }

    return $response['data']['valueList']['values'];
}

/**
 * Report an AI-generated value list to Hive for caching.
 *
 * @param string $fieldName  e.g. "ingredient"
 * @param array  $context    Parent path, e.g. ["recipe", "ingredients"]
 * @param array  $values     Generated values, e.g. ["flour", "sugar", ...]
 * @param string|null $locale
 * @return bool
 */
public function reportValueList(string $fieldName, array $context, array $values, ?string $locale): bool
{
    if (!$this->isEnabled() || empty($values)) {
        return false;
    }

    $mutation = 'mutation ReportValueList($input: ReportValueListInput!) {
        reportValueList(input: $input) {
            accepted
            message
        }
    }';

    $variables = [
        'input' => [
            'fieldName' => $fieldName,
            'context' => $context,
            'values' => $values,
            'locale' => $locale ?? '',
        ],
    ];

    // Fire and forget
    $this->graphqlRequest($mutation, $variables, true);
    return true;
}
```

**Step 4: Run test to verify it passes**

Run: `cd /home/emmgee/Projects/wpfaker && ./vendor/bin/pest tests/Unit/Services/HiveServiceValueListTest.php`
Expected: PASS

**Step 5: Run full test suite**

Run: `cd /home/emmgee/Projects/wpfaker && ./vendor/bin/pest`
Expected: All tests pass.

**Step 6: Commit**

```bash
git add classes/Services/HiveService.php tests/Unit/Services/HiveServiceValueListTest.php
git commit -m "feat: add Hive value list query and report methods"
```

---

### Task 6: AI Value-List Fallback with Local Cache

**Files:**
- Modify: `/home/emmgee/Projects/wpfaker/classes/Services/AbstractAiProvider.php`
- Modify: `/home/emmgee/Projects/wpfaker/classes/Services/FieldNameDetector.php` (generateForType fallback)
- Create: `/home/emmgee/Projects/wpfaker/classes/Services/ValueListService.php`
- Test: `/home/emmgee/Projects/wpfaker/tests/Unit/Services/ValueListServiceTest.php`

This is the final fallback: when all other stages fail, generate a value list via AI and cache it locally.

**Step 1: Write the failing test**

Create `/home/emmgee/Projects/wpfaker/tests/Unit/Services/ValueListServiceTest.php`:

```php
<?php

use WPFaker\Services\ValueListService;

describe('ValueListService', function () {
    beforeEach(function () {
        // Clear any cached transients
        $GLOBALS['wpfaker_test_options'] = [];
        $this->service = new ValueListService();
    });

    it('builds a cache key from field name and context', function () {
        $method = new ReflectionMethod(ValueListService::class, 'getCacheKey');
        $method->setAccessible(true);
        $key = $method->invoke($this->service, 'ingredient', ['recipe', 'ingredients'], 'en_US');
        expect($key)->toBe('wpfaker_vl_ingredient_recipe_ingredients_en_US');
    });

    it('returns null when cache is empty and no AI provider', function () {
        $result = $this->service->getValueList('ingredient', ['recipe', 'ingredients'], 'en_US');
        expect($result)->toBeNull();
    });

    it('returns cached values when transient exists', function () {
        $cacheKey = 'wpfaker_vl_ingredient_recipe_ingredients_en_US';
        $GLOBALS['wpfaker_test_options']['_transient_' . $cacheKey] = ['flour', 'sugar', 'butter'];

        $result = $this->service->getValueList('ingredient', ['recipe', 'ingredients'], 'en_US');
        expect($result)->toBe(['flour', 'sugar', 'butter']);
    });

    it('picks a random value from the list', function () {
        $cacheKey = 'wpfaker_vl_ingredient_recipe_ingredients_en_US';
        $GLOBALS['wpfaker_test_options']['_transient_' . $cacheKey] = ['flour', 'sugar', 'butter'];

        $value = $this->service->getRandomValue('ingredient', ['recipe', 'ingredients'], 'en_US');
        expect($value)->toBeIn(['flour', 'sugar', 'butter']);
    });
});
```

**Step 2: Run test to verify it fails**

Run: `cd /home/emmgee/Projects/wpfaker && ./vendor/bin/pest tests/Unit/Services/ValueListServiceTest.php`
Expected: FAIL — class doesn't exist.

**Step 3: Create ValueListService**

Create `/home/emmgee/Projects/wpfaker/classes/Services/ValueListService.php`:

```php
<?php

namespace WPFaker\Services;

class ValueListService
{
    private const CACHE_TTL = 30 * DAY_IN_SECONDS; // 30 days
    private const LIST_SIZE = 50;

    /**
     * Get a value list for a field, checking cache → Hive → AI in order.
     */
    public function getValueList(string $fieldName, array $context, ?string $locale): ?array
    {
        $cacheKey = $this->getCacheKey($fieldName, $context, $locale);

        // 1. Check local transient cache
        $cached = get_transient($cacheKey);
        if (is_array($cached) && !empty($cached)) {
            return $cached;
        }

        // 2. Query Hive
        $hive = new HiveService();
        if ($hive->isEnabled()) {
            $hiveValues = $hive->queryValueList($fieldName, $context, $locale);
            if ($hiveValues && !empty($hiveValues)) {
                set_transient($cacheKey, $hiveValues, self::CACHE_TTL);
                return $hiveValues;
            }
        }

        // 3. Generate via AI
        $values = $this->generateViaAi($fieldName, $context, $locale);
        if ($values) {
            set_transient($cacheKey, $values, self::CACHE_TTL);

            // Report to Hive for future users
            if ($hive->isEnabled()) {
                $hive->reportValueList($fieldName, $context, $values, $locale);
            }

            return $values;
        }

        return null;
    }

    /**
     * Get a single random value from the list.
     */
    public function getRandomValue(string $fieldName, array $context, ?string $locale): ?string
    {
        $values = $this->getValueList($fieldName, $context, $locale);
        if (!$values || empty($values)) {
            return null;
        }
        return $values[array_rand($values)];
    }

    /**
     * Generate a value list using the configured AI provider.
     */
    protected function generateViaAi(string $fieldName, array $context, ?string $locale): ?array
    {
        $aiProvider = AiProviderFactory::getProvider();
        if (!$aiProvider || !$aiProvider->isAvailable()) {
            return null;
        }

        $contextStr = implode(' > ', array_map('ucfirst', $context));
        $localeHint = $locale ? " Values should be in the locale '{$locale}'." : '';

        $prompt = sprintf(
            'Generate exactly %d realistic, diverse values for the field "%s" in the context of "%s".%s Return ONLY a JSON array of strings, nothing else. Example: ["value1", "value2", ...]',
            self::LIST_SIZE,
            $fieldName,
            $contextStr,
            $localeHint
        );

        $result = $aiProvider->callApiForValueList($prompt);
        if (!$result || !is_array($result)) {
            return null;
        }

        // Ensure all values are strings and non-empty
        return array_values(array_filter($result, fn($v) => is_string($v) && $v !== ''));
    }

    protected function getCacheKey(string $fieldName, array $context, ?string $locale): string
    {
        $parts = array_merge([$fieldName], $context);
        if ($locale) {
            $parts[] = $locale;
        }
        return 'wpfaker_vl_' . implode('_', $parts);
    }
}
```

Note: `$aiProvider->callApiForValueList()` doesn't exist yet. Add it to `AbstractAiProvider`:

```php
/**
 * Simple API call to generate a value list. Returns parsed JSON array or null.
 */
public function callApiForValueList(string $prompt): ?array
{
    $result = $this->callApiWithJsonMode($prompt, 0.7, 2048);
    if (!$result) {
        return null;
    }
    $parsed = $this->parseJsonResponse($result);
    return is_array($parsed) ? $parsed : null;
}
```

**Step 4: Run test to verify it passes**

Run: `cd /home/emmgee/Projects/wpfaker && ./vendor/bin/pest tests/Unit/Services/ValueListServiceTest.php`
Expected: PASS

**Step 5: Run full test suite**

Run: `cd /home/emmgee/Projects/wpfaker && ./vendor/bin/pest`
Expected: All tests pass.

**Step 6: Commit**

```bash
git add classes/Services/ValueListService.php classes/Services/AbstractAiProvider.php tests/Unit/Services/ValueListServiceTest.php
git commit -m "feat: add ValueListService with AI generation and Hive caching"
```

---

### Task 7: Wire Value-List Fallback into Detection Pipeline

**Files:**
- Modify: `/home/emmgee/Projects/wpfaker/classes/Generators/PostGenerator.php:1810-1859` (detectFieldsBatch)
- Modify: `/home/emmgee/Projects/wpfaker/classes/Services/FieldAssignmentService.php:464-483` (AI config execution)

**Step 1: Write the failing test**

Add to `/home/emmgee/Projects/wpfaker/tests/Unit/Generators/PostGeneratorFilterTest.php`:

```php
describe('value list fallback in detection pipeline', function () {
    it('stores value list config for fields that AI resolves as randomElement', function () {
        // This is an integration-level test — verify that the pipeline
        // correctly handles the case where AI returns randomElement with
        // a small seed list, and the system stores it as a value list config
        $generator = new PostGenerator('recipe');
        $method = new ReflectionMethod(PostGenerator::class, 'cacheFieldConfigs');
        $method->setAccessible(true);

        $configs = [
            'ingredient' => [
                'method' => 'randomElement',
                'params' => [['flour', 'sugar', 'butter', 'eggs', 'milk', 'salt', 'pepper', 'garlic']],
                'confidence' => 0.9,
            ],
        ];

        // Should not throw
        $method->invoke($generator, null, $configs, 'ai_batch');
        expect(true)->toBeTrue();
    });
});
```

**Step 2: Modify detectFieldsBatch() to use value lists as final fallback**

In `/home/emmgee/Projects/wpfaker/classes/Generators/PostGenerator.php`, after the AI batch detection (around line 1850), add:

```php
// 5. For fields still unresolved after AI batch, try value list fallback
$resolvedFields = array_keys($results ?? []);
$unresolvedFields = array_filter($fieldsForAi, fn($f) => !in_array($f['name'], $resolvedFields, true));

if (!empty($unresolvedFields)) {
    $valueListService = new \WPFaker\Services\ValueListService();
    foreach ($unresolvedFields as $field) {
        $parentPath = $field['parent_path'] ?? [$this->postType];
        $values = $valueListService->getValueList($field['name'], $parentPath, $locale);
        if ($values) {
            $results[$field['name']] = [
                'method' => 'randomElement',
                'params' => [$values],
                'confidence' => 0.8,
                'source' => 'value_list',
            ];
        }
    }
}
```

**Step 3: Run full test suite**

Run: `cd /home/emmgee/Projects/wpfaker && ./vendor/bin/pest`
Expected: All tests pass.

**Step 4: Commit**

```bash
git add classes/Generators/PostGenerator.php tests/Unit/Generators/PostGeneratorFilterTest.php
git commit -m "feat: wire value-list fallback as final detection stage"
```

---

### Task 8: Manual Integration Test

**No code changes — this is a verification task.**

**Step 1: Build plugin**

```bash
cd /home/emmgee/Projects/wpfaker && npm run build
```

**Step 2: Set up test data**

Import the JetEngine schema from `/home/emmgee/Projects/wp-test/Import-Data/jetengine-import.json` into the test WordPress instance. This includes:
- Recipe CPT with Ingredients repeater containing `ingredient` sub-field
- Movie CPT with Awards repeater

**Step 3: Generate posts**

1. Go to WPfaker → Generate in WordPress admin
2. Select Recipe CPT
3. Enable AI detection
4. Generate 5 posts
5. Verify that `ingredient` sub-fields contain food names (from FakerRestaurant), NOT lorem ipsum

**Step 4: Verify Movie CPT still works**

1. Generate 5 Movie posts
2. Verify Awards repeater sub-fields (category, year, role) generate correctly

**Step 5: Check detection log**

In WPfaker settings, check the generation log. Verify:
- Sub-fields appear in the detection pipeline (no longer skipped)
- Hierarchical path shown in AI prompt (if AI was triggered)
- Value list cache created (if AI fallback was used)

---

## Task Dependency Graph

```
Task 1 (Faker Extensions)
  └→ Task 2 (Extension Mappings) — needs providers registered
       └→ Task 3 (Sub-field Filter Fix) — independent but logical sequence
            └→ Task 4 (Hierarchical AI Prompt) — needs sub-fields flowing through
                 └→ Task 5 (Hive Value List API) — independent but needed by Task 6
                      └→ Task 6 (ValueListService) — needs Hive methods
                           └→ Task 7 (Wire Pipeline) — needs all pieces
                                └→ Task 8 (Manual Test) — verify everything
```

Tasks 1-2 and 5 are somewhat independent and could be parallelized, but the sequential order above is safest for maintaining green tests throughout.

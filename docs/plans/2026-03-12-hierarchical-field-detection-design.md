# Hierarchical Field Detection Design

**Date:** 2026-03-12
**Status:** Approved

## Problem

WPfaker's AI field detection completely skips sub-fields inside repeaters/groups. The `filterFieldsForAi()` method (line 1875) excludes all fields with a `parent_field`, so fields like "Ingredient" inside an "Ingredients" repeater in a Recipe CPT produce Lorem ipsum — even with AI and Hive enabled.

Additionally, the AI prompt is flat (no hierarchy info), hardcoded seed values are too limited and English-only, and there's no mechanism to dynamically create value lists when no faker function matches.

## Decisions

| Decision | Choice | Rationale |
|----------|--------|-----------|
| AI analysis approach | **Batch with hierarchy** — single API call, fields include full path context | Good context, low cost, one call |
| FakerPHP extensions | **Composer dependencies** — `jzonta/faker-restaurant`, `matusstafura/faker-commerce` | Clean, maintainable, ~17 new methods |
| Value list creation | **Hybrid** — local AI generation + Hive cache + report | Matches existing query/report pattern, Hive fills naturally via weekly reporting |
| Local fallback | **AI-generated cached lists** — ~50 values, transient cached 30 days | Cheap (~100 tokens once), fast after cache, works with existing AI infrastructure |

## Detection Pipeline (Cascade)

Each field with its full path (e.g., `['recipe', 'ingredients', 'ingredient']`) goes through stages until a faker method is found:

```
1. Pattern-Matching (FieldNameDetector with path context)
   → CONTEXT_RULES + detectWithFieldPath() — exists, now receives sub-fields

2. Faker Extensions (FakerRestaurant, FakerCommerce)
   → New mappings: ingredient→foodName(), product→productName(), etc.

3. Hive Lookup
   → queryFieldConfigs() — exists, now receives sub-fields with path

4. AI Batch with Hierarchy
   → All unresolved fields in one call, with path context

5. AI Value-List Fallback
   → Generate ~50 values once, cache locally, report to Hive
```

## Core Changes

### 1. Include Sub-Fields in Pipeline

- Remove `parent_field` filter in `filterFieldsForAi()` (line 1875)
- Sub-fields receive their full `parent_path`
- All 5 cascade stages receive path information

### 2. FakerPHP Extensions

- `composer require jzonta/faker-restaurant matusstafura/faker-commerce`
- Register in `FakerService::getOrCreateInstance()` via `addProvider()`
- New mappings in FieldNameDetector: ~17 methods (foodName, vegetable, department, productName, etc.)

### 3. AI Prompt with Hierarchy (Stage 4)

Rebuild `buildBatchConfigPrompt()` — instead of flat field list, hierarchical representation:

```
Recipe > Ingredients (Repeater) > Ingredient | text
Recipe > Ingredients (Repeater) > Amount | number
Movie > Awards (Repeater) > Category | text
```

AI sees full context and can correctly identify "Ingredient in Recipe context" as `foodName()`.

### 4. AI Value-List Fallback (Stage 5)

- New service/method: After stage 4, if still no faker match → AI generates ~50 context-specific values once
- Cache as WordPress transient (30 days)
- Report to Hive (existing pattern) → next user gets it directly
- Without AI: Generic fallback as today

### 5. Hive API Extension

- `queryFieldConfigs()` gets optional `context` field (parent path)
- New query type: `queryValueList(fieldName, context)` → returns cached value lists
- New report type: `reportValueList(fieldName, context, values)` → stores AI-generated lists

## Affected Files

| File | Change |
|------|--------|
| `composer.json` | Add faker-restaurant + faker-commerce |
| `classes/Services/FakerService.php` | Register new providers |
| `classes/Services/FieldNameDetector.php` | New faker extension mappings, extended CONTEXT_RULES |
| `classes/Services/FieldAssignmentService.php` | Remove sub-field filter, pass paths through pipeline |
| `classes/Services/AbstractAiProvider.php` | Hierarchical batch prompt, value-list generation |
| `classes/Services/HiveService.php` | queryValueList/reportValueList, context parameter |
| `classes/Generators/PostGenerator.php` | Consume value lists from new fallback |

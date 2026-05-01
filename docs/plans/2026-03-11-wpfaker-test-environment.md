# WPfaker Test Environment — Implementation Plan

> **For Claude:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task.

**Goal:** Create recipe-themed import JSONs for 5 plugins (ACF, Meta Box, JetEngine, ACPT, CPTUI), then set up an InstaWP template with all 7 plugins installed, both schemas (movies + recipes) imported, plugins deactivated, and WPfaker branding.

**Architecture:** Each plugin gets its own recipe-schema import file(s) following the exact same format as the existing movie-schema files. The InstaWP template is set up manually via the dashboard after all files are created and validated.

**Tech Stack:** JSON import files, WordPress mu-plugin (PHP), InstaWP dashboard

---

## Recipe Schema Reference

### CPTs
| CPT | Slug | Icon | Supports |
|-----|------|------|----------|
| Recipe | `recipe` | `dashicons-carrot` | title, thumbnail, custom-fields |
| Chef | `chef` | `dashicons-admin-users` | title, thumbnail, custom-fields |
| Cookbook | `cookbook` | `dashicons-book` | title, thumbnail, custom-fields |

### Taxonomies
| Taxonomy | Slug | Assigned to | Hierarchical |
|----------|------|-------------|-------------|
| Cuisine | `cuisine` | recipe | false |
| Course | `course` | recipe | false |
| Diet | `diet` | recipe | false |

### Relations (many-to-many)
- Recipe ↔ Chef
- Recipe ↔ Cookbook

### Fields per CPT

**Recipe:**
| Field | Name | Type | Required | Notes |
|-------|------|------|----------|-------|
| Image | `image` | image | yes | Recipe photo |
| Description | `description` | wysiwyg | no | |
| Prep Time | `prep_time` | number | no | minutes |
| Cook Time | `cook_time` | number | no | minutes |
| Servings | `servings` | number | no | |
| Difficulty | `difficulty` | select | no | Easy/Medium/Hard |
| Ingredients | `ingredients` | repeater | no | see below |
| Instructions | `instructions` | repeater | no | see below |
| Nutrition | `nutrition` | repeater | no | see below |

**Chef:**
| Field | Name | Type | Required |
|-------|------|------|----------|
| First Name | `first_name` | text | yes |
| Last Name | `last_name` | text | yes |
| Bio | `bio` | wysiwyg | no |
| Photo | `photo` | image | no |
| Website | `website` | url/text | no |

**Cookbook:**
| Field | Name | Type | Required |
|-------|------|------|----------|
| Cover Image | `cover_image` | image | no |
| Publisher | `publisher` | text | no |
| Year | `year` | number | no |
| ISBN | `isbn` | text | no |
| Description | `description` | textarea | no |

### Repeater Subfields

**Ingredients:**
| Subfield | Name | Type |
|----------|------|------|
| Ingredient | `ingredient` | text |
| Amount | `amount` | text |
| Unit | `unit` | select (g, kg, ml, l, tsp, tbsp, cup, piece, pinch) |

**Instructions:**
| Subfield | Name | Type |
|----------|------|------|
| Step | `step` | number |
| Description | `instruction_text` | textarea |
| Image | `step_image` | image |

**Nutrition:**
| Subfield | Name | Type |
|----------|------|------|
| Nutrient | `nutrient` | text |
| Value | `nutrient_value` | text |

---

## Task 1: Create ACF Recipe Import JSON

**Files:**
- Create: `wp-test/Import-Data/Recipes/acf-recipes.json`

**Step 1: Create the ACF JSON file**

Follow exact ACF export format from `acf-export-2026-03-07.json`. The file is a top-level array containing:
- 3 field groups (Recipe, Chef, Cookbook) — each with `key`, `title`, `fields[]`, `location[]`
- 3 taxonomy definitions (Cuisine, Course, Diet) — each with `key` starting with `taxonomy_`, `taxonomy` slug, `object_type`, `labels`, settings
- 3 CPT definitions (Recipe, Chef, Cookbook) — each with `key` starting with `post_type_`, `post_type` slug, `labels`, settings

Key format rules:
- Field keys: `field_` + 13 hex chars (e.g., `field_70a0000000001`)
- Group keys: `group_` + 13 hex chars
- Taxonomy keys: `taxonomy_` + 13 hex chars
- Post type keys: `post_type_` + 13 hex chars
- Use `"required": 1` (integer) for required fields
- Repeater: `"type": "repeater"` with `"sub_fields": [...]`, each sub_field has `"parent_repeater": "<repeater_field_key>"`
- Relationship: `"type": "relationship"` with `"post_type": [...]`, `"bidirectional": 1`, `"bidirectional_target": [self-key]`
- Taxonomy field: `"type": "taxonomy"` with `"taxonomy": "slug"`, `"add_term": 1`, `"save_terms": 1`
- Select: `"type": "select"` with `"choices": {"key": "label"}`
- Image: `"type": "image"` with `"return_format": "url"`, `"preview_size": "medium"`
- Date: `"type": "date_picker"` with `"display_format": "d\/m\/Y"`, `"return_format": "F j, Y"`
- URL: `"type": "url"` (ACF has native url type)
- Number: `"type": "number"` with `"prepend"` and `"append"` for units
- Wrapper widths: `"wrapper": {"width": "33", "class": "", "id": ""}`

**Step 2: Validate JSON**

Run: `python3 -c "import json; d=json.load(open('wp-test/Import-Data/Recipes/acf-recipes.json')); print(f'{len(d)} items')"`
Expected: `9 items` (3 field groups + 3 taxonomies + 3 CPTs)

**Step 3: Commit**

```bash
git add wp-test/Import-Data/Recipes/acf-recipes.json
git commit -m "feat: add ACF recipe schema import JSON"
```

---

## Task 2: Create CPTUI Recipe Import JSONs

**Files:**
- Create: `wp-test/Import-Data/Recipes/cptui-recipe-post-types.json`
- Create: `wp-test/Import-Data/Recipes/cptui-recipe-taxonomies.json`

**Step 1: Create CPT JSON**

Follow exact format from `cptui-post-types.json`. Object with slug keys, NOT array. All boolean-like values are **strings** (`"true"`, `"false"`).

Structure per CPT:
```json
{
  "recipe": {
    "name": "recipe",
    "label": "Recipes",
    "singular_label": "Recipe",
    "public": "true",
    "show_in_rest": "true",
    "supports": ["title", "thumbnail", "custom-fields"],
    "taxonomies": ["cuisine", "course", "diet"],
    "labels": { /* 25+ keys */ },
    "menu_icon": "dashicons-carrot"
  }
}
```

Note: CPTUI does NOT define fields or relationships — only CPT registration.

**Step 2: Create Taxonomy JSON**

Follow exact format from `cptui-taxonomies.json`. Object with slug keys.

Structure per taxonomy:
```json
{
  "cuisine": {
    "name": "cuisine",
    "label": "Cuisines",
    "singular_label": "Cuisine",
    "public": "true",
    "hierarchical": "false",
    "object_types": ["recipe"],
    "labels": { /* 18+ keys */ }
  }
}
```

**Step 3: Validate both JSONs**

Run: `python3 -c "import json; [json.load(open(f)) for f in ['wp-test/Import-Data/Recipes/cptui-recipe-post-types.json', 'wp-test/Import-Data/Recipes/cptui-recipe-taxonomies.json']]; print('OK')"`

**Step 4: Commit**

```bash
git add wp-test/Import-Data/Recipes/cptui-recipe-*.json
git commit -m "feat: add CPTUI recipe schema import JSONs"
```

---

## Task 3: Create Meta Box Recipe Import JSONs

**Files:**
- Create: `wp-test/Import-Data/Recipes/metabox-recipe-fields.json`
- Create: `wp-test/Import-Data/Recipes/metabox-recipe-cpts.json`
- Create: `wp-test/Import-Data/Recipes/metabox-recipe-taxonomies.json`
- Create: `wp-test/Import-Data/Recipes/metabox-recipe-relationships.json`

**Step 1: Create Fields JSON**

Follow exact format from `metabox-fields.json`. Array of field group objects with `$schema`.

Key format rules:
- `"$schema": "https://schemas.metabox.io/field-group.json"`
- `"required": true` (boolean)
- Repeater = `"type": "group"` with `"clone": true`, `"sort_clone": true`, `"collapsible": true`, `"group_title": "template"`, `"fields": [...]`
- Taxonomy = `"type": "taxonomy_advanced"` with `"taxonomy": ["slug"]`, `"field_type": "select_advanced"`, `"add_new": true`
- Image = `"type": "single_image"`
- Map = `"type": "osm"`
- Select = `"type": "select"` with `"options": {"key": "label"}`
- Column widths: `"columns": 4` (12-grid system, 4=33%)
- URL = `"type": "url"`

**Step 2: Create CPTs JSON**

Follow format from `metabox-cpts.json`. Array of post type posts with `"post_type": "mb-post-type"`.

**Step 3: Create Taxonomies JSON**

Follow format from `metabox-taxonomies.json`. Array with `"post_type": "mb-taxonomy"`.

**Step 4: Create Relationships JSON**

Follow format from `metabox-relationships.json`. Array with `"$schema": "https://schemas.metabox.io/relationships.json"`.

Two relationships:
- `recipes-to-chefs` (recipe ↔ chef)
- `recipes-to-cookbooks` (recipe ↔ cookbook)

**Step 5: Validate all JSONs**

Run: `python3 -c "import json, glob; [json.load(open(f)) for f in glob.glob('wp-test/Import-Data/Recipes/metabox-recipe-*.json')]; print('OK')"`

**Step 6: Commit**

```bash
git add wp-test/Import-Data/Recipes/metabox-recipe-*.json
git commit -m "feat: add Meta Box recipe schema import JSONs"
```

---

## Task 4: Create JetEngine Recipe Import JSON

**Files:**
- Create: `wp-test/Import-Data/Recipes/jetengine-recipes.json`

**Step 1: Create the JetEngine JSON file**

Follow exact format from `jetengine-import.json`. Single object with 4 top-level keys: `post_types`, `taxonomies`, `meta_boxes`, `relations`.

**CRITICAL:** `meta_fields` MUST be at the **top level** of each meta_box object, NOT nested inside `args`. This was the bug that crashed the movie import.

Key format rules:
- Post types: `meta_fields` on top-level (alongside `args`, NOT inside it). `args` has `rewrite_slug` alongside `rewrite.slug`
- Taxonomies: same structure, `meta_fields: []` on top-level
- Meta boxes: `meta_fields` on **top-level** (NOT in args!), `args` only has `name`, `object_type`, `allowed_post_type`, `allowed_tax`, `allowed_columns_position`
- Relations: `status: "relation"`, `args` has `type`, `parent_object: "posts::slug"`, `child_object`, etc.
- Repeater: `"type": "repeater"` with `"repeater-fields": [...]`
- Select: `"type": "select"` with `"options": [{"key": "k", "value": "v"}]`
- Taxonomy select: `"type": "select"` with `"options_from": "terms"`, `"options_tax": "slug"`
- Media: `"type": "media"` with `"value_format": "both"`
- Date: `"type": "date"` with `"is_timestamp": false`
- Required: `"is_required": true`
- Width: `"width": "33%"`

**Step 2: Validate JSON**

Run: `python3 -c "import json; d=json.load(open('wp-test/Import-Data/Recipes/jetengine-recipes.json')); print(f'CPTs: {len(d[\"post_types\"])}, Tax: {len(d[\"taxonomies\"])}, MB: {len(d[\"meta_boxes\"])}, Rel: {len(d[\"relations\"])}')"`
Expected: `CPTs: 3, Tax: 3, MB: 3, Rel: 2`

**Step 3: Commit**

```bash
git add wp-test/Import-Data/Recipes/jetengine-recipes.json
git commit -m "feat: add JetEngine recipe schema import JSON"
```

---

## Task 5: Create ACPT Recipe Import File

**Files:**
- Create: `wp-test/Import-Data/Recipes/acpt-recipes.acpt`

**Step 1: Create the ACPT file**

Follow exact format from `~/Downloads/acpt-import.acpt`. JSON file with `.acpt` extension. Structure:

```json
{
  "customPostTypes": [...],
  "taxonomies": [...],
  "metaGroups": [...]
}
```

Key format rules:
- UUIDs for all `id` fields (use `550e8400-e29b-41d4-a716-5` prefix + sequential hex for recipe items)
- CPT: `name`, `singular`, `plural`, `icon`, `native: false`, `supports`, `labels`, `settings`, `taxonomies: []`
- Taxonomy: `slug`, `singular`, `plural`, `native: false`, `labels`, `settings`, `customPostTypes: ["recipe"]`
- Meta groups: `name`, `label`, `display: "standard"`, `context: "normal"`, `priority: "high"`, `belongs: [...]`, `boxes: [...]`
- Each box has `fields: [...]`
- Field types: `Text`, `Number`, `Date`, `Image`, `Editor`, `Textarea`, `Select`, `Repeater`, `PostObjectMulti`, `Url`
- Repeater: `"type": "Repeater"` with `"children": [...]`
- Relations: `"type": "PostObjectMulti"` with `"relations": [{"post_type": "slug"}]`
- Select: `"options": [{"id": "uuid", "label": "L", "value": "V", "sort": N, "isDefault": false}]`
- Every field needs: `showInArchive`, `isRequired`, `quickEdit`, `filterableInAdmin`, `defaultValue`, `description`, `sort`, `options: []`, `children: []`, `blocks: []`, `advancedOptions: []`, `validationRules: []`, `visibilityConditions: []`, `relations: []`

**Step 2: Validate JSON**

Run: `python3 -c "import json; d=json.load(open('wp-test/Import-Data/Recipes/acpt-recipes.acpt')); print(f'CPTs: {len(d[\"customPostTypes\"])}, Tax: {len(d[\"taxonomies\"])}, Groups: {len(d[\"metaGroups\"])}')"`
Expected: `CPTs: 3, Tax: 3, Groups: 3`

**Step 3: Commit**

```bash
git add wp-test/Import-Data/Recipes/acpt-recipes.acpt
git commit -m "feat: add ACPT recipe schema import file"
```

---

## Task 6: Create ACPT Movie Import File

**Files:**
- Create: `wp-test/Import-Data/acpt-import.acpt` (copy from `~/Downloads/acpt-import.acpt`)

The existing file in `~/Downloads/` already has the movie schema. Copy it to the Import-Data directory alongside the other movie-schema files.

**Step 1: Copy file**

```bash
cp ~/Downloads/acpt-import.acpt wp-test/Import-Data/acpt-import.acpt
```

**Step 2: Validate**

Run: `python3 -c "import json; d=json.load(open('wp-test/Import-Data/acpt-import.acpt')); print(f'CPTs: {len(d[\"customPostTypes\"])}, Tax: {len(d[\"taxonomies\"])}, Groups: {len(d[\"metaGroups\"])}')"`

**Step 3: Commit**

```bash
git add wp-test/Import-Data/acpt-import.acpt
git commit -m "feat: add ACPT movie schema import file"
```

---

## Task 7: Create WPfaker Login Logo mu-plugin

**Files:**
- Create: `wp-test/mu-plugins/wpfaker-login-logo.php`

**Step 1: Create the mu-plugin**

A simple mu-plugin that replaces the WordPress login logo with the WPfaker logo. The logo URL should point to an uploaded media file or a base64-encoded SVG/PNG inline.

```php
<?php
/**
 * Plugin Name: WPfaker Login Logo
 * Description: Replaces the WP login logo with the WPfaker logo.
 */

add_action('login_enqueue_scripts', function () {
    ?>
    <style>
        #login h1 a, .login h1 a {
            background-image: url('<?php echo esc_url(content_url('/mu-plugins/wpfaker-logo.png')); ?>');
            background-size: contain;
            background-repeat: no-repeat;
            background-position: center;
            width: 100%;
            height: 80px;
        }
    </style>
    <?php
});

add_filter('login_headerurl', function () {
    return 'https://wpfaker.com';
});

add_filter('login_headertext', function () {
    return 'WPfaker';
});
```

Also need to place the WPfaker logo PNG at `wp-test/mu-plugins/wpfaker-logo.png`. Source the logo from the wpfaker project assets.

**Step 2: Also add admin bar branding (for video recordings)**

Add to the same mu-plugin or create a separate one that shows the WPfaker logo in the admin toolbar:

```php
add_action('admin_bar_menu', function ($wp_admin_bar) {
    $wp_admin_bar->add_node([
        'id'    => 'wpfaker-logo',
        'title' => '<img src="' . esc_url(content_url('/mu-plugins/wpfaker-logo.png')) . '" style="height:20px;vertical-align:middle;margin-right:5px;" alt="WPfaker"> WPfaker Test',
        'href'  => 'https://wpfaker.com',
        'meta'  => ['class' => 'wpfaker-admin-logo'],
    ]);
}, 1);
```

**Step 3: Commit**

```bash
git add wp-test/mu-plugins/
git commit -m "feat: add WPfaker login logo mu-plugin"
```

---

## Task 8: InstaWP Template Setup (Manual)

This task is manual — performed via the InstaWP dashboard, not automated.

**Step 1: Create a fresh InstaWP site**

Go to InstaWP dashboard → Create New Site → Latest WordPress

**Step 2: Install all 7 plugins**

Upload and install (DO NOT activate yet):
1. ACF Pro (from license/downloads)
2. ACF Extended (from wordpress.org or license)
3. Meta Box (`~/Downloads/meta-box.5.11.2.zip`)
4. Meta Box AIO (`~/Downloads/meta-box-aio-3.5.0.zip`)
5. JetEngine (`~/Downloads/jet-engine.zip`)
6. ACPT Pro (from license/downloads)
7. CPTUI (from wordpress.org)

**Step 3: Upload import files**

Upload all JSON/ACPT files from `wp-test/Import-Data/` (Movies) and `wp-test/Import-Data/Recipes/` to a known location on the server (e.g., via SFTP or Media Library).

**Step 4: Import data per plugin (activate one at a time)**

For each plugin:
1. Activate the plugin
2. Import the corresponding JSON(s):
   - **ACF:** Tools → Import → upload `acf-export-2026-03-07.json` + `acf-recipes.json`
   - **CPTUI:** Tools → Import Post Types / Import Taxonomies (paste JSON content)
   - **Meta Box:** Meta Box → Import (upload individual files or use `metabox-import-2026-03-07.json`)
   - **JetEngine:** JetEngine → Import/Export → Import (upload `jetengine-import.json` + `jetengine-recipes.json`)
   - **ACPT:** ACPT → Import (upload `.acpt` files)
3. Verify: check that CPTs appear in admin menu, fields show on edit screens, no PHP errors in debug log
4. Deactivate the plugin

**Step 5: Upload mu-plugin**

Via SFTP, upload `wpfaker-login-logo.php` and `wpfaker-logo.png` to `wp-content/mu-plugins/`.

**Step 6: Final verification**

- Visit `/wp-login.php` — WPfaker logo should show
- Check admin bar — WPfaker branding visible
- Enable WP_DEBUG temporarily, visit various admin pages, check `debug.log` for errors
- Disable WP_DEBUG

**Step 7: Create InstaWP template**

In InstaWP dashboard:
- Go to Templates (or site settings → Save as Template)
- Name: "WPfaker Test Environment"
- Description: "Pre-configured with ACF Pro, ACF Extended, Meta Box + AIO, JetEngine, ACPT Pro, CPTUI. Movie + Recipe schemas imported. All plugins deactivated. WPfaker branding."
- Save

---

## File Summary

### New files to create (Recipes/)
| File | Plugin | Format |
|------|--------|--------|
| `Recipes/acf-recipes.json` | ACF Pro | JSON array |
| `Recipes/cptui-recipe-post-types.json` | CPTUI | JSON object |
| `Recipes/cptui-recipe-taxonomies.json` | CPTUI | JSON object |
| `Recipes/metabox-recipe-fields.json` | Meta Box | JSON array |
| `Recipes/metabox-recipe-cpts.json` | Meta Box | JSON array |
| `Recipes/metabox-recipe-taxonomies.json` | Meta Box | JSON array |
| `Recipes/metabox-recipe-relationships.json` | Meta Box | JSON array |
| `Recipes/jetengine-recipes.json` | JetEngine | JSON object |
| `Recipes/acpt-recipes.acpt` | ACPT Pro | JSON file |

### Other files
| File | Purpose |
|------|---------|
| `acpt-import.acpt` | ACPT movie schema (copy from Downloads) |
| `mu-plugins/wpfaker-login-logo.php` | Login + admin branding |
| `mu-plugins/wpfaker-logo.png` | Logo image |

# Meta Box Standalone Plugins Implementation Plan

> **For Claude:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task.

**Goal:** Support Meta Box with individual plugins (MB Custom Post Types, MB Builder, MB Relationships) as alternative to Meta Box AIO, with correct test data import for both variants.

**Architecture:** The plugin checklist gets mutual exclusivity for Meta Box/AIO. Provision.sh installs companion plugins (from WP library + zip) when standalone is chosen. Test data JSONs are split into `metabox-aio/` and `metabox-standalone/` subdirectories with variant-specific field groups — the standalone variant omits AIO-only field types (e.g. `osm`). The existing `wp_insert_post` import loop handles both variants since field groups use `post_type: "meta-box"` and relationships use `post_type: "mb-relationship"`.

**Tech Stack:** Go (Bubbletea TUI), Bash (provision.sh), WordPress/PHP (WP-CLI), JSON test data

---

### Task 1: Move "Destroy" to top of main menu

**Files:**
- Modify: `cmd/wpt/main.go:48-57`

**Step 1: Reorder menu items**

In `runInteractive()`, move "Destroy" to the first position:

```go
mainItems := []tui.MenuItem{
    {Label: "Destroy (remove all)", Key: "destroy"},
    {Label: "Provision (full setup)", Key: "provision"},
    {Label: "Up (start containers)", Key: "up"},
    {Label: "Reset (restore snapshot)", Key: "reset"},
    {Label: "Snapshot (save DB)", Key: "snapshot"},
    {Label: "Status", Key: "status"},
    {Label: "Down (stop)", Key: "down"},
    {Label: "Logs", Key: "logs"},
}
```

**Step 2: Build and verify**

Run: `cd ~/Projects/wp-test && go build -o wpt ./cmd/wpt`
Expected: compiles without errors

Run: `./wpt` and verify "Destroy" appears first in menu, then quit.

**Step 3: Commit**

```bash
git add cmd/wpt/main.go
git commit -m "feat: move Destroy to top of main menu"
```

---

### Task 2: Add mutual exclusivity for Meta Box / Meta Box AIO

**Files:**
- Modify: `cmd/wpt/main.go:197-218`

**Step 1: Replace Meta Box checklist entries with mutual-exclusive selection**

In `startProvisionFlow()`, after the WPfaker/worktree selection and before the plugin checklist, add a Meta Box variant selector. Then adjust the plugin checklist to exclude both Meta Box entries and append the right slugs based on the variant choice.

Replace the plugin selection block (lines ~197-218) with:

```go
// Meta Box variant selection
mbItems := []tui.MenuItem{
    {Label: "Meta Box AIO (all-in-one)", Key: "aio"},
    {Label: "Meta Box (individual plugins)", Key: "standalone"},
    {Label: "None", Key: "none"},
}
mbMenu := tui.NewMenuModel("Meta Box variant", mbItems)
pMB := tea.NewProgram(mbMenu)
resultMB, err := pMB.Run()
if err != nil {
    return err
}
mbChosen := resultMB.(tui.MenuModel).Chosen()
if mbChosen == "" {
    return nil
}

// Plugin selection (without Meta Box entries)
pluginItems := []tui.ChecklistItem{
    {Label: "ACF Pro", Key: "advanced-custom-fields-pro"},
    {Label: "ACPT", Key: "advanced-custom-post-type"},
    {Label: "CPT UI", Key: "custom-post-type-ui"},
    {Label: "JetEngine", Key: "jet-engine"},
}
cl := tui.NewChecklistModel("Which test plugins should be activated?", pluginItems)
p3 := tea.NewProgram(cl)
result3, err := p3.Run()
if err != nil {
    return err
}
clModel := result3.(tui.ChecklistModel)
if clModel.Cancelled() {
    return nil
}
selected := clModel.Selected()

// Append Meta Box plugins based on variant
switch mbChosen {
case "aio":
    selected = append(selected, "meta-box", "meta-box-aio")
case "standalone":
    selected = append(selected, "meta-box", "mb-custom-post-type", "meta-box-builder", "mb-relationships")
}

pluginsFlag = strings.Join(selected, ",")
```

**Step 2: Build and verify**

Run: `cd ~/Projects/wp-test && go build -o wpt ./cmd/wpt`
Expected: compiles without errors

**Step 3: Commit**

```bash
git add cmd/wpt/main.go
git commit -m "feat: add Meta Box variant selector (AIO vs standalone)"
```

---

### Task 3: Copy MB Builder zip to Testplugins

**Files:**
- Create: `Testplugins/meta-box-builder/` (extracted from zip)

**Step 1: Extract MB Builder plugin**

```bash
cd ~/Projects/wp-test
unzip ~/Downloads/meta-box-builder-5.1.1.zip -d Testplugins/
```

Verify: `ls Testplugins/meta-box-builder/meta-box-builder.php` exists

**Step 2: Add volume mounts in docker-compose.yml**

In `Blueprint/docker-compose.yml`, add mounts for `meta-box-builder` in both the `wordpress` and `caddy` services, alongside the existing test plugin mounts:

WordPress service (after the `meta-box-aio` mount):
```yaml
      - ../Testplugins/meta-box-builder:/var/www/html/wp-content/plugins/meta-box-builder
```

Caddy service (after the `meta-box-aio` mount):
```yaml
      - ../Testplugins/meta-box-builder:/var/www/html/wp-content/plugins/meta-box-builder:ro
```

**Step 3: Commit**

```bash
git add Testplugins/meta-box-builder Blueprint/docker-compose.yml
git commit -m "feat: add MB Builder plugin to Testplugins"
```

---

### Task 4: Update provision.sh to install companion plugins

**Files:**
- Modify: `Blueprint/provision.sh`

**Step 1: Add mb-custom-post-type and mb-relationships to ALL_PLUGINS**

Update the `ALL_PLUGINS` array (line ~44) to include the new plugins:

```bash
ALL_PLUGINS=(
    advanced-custom-fields-pro
    advanced-custom-post-type
    custom-post-type-ui
    jet-engine
    meta-box
    meta-box-aio
    meta-box-builder
    mb-custom-post-type
    mb-relationships
)
```

**Step 2: Add WP library plugin installation before activation**

Add a new section before Task 3 (before "Activate all plugins") that installs plugins from WP library if they're not already present:

```bash
# ---------------------------------------------------------------------------
# Task 2b: Install plugins from WP library (if needed)
# ---------------------------------------------------------------------------
section "Task 2b: Install WP library plugins"

WP_LIBRARY_PLUGINS=(mb-custom-post-type mb-relationships)
for plugin in "${WP_LIBRARY_PLUGINS[@]}"; do
    if $WP plugin is-installed "$plugin" 2>/dev/null; then
        echo "  ✓ $plugin (already installed)"
    else
        $WP plugin install "$plugin" && echo "  → Installed $plugin" || echo "  ✗ Failed to install $plugin"
    fi
done
```

**Step 3: Commit**

```bash
git add Blueprint/provision.sh
git commit -m "feat: install MB companion plugins from WP library"
```

---

### Task 5: Create AIO field group JSONs (Movies)

**Files:**
- Create: `Import-Data/metabox-aio/metabox-aio-fields.json`
- Create: `Import-Data/metabox-aio/metabox-aio-relationships.json`

These use `post_type: "meta-box"` for field groups and `post_type: "mb-relationship"` for relationships, with settings in the `settings` key (same pattern as existing CPT JSONs). All field types including `osm` are included.

**Step 1: Create metabox-aio-fields.json**

```json
[
    {
        "post_title": "Actor",
        "post_type": "meta-box",
        "post_date": "2026-03-20 00:00:00",
        "post_status": "publish",
        "settings": {
            "id": "actor-fields",
            "title": "Actor",
            "post_types": ["actor"],
            "context": "normal",
            "priority": "high",
            "fields": [
                {"id": "first_name", "type": "text", "name": "First name", "required": true, "columns": 4},
                {"id": "last_name", "type": "text", "name": "Last name", "required": true, "columns": 4},
                {"id": "stage_name", "type": "text", "name": "Stage Name", "columns": 4},
                {"id": "date_of_birth", "type": "date", "name": "Date of Birth", "columns": 4, "js_options": {"dateFormat": "dd/mm/yy"}},
                {
                    "id": "awards", "type": "group", "name": "Awards",
                    "clone": true, "sort_clone": true, "collapsible": true,
                    "group_title": "{award} ({year})", "add_button": "Add Row",
                    "fields": [
                        {"id": "award", "type": "text", "name": "Award"},
                        {"id": "category", "type": "text", "name": "Category"},
                        {"id": "year", "type": "number", "name": "Year"},
                        {"id": "rolecharacter", "type": "text", "name": "Role/Character"},
                        {"id": "result", "type": "select", "name": "Result", "options": {"Won": "Won", "Nominated": "Nominated"}}
                    ]
                }
            ]
        }
    },
    {
        "post_title": "Director",
        "post_type": "meta-box",
        "post_date": "2026-03-20 00:00:00",
        "post_status": "publish",
        "settings": {
            "id": "director-fields",
            "title": "Director",
            "post_types": ["director"],
            "context": "normal",
            "priority": "high",
            "fields": [
                {"id": "first_name", "type": "text", "name": "First Name", "required": true, "columns": 4},
                {"id": "last_name", "type": "text", "name": "Last Name", "required": true, "columns": 4}
            ]
        }
    },
    {
        "post_title": "Movie",
        "post_type": "meta-box",
        "post_date": "2026-03-20 00:00:00",
        "post_status": "publish",
        "settings": {
            "id": "movie-fields",
            "title": "Movie",
            "post_types": ["movie"],
            "context": "normal",
            "priority": "high",
            "fields": [
                {"id": "poster", "type": "single_image", "name": "Poster", "required": true},
                {"id": "plot", "type": "wysiwyg", "name": "Plot", "options": {"textarea_rows": 10, "media_buttons": true}},
                {"id": "genre", "type": "taxonomy_advanced", "name": "Genre", "taxonomy": ["genre"], "field_type": "select_advanced", "multiple": false, "add_new": true},
                {"id": "budget", "type": "number", "name": "Budget", "columns": 4, "prepend": "$", "append": "Mio."},
                {"id": "published_at", "type": "date", "name": "Published at", "js_options": {"dateFormat": "dd/mm/yy"}},
                {"id": "impressions", "type": "image_advanced", "name": "Impressions", "max_file_uploads": 0, "force_delete": false, "max_status": false}
            ]
        }
    },
    {
        "post_title": "Studio",
        "post_type": "meta-box",
        "post_date": "2026-03-20 00:00:00",
        "post_status": "publish",
        "settings": {
            "id": "studio-fields",
            "title": "Studio",
            "post_types": ["studio"],
            "context": "normal",
            "priority": "high",
            "fields": [
                {"id": "logo", "type": "single_image", "name": "Logo", "required": true},
                {"id": "company_name", "type": "text", "name": "Company Name", "required": true},
                {"id": "address", "type": "textarea", "name": "Address", "columns": 4},
                {"id": "map", "type": "osm", "name": "Map", "address_field": "address", "visible": ["address", "!=", ""]}
            ]
        }
    }
]
```

**Step 2: Create metabox-aio-relationships.json**

```json
[
    {
        "post_title": "Movies to Actors",
        "post_type": "mb-relationship",
        "post_date": "2026-03-20 00:00:00",
        "post_status": "publish",
        "settings": {
            "id": "movies-to-actors",
            "from": {"object_type": "post", "post_type": ["movie"], "meta_box": {"title": "Actors"}},
            "to": {"object_type": "post", "post_type": ["actor"], "meta_box": {"title": "Movies"}}
        }
    },
    {
        "post_title": "Movies to Directors",
        "post_type": "mb-relationship",
        "post_date": "2026-03-20 00:00:00",
        "post_status": "publish",
        "settings": {
            "id": "movies-to-directors",
            "from": {"object_type": "post", "post_type": ["movie"], "meta_box": {"title": "Directors"}},
            "to": {"object_type": "post", "post_type": ["director"], "meta_box": {"title": "Movies"}}
        }
    },
    {
        "post_title": "Movies to Studios",
        "post_type": "mb-relationship",
        "post_date": "2026-03-20 00:00:00",
        "post_status": "publish",
        "settings": {
            "id": "movies-to-studios",
            "from": {"object_type": "post", "post_type": ["movie"], "meta_box": {"title": "Studios"}},
            "to": {"object_type": "post", "post_type": ["studio"], "meta_box": {"title": "Movies"}}
        }
    }
]
```

**Step 3: Commit**

```bash
git add Import-Data/metabox-aio/
git commit -m "feat: add AIO-variant field group and relationship JSONs"
```

---

### Task 6: Create AIO Recipe field group JSONs

**Files:**
- Create: `Import-Data/metabox-aio/Recipes/metabox-aio-recipe-fields.json`
- Create: `Import-Data/metabox-aio/Recipes/metabox-aio-recipe-relationships.json`

**Step 1: Create metabox-aio-recipe-fields.json**

Same pattern as Task 5. Convert the existing `Recipes/metabox-recipe-fields.json` to `wp_insert_post` format. All field types are core-compatible (no `osm`) so content is identical between AIO and standalone.

```json
[
    {
        "post_title": "Recipe",
        "post_type": "meta-box",
        "post_date": "2026-03-20 00:00:00",
        "post_status": "publish",
        "settings": {
            "id": "recipe-fields",
            "title": "Recipe",
            "post_types": ["recipe"],
            "context": "normal",
            "priority": "high",
            "fields": [
                {"id": "image", "type": "single_image", "name": "Image", "required": true},
                {"id": "description", "type": "wysiwyg", "name": "Description", "options": {"textarea_rows": 10, "media_buttons": true}},
                {"id": "prep_time", "type": "number", "name": "Prep Time", "columns": 4, "append": "min"},
                {"id": "cook_time", "type": "number", "name": "Cook Time", "columns": 4, "append": "min"},
                {"id": "servings", "type": "number", "name": "Servings", "columns": 4},
                {"id": "difficulty", "type": "select", "name": "Difficulty", "options": {"easy": "Easy", "medium": "Medium", "hard": "Hard"}},
                {"id": "cuisine", "type": "taxonomy_advanced", "name": "Cuisine", "taxonomy": ["cuisine"], "field_type": "select_advanced", "multiple": false, "add_new": true},
                {"id": "course", "type": "taxonomy_advanced", "name": "Course", "taxonomy": ["course"], "field_type": "select_advanced", "multiple": false, "add_new": true},
                {"id": "diet", "type": "taxonomy_advanced", "name": "Diet", "taxonomy": ["diet"], "field_type": "select_advanced", "multiple": false, "add_new": true},
                {
                    "id": "ingredients", "type": "group", "name": "Ingredients",
                    "clone": true, "sort_clone": true, "collapsible": true,
                    "group_title": "{ingredient}", "add_button": "Add Row",
                    "fields": [
                        {"id": "ingredient", "type": "text", "name": "Ingredient"},
                        {"id": "amount", "type": "text", "name": "Amount"},
                        {"id": "unit", "type": "select", "name": "Unit", "options": {"g": "g", "kg": "kg", "ml": "ml", "l": "l", "tsp": "tsp", "tbsp": "tbsp", "cup": "cup", "piece": "piece", "pinch": "pinch"}}
                    ]
                },
                {
                    "id": "instructions", "type": "group", "name": "Instructions",
                    "clone": true, "sort_clone": true, "collapsible": true,
                    "group_title": "Step {step}", "add_button": "Add Row",
                    "fields": [
                        {"id": "step", "type": "number", "name": "Step"},
                        {"id": "instruction_text", "type": "textarea", "name": "Instruction Text"},
                        {"id": "step_image", "type": "single_image", "name": "Step Image"}
                    ]
                },
                {
                    "id": "nutrition", "type": "group", "name": "Nutrition",
                    "clone": true, "sort_clone": true, "collapsible": true,
                    "group_title": "{nutrient}", "add_button": "Add Row",
                    "fields": [
                        {"id": "nutrient", "type": "text", "name": "Nutrient"},
                        {"id": "nutrient_value", "type": "text", "name": "Value"}
                    ]
                }
            ]
        }
    },
    {
        "post_title": "Chef",
        "post_type": "meta-box",
        "post_date": "2026-03-20 00:00:00",
        "post_status": "publish",
        "settings": {
            "id": "chef-fields",
            "title": "Chef",
            "post_types": ["chef"],
            "context": "normal",
            "priority": "high",
            "fields": [
                {"id": "first_name", "type": "text", "name": "First Name", "required": true},
                {"id": "last_name", "type": "text", "name": "Last Name", "required": true},
                {"id": "bio", "type": "wysiwyg", "name": "Bio", "options": {"textarea_rows": 10, "media_buttons": true}},
                {"id": "photo", "type": "single_image", "name": "Photo"},
                {"id": "website", "type": "url", "name": "Website"}
            ]
        }
    },
    {
        "post_title": "Cookbook",
        "post_type": "meta-box",
        "post_date": "2026-03-20 00:00:00",
        "post_status": "publish",
        "settings": {
            "id": "cookbook-fields",
            "title": "Cookbook",
            "post_types": ["cookbook"],
            "context": "normal",
            "priority": "high",
            "fields": [
                {"id": "cover_image", "type": "single_image", "name": "Cover Image"},
                {"id": "publisher", "type": "text", "name": "Publisher"},
                {"id": "year", "type": "number", "name": "Year"},
                {"id": "isbn", "type": "text", "name": "ISBN"},
                {"id": "description", "type": "textarea", "name": "Description"}
            ]
        }
    }
]
```

**Step 2: Create metabox-aio-recipe-relationships.json**

```json
[
    {
        "post_title": "Recipes to Chefs",
        "post_type": "mb-relationship",
        "post_date": "2026-03-20 00:00:00",
        "post_status": "publish",
        "settings": {
            "id": "recipes-to-chefs",
            "from": {"object_type": "post", "post_type": ["recipe"], "meta_box": {"title": "Chefs"}},
            "to": {"object_type": "post", "post_type": ["chef"], "meta_box": {"title": "Recipes"}}
        }
    },
    {
        "post_title": "Recipes to Cookbooks",
        "post_type": "mb-relationship",
        "post_date": "2026-03-20 00:00:00",
        "post_status": "publish",
        "settings": {
            "id": "recipes-to-cookbooks",
            "from": {"object_type": "post", "post_type": ["recipe"], "meta_box": {"title": "Cookbooks"}},
            "to": {"object_type": "post", "post_type": ["cookbook"], "meta_box": {"title": "Recipes"}}
        }
    }
]
```

**Step 3: Commit**

```bash
git add Import-Data/metabox-aio/Recipes/
git commit -m "feat: add AIO-variant recipe field group and relationship JSONs"
```

---

### Task 7: Create standalone field group JSONs (Movies)

**Files:**
- Create: `Import-Data/metabox-standalone/metabox-standalone-fields.json`
- Create: `Import-Data/metabox-standalone/metabox-standalone-relationships.json`

**Step 1: Create metabox-standalone-fields.json**

Identical to AIO variant except Studio field group omits the `osm` map field:

```json
[
    {
        "post_title": "Actor",
        "post_type": "meta-box",
        "post_date": "2026-03-20 00:00:00",
        "post_status": "publish",
        "settings": {
            "id": "actor-fields",
            "title": "Actor",
            "post_types": ["actor"],
            "context": "normal",
            "priority": "high",
            "fields": [
                {"id": "first_name", "type": "text", "name": "First name", "required": true, "columns": 4},
                {"id": "last_name", "type": "text", "name": "Last name", "required": true, "columns": 4},
                {"id": "stage_name", "type": "text", "name": "Stage Name", "columns": 4},
                {"id": "date_of_birth", "type": "date", "name": "Date of Birth", "columns": 4, "js_options": {"dateFormat": "dd/mm/yy"}},
                {
                    "id": "awards", "type": "group", "name": "Awards",
                    "clone": true, "sort_clone": true, "collapsible": true,
                    "group_title": "{award} ({year})", "add_button": "Add Row",
                    "fields": [
                        {"id": "award", "type": "text", "name": "Award"},
                        {"id": "category", "type": "text", "name": "Category"},
                        {"id": "year", "type": "number", "name": "Year"},
                        {"id": "rolecharacter", "type": "text", "name": "Role/Character"},
                        {"id": "result", "type": "select", "name": "Result", "options": {"Won": "Won", "Nominated": "Nominated"}}
                    ]
                }
            ]
        }
    },
    {
        "post_title": "Director",
        "post_type": "meta-box",
        "post_date": "2026-03-20 00:00:00",
        "post_status": "publish",
        "settings": {
            "id": "director-fields",
            "title": "Director",
            "post_types": ["director"],
            "context": "normal",
            "priority": "high",
            "fields": [
                {"id": "first_name", "type": "text", "name": "First Name", "required": true, "columns": 4},
                {"id": "last_name", "type": "text", "name": "Last Name", "required": true, "columns": 4}
            ]
        }
    },
    {
        "post_title": "Movie",
        "post_type": "meta-box",
        "post_date": "2026-03-20 00:00:00",
        "post_status": "publish",
        "settings": {
            "id": "movie-fields",
            "title": "Movie",
            "post_types": ["movie"],
            "context": "normal",
            "priority": "high",
            "fields": [
                {"id": "poster", "type": "single_image", "name": "Poster", "required": true},
                {"id": "plot", "type": "wysiwyg", "name": "Plot", "options": {"textarea_rows": 10, "media_buttons": true}},
                {"id": "genre", "type": "taxonomy_advanced", "name": "Genre", "taxonomy": ["genre"], "field_type": "select_advanced", "multiple": false, "add_new": true},
                {"id": "budget", "type": "number", "name": "Budget", "columns": 4, "prepend": "$", "append": "Mio."},
                {"id": "published_at", "type": "date", "name": "Published at", "js_options": {"dateFormat": "dd/mm/yy"}},
                {"id": "impressions", "type": "image_advanced", "name": "Impressions", "max_file_uploads": 0, "force_delete": false, "max_status": false}
            ]
        }
    },
    {
        "post_title": "Studio",
        "post_type": "meta-box",
        "post_date": "2026-03-20 00:00:00",
        "post_status": "publish",
        "settings": {
            "id": "studio-fields",
            "title": "Studio",
            "post_types": ["studio"],
            "context": "normal",
            "priority": "high",
            "fields": [
                {"id": "logo", "type": "single_image", "name": "Logo", "required": true},
                {"id": "company_name", "type": "text", "name": "Company Name", "required": true},
                {"id": "address", "type": "textarea", "name": "Address", "columns": 4}
            ]
        }
    }
]
```

Note: Studio has no `osm` map field — only `logo`, `company_name`, `address`.

**Step 2: Create metabox-standalone-relationships.json**

Identical to AIO variant (relationships use no AIO-specific features):

```json
[
    {
        "post_title": "Movies to Actors",
        "post_type": "mb-relationship",
        "post_date": "2026-03-20 00:00:00",
        "post_status": "publish",
        "settings": {
            "id": "movies-to-actors",
            "from": {"object_type": "post", "post_type": ["movie"], "meta_box": {"title": "Actors"}},
            "to": {"object_type": "post", "post_type": ["actor"], "meta_box": {"title": "Movies"}}
        }
    },
    {
        "post_title": "Movies to Directors",
        "post_type": "mb-relationship",
        "post_date": "2026-03-20 00:00:00",
        "post_status": "publish",
        "settings": {
            "id": "movies-to-directors",
            "from": {"object_type": "post", "post_type": ["movie"], "meta_box": {"title": "Directors"}},
            "to": {"object_type": "post", "post_type": ["director"], "meta_box": {"title": "Movies"}}
        }
    },
    {
        "post_title": "Movies to Studios",
        "post_type": "mb-relationship",
        "post_date": "2026-03-20 00:00:00",
        "post_status": "publish",
        "settings": {
            "id": "movies-to-studios",
            "from": {"object_type": "post", "post_type": ["movie"], "meta_box": {"title": "Studios"}},
            "to": {"object_type": "post", "post_type": ["studio"], "meta_box": {"title": "Movies"}}
        }
    }
]
```

**Step 3: Commit**

```bash
git add Import-Data/metabox-standalone/
git commit -m "feat: add standalone-variant field group and relationship JSONs (no osm)"
```

---

### Task 8: Create standalone Recipe field group JSONs

**Files:**
- Create: `Import-Data/metabox-standalone/Recipes/metabox-standalone-recipe-fields.json`
- Create: `Import-Data/metabox-standalone/Recipes/metabox-standalone-recipe-relationships.json`

**Step 1: Create recipe JSONs**

Recipe data has no AIO-specific field types, so content is identical to the AIO recipe variants. Copy the files from Task 6 but with `standalone` naming.

The JSON content is identical to Task 6's `metabox-aio-recipe-fields.json` and `metabox-aio-recipe-relationships.json`.

**Step 2: Commit**

```bash
git add Import-Data/metabox-standalone/Recipes/
git commit -m "feat: add standalone-variant recipe field group and relationship JSONs"
```

---

### Task 9: Update provision.sh Task 6 to use variant-specific data

**Files:**
- Modify: `Blueprint/provision.sh:196-252`

**Step 1: Detect Meta Box variant from ACTIVATE_PLUGINS**

Replace the current Task 6 section with variant-aware logic:

```bash
# ---------------------------------------------------------------------------
# Task 6: Import Meta Box schemas (Movies + Recipes)
# ---------------------------------------------------------------------------
section "Task 6: Import Meta Box schemas"

# Detect Meta Box variant from ACTIVATE_PLUGINS
MB_VARIANT=""
if echo "${ACTIVATE_PLUGINS:-}" | grep -q "meta-box-aio"; then
    MB_VARIANT="aio"
elif echo "${ACTIVATE_PLUGINS:-}" | grep -q "meta-box"; then
    MB_VARIANT="standalone"
fi

if [ -z "$MB_VARIANT" ]; then
    echo "No Meta Box variant selected, skipping Meta Box import."
else
    echo "Meta Box variant: $MB_VARIANT"

    # CPTs and taxonomies are shared (same format for both variants)
    METABOX_SHARED_FILES=(
        "/tmp/import-data/metabox-cpts.json"
        "/tmp/import-data/metabox-taxonomies.json"
        "/tmp/import-data/Recipes/metabox-recipe-cpts.json"
        "/tmp/import-data/Recipes/metabox-recipe-taxonomies.json"
    )

    # Variant-specific files (fields + relationships)
    METABOX_VARIANT_FILES=(
        "/tmp/import-data/metabox-${MB_VARIANT}/metabox-${MB_VARIANT}-fields.json"
        "/tmp/import-data/metabox-${MB_VARIANT}/metabox-${MB_VARIANT}-relationships.json"
        "/tmp/import-data/metabox-${MB_VARIANT}/Recipes/metabox-${MB_VARIANT}-recipe-fields.json"
        "/tmp/import-data/metabox-${MB_VARIANT}/Recipes/metabox-${MB_VARIANT}-recipe-relationships.json"
    )

    METABOX_FILES=("${METABOX_SHARED_FILES[@]}" "${METABOX_VARIANT_FILES[@]}")

    for file in "${METABOX_FILES[@]}"; do
        basename=$(basename "$file")
        dirname=$(basename "$(dirname "$file")")
        label="$basename"
        if [ "$dirname" != "import-data" ] && [ "$dirname" != "metabox-${MB_VARIANT}" ]; then
            label="$dirname/$basename"
        fi

        $WP eval "
            \$file = '$file';
            if (!file_exists(\$file)) {
                echo 'SKIP: $label not found';
                return;
            }
            \$posts = json_decode(file_get_contents(\$file), true);
            if (\$posts === null) {
                echo 'ERROR: Invalid JSON in $label';
                return;
            }
            \$imported = 0;
            foreach (\$posts as \$post) {
                if (empty(\$post['post_type']) || empty(\$post['post_title'])) { continue; }
                \$existing = get_posts([
                    'post_type'   => \$post['post_type'],
                    'title'       => \$post['post_title'],
                    'post_status' => 'publish',
                    'numberposts' => 1,
                ]);
                if (!empty(\$existing)) { continue; }

                if (isset(\$post['settings'])) {
                    \$post['post_content'] = wp_json_encode(\$post['settings'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                }
                \$post['post_status'] = 'publish';
                wp_insert_post(\$post);
                \$imported++;
            }
            echo '$label: ' . \$imported . ' imported, ' . (count(\$posts) - \$imported) . ' skipped (duplicates)';
        "
    done
fi
```

**Step 2: Commit**

```bash
git add Blueprint/provision.sh
git commit -m "feat: variant-aware Meta Box import (AIO vs standalone)"
```

---

### Task 10: Clean up old JSON schema files

**Files:**
- Delete: `Import-Data/metabox-fields.json`
- Delete: `Import-Data/metabox-relationships.json`
- Delete: `Import-Data/metabox-import-2026-03-07.json`
- Delete: `Import-Data/Recipes/metabox-recipe-fields.json`
- Delete: `Import-Data/Recipes/metabox-recipe-relationships.json`

These are replaced by the variant-specific files in `metabox-aio/` and `metabox-standalone/`.

Note: Keep `metabox-cpts.json`, `metabox-taxonomies.json`, and their Recipe counterparts — these are shared by both variants.

**Step 1: Remove old files**

```bash
cd ~/Projects/wp-test
git rm Import-Data/metabox-fields.json
git rm Import-Data/metabox-relationships.json
git rm Import-Data/metabox-import-2026-03-07.json
git rm Import-Data/Recipes/metabox-recipe-fields.json
git rm Import-Data/Recipes/metabox-recipe-relationships.json
```

**Step 2: Commit**

```bash
git commit -m "chore: remove old Meta Box JSON schema files (replaced by variant-specific)"
```

---

### Task 11: Build, provision, and verify

**Step 1: Build Go binary**

```bash
cd ~/Projects/wp-test && go build -o wpt ./cmd/wpt
```

Expected: compiles without errors

**Step 2: Test with AIO variant**

```bash
./wpt destroy
```

Then provision with Meta Box AIO selected. Verify:
- `docker exec wpt-wordpress wp --allow-root plugin list --status=active` shows `meta-box` and `meta-box-aio` active
- Field groups exist: `docker exec wpt-wordpress wp --allow-root post list --post_type=meta-box --format=table`
- Relationships exist: `docker exec wpt-wordpress wp --allow-root post list --post_type=mb-relationship --format=table`
- CPTs visible in WP admin sidebar (Actors, Directors, Movies, Studios)

**Step 3: Test with standalone variant**

```bash
./wpt destroy
```

Then provision with Meta Box (individual plugins) selected. Verify:
- `docker exec wpt-wordpress wp --allow-root plugin list --status=active` shows `meta-box`, `mb-custom-post-type`, `meta-box-builder`, `mb-relationships` active
- Same field groups and relationships exist
- Studio field group has NO `osm` field
- CPTs visible in WP admin sidebar

**Step 4: Final commit**

```bash
git add -A
git commit -m "feat: complete Meta Box standalone plugin support"
```

# WPfaker Test Environment — Snapshot/Reset Implementation Plan

> **For Claude:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task.

**Goal:** Reproducible WordPress test environment with all 6 test plugins installed, schemas imported, and fast DB reset from golden snapshot.

**Architecture:** Docker Compose (WordPress FPM + MySQL + Caddy) orchestrated by a Makefile. A provision script handles plugin activation and schema imports. DB snapshots enable instant reset.

**Tech Stack:** Docker, Bash, WP-CLI, Make

---

### Task 1: Makefile

**Files:**
- Create: `Makefile`

**Step 1: Create the Makefile**

```makefile
.PHONY: up down provision snapshot reset destroy status logs

DOCKER_DIR = Docker
BLUEPRINT_DIR = Blueprint
SNAPSHOT_DIR = snapshots
GOLDEN_SNAPSHOT = $(SNAPSHOT_DIR)/golden.sql.gz
WP = docker exec wpt-wordpress wp --allow-root --path=/var/www/html

# Start containers (copy Blueprint → Docker first)
up:
	@mkdir -p $(DOCKER_DIR) $(SNAPSHOT_DIR)
	@cp $(BLUEPRINT_DIR)/docker-compose.yml $(DOCKER_DIR)/
	@cp $(BLUEPRINT_DIR)/Caddyfile $(DOCKER_DIR)/
	@cp $(BLUEPRINT_DIR)/wp-setup.sh $(DOCKER_DIR)/
	@cp $(BLUEPRINT_DIR)/php-uploads.ini $(DOCKER_DIR)/
	@cd $(DOCKER_DIR) && docker compose up -d
	@echo "Waiting for WordPress setup..."
	@until $(WP) core is-installed 2>/dev/null; do sleep 2; done
	@echo "WordPress is ready at http://wpfaker-test.dv"

# Stop containers (keep volumes)
down:
	@cd $(DOCKER_DIR) && docker compose down

# Install plugins, import schemas, create golden snapshot
provision: up
	@bash $(BLUEPRINT_DIR)/provision.sh
	@$(MAKE) snapshot
	@echo "Provisioning complete. Golden snapshot saved."

# Export current DB as golden snapshot
snapshot:
	@mkdir -p $(SNAPSHOT_DIR)
	@$(WP) db export - | gzip > $(GOLDEN_SNAPSHOT)
	@echo "Snapshot saved to $(GOLDEN_SNAPSHOT) ($$(du -h $(GOLDEN_SNAPSHOT) | cut -f1))"

# Reset DB from golden snapshot (~3 seconds)
reset:
	@test -f $(GOLDEN_SNAPSHOT) || (echo "No snapshot found. Run 'make provision' first." && exit 1)
	@echo "Resetting database..."
	@gunzip -c $(GOLDEN_SNAPSHOT) | $(WP) db import -
	@$(WP) cache flush 2>/dev/null || true
	@echo "Database reset complete."

# Full teardown — removes containers AND volumes
destroy:
	@cd $(DOCKER_DIR) && docker compose down -v
	@echo "All containers and volumes destroyed."

# Show container status and active plugins
status:
	@cd $(DOCKER_DIR) && docker compose ps
	@echo ""
	@echo "Active plugins:"
	@$(WP) plugin list --status=active --format=table 2>/dev/null || echo "(WordPress not running)"

# Tail container logs
logs:
	@cd $(DOCKER_DIR) && docker compose logs -f
```

**Step 2: Verify `make up` works**

Run: `cd /home/emmgee/Projects/wp-test && make up`
Expected: Containers start, WordPress installs, "WordPress is ready" message.

**Step 3: Commit**

```bash
git add Makefile
git commit -m "feat: add Makefile for test environment orchestration"
```

---

### Task 2: Modify docker-compose.yml for plugin + mu-plugin mounts

The plugins and mu-plugins need to be mounted into the WordPress container. Also mount `Import-Data/` so provision.sh can access the JSON files, and mount `wp-setup.sh` so it runs on first start.

**Files:**
- Modify: `Blueprint/docker-compose.yml`

**Step 1: Add volume mounts to WordPress service**

Add these volumes to the `wordpress` service (after the existing `wordpress_data` and `php-uploads.ini` volumes):

```yaml
      # Test plugins (each mounted individually)
      - ../Testplugins/advanced-custom-fields-pro:/var/www/html/wp-content/plugins/advanced-custom-fields-pro
      - ../Testplugins/advanced-custom-post-type:/var/www/html/wp-content/plugins/advanced-custom-post-type
      - ../Testplugins/custom-post-type-ui:/var/www/html/wp-content/plugins/custom-post-type-ui
      - ../Testplugins/jet-engine:/var/www/html/wp-content/plugins/jet-engine
      - ../Testplugins/meta-box:/var/www/html/wp-content/plugins/meta-box
      - ../Testplugins/meta-box-aio:/var/www/html/wp-content/plugins/meta-box-aio
      # Must-use plugins
      - ../mu-plugins:/var/www/html/wp-content/mu-plugins
      # Import data (for provision.sh)
      - ../Import-Data:/tmp/import-data:ro
      # Setup script
      - ./wp-setup.sh:/tmp/wp-setup.sh:ro
```

Note: Paths are relative to `Docker/` directory (one level up with `../`).

**Step 2: Add entrypoint to run wp-setup.sh**

Add a `command` to the WordPress service that runs the setup script in the background, then starts php-fpm:

```yaml
    command: >
      bash -c '
        /tmp/wp-setup.sh &
        docker-entrypoint.sh php-fpm
      '
```

**Step 3: Verify containers start with mounted plugins**

Run: `cd /home/emmgee/Projects/wp-test && make destroy && make up`
Then: `make status`
Expected: All 6 plugins show as "inactive" (installed but not activated).

**Step 4: Commit**

```bash
git add Blueprint/docker-compose.yml
git commit -m "feat: mount test plugins, mu-plugins, and import data into container"
```

---

### Task 3: Provision script — plugin activation

**Files:**
- Create: `Blueprint/provision.sh`

**Step 1: Create the script with plugin activation**

```bash
#!/usr/bin/env bash
# Provision the test environment: activate plugins, import schemas, set defaults
set -euo pipefail

WP="docker exec wpt-wordpress wp --allow-root --path=/var/www/html"

echo "=== WPfaker Test Environment Provisioning ==="

# Wait for WordPress
echo "Waiting for WordPress..."
until $WP core is-installed 2>/dev/null; do sleep 2; done

# Activate all test plugins
echo ""
echo "--- Activating plugins ---"
PLUGINS=(
    advanced-custom-fields-pro
    advanced-custom-post-type
    custom-post-type-ui
    jet-engine
    meta-box
    meta-box-aio
)

for plugin in "${PLUGINS[@]}"; do
    if $WP plugin is-active "$plugin" 2>/dev/null; then
        echo "  ✓ $plugin (already active)"
    else
        $WP plugin activate "$plugin"
        echo "  ✓ $plugin activated"
    fi
done

echo ""
echo "All plugins activated."
```

**Step 2: Make executable and test**

Run: `chmod +x Blueprint/provision.sh`
Run: `cd /home/emmgee/Projects/wp-test && bash Blueprint/provision.sh`
Expected: All 6 plugins activate without errors.

**Step 3: Commit**

```bash
git add Blueprint/provision.sh
git commit -m "feat: add provision script with plugin activation"
```

---

### Task 4: Provision script — ACF Pro import

**Files:**
- Modify: `Blueprint/provision.sh`

ACF Pro has no WP-CLI import command. Use `acf_import_field_group()` via `wp eval`.

**Step 1: Add ACF import function to provision.sh**

Append after plugin activation:

```bash
# --- ACF Pro Import ---
echo ""
echo "--- Importing ACF Pro schemas ---"

import_acf() {
    local file="$1"
    local label="$2"
    $WP eval "
        \$json = file_get_contents('$file');
        \$groups = json_decode(\$json, true);
        if (!is_array(\$groups)) { echo 'ERROR: Invalid JSON'; return; }
        // Handle both single group and array of groups
        if (isset(\$groups['key'])) { \$groups = [\$groups]; }
        foreach (\$groups as \$group) {
            acf_import_field_group(\$group);
        }
        echo count(\$groups) . ' field group(s) imported';
    "
    echo "  ✓ $label"
}

import_acf "/tmp/import-data/acf-export-2026-03-07.json" "ACF Movies schema"
import_acf "/tmp/import-data/Recipes/acf-recipes.json" "ACF Recipes schema"
```

**Step 2: Test**

Run: `bash Blueprint/provision.sh`
Expected: ACF field groups imported without errors. Verify: `docker exec wpt-wordpress wp --allow-root eval "echo count(acf_get_field_groups()) . ' field groups';"`

**Step 3: Commit**

```bash
git add Blueprint/provision.sh
git commit -m "feat: add ACF Pro schema import to provision script"
```

---

### Task 5: Provision script — CPTUI import

**Files:**
- Modify: `Blueprint/provision.sh`

CPTUI stores its data in `wp_options` as JSON. The `wp cptui import` WP-CLI command may not be available without an extra package. Safer to use `wp option update`.

**Step 1: Add CPTUI import to provision.sh**

Append:

```bash
# --- CPTUI Import ---
echo ""
echo "--- Importing CPTUI schemas ---"

import_cptui() {
    local cpt_file="$1"
    local tax_file="$2"
    local label="$3"

    # CPTUI stores CPTs/taxonomies in wp_options, keyed by slug
    # We need to merge into existing options (don't overwrite)
    $WP eval "
        // Import post types
        \$existing_cpts = get_option('cptui_post_types', []);
        \$new_cpts = json_decode(file_get_contents('$cpt_file'), true);
        if (is_array(\$new_cpts)) {
            foreach (\$new_cpts as \$slug => \$cpt) {
                \$existing_cpts[\$slug] = \$cpt;
            }
            update_option('cptui_post_types', \$existing_cpts);
            echo count(\$new_cpts) . ' post type(s) imported. ';
        }

        // Import taxonomies
        \$existing_taxes = get_option('cptui_taxonomies', []);
        \$new_taxes = json_decode(file_get_contents('$tax_file'), true);
        if (is_array(\$new_taxes)) {
            foreach (\$new_taxes as \$slug => \$tax) {
                \$existing_taxes[\$slug] = \$tax;
            }
            update_option('cptui_taxonomies', \$existing_taxes);
            echo count(\$new_taxes) . ' taxonomy(ies) imported.';
        }
    "
    echo "  ✓ $label"
}

import_cptui "/tmp/import-data/cptui-post-types.json" "/tmp/import-data/cptui-taxonomies.json" "CPTUI Movies schema"
import_cptui "/tmp/import-data/Recipes/cptui-recipe-post-types.json" "/tmp/import-data/Recipes/cptui-recipe-taxonomies.json" "CPTUI Recipes schema"
```

**Step 2: Test**

Run: `bash Blueprint/provision.sh`
Verify: `docker exec wpt-wordpress wp --allow-root option get cptui_post_types --format=json | python3 -m json.tool | head -5`

**Step 3: Commit**

```bash
git add Blueprint/provision.sh
git commit -m "feat: add CPTUI schema import to provision script"
```

---

### Task 6: Provision script — Meta Box import

**Files:**
- Modify: `Blueprint/provision.sh`

Meta Box stores CPTs/taxonomies/fields as WordPress posts (post types: `mb-post-type`, `mb-taxonomy`, `meta-box`). The import JSON format contains `post_title`, `post_type`, `settings` — with `settings` going into `post_content` as JSON.

**Step 1: Add Meta Box import to provision.sh**

Append:

```bash
# --- Meta Box Import ---
echo ""
echo "--- Importing Meta Box schemas ---"

import_metabox() {
    local file="$1"
    local label="$2"

    $WP eval "
        \$json = file_get_contents('$file');
        \$posts = json_decode(\$json, true);
        if (!is_array(\$posts)) { echo 'ERROR: Invalid JSON'; return; }
        \$count = 0;
        foreach (\$posts as \$post) {
            // Skip if already exists (by post_title + post_type)
            \$existing = get_posts([
                'post_type' => \$post['post_type'],
                'title' => \$post['post_title'],
                'post_status' => 'publish',
                'numberposts' => 1,
            ]);
            if (!empty(\$existing)) { continue; }

            // Meta Box stores settings as JSON in post_content
            if (isset(\$post['settings'])) {
                \$post['post_content'] = wp_json_encode(\$post['settings'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            }
            \$post['post_status'] = 'publish';
            \$id = wp_insert_post(\$post);
            if (\$id && !is_wp_error(\$id)) { \$count++; }
        }
        echo \$count . ' item(s) imported';
    "
    echo "  ✓ $label"
}

# Movies
import_metabox "/tmp/import-data/metabox-cpts.json" "Meta Box Movies CPTs"
import_metabox "/tmp/import-data/metabox-taxonomies.json" "Meta Box Movies taxonomies"
import_metabox "/tmp/import-data/metabox-fields.json" "Meta Box Movies fields"
import_metabox "/tmp/import-data/metabox-relationships.json" "Meta Box Movies relationships"

# Recipes
import_metabox "/tmp/import-data/Recipes/metabox-recipe-cpts.json" "Meta Box Recipes CPTs"
import_metabox "/tmp/import-data/Recipes/metabox-recipe-taxonomies.json" "Meta Box Recipes taxonomies"
import_metabox "/tmp/import-data/Recipes/metabox-recipe-fields.json" "Meta Box Recipes fields"
import_metabox "/tmp/import-data/Recipes/metabox-recipe-relationships.json" "Meta Box Recipes relationships"
```

**Step 2: Test**

Run: `bash Blueprint/provision.sh`
Verify: `docker exec wpt-wordpress wp --allow-root post list --post_type=mb-post-type --format=table`

**Step 3: Commit**

```bash
git add Blueprint/provision.sh
git commit -m "feat: add Meta Box schema import to provision script"
```

---

### Task 7: Provision script — JetEngine import

**Files:**
- Modify: `Blueprint/provision.sh`

JetEngine uses `Jet_Engine_Skins_Import` class with methods `import_post_types()`, `import_taxonomies()`, `import_meta_boxes()`, `import_relations()`. The JSON has top-level keys: `post_types`, `taxonomies`, `meta_boxes`, `relations`, etc.

**Step 1: Add JetEngine import to provision.sh**

Append:

```bash
# --- JetEngine Import ---
echo ""
echo "--- Importing JetEngine schemas ---"

import_jetengine() {
    local file="$1"
    local label="$2"

    $WP eval "
        if (!class_exists('Jet_Engine_Skins_Import')) {
            // Load the import class
            \$file = WP_PLUGIN_DIR . '/jet-engine/includes/dashboard/skins-import.php';
            if (file_exists(\$file)) { require_once \$file; }
        }

        if (!class_exists('Jet_Engine_Skins_Import')) {
            echo 'ERROR: Jet_Engine_Skins_Import class not found';
            return;
        }

        \$json = file_get_contents('$file');
        \$content = json_decode(\$json, true);
        if (!is_array(\$content)) { echo 'ERROR: Invalid JSON'; return; }

        \$importer = new Jet_Engine_Skins_Import();

        if (!empty(\$content['post_types'])) {
            \$importer->import_post_types(\$content['post_types']);
            echo count(\$content['post_types']) . ' post type(s), ';
        }
        if (!empty(\$content['taxonomies'])) {
            \$importer->import_taxonomies(\$content['taxonomies']);
            echo count(\$content['taxonomies']) . ' taxonomy(ies), ';
        }
        if (!empty(\$content['meta_boxes'])) {
            \$importer->import_meta_boxes(\$content['meta_boxes']);
            echo count(\$content['meta_boxes']) . ' meta box(es), ';
        }
        if (!empty(\$content['relations'])) {
            \$importer->import_relations(\$content['relations']);
            echo count(\$content['relations']) . ' relation(s), ';
        }
        echo 'imported';
    "
    echo "  ✓ $label"
}

import_jetengine "/tmp/import-data/jetengine-import.json" "JetEngine Movies schema"
import_jetengine "/tmp/import-data/Recipes/jetengine-recipes.json" "JetEngine Recipes schema"
```

**Step 2: Test**

Run: `bash Blueprint/provision.sh`
Expected: JetEngine schemas imported. Check for the JetEngine DB fix mu-plugin running automatically.

**Step 3: Commit**

```bash
git add Blueprint/provision.sh
git commit -m "feat: add JetEngine schema import to provision script"
```

---

### Task 8: Provision script — ACPT import

**Files:**
- Modify: `Blueprint/provision.sh`

ACPT uses `ImportRepository::import($data)` which accepts the decoded array from the .acpt file.

**Step 1: Add ACPT import to provision.sh**

Append:

```bash
# --- ACPT Import ---
echo ""
echo "--- Importing ACPT schemas ---"

import_acpt() {
    local file="$1"
    local label="$2"

    $WP eval "
        if (!class_exists('ACPT\Core\Repository\ImportRepository')) {
            echo 'ERROR: ACPT ImportRepository class not found';
            return;
        }

        \$json = file_get_contents('$file');
        \$data = json_decode(\$json, true);
        if (!is_array(\$data)) { echo 'ERROR: Invalid JSON'; return; }

        try {
            ACPT\Core\Repository\ImportRepository::import(\$data);
            echo 'Schema imported';
        } catch (Exception \$e) {
            echo 'ERROR: ' . \$e->getMessage();
        }
    "
    echo "  ✓ $label"
}

import_acpt "/tmp/import-data/acpt-import.acpt" "ACPT Movies schema"
import_acpt "/tmp/import-data/Recipes/acpt-recipes.acpt" "ACPT Recipes schema"
```

**Step 2: Test**

Run: `bash Blueprint/provision.sh`
Expected: ACPT schemas imported without errors.

**Step 3: Commit**

```bash
git add Blueprint/provision.sh
git commit -m "feat: add ACPT schema import to provision script"
```

---

### Task 9: Provision script — set default state

**Files:**
- Modify: `Blueprint/provision.sh`

After all imports, deactivate all plugins except ACF Pro (the default active plugin).

**Step 1: Add default state to end of provision.sh**

Append:

```bash
# --- Set Default State ---
echo ""
echo "--- Setting default plugin state ---"

# Deactivate all except ACF Pro
DEACTIVATE_PLUGINS=(
    advanced-custom-post-type
    custom-post-type-ui
    jet-engine
    meta-box
    meta-box-aio
)

for plugin in "${DEACTIVATE_PLUGINS[@]}"; do
    $WP plugin deactivate "$plugin" 2>/dev/null || true
done

echo "  ✓ Only ACF Pro remains active"

# Flush rewrite rules
$WP rewrite flush --hard
echo "  ✓ Rewrite rules flushed"

echo ""
echo "=== Provisioning complete ==="
echo "Active plugin: ACF Pro"
echo "Run 'make snapshot' to save, or 'make reset' to restore."
```

**Step 2: Test full provision**

Run: `cd /home/emmgee/Projects/wp-test && make destroy && make provision`
Expected: Full cycle completes — containers up, plugins activated, schemas imported, plugins deactivated (except ACF), snapshot created.

**Step 3: Verify snapshot and reset**

Run: `make reset`
Expected: "Database reset complete." in ~3 seconds.
Verify: `make status` shows ACF Pro active, others inactive.

**Step 4: Commit**

```bash
git add Blueprint/provision.sh
git commit -m "feat: set default plugin state and complete provision script"
```

---

### Task 10: Add snapshots to .gitignore and update README

**Files:**
- Modify: `.gitignore`

**Step 1: Add snapshot directory to .gitignore exceptions**

The golden snapshot should be committed (it IS the reproducible state). But add a note about large snapshots. Check current `.gitignore` and ensure `snapshots/` is NOT ignored.

If `snapshots/` needs to be explicitly included (because of a wildcard ignore), add:

```
!snapshots/
!snapshots/golden.sql.gz
```

**Step 2: Test full cycle end-to-end**

```bash
cd /home/emmgee/Projects/wp-test
make destroy          # Clean slate
make provision        # Full setup + snapshot
make status           # Verify ACF Pro active
make reset            # Restore from snapshot
make status           # Verify same state
make destroy          # Teardown
```

**Step 3: Commit everything**

```bash
git add .gitignore Makefile Blueprint/ snapshots/
git commit -m "feat: complete snapshot/reset test environment system"
```

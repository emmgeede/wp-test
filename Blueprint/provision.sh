#!/usr/bin/env bash
# provision.sh — Imports all test plugin schemas into WordPress
# Run from HOST machine after containers are up and wp-setup.sh has completed.
# Idempotent: safe to re-run.
set -euo pipefail

WP="docker exec wpt-wordpress wp --allow-root --path=/var/www/html"

# ---------------------------------------------------------------------------
# Helpers
# ---------------------------------------------------------------------------
section() {
    echo ""
    echo "================================================================"
    echo "  $1"
    echo "================================================================"
}

check_container() {
    if ! docker inspect wpt-wordpress &>/dev/null; then
        echo "ERROR: Container wpt-wordpress is not running."
        echo "Start it with: docker compose -f Blueprint/docker-compose.yml up -d"
        exit 1
    fi
}

# ---------------------------------------------------------------------------
# Preflight
# ---------------------------------------------------------------------------
section "Preflight checks"
check_container

echo "Waiting for WordPress to be installed..."
until $WP core is-installed 2>/dev/null; do
    sleep 2
done
echo "WordPress is ready."

# ---------------------------------------------------------------------------
# Task 3: Activate all 6 test plugins
# ---------------------------------------------------------------------------
section "Task 3: Activate test plugins"

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
        $WP plugin activate "$plugin" 2>/dev/null && echo "  → Activated $plugin" || echo "  ✗ Failed to activate $plugin"
    fi
done

# ---------------------------------------------------------------------------
# Task 4: Import ACF Pro schemas (Movies + Recipes)
# ---------------------------------------------------------------------------
section "Task 4: Import ACF Pro field groups"

$WP eval "
    \$json = file_get_contents('/tmp/import-data/acf-export-2026-03-07.json');
    if (!\$json) { echo 'ERROR: Could not read ACF export file'; exit(1); }
    \$groups = json_decode(\$json, true);
    if (\$groups === null) { echo 'ERROR: Invalid JSON in ACF export'; exit(1); }
    if (isset(\$groups['key'])) { \$groups = [\$groups]; }
    \$counts = ['group' => 0, 'post_type' => 0, 'taxonomy' => 0];
    foreach (\$groups as \$item) {
        \$key = \$item['key'] ?? '';
        if (str_starts_with(\$key, 'post_type_') && function_exists('acf_import_post_type')) {
            acf_import_post_type(\$item);
            \$counts['post_type']++;
        } elseif (str_starts_with(\$key, 'taxonomy_') && function_exists('acf_import_taxonomy')) {
            acf_import_taxonomy(\$item);
            \$counts['taxonomy']++;
        } else {
            acf_import_field_group(\$item);
            \$counts['group']++;
        }
    }
    echo \$counts['group'] . ' field group(s), ' . \$counts['post_type'] . ' post type(s), ' . \$counts['taxonomy'] . ' taxonomy(ies) imported';
"

# ---------------------------------------------------------------------------
# Task 5: Import CPTUI schemas (Movies + Recipes)
# ---------------------------------------------------------------------------
section "Task 5: Import CPTUI post types and taxonomies"

$WP eval "
    // Import post types
    \$existing_cpts = get_option('cptui_post_types', []);
    \$new_cpts = json_decode(file_get_contents('/tmp/import-data/cptui-post-types.json'), true);
    if (\$new_cpts === null) { echo 'ERROR: Invalid JSON in cptui-post-types.json'; exit(1); }
    foreach (\$new_cpts as \$slug => \$cpt) {
        \$existing_cpts[\$slug] = \$cpt;
    }
    update_option('cptui_post_types', \$existing_cpts);
    echo count(\$new_cpts) . ' CPTUI post type(s) imported. ';

    // Import taxonomies
    \$existing_tax = get_option('cptui_taxonomies', []);
    \$tax_file = '/tmp/import-data/cptui-taxonomies.json';
    if (file_exists(\$tax_file)) {
        \$new_tax = json_decode(file_get_contents(\$tax_file), true);
        if (\$new_tax !== null) {
            foreach (\$new_tax as \$slug => \$tax) {
                \$existing_tax[\$slug] = \$tax;
            }
            update_option('cptui_taxonomies', \$existing_tax);
            echo count(\$new_tax) . ' CPTUI taxonomy(ies) imported.';
        }
    } else {
        echo 'No CPTUI taxonomies file found (skipping).';
    }
"

# ---------------------------------------------------------------------------
# Task 6: Import Meta Box schemas (Movies + Recipes)
# ---------------------------------------------------------------------------
section "Task 6: Import Meta Box schemas"

METABOX_FILES=(
    "/tmp/import-data/metabox-cpts.json"
    "/tmp/import-data/metabox-taxonomies.json"
    "/tmp/import-data/metabox-fields.json"
    "/tmp/import-data/metabox-relationships.json"
    "/tmp/import-data/Recipes/metabox-recipe-cpts.json"
    "/tmp/import-data/Recipes/metabox-recipe-taxonomies.json"
    "/tmp/import-data/Recipes/metabox-recipe-fields.json"
    "/tmp/import-data/Recipes/metabox-recipe-relationships.json"
)

for file in "${METABOX_FILES[@]}"; do
    basename=$(basename "$file")
    dirname=$(basename "$(dirname "$file")")
    label="$basename"
    if [ "$dirname" != "import-data" ]; then
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
            // Skip entries without required fields
            if (empty(\$post['post_type']) || empty(\$post['post_title'])) { continue; }
            // Skip duplicates by title + post_type
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

# ---------------------------------------------------------------------------
# Task 7: Import JetEngine schemas (Movies + Recipes)
# ---------------------------------------------------------------------------
section "Task 7: Import JetEngine schemas"

$WP eval "
    \$skin_file = WP_PLUGIN_DIR . '/jet-engine/includes/dashboard/skins-import.php';
    if (!file_exists(\$skin_file)) {
        echo 'ERROR: JetEngine skins-import.php not found';
        exit(1);
    }
    require_once \$skin_file;

    \$content = json_decode(file_get_contents('/tmp/import-data/jetengine-import.json'), true);
    if (\$content === null) {
        echo 'ERROR: Invalid JSON in jetengine-import.json';
        exit(1);
    }

    \$importer = new Jet_Engine_Skins_Import();

    if (!empty(\$content['post_types'])) {
        \$importer->import_post_types(\$content['post_types']);
        echo count(\$content['post_types']) . ' JetEngine post type(s) imported. ';
    }
    if (!empty(\$content['taxonomies'])) {
        \$importer->import_taxonomies(\$content['taxonomies']);
        echo count(\$content['taxonomies']) . ' JetEngine taxonomy(ies) imported. ';
    }
    if (!empty(\$content['meta_boxes'])) {
        \$importer->import_meta_boxes(\$content['meta_boxes']);
        echo count(\$content['meta_boxes']) . ' JetEngine meta box(es) imported. ';
    }
    if (!empty(\$content['relations'])) {
        \$importer->import_relations(\$content['relations']);
        echo count(\$content['relations']) . ' JetEngine relation(s) imported. ';
    }
    echo 'JetEngine import complete.';
"

# ---------------------------------------------------------------------------
# Task 8: Import ACPT schemas (Movies + Recipes)
# ---------------------------------------------------------------------------
section "Task 8: Import ACPT schemas"

$WP eval-file /tmp/acpt-import.php

# ---------------------------------------------------------------------------
# Task 9: Set default state
# ---------------------------------------------------------------------------
section "Task 9: Set default state"

DEACTIVATE_PLUGINS=(
    advanced-custom-post-type
    custom-post-type-ui
    jet-engine
    meta-box
    meta-box-aio
)

echo "Deactivating non-default plugins..."
for plugin in "${DEACTIVATE_PLUGINS[@]}"; do
    if $WP plugin is-active "$plugin" 2>/dev/null; then
        $WP plugin deactivate "$plugin" && echo "  → Deactivated $plugin"
    else
        echo "  ✓ $plugin (already inactive)"
    fi
done

echo "Keeping active: advanced-custom-fields-pro"
if ! $WP plugin is-active advanced-custom-fields-pro 2>/dev/null; then
    $WP plugin activate advanced-custom-fields-pro
fi

# Install WPfaker based on WPFAKER env var
case "${WPFAKER:-}" in
    local)
        echo "Activating WPfaker (local mount)..."
        $WP plugin activate wpfaker && echo "  → Activated wpfaker (local)"
        ;;
    zip)
        echo "Installing WPfaker from zip..."
        WPFAKER_ZIP=$(ls -t /home/emmgee/Projects/wpfaker/dist/wpfaker-*.zip 2>/dev/null | head -1)
        if [ -z "$WPFAKER_ZIP" ]; then
            echo "  ✗ No zip found in ~/Projects/wpfaker/dist/ — run 'npm run build' in wpfaker first"
        else
            docker cp "$WPFAKER_ZIP" wpt-wordpress:/tmp/wpfaker.zip
            $WP plugin install /tmp/wpfaker.zip --activate --force && echo "  → Installed wpfaker from $(basename "$WPFAKER_ZIP")"
        fi
        ;;
    *)
        echo "No WPfaker requested (use WPFAKER=local or WPFAKER=zip)"
        ;;
esac

echo "Flushing rewrite rules..."
$WP rewrite flush --hard

# ---------------------------------------------------------------------------
# Done
# ---------------------------------------------------------------------------
section "Provisioning complete"
echo "Active plugins:"
$WP plugin list --status=active --fields=name,version --format=table
echo ""
echo "Done! WordPress is ready at http://wpfaker-test.dv:8089"

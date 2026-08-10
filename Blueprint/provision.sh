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

# Check if a plugin (or prefix) is in the ACTIVATE_PLUGINS list
is_selected() {
    echo "${ACTIVATE_PLUGINS:-}" | grep -q "$1"
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
# Task 2b: Install WP library plugins
# ---------------------------------------------------------------------------
section "Task 2b: Install WP library plugins"

if is_selected "mb-custom-post-type"; then
    WP_LIBRARY_PLUGINS=(mb-custom-post-type mb-relationships)
    for plugin in "${WP_LIBRARY_PLUGINS[@]}"; do
        if $WP plugin is-installed "$plugin" 2>/dev/null; then
            echo "  ✓ $plugin (already installed)"
        else
            $WP plugin install "$plugin" && echo "  → Installed $plugin" || echo "  ✗ Failed to install $plugin"
        fi
    done
else
    echo "No WP library plugins needed for selected plugins."
fi

# ---------------------------------------------------------------------------
# Task 3: Activate all plugins for schema import
# ---------------------------------------------------------------------------
section "Task 3: Activate selected plugins for schema import"

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

if [ -n "${ACTIVATE_PLUGINS:-}" ]; then
    echo "Activating selected plugins..."
    IFS=',' read -ra SELECTED <<< "$ACTIVATE_PLUGINS"
    for plugin in "${SELECTED[@]}"; do
        plugin=$(echo "$plugin" | xargs)
        if $WP plugin is-active "$plugin" 2>/dev/null; then
            echo "  ✓ $plugin (already active)"
        else
            $WP plugin activate "$plugin" 2>/dev/null && echo "  → Activated $plugin" || echo "  ✗ $plugin not installed or failed"
        fi
    done
else
    echo "No plugins selected."
fi

# ---------------------------------------------------------------------------
# Task 4: Import ACF Pro schemas (Movies + Recipes)
# ---------------------------------------------------------------------------
section "Task 4: Import ACF Pro field groups"

if ! is_selected "advanced-custom-fields-pro"; then
    echo "ACF Pro not selected, skipping."
else
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
fi

# ---------------------------------------------------------------------------
# Task 5: Import CPTUI schemas (Movies + Recipes)
# ---------------------------------------------------------------------------
section "Task 5: Import CPTUI post types and taxonomies"

if ! is_selected "custom-post-type-ui"; then
    echo "CPTUI not selected, skipping."
else
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
fi

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

                \$settings = \$post['settings'] ?? null;
                unset(\$post['settings']);
                \$post['post_status'] = 'publish';

                if (\$settings && \$post['post_type'] === 'meta-box') {
                    // MB Builder reads 'meta_box' meta key (full config incl. fields)
                    // and 'settings' meta key (additional settings without fields)
                    \$meta_box_config = \$settings; // full config including fields
                    \$settings_only = \$settings;
                    unset(\$settings_only['fields']);
                    \$post['post_content'] = '';
                    \$post_id = wp_insert_post(\$post);
                    if (\$post_id && !is_wp_error(\$post_id)) {
                        update_post_meta(\$post_id, 'meta_box', \$meta_box_config);
                        update_post_meta(\$post_id, 'settings', \$settings_only);
                    }
                } else {
                    if (\$settings) {
                        \$post['post_content'] = wp_json_encode(\$settings, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                    }
                    wp_insert_post(\$post);
                }
                \$imported++;
            }
            echo '$label: ' . \$imported . ' imported, ' . (count(\$posts) - \$imported) . ' skipped (duplicates)';
        "
    done
fi

# ---------------------------------------------------------------------------
# Task 7: Import JetEngine schemas (Movies + Recipes)
# ---------------------------------------------------------------------------
section "Task 7: Import JetEngine schemas"

if ! is_selected "jet-engine"; then
    echo "JetEngine not selected, skipping."
else
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
fi

# ---------------------------------------------------------------------------
# Task 8: Import ACPT schemas (Movies + Recipes)
# ---------------------------------------------------------------------------
section "Task 8: Import ACPT schemas"

if ! is_selected "advanced-custom-post-type"; then
    echo "ACPT not selected, skipping."
else
$WP eval-file /tmp/acpt-import.php
fi

# ---------------------------------------------------------------------------
# Task 9: Set final plugin state
# ---------------------------------------------------------------------------
section "Task 9: Verify final plugin state"

echo "Selected plugins are already active from Task 3."

# Install WPfaker based on WPFAKER env var
case "${WPFAKER:-}" in
    local)
        echo "Activating WPfaker Premium (local mount)..."
        $WP plugin activate wpfaker && echo "  → Activated wpfaker (local)"
        ;;
    zip)
        echo "Installing WPfaker Premium from zip..."
        WPFAKER_ZIP=$(ls -t /home/emmgee/Projects/wpfaker/dist/wpfaker-*.zip 2>/dev/null | head -1)
        if [ -z "$WPFAKER_ZIP" ]; then
            echo "  ✗ No zip found in ~/Projects/wpfaker/dist/ — run 'npm run build' in wpfaker first"
        else
            docker cp "$WPFAKER_ZIP" wpt-wordpress:/tmp/wpfaker.zip
            $WP plugin install /tmp/wpfaker.zip --activate --force && echo "  → Installed wpfaker from $(basename "$WPFAKER_ZIP")"
        fi
        ;;
    free-local)
        echo "Activating Faker Studio Lite (local mount)..."
        $WP plugin activate faker-studio-lite && echo "  → Activated faker-studio-lite (local)"
        ;;
    free-zip)
        echo "Installing Faker Studio Lite from zip..."
        WPFAKER_ZIP=$(ls -t /home/mg/ownCloud/30-39-Business/31-Projects/31.11-WPFaker/wpfaker-free/dist/faker-studio-lite-*.zip 2>/dev/null | head -1)
        if [ -z "$WPFAKER_ZIP" ]; then
            echo "  ✗ No zip found in wpfaker-free/dist/ — run 'npm run build' in wpfaker-free first"
        else
            docker cp "$WPFAKER_ZIP" wpt-wordpress:/tmp/faker-studio-lite.zip
            $WP plugin install /tmp/faker-studio-lite.zip --activate --force && echo "  → Installed faker-studio-lite from $(basename "$WPFAKER_ZIP")"
        fi
        ;;
    *)
        echo "No WPfaker requested"
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

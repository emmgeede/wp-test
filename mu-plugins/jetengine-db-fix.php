<?php
/**
 * Plugin Name: JetEngine DB Fix (one-time)
 * Description: Fixes JetEngine data in DB: cleans duplicate imports, fixes meta_fields serialization, fixes rewrite format. Delete this file after it runs.
 */

// Run immediately as top-level code (BEFORE regular plugins load).
// Using add_action('init') was too late — JetEngine registers instances earlier.

if (get_option('_jetengine_db_fix_done')) {
    return;
}

global $wpdb;

$log = [];

// 1. Fix jet_post_types table - remove duplicates, fix meta_fields and rewrite
$table_pt = $wpdb->prefix . 'jet_post_types';
if ($wpdb->get_var("SHOW TABLES LIKE '$table_pt'") === $table_pt) {
    $rows = $wpdb->get_results("SELECT * FROM $table_pt", ARRAY_A);
    $seen_slugs = [];
    $deleted = 0;
    $fixed = 0;

    foreach ($rows as $row) {
        $slug = $row['slug'];

        // Delete duplicates - keep only the latest (highest id)
        if (isset($seen_slugs[$slug])) {
            $wpdb->delete($table_pt, ['id' => $seen_slugs[$slug]]);
            $deleted++;
        }
        $seen_slugs[$slug] = $row['id'];

        // Fix meta_fields: set to empty string (not serialized empty array)
        $meta_fields = $row['meta_fields'];
        if ($meta_fields === 'a:0:{}' || $meta_fields === 'b:0;' || $meta_fields === 'N;') {
            $wpdb->update($table_pt, ['meta_fields' => ''], ['id' => $row['id']]);
            $fixed++;
        }

        // Fix rewrite in args: ensure rewrite_slug exists
        $args = maybe_unserialize($row['args']);
        if (is_array($args)) {
            $changed = false;

            if (isset($args['rewrite']) && is_array($args['rewrite'])) {
                if (isset($args['rewrite']['slug']) && !isset($args['rewrite_slug'])) {
                    $args['rewrite_slug'] = $args['rewrite']['slug'];
                }
                $args['rewrite'] = true;
                $changed = true;
            }

            if (!empty($args['rewrite']) && !isset($args['rewrite_slug'])) {
                $args['rewrite_slug'] = $slug;
                $changed = true;
            }

            if ($changed) {
                $wpdb->update($table_pt, ['args' => maybe_serialize($args)], ['id' => $row['id']]);
                $fixed++;
            }
        }
    }
    $log[] = "post_types: deleted $deleted duplicates, fixed $fixed rows";
}

// 2. Fix jet_taxonomies table - same treatment
$table_tax = $wpdb->prefix . 'jet_taxonomies';
if ($wpdb->get_var("SHOW TABLES LIKE '$table_tax'") === $table_tax) {
    $rows = $wpdb->get_results("SELECT * FROM $table_tax", ARRAY_A);
    $seen_slugs = [];
    $deleted = 0;
    $fixed = 0;

    foreach ($rows as $row) {
        $slug = $row['slug'];

        if (isset($seen_slugs[$slug])) {
            $wpdb->delete($table_tax, ['id' => $seen_slugs[$slug]]);
            $deleted++;
        }
        $seen_slugs[$slug] = $row['id'];

        $meta_fields = $row['meta_fields'];
        if ($meta_fields === 'a:0:{}' || $meta_fields === 'b:0;' || $meta_fields === 'N;') {
            $wpdb->update($table_tax, ['meta_fields' => ''], ['id' => $row['id']]);
            $fixed++;
        }

        $args = maybe_unserialize($row['args']);
        if (is_array($args)) {
            $changed = false;

            if (isset($args['rewrite']) && is_array($args['rewrite'])) {
                if (isset($args['rewrite']['slug']) && !isset($args['rewrite_slug'])) {
                    $args['rewrite_slug'] = $args['rewrite']['slug'];
                }
                $args['rewrite'] = true;
                $changed = true;
            }

            if (!empty($args['rewrite']) && !isset($args['rewrite_slug'])) {
                $args['rewrite_slug'] = $slug;
                $changed = true;
            }

            if ($changed) {
                $wpdb->update($table_tax, ['args' => maybe_serialize($args)], ['id' => $row['id']]);
                $fixed++;
            }
        }
    }
    $log[] = "taxonomies: deleted $deleted duplicates, fixed $fixed rows";
}

// 3. Fix meta_boxes in wp_options - ensure meta_fields key exists on every entry
$option_name = 'jet_engine_meta_boxes';
$meta_boxes = get_option($option_name, []);
if (!empty($meta_boxes) && is_array($meta_boxes)) {
    $fixed = 0;
    foreach ($meta_boxes as $id => &$meta_box) {
        if (!isset($meta_box['meta_fields'])) {
            $meta_box['meta_fields'] = [];
            $fixed++;
        }
    }
    unset($meta_box);
    if ($fixed > 0) {
        update_option($option_name, $meta_boxes);
    }
    $log[] = "meta_boxes: fixed $fixed entries missing meta_fields";
}

// Mark as done
update_option('_jetengine_db_fix_done', implode('; ', $log));

// Show admin notice on next page load
add_action('admin_notices', function () use ($log) {
    echo '<div class="notice notice-success"><p><strong>JetEngine DB Fix:</strong> ' . esc_html(implode(' | ', $log)) . '</p><p>You can now delete <code>mu-plugins/jetengine-db-fix.php</code></p></div>';
});

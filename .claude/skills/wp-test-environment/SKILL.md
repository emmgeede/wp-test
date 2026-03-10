---
name: wp-test-environment
description: Spin up a fresh WordPress test environment from Blueprint. Installs all test plugins, imports CPT data, builds and copies wpfaker, opens in browser.
---

# WP Test Environment

Creates a clean WordPress environment for testing WPFaker and recording videos.

## Trigger

User invokes `/wp-test-environment` or asks to "set up the test environment" / "fresh WP install".

## Process

### 1. Stop and clean any existing Docker instance

```bash
cd ~/Projects/wp-test/Docker
docker compose down -v 2>/dev/null || true
rm -rf ~/Projects/wp-test/Docker/*
```

### 2. Copy Blueprint to Docker

```bash
cp ~/Projects/wp-test/Blueprint/docker-compose.yml ~/Projects/wp-test/Docker/
cp ~/Projects/wp-test/Blueprint/Caddyfile ~/Projects/wp-test/Docker/
cp ~/Projects/wp-test/Blueprint/wp-setup.sh ~/Projects/wp-test/Docker/
```

### 3. Start Docker containers

```bash
cd ~/Projects/wp-test/Docker
docker compose up -d
```

Wait for WordPress to be ready:
```bash
until docker exec wpt-wordpress test -f /var/www/html/wp-includes/version.php 2>/dev/null; do
    sleep 2
done
```

### 4. Install WP-CLI in the WordPress container

The wordpress:fpm image does not include WP-CLI. Install it:
```bash
docker exec wpt-wordpress bash -c 'curl -sO https://raw.githubusercontent.com/wp-cli/builds/gh-pages/phar/wp-cli.phar && chmod +x wp-cli.phar && mv wp-cli.phar /usr/local/bin/wp'
```

### 5. Run WordPress setup

Copy wp-setup.sh into the container and execute it:
```bash
docker cp ~/Projects/wp-test/Docker/wp-setup.sh wpt-wordpress:/var/www/html/wp-setup.sh
docker exec wpt-wordpress bash /var/www/html/wp-setup.sh
```

### 6. Copy test plugins into WordPress

```bash
for plugin in advanced-custom-fields-pro meta-box meta-box-aio jet-engine advanced-custom-post-type custom-post-type-ui; do
    docker cp ~/Projects/wp-test/Testplugins/$plugin wpt-wordpress:/var/www/html/wp-content/plugins/$plugin
done
```

Fix file ownership (wordpress container runs as www-data):
```bash
docker exec wpt-wordpress chown -R www-data:www-data /var/www/html/wp-content/plugins/
```

### 7. Activate all plugins

```bash
docker exec wpt-wordpress wp plugin activate \
    advanced-custom-fields-pro \
    meta-box \
    meta-box-aio \
    jet-engine \
    advanced-custom-post-type \
    custom-post-type-ui \
    --allow-root
```

### 8. Import CPT data

Import data for each plugin. Copy all import files into the container first:

```bash
docker cp ~/Projects/wp-test/Import-Data/acf-export-2026-03-07.json wpt-wordpress:/tmp/acf-import.json
docker cp ~/Projects/wp-test/Import-Data/cptui-post-types.json wpt-wordpress:/tmp/cptui-post-types.json
docker cp ~/Projects/wp-test/Import-Data/cptui-taxonomies.json wpt-wordpress:/tmp/cptui-taxonomies.json
docker cp ~/Projects/wp-test/Import-Data/jetengine-import.json wpt-wordpress:/tmp/jetengine-import.json
for file in metabox-cpts.json metabox-fields.json metabox-taxonomies.json metabox-relationships.json; do
    docker cp ~/Projects/wp-test/Import-Data/$file wpt-wordpress:/tmp/$file
done
```

**ACF Pro** (field groups with CPTs) — uses PHP API, not WP-CLI:
```bash
docker exec wpt-wordpress wp eval '
    $json = file_get_contents("/tmp/acf-import.json");
    $groups = json_decode($json, true);
    foreach ($groups as $group) {
        acf_import_field_group($group);
    }
    echo "Imported " . count($groups) . " ACF field groups.\n";
' --allow-root
```

**CPTUI** (post types and taxonomies):
```bash
docker exec wpt-wordpress wp eval '
    $data = json_decode(file_get_contents("/tmp/cptui-post-types.json"), true);
    update_option("cptui_post_types", $data);
    echo "Imported " . count($data) . " CPTUI post types.\n";
' --allow-root

docker exec wpt-wordpress wp eval '
    $data = json_decode(file_get_contents("/tmp/cptui-taxonomies.json"), true);
    update_option("cptui_taxonomies", $data);
    echo "Imported " . count($data) . " CPTUI taxonomies.\n";
' --allow-root
```

**JetEngine** (post types, taxonomies, meta fields):
```bash
docker exec wpt-wordpress wp eval '
    if (function_exists("jet_engine")) {
        $data = json_decode(file_get_contents("/tmp/jetengine-import.json"), true);
        if (!empty($data["post_types"])) {
            foreach ($data["post_types"] as $pt) {
                jet_engine()->cpt->data->update_item_in_db($pt);
            }
            echo "Imported " . count($data["post_types"]) . " JetEngine post types.\n";
        }
        if (!empty($data["taxonomies"])) {
            foreach ($data["taxonomies"] as $tax) {
                jet_engine()->taxonomies->data->update_item_in_db($tax);
            }
            echo "Imported " . count($data["taxonomies"]) . " JetEngine taxonomies.\n";
        }
        if (!empty($data["meta_boxes"])) {
            foreach ($data["meta_boxes"] as $mb) {
                jet_engine()->meta_boxes->data->update_item_in_db($mb);
            }
            echo "Imported " . count($data["meta_boxes"]) . " JetEngine meta boxes.\n";
        }
    }
' --allow-root
```

**Meta Box** (CPTs, fields, taxonomies, relationships):
```bash
docker exec wpt-wordpress wp eval '
    $files = [
        "/tmp/metabox-cpts.json",
        "/tmp/metabox-fields.json",
        "/tmp/metabox-taxonomies.json",
        "/tmp/metabox-relationships.json",
    ];
    foreach ($files as $file) {
        if (!file_exists($file)) continue;
        $items = json_decode(file_get_contents($file), true);
        if (!$items) continue;
        foreach ($items as $item) {
            $post_type = $item["post_type"] ?? "mb-post-type";
            $post_data = [
                "post_title"   => $item["post_title"] ?? "",
                "post_type"    => $post_type,
                "post_status"  => "publish",
                "post_content" => isset($item["post_content"]) ? $item["post_content"] : "",
                "post_date"    => $item["post_date"] ?? current_time("mysql"),
            ];
            $post_id = wp_insert_post($post_data);
            if (!is_wp_error($post_id) && !empty($item["meta"])) {
                foreach ($item["meta"] as $key => $value) {
                    update_post_meta($post_id, $key, $value);
                }
            }
        }
        echo "Imported " . count($items) . " items from " . basename($file) . "\n";
    }
' --allow-root
```

### 9. Deactivate all plugins

```bash
docker exec wpt-wordpress wp plugin deactivate --all --allow-root
```

### 10. Build and copy WPFaker

Build a fresh wpfaker zip from the current source:
```bash
cd ~/Projects/wpfaker
npm run build
```

If a `dist/` folder with a zip exists, use that. Otherwise create one:
```bash
cd ~/Projects/wpfaker
WPFAKER_ZIP=$(ls -t dist/wpfaker-*.zip 2>/dev/null | head -1)

if [ -z "$WPFAKER_ZIP" ]; then
    npm run zip 2>/dev/null || npm run package 2>/dev/null || (
        mkdir -p /tmp/wpfaker-build
        rsync -a --exclude='node_modules' --exclude='.git' --exclude='tests' --exclude='src' . /tmp/wpfaker-build/wpfaker/
        cd /tmp && zip -r wpfaker.zip wpfaker/
        WPFAKER_ZIP="/tmp/wpfaker.zip"
    )
fi

docker cp "$WPFAKER_ZIP" wpt-wordpress:/var/www/html/wp-content/plugins/wpfaker.zip
```

### 11. Flush rewrite rules

```bash
docker exec wpt-wordpress wp rewrite flush --allow-root
```

### 12. Verify /etc/hosts

Ensure `wpfaker-test.dv` is in `/etc/hosts`:
```bash
grep -q "wpfaker-test.dv" /etc/hosts || echo '127.0.0.1 wpfaker-test.dv' | sudo tee -a /etc/hosts
```

### 13. Open in browser

```bash
xdg-open "http://wpfaker-test.dv/wp-admin" 2>/dev/null || firefox "http://wpfaker-test.dv/wp-admin" &
```

## Verification Checklist

After the environment is up, verify:
- [ ] `http://wpfaker-test.dv` loads WordPress
- [ ] Admin login works at `http://wpfaker-test.dv/wp-admin/` (admin/admin)
- [ ] All test plugins are visible in Plugins page (deactivated)
- [ ] `wpfaker.zip` is present in `/wp-content/plugins/`
- [ ] CPT data is imported (will be visible once plugins are activated)

## Cleanup

To tear down completely:
```bash
cd ~/Projects/wp-test/Docker
docker compose down -v
rm -rf ~/Projects/wp-test/Docker/*
touch ~/Projects/wp-test/Docker/.gitkeep
```

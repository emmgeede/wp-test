<?php
/**
 * Shim: expose wp.i18n.sprintf as global sprintf for Meta Box Builder 5.1.x
 *
 * MBB standalone 5.1.1 has a bug where one call site references bare `sprintf`
 * instead of the module-scoped import. Fixed in 5.2.1 (shipped with AIO).
 * This shim can be removed once the standalone plugin is updated.
 */
add_action('admin_enqueue_scripts', function () {
    wp_add_inline_script('wp-i18n', 'window.sprintf = window.sprintf || wp.i18n.sprintf;');
});

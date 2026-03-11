<?php
/**
 * Plugin Name: WPfaker Branding
 * Description: Replaces WP branding with WPfaker logo in login and admin bar.
 */

// Login page logo
add_action('login_enqueue_scripts', function () {
    ?>
    <style>
        #login h1 a, .login h1 a {
            background-image: url('<?php echo esc_url(content_url('/mu-plugins/wpfaker-logo.svg')); ?>');
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
    return admin_url();
});

add_filter('login_headertext', function () {
    return 'WPfaker';
});

// Replace WP logo in admin bar with WPfaker logo
add_action('admin_bar_menu', function ($wp_admin_bar) {
    $wp_admin_bar->remove_node('wp-logo');
    $wp_admin_bar->remove_node('site-name');
}, 999);

add_action('admin_bar_menu', function ($wp_admin_bar) {
    $logo = '<img src="' . esc_url(content_url('/mu-plugins/wpfaker-logo.svg')) . '" style="height:22px;vertical-align:middle;" alt="WPfaker">';
    $wp_admin_bar->add_node([
        'id'    => 'wpfaker-logo',
        'title' => $logo,
        'href'  => admin_url(),
        'meta'  => ['class' => 'menupop'],
    ]);
}, 0);

// CSS to ensure first position and styling
add_action('admin_head', 'wpfaker_admin_bar_css');
add_action('wp_head', 'wpfaker_admin_bar_css');
function wpfaker_admin_bar_css() {
    ?>
    <style>
        #wpadminbar #wp-admin-bar-wpfaker-logo {
            order: -1;
        }
        #wpadminbar #wp-admin-bar-wpfaker-logo > .ab-item {
            color: #fff !important;
            padding: 0 12px !important;
        }
        #wpadminbar #wp-admin-bar-wpfaker-logo > .ab-item:hover {
            color: #44CEFF !important;
        }
        #wpadminbar #wp-admin-bar-wpfaker-logo img {
            height: 22px;
            vertical-align: middle;
        }
    </style>
    <?php
}

<?php
/**
 * Plugin Name: WPfaker Login Logo
 * Description: Replaces the WP login logo with the WPfaker logo and adds admin bar branding.
 */

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
    return 'https://wpfaker.com';
});

add_filter('login_headertext', function () {
    return 'WPfaker';
});

add_action('admin_bar_menu', function ($wp_admin_bar) {
    $wp_admin_bar->add_node([
        'id'    => 'wpfaker-logo',
        'title' => '<img src="' . esc_url(content_url('/mu-plugins/wpfaker-logo.svg')) . '" style="height:20px;vertical-align:middle;margin-right:5px;" alt="WPfaker"> WPfaker Test',
        'href'  => 'https://wpfaker.com',
        'meta'  => ['class' => 'wpfaker-admin-logo'],
    ]);
}, 1);

<?php
/**
 * Auto-login as admin for the wp-test dev environment.
 * Logs in automatically when visiting wp-admin while not authenticated.
 */
add_action('init', function () {
    if (is_user_logged_in()) {
        return;
    }

    // Only trigger on admin/login pages
    $request = $_SERVER['REQUEST_URI'] ?? '';
    if (strpos($request, '/wp-admin') === false && strpos($request, '/wp-login.php') === false) {
        return;
    }

    $user = get_user_by('login', 'admin');
    if (! $user) {
        return;
    }

    wp_set_current_user($user->ID);
    wp_set_auth_cookie($user->ID, true);

    // Redirect to wp-admin if on login page
    if (strpos($request, '/wp-login.php') !== false) {
        wp_safe_redirect(admin_url());
        exit;
    }
});

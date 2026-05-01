<?php
/**
 * Auto-login as admin for the wp-test dev environment.
 * Logs in automatically when visiting wp-admin while not authenticated.
 */
add_action('init', function () {
    $request = $_SERVER['REQUEST_URI'] ?? '';

    // Only trigger on admin/login pages
    if (strpos($request, '/wp-admin') === false && strpos($request, '/wp-login.php') === false) {
        return;
    }

    // Already logged in — handle reauth redirect loop
    if (is_user_logged_in()) {
        if (strpos($request, '/wp-login.php') !== false && isset($_GET['reauth'])) {
            wp_safe_redirect(admin_url());
            exit;
        }
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

<?php
// A local HTTP harness models the external TLS terminator; production has no such override.
$_SERVER['HTTPS'] = 'on'; $_SERVER['HTTP_HOST'] = 'wp.frica.test'; $_SERVER['SERVER_PORT'] = '443';
require rtrim(getenv('WP_ROOT'), '/') . '/wp-load.php';
require __DIR__ . '/provider.php';
foreach (['_maybe_update_core', '_maybe_update_plugins', '_maybe_update_themes'] as $callback) { remove_action('admin_init', $callback); }
// Do not deliver test account notification mail.
add_filter('pre_wp_mail', '__return_true');
$path = wp_parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
if ($path === '/wp-admin/admin-post.php') { require ABSPATH . 'wp-admin/admin-post.php'; }
elseif ($path === '/whoami') { header('Content-Type: application/json'); echo wp_json_encode(['user' => get_current_user_id()]); }
elseif ($path === '/login-button') { echo do_shortcode('[frica_id_login redirect_to="/members/"]'); }
else { status_header(404); echo 'Not found'; }

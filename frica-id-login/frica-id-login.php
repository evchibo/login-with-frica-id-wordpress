<?php
/**
 * Plugin Name: Login with Frica ID
 * Plugin URI: https://github.com/evchibo/login-with-frica-id-wordpress
 * Description: Secure Frica ID sign-in and account linking with OpenID Connect and PKCE.
 * Version: 1.0.0
 * Requires at least: 6.6
 * Requires PHP: 8.0
 * Author: Frica
 * Author URI: https://frica.id
 * License: GPL-2.0-or-later
 * Text Domain: frica-id-login
 */

if (!defined('ABSPATH')) { exit; }

define('FRICA_ID_LOGIN_VERSION', '1.0.0');
define('FRICA_ID_LOGIN_FILE', __FILE__);

// A private namespace prevents conflicts with JWT libraries in other plugins.
spl_autoload_register(static function ($class) {
    $prefix = 'FricaID\\Vendor\\Firebase\\JWT\\';
    if (strpos($class, $prefix) === 0) {
        $name = substr($class, strlen($prefix));
        if (preg_match('/^[A-Za-z]+$/D', $name)) {
            $path = __DIR__ . '/vendor/php-jwt/' . $name . '.php';
            if (is_file($path)) { require_once $path; }
        }
    }
});

require_once __DIR__ . '/includes/class-store.php';
require_once __DIR__ . '/includes/class-oidc.php';
require_once __DIR__ . '/includes/class-plugin.php';
register_activation_hook(__FILE__, ['FricaID\\WordPress\\Plugin', 'activate']);
register_deactivation_hook(__FILE__, ['FricaID\\WordPress\\Plugin', 'deactivate']);
add_action('plugins_loaded', static function () { (new FricaID\WordPress\Plugin())->register(); });

<?php
// Isolated, disposable WordPress installation only. Never point this at a real site.
$root = rtrim(getenv('WP_ROOT') ?: '', '/');
if (!$root || !is_file($root . '/wp-load.php')) { throw new RuntimeException('WP_ROOT must point to downloaded WordPress'); }
$config = "<?php\n";
foreach ([
    'DB_NAME' => getenv('WP_TEST_DB_NAME') ?: 'frica_wordpress_test',
    'DB_USER' => getenv('WP_TEST_DB_USER') ?: 'root',
    'DB_PASSWORD' => getenv('WP_TEST_DB_PASSWORD') ?: '',
    'DB_HOST' => getenv('WP_TEST_DB_HOST') ?: '127.0.0.1:3306',
    'DB_CHARSET' => 'utf8mb4', 'DB_COLLATE' => '',
    'WP_HOME' => 'https://wp.frica.test', 'WP_SITEURL' => 'https://wp.frica.test',
    'AUTH_KEY' => bin2hex(random_bytes(32)), 'SECURE_AUTH_KEY' => bin2hex(random_bytes(32)),
    'LOGGED_IN_KEY' => bin2hex(random_bytes(32)), 'NONCE_KEY' => bin2hex(random_bytes(32)),
    'AUTH_SALT' => bin2hex(random_bytes(32)), 'SECURE_AUTH_SALT' => bin2hex(random_bytes(32)),
    'LOGGED_IN_SALT' => bin2hex(random_bytes(32)), 'NONCE_SALT' => bin2hex(random_bytes(32)),
] as $key => $value) { $config .= 'define(' . var_export($key, true) . ', ' . var_export($value, true) . ");\n"; }
$config .= "define('WP_DEBUG', true);\ndefine('DISABLE_WP_CRON', true);\ndefine('WP_ENVIRONMENT_TYPE', 'local');\n\$table_prefix = 'wp_';\nrequire_once __DIR__ . '/wp-settings.php';\n";
file_put_contents($root . '/wp-config.php', $config);
$_SERVER['HTTPS'] = 'on'; $_SERVER['HTTP_HOST'] = 'wp.frica.test'; $_SERVER['SERVER_PORT'] = '443';
define('WP_INSTALLING', true);
require $root . '/wp-load.php';
require_once ABSPATH . 'wp-admin/includes/upgrade.php';
add_filter('pre_wp_mail', '__return_true');
if (!is_blog_installed()) { wp_install('Frica Plugin Test', 'site-admin', 'admin@example.test', true, '', wp_generate_password(32)); }
require_once dirname(__DIR__) . '/frica-id-login/frica-id-login.php';
FricaID\WordPress\Plugin::activate();
update_option('active_plugins', ['frica-id-login/frica-id-login.php']);
update_option('frica_id_settings', ['enabled' => 1, 'client_id' => 'fcli_wordpress_suite', 'registration' => 1]);
update_option('users_can_register', 1);
$resource = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
openssl_pkey_export($resource, $private);
file_put_contents(dirname($root) . '/test-signing.pem', $private);
echo 'Installed WordPress ' . get_bloginfo('version') . " and activated plugin\n";

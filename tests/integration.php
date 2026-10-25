<?php
$_SERVER['HTTPS'] = 'on'; $_SERVER['HTTP_HOST'] = 'wp.frica.test'; $_SERVER['SERVER_PORT'] = '443';
require rtrim(getenv('WP_ROOT'), '/') . '/wp-load.php';
require __DIR__ . '/provider.php';
add_filter('pre_wp_mail', '__return_true');
use FricaID\WordPress\Plugin;
use FricaID\WordPress\Store;
use FricaID\WordPress\OIDC;

$count = 0;
function check($condition, string $message): void {
    global $count;
    if (!$condition) { throw new RuntimeException('FAIL: ' . $message); }
    $count++; echo 'PASS: ' . $message . "\n";
}
function http(string $path, array $cookies = [], array $post = []): array {
    $handle = curl_init('http://127.0.0.1:' . (getenv('WP_TEST_PORT') ?: '8787') . $path);
    $headers = [];
    curl_setopt_array($handle, [CURLOPT_RETURNTRANSFER => true, CURLOPT_FOLLOWLOCATION => false, CURLOPT_TIMEOUT => 30,
        CURLOPT_HTTPHEADER => ['Host: wp.frica.test'], CURLOPT_COOKIE => implode('; ', $cookies),
        CURLOPT_HEADERFUNCTION => static function ($handle, $line) use (&$headers) {
            if (strpos($line, ':') !== false) { [$key, $value] = explode(':', $line, 2); $headers[strtolower(trim($key))][] = trim($value); }
            return strlen($line);
        },
    ]);
    if ($post) { curl_setopt($handle, CURLOPT_POSTFIELDS, http_build_query($post)); }
    $body = curl_exec($handle);
    if ($body === false) { throw new RuntimeException(curl_error($handle)); }
    return ['body' => $body, 'headers' => $headers, 'status' => curl_getinfo($handle, CURLINFO_RESPONSE_CODE)];
}
function begin(array $cookies = [], array $post = []): array {
    $response = http('/wp-admin/admin-post.php?action=frica_id_start&redirect_to=%2Fmembers%2F', $cookies, $post);
    $location = $response['headers']['location'][0] ?? '';
    parse_str(wp_parse_url($location, PHP_URL_QUERY) ?: '', $query);
    return [$response, $query];
}
function spec(array $query, string $code, string $sub, string $email, array $extra = []): void {
    update_option('frica_suite_' . $code, array_merge(['nonce' => $query['nonce'], 'challenge' => $query['code_challenge'], 'sub' => $sub, 'email' => $email], $extra));
}
function finish(array $response, array $query, string $code, array $extra_cookies = []): array {
    $cookie = explode(';', $response['headers']['set-cookie'][0])[0];
    return http('/wp-admin/admin-post.php?action=frica_id_callback&state=' . rawurlencode($query['state']) . '&code=' . rawurlencode($code), array_merge([$cookie], $extra_cookies));
}
function login_cookies(int $id): array {
    $expiration = time() + 3600;
    $session = WP_Session_Tokens::get_instance($id)->create($expiration);
    return [SECURE_AUTH_COOKIE . '=' . wp_generate_auth_cookie($id, $expiration, 'secure_auth', $session), LOGGED_IN_COOKIE . '=' . wp_generate_auth_cookie($id, $expiration, 'logged_in', $session)];
}

$plugin = new Plugin();
check(OIDC::challenge('dBjftJeZ4CVP-mB92K27uhbUJU1p1r_wW1gFWFOEjXk') === 'E9Melhoa2OwvFrEMTJguCHaoeK1t8URWbuGJSstw-cM', 'PKCE matches RFC 7636 test vector');
check(Plugin::local_redirect('https://attacker.example/steal') === home_url('/'), 'cross-origin redirect rejected');
check(Plugin::local_redirect('//attacker.example') === home_url('/'), 'protocol-relative redirect rejected');
check(Plugin::local_redirect('/members/') === home_url('/members/'), 'same-origin redirect preserved');
$state = OIDC::random(); $browser = OIDC::random();
check(Store::save_flow($state, $browser, ['value' => 1]), 'transaction stored in real database');
check(Store::consume_flow($state, 'wrong') === null, 'browser binding enforced');
check(Store::consume_flow($state, $browser) === ['value' => 1], 'bound transaction consumed');
check(Store::consume_flow($state, $browser) === null, 'transaction cannot be replayed');

[$response, $query] = begin();
if ($response['status'] !== 302 || empty($query['state'])) { fwrite(STDERR, json_encode($response) . PHP_EOL); }
check($response['status'] === 302 && strpos($response['headers']['location'][0], OIDC::API . '/oauth/authorize?') === 0, 'login starts at fixed provider');
check($query['scope'] === 'openid profile email' && $query['code_challenge_method'] === 'S256', 'minimal scopes and PKCE sent');
check(stripos($response['headers']['set-cookie'][0], 'secure') !== false && stripos($response['headers']['set-cookie'][0], 'HttpOnly') !== false && stripos($response['headers']['set-cookie'][0], 'SameSite=Lax') !== false, 'secure HttpOnly SameSite browser cookie');
check(($response['headers']['referrer-policy'][0] ?? '') === 'no-referrer', 'callback referrer protection');
update_option('default_role', 'administrator'); // A privileged site default must never affect social registration.
$sub = 'test-private-subject-1';
spec($query, 'new-user', $sub, 'new-member@example.test');
$result = finish($response, $query, 'new-user');
check(($result['headers']['location'][0] ?? '') === home_url('/members/'), 'complete authorization callback redirects locally');
$id = Store::linked_user($sub); $user = get_userdata($id);
check($user instanceof WP_User && $user->roles === ['subscriber'], 'new account gets Subscriber role only');
check(strpos($user->user_login, $sub) === false && $user->display_name === 'Frica Member', 'internal subject never displayed as username or name');
check(get_option('frica_suite_revoked') === 'refresh-new-user', 'unneeded refresh token revoked');
$auth = [];
foreach ($result['headers']['set-cookie'] ?? [] as $cookie) {
    $value = explode(';', $cookie)[0]; if (strpos($value, 'wordpress_') === 0) { $auth[] = $value; }
}
$identity = json_decode(http('/whoami', $auth)['body'], true);
check(($identity['user'] ?? 0) === $id, 'WordPress auth cookies establish a real session');
$replay = finish($response, $query, 'new-user');
check(strpos($replay['headers']['location'][0] ?? '', 'frica_id_error=expired') !== false, 'callback replay fails');

foreach ([
    'issuer' => ['claims' => ['iss' => 'https://evil.example']],
    'audience' => ['claims' => ['aud' => 'another-client']],
    'azp' => ['claims' => ['aud' => ['fcli_wordpress_suite', 'other'], 'azp' => 'other']],
    'expiry' => ['claims' => ['exp' => time() - 20]],
    'nonce' => ['claims' => ['nonce' => 'attacker-nonce']],
    'token_use' => ['claims' => ['token_use' => 'access']],
    'signature' => ['tamper' => true],
    'subject' => ['profile' => ['sub' => 'another-person']],
    'verified_email' => ['profile' => ['email_verified' => false]],
] as $name => $extra) {
    [$start, $q] = begin(); spec($q, 'reject-' . $name, $sub, 'new-member@example.test', $extra);
    $rejected = finish($start, $q, 'reject-' . $name);
    check(strpos($rejected['headers']['location'][0] ?? '', 'frica_id_error=') !== false, 'rejects invalid ' . $name);
    check(count(array_filter($rejected['headers']['set-cookie'] ?? [], static function ($c) { return strpos($c, 'wordpress_sec_') === 0; })) === 0, 'no WordPress session for invalid ' . $name);
}

$admin = get_user_by('login', 'site-admin');
[$start, $q] = begin(); spec($q, 'email-conflict', 'other-subject', $admin->user_email);
$conflict = finish($start, $q, 'email-conflict');
check(strpos($conflict['headers']['location'][0] ?? '', 'frica_id_error=email_exists') !== false, 'email match cannot take over existing administrator');
check(Store::linked_user('other-subject') === 0, 'conflicting email never attaches');
$settings = Plugin::options(); update_option('frica_id_settings', array_merge($settings, ['registration' => 0]));
[$start, $q] = begin(); spec($q, 'closed-registration', 'new-closed', 'closed@example.test');
$closed = finish($start, $q, 'closed-registration');
check(strpos($closed['headers']['location'][0] ?? '', 'registration_disabled') !== false, 'registration disabled is respected');
[$start, $q] = begin(); spec($q, 'existing-login', $sub, 'new-member@example.test');
$again = finish($start, $q, 'existing-login');
check(($again['headers']['location'][0] ?? '') === home_url('/members/'), 'linked member can sign in when registration closed');
update_option('frica_id_settings', $settings);

// Exercise account linking and disconnection through the actual authenticated endpoints.
$admin_cookies = login_cookies($admin->ID);
foreach ($admin_cookies as $cookie) { [$key, $value] = explode('=', $cookie, 2); $_COOKIE[$key] = $value; }
wp_set_current_user($admin->ID);
$link_nonce = wp_create_nonce('frica_id_link');
$bad_link = http('/wp-admin/admin-post.php?action=frica_id_start', $admin_cookies, ['_wpnonce' => 'invalid']);
check($bad_link['status'] === 403 && !Store::is_linked($admin->ID), 'account linking rejects CSRF');
[$changed_start, $changed_q] = begin($admin_cookies, ['_wpnonce' => $link_nonce]);
$changed = finish($changed_start, $changed_q, 'unused-code');
check(strpos($changed['headers']['location'][0] ?? '', 'session_changed') !== false && !Store::is_linked($admin->ID), 'link callback rejects changed WordPress session');
[$start, $q] = begin($admin_cookies, ['_wpnonce' => $link_nonce]);
check(!empty($q['nonce']), 'authenticated user can start linking with valid nonce');
spec($q, 'admin-link', 'explicit-admin-link', 'different@example.test');
$linked_response = finish($start, $q, 'admin-link', $admin_cookies);
check(strpos($linked_response['headers']['location'][0] ?? '', 'frica_id_notice=connected') !== false, 'link callback confirms connection');
$linked = get_userdata($admin->ID);
check(Store::linked_user('explicit-admin-link') === $admin->ID && in_array('administrator', $linked->roles, true), 'explicit link preserves existing roles');
check(!Store::link($id, 'explicit-admin-link'), 'database prevents one provider identity linking to two users');
check(!Store::link($admin->ID, 'third-subject'), 'database prevents one WordPress user linking twice');
$bad_disconnect = http('/wp-admin/admin-post.php?action=frica_id_disconnect', $admin_cookies, ['_wpnonce' => 'invalid']);
check($bad_disconnect['status'] === 403 && Store::is_linked($admin->ID), 'disconnect rejects CSRF');
$disconnect = http('/wp-admin/admin-post.php?action=frica_id_disconnect', $admin_cookies, ['_wpnonce' => wp_create_nonce('frica_id_disconnect')]);
check(strpos($disconnect['headers']['location'][0] ?? '', 'frica_id_notice=disconnected') !== false, 'authenticated disconnect confirms result');
check(!Store::is_linked($admin->ID) && get_userdata($admin->ID) instanceof WP_User, 'disconnect retains WordPress account');
wp_set_current_user(0); $_COOKIE = [];

// Expires before consumption even if scheduled cleanup has not run yet.
global $wpdb;
$old = OIDC::random(); Store::save_flow($old, $browser, ['value' => 2]);
$wpdb->update(Store::table('flows'), ['expires_at' => time() - 1], ['state_hash' => hash('sha256', $old)]);
check(Store::consume_flow($old, $browser) === null, 'expired transaction rejected before cleanup');
Store::cleanup();
check(!(int) $wpdb->get_var($wpdb->prepare('SELECT COUNT(*) FROM ' . Store::table('flows') . ' WHERE state_hash = %s', hash('sha256', $old))), 'expired transaction deleted');

$button = $plugin->shortcode(['redirect_to' => 'https://attacker.example']);
check(strpos($button, 'Login with Frica ID') !== false && strpos($button, 'attacker.example') === false, 'shortcode renders safe button');
check(strpos($plugin->login_message(''), '<script>') === false, 'errors do not echo arbitrary request text');
$user_count = count_users()['total_users'];
define('WP_UNINSTALL_PLUGIN', 'frica-id-login/frica-id-login.php');
require dirname(__DIR__) . '/frica-id-login/uninstall.php';
check(get_option('frica_id_settings', null) === null && get_option('frica_id_schema', null) === null, 'uninstall removes plugin settings');
check(count_users()['total_users'] === $user_count, 'uninstall preserves WordPress users');
check(!$wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', Store::table('links'))), 'uninstall removes connection table');
echo "Completed $count WordPress integration assertions\n";

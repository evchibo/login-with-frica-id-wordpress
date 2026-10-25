<?php
namespace FricaID\WordPress;

if (!defined('ABSPATH')) { exit; }

final class Plugin {
    public static function activate(bool $network_wide = false): void {
        if ($network_wide) {
            wp_die(esc_html__('Activate Login with Frica ID separately on each site, rather than network-wide.', 'frica-id-login'));
        }
        Store::install();
        if (!wp_next_scheduled('frica_id_cleanup')) { wp_schedule_event(time() + 3600, 'hourly', 'frica_id_cleanup'); }
    }

    public static function deactivate(): void { wp_clear_scheduled_hook('frica_id_cleanup'); }

    public function register(): void {
        add_action('admin_menu', [$this, 'menu']);
        add_action('admin_notices', [$this, 'connection_notice']);
        add_action('admin_init', [$this, 'settings']);
        add_action('admin_post_frica_id_start', [$this, 'start']);
        add_action('admin_post_nopriv_frica_id_start', [$this, 'start']);
        add_action('admin_post_frica_id_callback', [$this, 'callback']);
        add_action('admin_post_nopriv_frica_id_callback', [$this, 'callback']);
        add_action('admin_post_frica_id_disconnect', [$this, 'disconnect']);
        add_action('login_form', [$this, 'login_button']);
        add_action('register_form', [$this, 'login_button']);
        add_filter('login_message', [$this, 'login_message']);
        add_shortcode('frica_id_login', [$this, 'shortcode']);
        add_action('show_user_profile', [$this, 'profile']);
        add_action('delete_user', [Store::class, 'unlink']);
        add_action('wpmu_delete_user', [Store::class, 'unlink']);
        add_action('frica_id_cleanup', [Store::class, 'cleanup']);
        add_action('login_enqueue_scripts', [$this, 'styles']);
        add_action('wp_enqueue_scripts', [$this, 'frontend_styles']);
        add_action('wp_footer', [$this, 'footer_styles']);
        add_action('admin_enqueue_scripts', [$this, 'admin_styles']);
        add_action('admin_init', static function () {
            if (get_option('frica_id_schema') !== '1') { Store::install(); }
        });
        add_action('admin_init', static function () {
            if (function_exists('wp_add_privacy_policy_content')) {
                wp_add_privacy_policy_content('Login with Frica ID', wp_kses_post(
                    '<p>' . __('When you choose Login with Frica ID, this site redirects you to Frica ID for authentication and requests your name and verified email address. A private hash of your Frica account reference is stored to recognize your linked WordPress account. Authentication tokens are not saved. A secure temporary cookie and a login transaction are kept for up to ten minutes; expired transaction records are deleted by scheduled cleanup or the next login attempt. Your WordPress profile and connection remain until disconnected or deleted. See https://frica.id/legal/privacy for Frica’s privacy policy.', 'frica-id-login') . '</p>'
                ));
            }
        });
    }

    public static function options(): array {
        return wp_parse_args(get_option('frica_id_settings', []), ['enabled' => 0, 'client_id' => '', 'registration' => 0]);
    }

    public static function callback_url(): string { return admin_url('admin-post.php?action=frica_id_callback', 'https'); }

    private static function ready(): bool {
        $settings = self::options();
        return !empty($settings['enabled']) && !empty($settings['client_id']) && is_ssl()
            && wp_parse_url(home_url(), PHP_URL_SCHEME) === 'https' && extension_loaded('openssl');
    }

    public function styles(): void {
        wp_enqueue_style('frica-id-login', plugins_url('assets/login.css', FRICA_ID_LOGIN_FILE), [], FRICA_ID_LOGIN_VERSION);
    }

    public function frontend_styles(): void {
        global $post;
        if ($post instanceof \WP_Post && has_shortcode($post->post_content, 'frica_id_login')) { $this->styles(); }
    }

    public function admin_styles(string $hook): void {
        if (in_array($hook, ['settings_page_frica-id-login', 'profile.php'], true)) { $this->styles(); }
    }

    public function footer_styles(): void {
        // Support shortcodes rendered by widgets/builders after wp_head, without loading CSS on unrelated pages.
        if (wp_style_is('frica-id-login', 'enqueued') && !wp_style_is('frica-id-login', 'done')) { wp_print_styles('frica-id-login'); }
    }

    public function menu(): void {
        add_options_page(__('Login with Frica ID', 'frica-id-login'), __('Frica ID', 'frica-id-login'), 'manage_options', 'frica-id-login', [$this, 'settings_page']);
    }

    public function settings(): void {
        register_setting('frica_id_login', 'frica_id_settings', ['type' => 'array', 'sanitize_callback' => [$this, 'sanitize'], 'show_in_rest' => false]);
    }

    public function sanitize($input): array {
        $input = is_array($input) ? $input : [];
        $client = trim(is_string($input['client_id'] ?? null) ? $input['client_id'] : '');
        if ($client !== '' && !preg_match('/^[A-Za-z0-9_-]{3,200}$/D', $client)) {
            add_settings_error('frica_id_settings', 'client', __('Enter the OAuth Client ID from your Frica developer application.', 'frica-id-login'));
            $client = '';
        }
        delete_transient('frica_id_jwks');
        return ['client_id' => $client, 'enabled' => empty($input['enabled']) || $client === '' ? 0 : 1, 'registration' => empty($input['registration']) ? 0 : 1];
    }

    public function settings_page(): void {
        if (!current_user_can('manage_options')) { return; }
        $settings = self::options();
        ?>
        <div class="wrap frica-id-settings">
            <h1><?php esc_html_e('Login with Frica ID', 'frica-id-login'); ?></h1>
            <p><?php esc_html_e('Connect your site to Frica ID using OpenID Connect and PKCE. No client secret is required.', 'frica-id-login'); ?></p>
            <?php if (!is_ssl() || wp_parse_url(home_url(), PHP_URL_SCHEME) !== 'https') : ?>
                <div class="notice notice-error"><p><?php esc_html_e('HTTPS is required for sign-in. Configure your site URL and reverse proxy HTTPS detection before enabling the plugin.', 'frica-id-login'); ?></p></div>
            <?php endif; ?>
            <?php if (!extension_loaded('openssl')) : ?>
                <div class="notice notice-error"><p><?php esc_html_e('Enable the PHP OpenSSL extension before using this plugin.', 'frica-id-login'); ?></p></div>
            <?php endif; ?>
            <h2><?php esc_html_e('Setup', 'frica-id-login'); ?></h2>
            <ol>
                <li><?php echo wp_kses_post(__('Create a web application in the <a href="https://frica.id/dashboard/developer" target="_blank" rel="noopener noreferrer">Frica developer portal</a>. Use its OAuth Client ID, not an API key.', 'frica-id-login')); ?></li>
                <li><?php esc_html_e('Register this exact redirect URI in the application:', 'frica-id-login'); ?> <code><?php echo esc_html(self::callback_url()); ?></code></li>
                <li><?php esc_html_e('Allow the openid, profile and email scopes. Paste the Client ID below and enable sign-in.', 'frica-id-login'); ?></li>
                <li><?php esc_html_e('To link an existing account, sign into WordPress normally and connect Frica ID from Users → Profile.', 'frica-id-login'); ?></li>
            </ol>
            <?php settings_errors('frica_id_settings'); ?>
            <form action="options.php" method="post">
                <?php settings_fields('frica_id_login'); ?>
                <table class="form-table" role="presentation">
                    <tr><th scope="row"><label for="frica-client-id"><?php esc_html_e('OAuth Client ID', 'frica-id-login'); ?></label></th><td><input id="frica-client-id" class="regular-text" name="frica_id_settings[client_id]" value="<?php echo esc_attr($settings['client_id']); ?>" autocomplete="off" /></td></tr>
                    <tr><th scope="row"><?php esc_html_e('Sign-in', 'frica-id-login'); ?></th><td><label><input type="checkbox" name="frica_id_settings[enabled]" value="1" <?php checked($settings['enabled'], 1); ?> /> <?php esc_html_e('Enable Login with Frica ID', 'frica-id-login'); ?></label></td></tr>
                    <tr><th scope="row"><?php esc_html_e('New accounts', 'frica-id-login'); ?></th><td><label><input type="checkbox" name="frica_id_settings[registration]" value="1" <?php checked($settings['registration'], 1); ?> /> <?php esc_html_e('Allow new subscriber accounts', 'frica-id-login'); ?></label><p class="description"><?php esc_html_e('Also requires Settings → General → Anyone can register. New accounts always receive the Subscriber role. On Multisite, users must already belong to this site.', 'frica-id-login'); ?></p></td></tr>
                </table>
                <?php submit_button(); ?>
            </form>
            <h2><?php esc_html_e('Add a button to a page', 'frica-id-login'); ?></h2>
            <p><code>[frica_id_login]</code></p>
            <p><code>[frica_id_login redirect_to="/my-account/"]</code></p>
            <p><?php esc_html_e('A button also appears on the standard WordPress login and registration forms. Local passwords and password recovery remain available. Exclude admin-post.php and wp-login.php from caching and do not log their query strings.', 'frica-id-login'); ?></p>
            <p><?php echo wp_kses_post(__('Frica ID is an external authentication service. <a href="https://frica.id/legal/privacy">Privacy policy</a> · <a href="https://frica.id/legal/terms">Terms</a>', 'frica-id-login')); ?></p>
        </div>
        <?php
    }

    public static function local_redirect(string $value): string {
        $fallback = home_url('/');
        $value = wp_validate_redirect($value, $fallback);
        if (strpos($value, '/') === 0 && strpos($value, '//') !== 0) { $value = home_url($value); }
        // Stricter than allowed_redirect_hosts: login redirects stay on this exact origin.
        foreach ([PHP_URL_SCHEME, PHP_URL_HOST, PHP_URL_PORT] as $part) {
            if (wp_parse_url($value, $part) !== wp_parse_url($fallback, $part)) { return $fallback; }
        }
        return $value;
    }

    public function shortcode($attributes = []): string {
        if (!self::ready()) { return ''; }
        $this->styles();
        if (is_user_logged_in()) {
            return '<a class="frica-id-button" href="' . esc_url(admin_url('profile.php#frica-id')) . '">' . esc_html__('Manage Frica ID connection', 'frica-id-login') . '</a>';
        }
        $attributes = shortcode_atts(['redirect_to' => home_url('/')], $attributes, 'frica_id_login');
        $url = add_query_arg(['action' => 'frica_id_start', 'redirect_to' => self::local_redirect((string) $attributes['redirect_to'])], admin_url('admin-post.php', 'https'));
        return '<a class="frica-id-button" href="' . esc_url($url) . '"><span class="frica-id-mark" aria-hidden="true">F</span>' . esc_html__('Login with Frica ID', 'frica-id-login') . '</a>';
    }

    public function login_button(): void {
        $redirect = isset($_REQUEST['redirect_to']) && is_string($_REQUEST['redirect_to']) ? sanitize_text_field(wp_unslash($_REQUEST['redirect_to'])) : admin_url(); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only login return path, validated to the local origin by shortcode().
        echo '<div class="frica-id-login-row">' . $this->shortcode(['redirect_to' => $redirect]) . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- shortcode escapes each dynamic value.
    }

    private static function input(string $key): string {
        return isset($_GET[$key]) && is_string($_GET[$key]) ? sanitize_text_field(wp_unslash($_GET[$key])) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- OAuth callback uses browser-bound single-use state; other reads only select fixed notices or local return paths.
    }

    private static function headers(): void {
        nocache_headers();
        header('Referrer-Policy: no-referrer');
        header('X-Robots-Tag: noindex, nofollow');
    }

    private static function cookie_name(string $state): string { return '__Host-frica_id_' . substr(hash('sha256', $state), 0, 16); }

    private static function cookie(string $state, string $value, int $expires): void {
        setcookie(self::cookie_name($state), $value, ['expires' => $expires, 'path' => '/', 'secure' => true, 'httponly' => true, 'samesite' => 'Lax']);
    }

    public function start(): void {
        self::headers();
        if (!self::ready()) { $this->fail('not_configured'); }
        $link = is_user_logged_in();
        if ($link && (sanitize_text_field(wp_unslash($_SERVER['REQUEST_METHOD'] ?? '')) !== 'POST' || !check_admin_referer('frica_id_link'))) { $this->fail('invalid_request'); }
        if ($link && Store::is_linked(get_current_user_id())) { $this->fail('already_linked'); }
        if ($link && is_multisite() && !is_user_member_of_blog()) { $this->fail('membership'); }
        // Bound per-address start attempts; only a short-lived keyed hash is retained.
        $rate_key = 'frica_id_rate_' . hash_hmac('sha256', (string) filter_var(wp_unslash($_SERVER['REMOTE_ADDR'] ?? ''), FILTER_VALIDATE_IP), wp_salt('nonce'));
        $attempts = (int) get_transient($rate_key);
        if ($attempts >= 20) { $this->fail('rate_limited'); }
        set_transient($rate_key, $attempts + 1, 60);
        $state = OIDC::random();
        $browser = OIDC::random();
        $verifier = OIDC::random();
        $nonce = OIDC::random();
        $settings = self::options();
        $flow = [
            'verifier' => $verifier, 'nonce' => $nonce, 'callback' => self::callback_url(), 'client' => $settings['client_id'],
            'user' => $link ? get_current_user_id() : 0,
            'session' => $link ? hash('sha256', wp_get_session_token()) : '',
            'redirect' => $link ? admin_url('profile.php#frica-id') : self::local_redirect(self::input('redirect_to')),
        ];
        if (!Store::save_flow($state, $browser, $flow)) { $this->fail('storage'); }
        self::cookie($state, $browser, time() + 600);
        $url = add_query_arg([
            'response_type' => 'code', 'client_id' => $settings['client_id'], 'redirect_uri' => $flow['callback'],
            'scope' => 'openid profile email', 'state' => $state, 'nonce' => $nonce,
            'code_challenge' => OIDC::challenge($verifier), 'code_challenge_method' => 'S256',
        ], OIDC::API . '/oauth/authorize');
        $allow_provider = static function ($hosts) { $hosts[] = 'api.frica.id'; return $hosts; };
        add_filter('allowed_redirect_hosts', $allow_provider);
        wp_safe_redirect($url, 302, 'Login with Frica ID');
        remove_filter('allowed_redirect_hosts', $allow_provider);
        exit;
    }

    public function callback(): void {
        self::headers();
        $state = self::input('state');
        if (!preg_match('/^[A-Za-z0-9_-]{43}$/D', $state)) { $this->fail('expired'); }
        $browser = $_COOKIE[self::cookie_name($state)] ?? ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized,WordPress.Security.ValidatedSanitizedInput.MissingUnslash -- Scalar type, unslashing and exact base64url validation follow immediately.
        if (is_string($browser)) { $browser = wp_unslash($browser); }
        if (!is_string($browser) || !preg_match('/^[A-Za-z0-9_-]{43}$/D', $browser)) { $this->fail('expired'); }
        $flow = Store::consume_flow($state, $browser);
        self::cookie($state, '', time() - 3600);
        if (!$flow) { $this->fail('expired'); }
        if (!self::ready() || $flow['client'] !== self::options()['client_id'] || $flow['callback'] !== self::callback_url()) { $this->fail('not_configured'); }
        if (self::input('error') !== '') { $this->fail('cancelled'); }
        $code = self::input('code');
        if ($code === '' || strlen($code) > 4096) { $this->fail('invalid_request'); }
        if ($flow['user']) {
            if (get_current_user_id() !== $flow['user'] || !hash_equals($flow['session'], hash('sha256', wp_get_session_token()))) { $this->fail('session_changed'); }
        } elseif (is_user_logged_in()) { $this->fail('session_changed'); }
        try {
            $profile = OIDC::authenticate($code, $flow['client'], $flow);
            $user = $this->resolve_user($profile, (int) $flow['user']);
            if ($flow['user']) {
                wp_safe_redirect(admin_url('profile.php?frica_id_notice=connected#frica-id'));
                exit;
            }
            $checked = apply_filters('wp_authenticate_user', $user, ''); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Invoke the documented WordPress authentication hook for security-plugin checks.
            if (is_wp_error($checked) || !($checked instanceof \WP_User) || $checked->ID !== $user->ID) { throw new \RuntimeException('login_blocked'); }
            wp_set_current_user($user->ID);
            wp_set_auth_cookie($user->ID, false, true);
            do_action('wp_login', $user->user_login, $user); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- WordPress core login notification hook.
            wp_safe_redirect(self::local_redirect($flow['redirect']));
            exit;
        } catch (\Throwable $error) {
            $this->fail($error->getMessage());
        }
    }

    public function resolve_user(array $profile, int $link_user = 0): \WP_User {
        global $wpdb;
        $existing = Store::linked_user($profile['sub']);
        if ($link_user) {
            if (($existing && $existing !== $link_user) || Store::is_linked($link_user)) { throw new \RuntimeException('already_linked'); }
            $user = get_userdata($link_user);
            if (!$user || (is_multisite() && !is_user_member_of_blog($user->ID))) { throw new \RuntimeException('membership'); }
            if (!Store::link($link_user, $profile['sub'])) { throw new \RuntimeException('already_linked'); }
            return $user;
        }
        if ($existing) {
            $user = get_userdata($existing);
            if (!$user || $user->spam || $user->deleted || (is_multisite() && !is_user_member_of_blog($user->ID))) { throw new \RuntimeException('login_blocked'); }
            return $user;
        }
        // Matching email alone is never authority to attach to an existing account.
        if (email_exists($profile['email'])) { throw new \RuntimeException('email_exists'); }
        if (empty(self::options()['registration']) || !get_option('users_can_register') || is_multisite()) { throw new \RuntimeException('registration_disabled'); }
        // Serialize plugin registrations and recheck after locking. This avoids orphan accounts on parallel sign-ins.
        $lock = 'frica_id_signup_' . substr(hash('sha256', $wpdb->prefix), 0, 40);
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Advisory locking must query the database rather than an object cache.
        if ((int) $wpdb->get_var($wpdb->prepare('SELECT GET_LOCK(%s, 5)', $lock)) !== 1) { throw new \RuntimeException('storage'); }
        try {
            $existing = Store::linked_user($profile['sub']);
            if ($existing) { return get_userdata($existing); }
            if (email_exists($profile['email'])) { throw new \RuntimeException('email_exists'); }
            $name = isset($profile['name']) && is_string($profile['name']) ? sanitize_text_field($profile['name']) : '';
            if ($name === '' || $name === $profile['sub']) { $name = __('Frica member', 'frica-id-login'); }
            $id = wp_insert_user([
                'user_login' => 'frica_' . bin2hex(random_bytes(12)), 'user_pass' => wp_generate_password(64, true, true),
                'user_email' => $profile['email'], 'display_name' => wp_html_excerpt($name, 250, ''), 'role' => 'subscriber',
                'first_name' => sanitize_text_field(is_string($profile['given_name'] ?? null) ? $profile['given_name'] : ''),
                'last_name' => sanitize_text_field(is_string($profile['family_name'] ?? null) ? $profile['family_name'] : ''),
            ]);
            if (is_wp_error($id)) { throw new \RuntimeException('registration_failed'); }
            if (!Store::link((int) $id, $profile['sub'])) {
                require_once ABSPATH . 'wp-admin/includes/user.php';
                wp_delete_user($id);
                throw new \RuntimeException('storage');
            }
            wp_new_user_notification($id, null, 'both');
            return get_userdata($id);
        } finally {
            $wpdb->get_var($wpdb->prepare('SELECT RELEASE_LOCK(%s)', $lock)); // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Release the connection-scoped advisory lock, with no cached result.
        }
    }

    public function profile(\WP_User $user): void {
        if ($user->ID !== get_current_user_id()) { return; }
        ?>
        <h2 id="frica-id"><?php esc_html_e('Frica ID connection', 'frica-id-login'); ?></h2>
        <?php if (Store::is_linked($user->ID)) : ?>
            <p><?php esc_html_e('Your account is connected to Frica ID. Before disconnecting, make sure you can sign in with your WordPress password or recover it by email.', 'frica-id-login'); ?></p>
            <p><button type="submit" class="button" form="frica-id-disconnect-form"><?php esc_html_e('Disconnect Frica ID', 'frica-id-login'); ?></button></p>
        <?php elseif (self::ready()) : ?>
            <p><?php esc_html_e('Connect Frica ID to sign into this WordPress account. Your WordPress permissions will stay the same.', 'frica-id-login'); ?></p>
            <p><button type="submit" class="frica-id-button" form="frica-id-link-form"><?php esc_html_e('Connect Frica ID', 'frica-id-login'); ?></button></p>
        <?php else : ?>
            <p><?php esc_html_e('Frica ID sign-in is not enabled on this site.', 'frica-id-login'); ?></p>
        <?php endif;
        // WP profile is already inside a form; emit separate forms in the footer to avoid nested forms.
        add_action('admin_footer', function () {
            foreach (['link' => 'frica_id_start', 'disconnect' => 'frica_id_disconnect'] as $kind => $action) {
                echo '<form id="frica-id-' . esc_attr($kind) . '-form" method="post" action="' . esc_url(admin_url('admin-post.php', 'https')) . '"><input type="hidden" name="action" value="' . esc_attr($action) . '" />';
                wp_nonce_field('frica_id_' . $kind);
                echo '</form>';
            }
        });
    }

    public function connection_notice(): void {
        global $pagenow;
        if ($pagenow !== 'profile.php') { return; }
        $notice = self::input('frica_id_notice');
        if (!in_array($notice, ['connected', 'disconnected'], true)) { return; }
        $message = $notice === 'connected' ? __('Frica ID connected successfully.', 'frica-id-login') : __('Frica ID disconnected.', 'frica-id-login');
        echo '<div class="notice notice-success is-dismissible"><p>' . esc_html($message) . '</p></div>';
    }

    public function disconnect(): void {
        self::headers();
        if (sanitize_text_field(wp_unslash($_SERVER['REQUEST_METHOD'] ?? '')) !== 'POST' || !is_user_logged_in()) { $this->fail('invalid_request'); }
        check_admin_referer('frica_id_disconnect');
        Store::unlink(get_current_user_id());
        wp_safe_redirect(admin_url('profile.php?frica_id_notice=disconnected#frica-id'));
        exit;
    }

    private static function messages(): array {
        return [
            'not_configured' => __('Frica ID sign-in is not configured. Use your WordPress login or contact the site administrator.', 'frica-id-login'),
            'expired' => __('This sign-in attempt expired or its secure cookie is missing. Start again in the same browser.', 'frica-id-login'),
            'cancelled' => __('Frica ID sign-in was cancelled. You can try again.', 'frica-id-login'),
            'email_unverified' => __('Verify your email address in Frica ID, then try again.', 'frica-id-login'),
            'email_exists' => __('A WordPress account already uses this email. Sign into WordPress normally, then connect Frica ID from your Profile.', 'frica-id-login'),
            'registration_disabled' => __('New accounts through Frica ID are not enabled. Existing users can sign into WordPress and connect Frica ID from their Profile.', 'frica-id-login'),
            'already_linked' => __('This account already has a connection. Disconnect the existing connection from its WordPress Profile first.', 'frica-id-login'),
            'session_changed' => __('Your WordPress session changed during sign-in. Start again from the account you want to connect.', 'frica-id-login'),
            'rate_limited' => __('Too many sign-in attempts. Wait one minute and try again.', 'frica-id-login'),
            'membership' => __('Your WordPress account must belong to this site before you can connect Frica ID.', 'frica-id-login'),
            'login_blocked' => __('This WordPress account cannot sign in. Contact the site administrator.', 'frica-id-login'),
            'storage' => __('The site could not complete sign-in. Try again or contact the site administrator.', 'frica-id-login'),
            'authorization_rejected' => __('Frica ID rejected this sign-in attempt. Start again. If it keeps happening, ask the site administrator to check the OAuth Client ID and registered callback URL.', 'frica-id-login'),
            'provider_busy' => __('Frica ID is receiving too many requests. Wait a little and try again.', 'frica-id-login'),
            'provider_unavailable' => __('Frica ID could not be reached. Try again shortly or use your WordPress login.', 'frica-id-login'),
            'invalid_request' => __('This sign-in request is invalid. Start again from the login page.', 'frica-id-login'),
            'registration_failed' => __('Your WordPress account could not be created. Contact the site administrator.', 'frica-id-login'),
            'failed' => __('Frica ID sign-in could not be verified. Start again or use your WordPress login.', 'frica-id-login'),
        ];
    }

    private function fail(string $code): void {
        if (!isset(self::messages()[$code])) { $code = 'failed'; }
        wp_safe_redirect(add_query_arg('frica_id_error', $code, wp_login_url()));
        exit;
    }

    public function login_message(string $message): string {
        $messages = self::messages();
        $code = self::input('frica_id_error');
        if (isset($messages[$code])) { $message .= '<div class="message frica-id-error" role="alert">' . esc_html($messages[$code]) . '</div>'; }
        return $message;
    }
}

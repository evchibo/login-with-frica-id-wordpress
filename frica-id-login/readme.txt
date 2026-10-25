=== Login with Frica ID ===
Contributors: evchibo
Tags: login, single sign on, openid connect, oauth, frica
Requires at least: 6.6
Tested up to: 7.1
Requires PHP: 8.0
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Add Frica ID sign-in to WordPress with secure account linking and OpenID Connect authorization-code login with PKCE.

== Description ==

Login with Frica ID lets visitors sign into your WordPress site through their Frica ID account. A site administrator registers a Frica developer application and configures its OAuth Client ID. No client secret or API key is required.

Learn more at [Frica ID](https://frica.id).

Features:

* Login button on the standard WordPress sign-in and registration screens.
* [frica_id_login] shortcode for pages, posts and shortcode blocks.
* Explicit connection and disconnection from the user's own WordPress Profile.
* Optional new Subscriber accounts, only when WordPress registration is also enabled.
* Verified email required; matching email alone never attaches to an existing account.
* PKCE S256, browser-bound single-use state, OIDC nonce, RS256 signature and claim validation.
* HTTPS-only operation, safe local return URLs, and no stored access or refresh tokens.
* Clear errors for unverified email, existing accounts, expired attempts and closed registration.
* Existing WordPress passwords and recovery remain available.

The plugin does not expose Frica's internal account reference in the user interface. It stores a private one-way hash to recognize the connected account. It does not fetch KYC, wallet, phone, or payment information.

= External service =

This plugin connects to Frica ID, an external authentication service operated at https://frica.id. An administrator must register a developer application at https://frica.id/dashboard/developer.

Only when a visitor starts sign-in or connects their account, their browser is redirected to https://api.frica.id/oauth/authorize and Frica's login/consent screens. The site exchanges the returned authorization code at https://api.frica.id/oauth/token and requests the user's name and verified email at https://api.frica.id/oauth/userinfo. Public signing keys are retrieved from https://api.frica.id/.well-known/jwks.json and cached for one hour. Unneeded refresh tokens are sent to https://api.frica.id/oauth/revoke for revocation. WordPress HTTP requests naturally send the site's server IP to Frica; browser sign-in sends the visitor's IP and browser details to Frica.

Requested scopes: openid profile email. No analytics, tracking pixels, remote fonts or external JavaScript are included. WordPress authentication cookies remain governed by WordPress.

Frica privacy policy: https://frica.id/legal/privacy
Frica terms: https://frica.id/legal/terms

A secure browser cookie and server-side login transaction expire after ten minutes. Expired transaction records are removed during scheduled cleanup or the next attempt. A short-lived keyed hash of the requesting IP address rate-limits sign-in starts; no raw IP is stored by the plugin. WordPress profiles and private connection hashes remain until disconnected, account deletion or plugin uninstall. Uninstall removes plugin settings and connections, not WordPress accounts. Add the supplied suggested text to your site's privacy policy and configure your web server to omit callback query strings from logs.

== Installation ==

1. Upload frica-id-login.zip in Plugins > Add New > Upload Plugin, then activate.
2. Open Settings > Frica ID. Your site must use HTTPS and PHP OpenSSL.
3. Create an application at https://frica.id/dashboard/developer. This is the standard PUBLIC/PKCE application. Register the exact redirect URI shown in WordPress Settings, including its action query parameter. Allow openid, profile and email scopes.
4. Copy the application's OAuth Client ID (normally beginning fcli_) into WordPress. Do not use a frik_ API key.
5. Enable sign-in and save. The button appears on the standard WordPress login page.
6. Existing users must first sign into WordPress normally and choose Users > Profile > Connect Frica ID. An email match by itself cannot link an account.
7. For new accounts, enable the plugin's new account option AND Settings > General > Membership > Anyone can register. Accounts created by this plugin always receive Subscriber, even if the WordPress default role is more privileged.
8. Optionally add [frica_id_login] to a page. Use [frica_id_login redirect_to="/my-account/"] to choose a local return path.
9. Exclude wp-login.php and wp-admin/admin-post.php from caching. Reverse proxies must correctly report HTTPS to WordPress. Exclude callback query strings from access logs and analytics.

== Frequently Asked Questions ==

= Can I link an existing WordPress account? =

Yes. Sign into WordPress with your existing method, open your own Profile, and choose Connect Frica ID. Connecting preserves existing permissions. Connection requires the same WordPress browser session throughout the flow.

= Why does it reject an email that already exists? =

An email match alone is not proof of ownership of a local WordPress account. Sign into that WordPress account normally first, then connect Frica ID from Profile. The plugin never silently merges accounts.

= Can I disable new account registration? =

Yes. It is disabled by default in the plugin. Existing connected members can continue signing in. Enabling it also requires WordPress's Anyone can register setting. WordPress Multisite does not allow account creation through this plugin.

= Does it support Multisite? =

Activate and configure the plugin separately for each site. Network-wide activation is intentionally blocked. Existing network users must already belong to the selected site, sign into that site, and explicitly connect their account there. Each site's application and redirect URI must be configured separately.

= Does it work with every two-factor, membership or security plugin? =

Frica ID authenticates the user; WordPress creates the local session. The plugin runs wp_authenticate_user and wp_login hooks, but password-specific challenges and custom login workflows may need integration. Test your installed security and membership plugins before rollout. Configure your required identity assurance and two-factor controls in Frica ID. The plugin does not disable WordPress password login.

= Does WordPress logout sign me out of Frica ID? =

No. WordPress logout ends the local WordPress session. Frica ID's browser session remains independent; this plugin does not implement global logout.

= What happens if I disconnect or uninstall? =

Disconnect removes the local connection without deleting the WordPress user. Confirm your WordPress password or email recovery works first. Deactivation keeps settings and connections; deleting the plugin removes its own settings, temporary transactions and connections. WordPress user accounts and content remain.

= Why does an attempt expire? =

State must be used once, within ten minutes, in the same browser. Secure cookies must be allowed. Retry from the login page, check HTTPS/reverse-proxy configuration, and verify callback routes are not cached.

== Changelog ==

= 1.0.0 =
* Initial release with OpenID Connect, PKCE, explicit linking, optional Subscriber registration and a login shortcode.

== Upgrade Notice ==

= 1.0.0 =
Initial release. Configure a Frica developer application and test sign-in before enabling for visitors.

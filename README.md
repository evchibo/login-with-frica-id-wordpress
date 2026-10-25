# Login with Frica ID for WordPress

An installable, GPL-licensed plugin for Frica ID sign-in using OpenID Connect authorization code + PKCE S256. The standard Frica developer application is a PUBLIC client; configure its OAuth Client ID, with no secret or API key.

## Installation

Build the package with `python3 tools/build.py`. Upload the ZIP under **WordPress → Plugins → Add New → Upload Plugin**, then activate it. Open **Settings → Frica ID** for the exact callback URI and setup steps.

Create an app at [Frica's developer portal](https://frica.id/dashboard/developer), register the exact URI shown in WordPress, and allow `openid profile email`. Save its OAuth Client ID and enable the plugin. Sites require HTTPS, PHP 8.0+ with OpenSSL, and WordPress 6.6+.

Existing members sign into WordPress normally, then choose **Users → Profile → Connect Frica ID**. Verified email alone never links an existing WordPress account. New users can register only when both the plugin option and WordPress's **Anyone can register** setting are enabled. New accounts always receive `subscriber`; existing roles are preserved.

The plugin adds a button to the standard WordPress login/registration forms. Add `[frica_id_login]` to a page or shortcode block; `[frica_id_login redirect_to="/my-account/"]` chooses a same-origin return path. Normal WordPress passwords and recovery remain available.

The [packaged readme](frica-id-login/readme.txt) includes external-service disclosures, privacy information, Multisite limitations, caching configuration, security-plugin compatibility and troubleshooting.

## Security and data

- Single-use database-backed OAuth state, ten-minute expiry, and a Secure/HttpOnly/SameSite=Lax host-only browser cookie.
- PKCE verifier and nonce bound to each transaction; callback consumes state atomically.
- Signature verification with an isolated, bundled `firebase/php-jwt` 7.2.1, pinned to upstream commit `0501f5729b9e2448516674aff07c7d1727869b8e`. RS256 keys come only from Frica's fixed HTTPS JWKS endpoint; issuer, audience, azp, nonce, timestamps, token_use and UserInfo subject are checked.
- No configurable discovery URLs, token-supplied keys, unsigned tokens, browser token storage, client secrets or disabled TLS checks. Provider requests do not follow redirects.
- Verified email required; account linking requires the logged-in WordPress account, a POST nonce, and the same WordPress session at callback. No automatic email merge.
- The local connection table stores an issuer-bound subject hash. Internal Frica subject values are never shown as display names or usernames. Provider tokens are not retained; returned refresh tokens are revoked on a best-effort basis.
- New accounts use random passwords and receive standard WordPress recovery notifications. Profile updates do not overwrite an existing local account's email or permissions.
- New registration is serialized with a database advisory lock; unique constraints protect both sides of the account connection.
- API diagnostics are not echoed to visitors. Callback responses use no-store/no-cache and a no-referrer policy. Administrators must omit callback query strings from server logs and bypass caches for login/callback routes.

Frica sign-in creates a local WordPress session. Password-specific two-factor/security plugins may require integration: the plugin calls `wp_authenticate_user` and `wp_login`, but cannot promise compatibility with every third-party challenge. WordPress logout does not globally log out of Frica ID. Configure provider assurance and test installed security plugins before rollout.

Multisite supports per-site activation and existing site members only. Network-wide activation and plugin-driven new network account creation are intentionally blocked.

## Test and build

The GitHub Actions workflow installs real WordPress 6.6/PHP 8.0 and WordPress 7.1.3/PHP 8.3/8.4 with MariaDB 10.11. It executes HTTP redirects, real cookie-authenticated WordPress sessions, PKCE, signed-token validation, account linking, CSRF rejection, role preservation, registration controls, single-use/expiry checks and uninstall preservation. Provider responses use generated test-only RSA keys. No production users or credentials are involved.

To reproduce with a disposable database:

1. Download WordPress and set `WP_ROOT` to its extracted `wordpress` directory. Set `WP_TEST_DB_HOST`, `WP_TEST_DB_NAME`, `WP_TEST_DB_USER` and `WP_TEST_DB_PASSWORD` for an empty test database. Never point this harness at an existing site: setup writes wp-config.php and the integration test uninstalls the plugin.
2. Link `frica-id-login` into `$WP_ROOT/wp-content/plugins/`.
3. Run `php tests/setup.php`.
4. Run `php -S 127.0.0.1:8787 tests/router.php`.
5. In the same environment run `php tests/integration.php`.
6. Run `python3 tools/build.py` to create `dist/frica-id-login-1.0.0.zip`. The package contains only runtime code, the readme, licenses and pinned dependency sources.

Rebuild the isolated JWT dependency with `python3 tools/vendor-jwt.py`. Its manifest records upstream and bundled SHA256 hashes. Namespace replacement is the only modification; the original BSD license is retained. No Composer install is needed on the user's server.

## Releases

Create a version tag such as `v1.0.0` after updating the plugin header, stable tag and changelog. CI tests the tag and publishes an installable ZIP to GitHub Releases only after every compatibility and Plugin Check job succeeds. The plugin does not install an external updater; manual ZIP updates work until a WordPress.org listing is approved.

## Public distribution

The ZIP is suitable for manual WordPress installation and includes the files needed for a WordPress.org submission. A directory listing is a separate publication step: it requires a WordPress.org contributor account, an approved plugin slug, review of the current directory guidelines and WordPress.org review approval. GitHub distribution does not imply directory approval, automatic updates from WordPress.org, or a live end-to-end sign-in on an external site.

Before enabling on a live site, register that site's real redirect URI and exercise a new account, existing linked member, same-email collision, explicit connection, disconnect, logout and password recovery. Keep WordPress password login available while checking third-party membership/MFA compatibility.

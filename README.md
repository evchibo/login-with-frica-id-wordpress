# Login with Frica ID for WordPress

**Let your visitors sign in with their [Frica ID](https://frica.id).**

A free, open-source WordPress plugin for secure single sign-on using OpenID Connect and PKCE. Add a login button, connect existing WordPress accounts, or let new members register with a verified Frica email address.

[Frica ID website](https://frica.id) · [Download the latest plugin](https://github.com/evchibo/login-with-frica-id-wordpress/releases/latest) · [Setup guide](#setup) · [Report a bug](https://github.com/evchibo/login-with-frica-id-wordpress/issues)

## Features

- **Login with Frica ID** on WordPress login and registration screens.
- A shortcode for pages, posts, widgets and shortcode blocks.
- Explicit account connection and disconnection from the member's WordPress Profile.
- Optional new account registration with the **Subscriber** role.
- Verified email, browser-bound one-use login attempts, PKCE S256 and signed ID-token validation.
- Local WordPress passwords and recovery remain available.
- No client secret, API key, tracking scripts or stored authentication tokens.

## Requirements

| Requirement | Minimum |
| --- | --- |
| WordPress | 6.6 |
| PHP | 8.0 with OpenSSL |
| Site connection | HTTPS |
| Frica configuration | A developer application with an OAuth Client ID |

Compatibility checks run against WordPress 6.6 / PHP 8.0 and WordPress 7.1.3 / PHP 8.3 and 8.4. MariaDB integration tests and strict WordPress Plugin Check run in CI.

## Install

1. Download **`frica-id-login-1.0.2.zip`** from [GitHub Releases](https://github.com/evchibo/login-with-frica-id-wordpress/releases/latest). Choose the plugin ZIP under **Assets**, rather than GitHub's generated source archive.
2. In WordPress, open **Plugins → Add New → Upload Plugin**.
3. Upload the ZIP, install it, and activate **Login with Frica ID**.
4. Open **Settings → Frica ID** and follow the setup steps below.

## Setup

1. Create an application in the [Frica developer portal](https://frica.id/dashboard/developer).
2. Copy the **exact redirect URI** displayed in WordPress **Settings → Frica ID** into your application's allowed redirect URIs. Preserve the entire URL, including `?action=frica_id_callback`.
3. Allow the `openid`, `profile` and `email` scopes.
4. Copy the application's **OAuth Client ID** into WordPress. This is typically an `fcli_` value; do not use a `frik_` API key. Frica's standard application uses PUBLIC-client PKCE and needs no client secret.
5. Enable sign-in and save your settings.
6. Test sign-in on your site before inviting visitors to use it.

### Existing WordPress members

Sign into WordPress normally, open **Users → Profile**, and choose **Connect Frica ID**. Complete the Frica consent screen in the same browser session. The connection preserves the member's WordPress permissions.

An email match by itself never links an existing WordPress account. If you see an existing-email message, sign into that WordPress account first and connect it from Profile.

### New members

Enable **Allow new subscriber accounts** in the plugin settings and **Anyone can register** in **Settings → General**. Both settings are required. Accounts created by this plugin always receive the Subscriber role, regardless of the site's default role.

### Add a button to a page

Use a Shortcode block:

```text
[frica_id_login]
```

Choose a return path on your own site:

```text
[frica_id_login redirect_to="/my-account/"]
```

A signed-in visitor sees a link to manage their Frica ID connection.

## Privacy and account security

The plugin requests your name and verified email through [Frica ID](https://frica.id), using `openid profile email`. It does not request KYC, wallet, payment or phone information. A private one-way account-reference hash recognizes the connection; Frica's internal user identifier is not displayed as a member's username or name.

Login state expires after ten minutes and can be consumed only once. The flow uses a Secure, HttpOnly, SameSite=Lax cookie, PKCE S256, nonce binding, RS256 signatures, issuer/audience checks and matching UserInfo subjects. Access and refresh tokens are not retained; unnecessary refresh tokens are revoked on a best-effort basis.

Frica ID is an external service. Read its [Privacy Policy](https://frica.id/legal/privacy) and [Terms](https://frica.id/legal/terms). The [packaged readme](frica-id-login/readme.txt) contains the full service and data-retention disclosures. Suggested privacy-policy text is available through WordPress's privacy tools after activation.

## Compatibility and troubleshooting

- **HTTPS:** Configure WordPress and any reverse proxy to recognize HTTPS correctly. The plugin does not enable sign-in on an insecure site.
- **Caching:** Exclude `wp-login.php` and `wp-admin/admin-post.php` from caching. Omit OAuth callback query strings from access logs and analytics.
- **Connection errors:** Version 1.0.2 accepts the legacy HTTP 201 success response from the token endpoint. Rejected authorization attempts and rate limits have separate messages. Actual transport errors still require the WordPress server to reach `https://api.frica.id` over HTTPS.
- **Expired attempts:** Start again in the same browser with secure cookies enabled.
- **Existing email:** Sign into WordPress first, then connect Frica ID from your Profile.
- **Membership and MFA plugins:** Frica authenticates the member and WordPress creates the local session. `wp_authenticate_user` and `wp_login` hooks run, but password-specific challenges and custom membership workflows may require integration. Test your installed plugins before rollout.
- **Logout:** WordPress logout ends the WordPress session. It does not sign the visitor out of Frica ID globally.
- **Multisite:** Activate and configure separately on each site. Existing users must already belong to that site. Network-wide activation and creation of new network accounts through this plugin are not supported.
- **Disconnecting:** Confirm WordPress password login or email recovery works before disconnecting. Disconnect removes only the connection; uninstall removes plugin-owned data and settings while retaining WordPress users and content.

## Development

Source, tests and packaging tools live in this repository. No Composer installation is required on a user's server.

```bash
python3 tools/build.py
```

This creates a WordPress-uploadable ZIP in `dist/` and checks the bundled dependency hashes. See [CONTRIBUTING.md](CONTRIBUTING.md) for the disposable WordPress test setup. The integration harness writes `wp-config.php` and uninstalls the plugin at the end; never run it against an existing site.

The bundled `firebase/php-jwt` 7.2.1 is pinned to upstream commit `0501f5729b9e2448516674aff07c7d1727869b8e` and isolated in a private namespace. Its original BSD license and provenance hashes are included. `python3 tools/vendor-jwt.py` rebuilds that dependency.

## Releases and WordPress.org

After the compatibility matrix and Plugin Check pass, a push to `main` automatically publishes a GitHub release if that plugin version has no release yet. Existing releases are preserved: increment the plugin version, readme stable tag and changelog for a new release. Explicit version tags are supported too. Release notes come from [RELEASE_NOTES.md](RELEASE_NOTES.md).

The plugin is distributed on GitHub. **A WordPress.org listing is not yet approved.** See [WORDPRESS_ORG.md](WORDPRESS_ORG.md) for submission and review steps. Automatic WordPress.org updates become available only after directory approval and publication; this plugin does not install a third-party updater.

## Support and license

For ordinary issues, use [GitHub Issues](https://github.com/evchibo/login-with-frica-id-wordpress/issues). For sensitive security reports, see [SECURITY.md](SECURITY.md).

Copyright © 2026 Frica and contributors. Licensed under **[GPL-2.0-or-later](LICENSE.txt)**: the general public may use, study, modify and redistribute the plugin, including commercially, under the license terms. Distributing covered code or modifications requires preserving notices and making the corresponding source available under the GPL. The bundled JWT library retains its [BSD-3-Clause license](frica-id-login/vendor/php-jwt/LICENSE).

Visit **[frica.id](https://frica.id)** to learn about Frica ID.

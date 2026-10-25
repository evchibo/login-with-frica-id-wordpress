# Login with Frica ID for WordPress — v1.1.0

Add secure **[Frica ID](https://frica.id)** sign-in to your WordPress website.

## Download and install

Download **`frica-id-login-1.1.0.zip`** from the release assets. In WordPress, open **Plugins → Add New → Upload Plugin**, install the ZIP, and activate it. GitHub's automatically generated source archives are development source, not the packaged plugin.

Open **Settings → Frica ID**, register the displayed callback URL in your [Frica developer application](https://frica.id/dashboard/developer), enter its OAuth Client ID, and enable sign-in. No client secret is required.

## Changes in v1.1.0

- Use the Public Frica ID as the WordPress username and nickname for new accounts, with a local numeric suffix for collisions. Authentication remains bound to the issuer and subject, never the public username.
- Upgrade existing generated `frica_` plus 24-hex-character usernames on their next verified Frica sign-in on single sites. Preserve user IDs, passwords, roles, content, display names, author URLs and account links. Retire old sessions and issue a fresh WordPress session.
- Keep custom usernames and shared Multisite usernames unchanged. Missing or invalid public IDs retain a private generated fallback.
- Update privacy disclosures for the Public Frica ID profile claim and add registration, collision and migration coverage.

## Included

- Login button on WordPress login and registration forms.
- `[frica_id_login]` shortcode with an optional local return path.
- Explicit account connection and disconnection from WordPress Profile.
- Optional registration with verified email and Subscriber permissions only.
- Authorization code + PKCE S256, browser-bound one-use state, nonce and RS256 ID-token validation.
- Privacy disclosures and local WordPress password/recovery support.

## License

Free and open source under **GPL-2.0-or-later**, with the full license included in the ZIP. The bundled JWT library retains its BSD-3-Clause license.

## Requirements and validation

Requires **WordPress 6.6+**, **PHP 8.0+ with OpenSSL**, HTTPS, and a Frica developer application.

The release pipeline tests WordPress 6.6/PHP 8.0 and WordPress 7.1.3/PHP 8.3 and 8.4, with real WordPress sessions, MariaDB and simulated signed provider responses. Strict WordPress Plugin Check must pass before publishing. Register your actual site's callback and perform a live sign-in before rolling out to visitors; third-party membership and MFA plugins need site-specific testing.

Multisite supports per-site activation and existing site members. WordPress logout does not globally log out of Frica ID.

[Website](https://frica.id) · [Setup and documentation](https://github.com/evchibo/login-with-frica-id-wordpress#setup) · [Report a bug](https://github.com/evchibo/login-with-frica-id-wordpress/issues)

WordPress.org directory submission is a separate review process. This release does not claim directory approval.

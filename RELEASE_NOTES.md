# Login with Frica ID for WordPress — v1.0.3

Add secure **[Frica ID](https://frica.id)** sign-in to your WordPress website.

## Download and install

Download **`frica-id-login-1.0.3.zip`** from the release assets. In WordPress, open **Plugins → Add New → Upload Plugin**, install the ZIP, and activate it. GitHub's automatically generated source archives are development source, not the packaged plugin.

Open **Settings → Frica ID**, register the displayed callback URL in your [Frica developer application](https://frica.id/dashboard/developer), enter its OAuth Client ID, and enable sign-in. No client secret is required.

## Changes in v1.0.3

- Apply a consistent maximum 30-second JWT clock tolerance so small differences between issuer and WordPress server clocks do not reject otherwise valid tokens. Already-expired tokens remain rejected.
- Replace generic verification failures with safe messages identifying signature, issuer, audience, nonce, expiry, clock and response problems.
- Add a random support reference and an administrator-only last-failure diagnostic under Settings → Frica ID. It stores only a reference, stage, code and UTC timestamp for 24 hours; no tokens, codes, passwords, emails or response bodies.
- Expand WordPress integration coverage to 85 assertions, including independent clock differences, strict expiry rejection, tolerance restoration and diagnostic privacy.

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

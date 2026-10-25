# Contributing

Open an issue for a proposed improvement or bug report. For security issues, follow SECURITY.md. Develop against a disposable WordPress site and database; the integration harness writes wp-config.php and uninstalls the plugin at the end. See README.md for setup and test commands.

Changes must pass the WordPress/PHP compatibility matrix and strict WordPress Plugin Check. Keep provider scopes minimal, preserve explicit account linking, never automatically link by email, and never commit real credentials or user data. Dependency updates must preserve the private namespace, upstream license and provenance hashes.

Contributions are licensed under GPL-2.0-or-later, with the bundled JWT dependency retaining its BSD-3-Clause license.

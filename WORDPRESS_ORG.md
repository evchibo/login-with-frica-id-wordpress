# WordPress.org review and SVN publishing

GitHub is the development repository; WordPress.org SVN holds finished releases.
The approved plugin slug must be **`frica-id-login`**, not this GitHub repository's name.
The public SVN repository exists and was empty when this workflow was added. This
does not verify directory approval or the account's write permissions.

## Review prerequisites

Use the existing submission and WordPress.org account **`ivanchibo`**. Address the
review team's ownership-verification request and any subsequent review issues in
the same email thread. Do not create a duplicate submission. Until WordPress.org
grants approval and committer access, updates for review go through
[Add your plugin](https://wordpress.org/plugins/developers/add/), not SVN.

The plugin includes the directory readme, GPL-compatible licenses and external
service disclosures. Test the packaged release on a regular HTTPS WordPress site
with its exact registered OAuth callback. WordPress Playground embeds its site;
Frica ID sign-in intentionally blocks framing. Use a regular staging site for the
complete OAuth round trip.

## One-time GitHub configuration

1. Confirm WordPress.org has approved the plugin and `ivanchibo` is a committer.
2. Enable two-factor authentication on the WordPress.org account and generate an
   **SVN-specific password** under [Account & Security → SVN credentials](https://profiles.wordpress.org/me/profile/edit/group/3/?screen=svn-password).
   Use this generated credential, not your normal account password.
3. In this repository's [Actions secrets](https://github.com/evchibo/login-with-frica-id-wordpress/settings/secrets/actions), add:

   | Secret | Value |
   | --- | --- |
   | `SVN_USERNAME` | `ivanchibo` (case-sensitive WordPress.org username) |
   | `SVN_PASSWORD` | The generated SVN-specific password |

   Never put credentials in YAML, source files, issues or release notes.
4. Run the manual dry run below. It does not require either secret.
5. When ready to publish, add the repository **Actions variable**
   `WORDPRESS_SVN_ENABLED` with value `true` in
   [Actions variables](https://github.com/evchibo/login-with-frica-id-wordpress/settings/variables/actions).
   Leave it unset or set it to `false` to run automatic dry runs without publishing.

The workflow uses a commit-pinned 10up deployment action with read-only GitHub
permissions. SVN secrets are passed only to credential validation and the live
deployment step; dry runs supply dummy values. SVN authentication is not cached.

## First publication and manual retries

Open **Actions → Publish to WordPress.org SVN → Run workflow**:

1. Select branch **main** for the workflow definition.
2. Enter an existing stable GitHub release tag, initially **`v1.1.0`**.
3. Leave **Preview SVN changes without committing** checked for a dry run.
4. Review the log and workflow summary. The runtime plugin files must appear
   directly under `trunk/` and `tags/1.1.0/`, with no extra plugin folder nesting.
5. Once secrets and the enabling variable are configured, run the same tag again
   with the dry-run checkbox cleared to commit it.

This manual path also publishes an existing GitHub release after approval,
recovers from a failed SVN deployment, or verifies that an identical release is
already present. It checks that the exact tagged commit has a successful
**WordPress plugin** CI run. If that historical run is unavailable, run the
existing WordPress plugin workflow against that tag first.

## Automatic releases

For a new release, update the plugin header, `FRICA_ID_LOGIN_VERSION`, readme
`Stable tag`, changelog and `RELEASE_NOTES.md`. Merge the tested changes to main
or push the matching stable `vX.Y.Z` tag.

The existing WordPress compatibility matrix, integration tests and strict Plugin
Check run first. The release job publishes the GitHub ZIP and then calls the SVN
workflow directly. It previews changes unless `WORDPRESS_SVN_ENABLED=true`, which
enables live publication. This does not depend on a
second release event, which a GitHub-token-created release would not trigger.

The SVN workflow checks out the release tag, requires its commit to be on main's
history, checks the stable GitHub release, verifies all version fields and bundled
dependency hashes, then publishes only `frica-id-login/` to SVN `trunk/` and
`tags/X.Y.Z/` in one commit. The reusable caller attests only the exact commit that
already passed its tests; manual runs independently check successful CI.

All SVN deployments are serialized. A retry with identical files makes no commit;
an existing tag with different files is refused. A version older than an existing
SVN release or trunk stable tag is refused. Network errors fail the deployment
rather than being treated as a missing version. A live run verifies the committed
SVN tag's file hashes afterward. To correct released code, increment the version.

Optional directory artwork belongs in repository `.wordpress-org/`; it is copied
to SVN's top-level `assets/`. Runtime CSS stays in `frica-id-login/assets/`.
Without directory artwork, existing SVN artwork is left alone. Tests, tools,
GitHub configuration and ZIP archives are not published to SVN.

If WordPress.org release confirmation is enabled, confirm the release in its
Release Management dashboard after the SVN commit. An SVN commit alone does not
bypass that confirmation or guarantee immediate directory availability.

## References

- [Using Subversion](https://developer.wordpress.org/plugins/wordpress-org/how-to-use-subversion/)
- [SVN-specific passwords](https://make.wordpress.org/meta/handbook/tutorials-guides/svn-access/)
- [Release confirmation](https://developer.wordpress.org/plugins/wordpress-org/release-confirmation-emails/)
- [Plugin guidelines](https://developer.wordpress.org/plugins/wordpress-org/detailed-plugin-guidelines/)

Keep reviewer test accounts and credentials private. The plugin contacts the
external Frica ID service; its readme discloses scopes, transmitted data, token
handling and policy links. Website: https://frica.id.

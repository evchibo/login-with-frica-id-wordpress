# WordPress.org submission

## Current status

The plugin has a public GitHub repository, an installable package, directory-format `readme.txt`, GPL-compatible licenses, external-service disclosures and automated WordPress compatibility/Plugin Check jobs. It has not been approved or published in the WordPress.org Plugin Directory.

## Submit for review

1. Sign into the WordPress.org account that should own the plugin. Confirm the `Contributors` field in `frica-id-login/readme.txt` matches actual WordPress.org usernames; the verified publishing account is `ivanchibo`.
2. Review the [Detailed Plugin Guidelines](https://developer.wordpress.org/plugins/wordpress-org/detailed-plugin-guidelines/) and [Plugin Developer FAQ](https://developer.wordpress.org/plugins/wordpress-org/plugin-developer-faq/).
3. Download the plugin ZIP from [GitHub Releases](https://github.com/evchibo/login-with-frica-id-wordpress/releases/latest). Use the packaged asset, not the generated source-code archive. It includes the plugin folder, PHP/CSS, readme, licenses and bundled runtime dependency; it excludes tests and development tools.
4. Test the packaged plugin on an HTTPS WordPress site with a registered Frica OAuth application and the site's exact callback URI. Confirm sign-in, new registration, existing-account linking, email-collision rejection, disconnect and password recovery.
5. Open [Add your plugin](https://wordpress.org/plugins/developers/add/), upload the ZIP, complete the accurate ownership/guidelines attestations and submit it for manual review. Proposed plugin name: **Login with Frica ID**. Proposed slug: **frica-id-login**, subject to availability and review-team approval.
6. Follow the review team's email instructions and address requested corrections. Submission is not publication or approval.

The Frica service requires a developer application. The readme discloses the service, requested scopes, transmitted data, token handling and policies. Website: https://frica.id . Keep review-team credentials and any test account details private; do not commit them to this repository.

## After approval

WordPress.org supplies an SVN repository for the approved slug. Publish the tested runtime plugin contents in `trunk/`, create the matching version under `tags/1.0.1/`, and keep `Stable tag` aligned with the plugin version. Directory artwork belongs in the SVN `assets/` directory rather than the runtime ZIP. Refer to the [official SVN guide](https://developer.wordpress.org/plugins/wordpress-org/how-to-use-subversion/) before the first deployment.

GitHub remains the development repository. SVN holds tested releases. An automated SVN deploy can be configured after approval using the approved slug and a dedicated WordPress.org SVN credential stored in GitHub Actions secrets. No WordPress.org credentials are stored in this project and no SVN publication is attempted before approval.

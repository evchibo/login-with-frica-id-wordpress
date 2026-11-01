"""Deployment guards: no production SVN writes or credentials used by these tests."""
import importlib.util
from pathlib import Path
import tempfile
import shutil
import subprocess
import unittest
from unittest.mock import patch

ROOT = Path(__file__).resolve().parents[1]
spec = importlib.util.spec_from_file_location('wordpress_svn', ROOT / 'tools/wordpress-svn.py')
deploy = importlib.util.module_from_spec(spec)
spec.loader.exec_module(deploy)


def listing(names):
    return '<lists><list>' + ''.join('<entry kind="dir"><name>' + name + '</name></entry>' for name in names) + '</list></lists>'


class DeploymentGuards(unittest.TestCase):
    @unittest.skipUnless(shutil.which('svn') and shutil.which('svnadmin'), 'SVN tools required')
    def test_real_svn_repository_first_release_retry_and_changed_files(self):
        with tempfile.TemporaryDirectory() as temp:
            root = Path(temp)
            repository = root / 'repo'
            subprocess.run(['svnadmin', 'create', str(repository)], check=True, capture_output=True)
            url = repository.as_uri()
            deploy.svn('mkdir', url + '/trunk', url + '/tags', url + '/assets', '-m', 'Initialize test repository')
            plugin = root / 'plugin'
            plugin.mkdir()
            (plugin / 'readme.txt').write_text('Stable tag: 1.1.0\n')
            (plugin / 'test.php').write_text('<?php // example\n')
            self.assertTrue(deploy.check_svn(plugin, '1.1.0', url))
            deploy.svn('import', str(plugin), url + '/tags/1.1.0', '-m', 'Test version')
            self.assertFalse(deploy.check_svn(plugin, '1.1.0', url))
            (plugin / 'test.php').write_text('<?php // changed\n')
            with self.assertRaisesRegex(ValueError, 'different files'):
                deploy.check_svn(plugin, '1.1.0', url)
            with self.assertRaisesRegex(ValueError, 'newer SVN release'):
                deploy.check_svn(plugin, '1.0.0', url)

    def test_release_metadata_matches(self):
        version = (ROOT / 'frica-id-login/readme.txt').read_text().split('Stable tag: ', 1)[1].splitlines()[0]
        self.assertEqual(deploy.validate_version(ROOT, 'v' + version), version)

    def test_rejects_bad_tags_and_version_mismatch(self):
        for tag in ['main', 'v1.1.0-beta', 'v01.1.0', 'v1.1.0\n', 'v999.0.0', 'v1.1.0;echo bad']:
            with self.subTest(tag=tag), self.assertRaises(ValueError):
                deploy.validate_version(ROOT, tag)

    def test_stable_tag_mismatch(self):
        with tempfile.TemporaryDirectory() as temp:
            root = Path(temp)
            plugin = root / deploy.SLUG
            plugin.mkdir()
            (plugin / 'frica-id-login.php').write_text(" * Version: 2.0.0\ndefine('FRICA_ID_LOGIN_VERSION', '2.0.0');")
            (plugin / 'readme.txt').write_text('Stable tag: 1.0.0\n')
            with self.assertRaisesRegex(ValueError, 'Stable tag'):
                deploy.validate_version(root, 'v2.0.0')

    def test_first_release_with_empty_repository(self):
        with patch.object(deploy, 'svn', return_value=listing([])):
            self.assertTrue(deploy.check_svn(ROOT / deploy.SLUG, '1.1.0'))

    def test_existing_identical_version_is_noop(self):
        with patch.object(deploy, 'svn', side_effect=[listing(['1.1.0']), 'exported']), \
             patch.object(deploy, 'snapshot', return_value={'file': 'same'}):
            self.assertFalse(deploy.check_svn(ROOT / deploy.SLUG, '1.1.0'))

    def test_existing_different_version_is_never_overwritten(self):
        with patch.object(deploy, 'svn', side_effect=[listing(['1.1.0']), 'exported']), \
             patch.object(deploy, 'snapshot', side_effect=[{'file': 'old'}, {'file': 'changed'}]):
            with self.assertRaisesRegex(ValueError, 'different files'):
                deploy.check_svn(ROOT / deploy.SLUG, '1.1.0')

    def test_older_release_cannot_replace_newer_release(self):
        with patch.object(deploy, 'svn', return_value=listing(['1.2.0'])):
            with self.assertRaisesRegex(ValueError, 'newer SVN release'):
                deploy.check_svn(ROOT / deploy.SLUG, '1.1.0')

    def test_newer_trunk_is_not_rolled_back(self):
        with patch.object(deploy, 'svn', side_effect=[listing([]), listing(['readme.txt']), 'Stable tag: 2.0.0\n']):
            with self.assertRaisesRegex(ValueError, 'newer SVN trunk'):
                deploy.check_svn(ROOT / deploy.SLUG, '1.1.0')

    def test_network_failure_is_not_a_missing_version(self):
        with patch.object(deploy, 'svn', side_effect=ValueError('SVN read failed')):
            with self.assertRaisesRegex(ValueError, 'SVN read failed'):
                deploy.check_svn(ROOT / deploy.SLUG, '1.1.0')

    def test_manual_release_needs_exact_successful_ci(self):
        release = {'tag_name': 'v1.1.0', 'draft': False, 'prerelease': False}
        for run in [{'head_sha': 'other', 'conclusion': 'success', 'event': 'push'},
                    {'head_sha': 'abc', 'conclusion': 'failure', 'event': 'push'},
                    {'head_sha': 'abc', 'conclusion': 'success', 'event': 'pull_request'}]:
            with patch.object(deploy, 'github_json', side_effect=[release, {'workflow_runs': [run]}]):
                with self.assertRaisesRegex(ValueError, 'successful'):
                    deploy.check_github_release('example/plugin', 'v1.1.0', 'abc')

    def test_successful_ci_and_stable_release_are_accepted(self):
        with patch.object(deploy, 'github_json', side_effect=[{'tag_name': 'v1.1.0'}, {'workflow_runs': [
            {'head_sha': 'abc', 'conclusion': 'success', 'event': 'push'}]}]):
            deploy.check_github_release('example/plugin', 'v1.1.0', 'abc')

    def test_reusable_caller_can_attest_only_the_exact_commit(self):
        with patch.object(deploy, 'github_json', return_value={'tag_name': 'v1.1.0'}) as remote:
            deploy.check_github_release('example/plugin', 'v1.1.0', 'abc', 'abc')
            self.assertEqual(remote.call_count, 1)
        with patch.object(deploy, 'github_json', side_effect=[{'tag_name': 'v1.1.0'}, {'workflow_runs': []}]):
            with self.assertRaises(ValueError):
                deploy.check_github_release('example/plugin', 'v1.1.0', 'abc', 'other')

    def test_draft_or_prerelease_is_rejected(self):
        for release in [{'tag_name': 'v1.1.0', 'draft': True}, {'tag_name': 'v1.1.0', 'prerelease': True}]:
            with patch.object(deploy, 'github_json', return_value=release), self.assertRaises(ValueError):
                deploy.check_github_release('example/plugin', 'v1.1.0', 'abc', 'abc')

    def test_snapshot_detects_changes_and_symlinks(self):
        with tempfile.TemporaryDirectory() as temp:
            root = Path(temp)
            (root / 'file').write_text('a')
            old = deploy.snapshot(root)
            (root / 'file').write_text('b')
            self.assertNotEqual(old, deploy.snapshot(root))
            (root / 'link').symlink_to('file')
            with self.assertRaisesRegex(ValueError, 'Symlinks'):
                deploy.snapshot(root)


if __name__ == '__main__':
    unittest.main()

"""Ensure public releases cannot overwrite an existing version or ignore API failures."""
import contextlib
import io
import json
import os
from pathlib import Path
import runpy
import unittest
from unittest.mock import patch
import urllib.error

ROOT = Path(__file__).resolve().parents[1]
SCRIPT = ROOT / 'tools/release-version.py'
VERSION = (ROOT / 'frica-id-login/readme.txt').read_text().split('Stable tag: ', 1)[1].splitlines()[0]

class ReleaseGuards(unittest.TestCase):
    def run_release(self, response=None, error=None, ref='refs/heads/main'):
        env = {'GITHUB_REF': ref, 'GITHUB_REPOSITORY': 'example/plugin', 'GH_TOKEN': 'test-only'}
        output = io.StringIO()
        with patch.dict(os.environ, env), patch('urllib.request.urlopen') as remote, contextlib.redirect_stdout(output):
            if error:
                remote.side_effect = urllib.error.HTTPError('https://api.github.com', error, 'test', {}, None)
            else:
                remote.return_value.__enter__.return_value = io.StringIO(json.dumps(response))
            runpy.run_path(str(SCRIPT), run_name='__main__')
        return output.getvalue()

    def test_missing_release_can_publish(self):
        self.assertEqual(self.run_release(error=404), f'tag=v{VERSION}\npublish=true\n')

    def test_existing_release_is_preserved(self):
        self.assertEqual(self.run_release(response={'draft': False, 'prerelease': False}), f'tag=v{VERSION}\npublish=false\n')

    def test_api_permission_failure_does_not_publish(self):
        with self.assertRaisesRegex(SystemExit, 'HTTP 403'):
            self.run_release(error=403)

    def test_mismatched_tag_cannot_publish(self):
        with self.assertRaisesRegex(SystemExit, 'does not match'):
            self.run_release(ref='refs/tags/v9.9.9')

    def test_draft_release_is_not_overwritten(self):
        with self.assertRaisesRegex(SystemExit, 'manual review'):
            self.run_release(response={'draft': True})

if __name__ == '__main__':
    unittest.main()

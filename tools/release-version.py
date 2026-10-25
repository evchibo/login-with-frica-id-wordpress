#!/usr/bin/env python3
"""Validate release metadata and create only a missing immutable version release."""
import json
import os
from pathlib import Path
import re
import urllib.error
import urllib.request

ROOT = Path(__file__).resolve().parents[1]
header = (ROOT / 'frica-id-login/frica-id-login.php').read_text()
version = re.search(r'^ \* Version: (\d+\.\d+\.\d+)$', header, re.M).group(1)
stable = re.search(r'^Stable tag: (.+)$', (ROOT / 'frica-id-login/readme.txt').read_text(), re.M).group(1).strip()
if stable != version:
    raise SystemExit('Plugin version and readme stable tag differ')
tag = 'v' + version
ref = os.environ['GITHUB_REF']
if ref.startswith('refs/tags/') and ref != 'refs/tags/' + tag:
    raise SystemExit('Pushed tag does not match the plugin version')
if tag not in (ROOT / 'RELEASE_NOTES.md').read_text().splitlines()[0]:
    raise SystemExit('Release notes heading does not match the plugin version')
request = urllib.request.Request(
    'https://api.github.com/repos/' + os.environ['GITHUB_REPOSITORY'] + '/releases/tags/' + tag,
    headers={'Accept': 'application/vnd.github+json', 'Authorization': 'Bearer ' + os.environ['GH_TOKEN'], 'X-GitHub-Api-Version': '2022-11-28'},
)
try:
    with urllib.request.urlopen(request, timeout=30) as response:
        release = json.load(response)
        if release.get('draft') or release.get('prerelease'):
            raise SystemExit('An existing draft or prerelease requires manual review')
        publish = False
except urllib.error.HTTPError as error:
    if error.code != 404:
        raise SystemExit('Could not check existing release (HTTP ' + str(error.code) + ')')
    publish = True
print('tag=' + tag)
print('publish=' + str(publish).lower())

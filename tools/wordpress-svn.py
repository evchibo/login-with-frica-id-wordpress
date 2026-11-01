#!/usr/bin/env python3
"""Read-only release checks before the pinned WordPress.org deployment action."""
import argparse
import hashlib
import json
import os
from pathlib import Path
import re
import subprocess
import tempfile
import urllib.request
import xml.etree.ElementTree as ET

SLUG = 'frica-id-login'
SVN_URL = 'https://plugins.svn.wordpress.org/' + SLUG


def version_tuple(value):
    if not re.fullmatch(r'(0|[1-9]\d*)\.(0|[1-9]\d*)\.(0|[1-9]\d*)', value):
        raise ValueError('Expected a stable three-part version')
    return tuple(map(int, value.split('.')))


def validate_version(source, tag):
    if not tag.startswith('v'):
        raise ValueError('Use a version tag such as v1.1.0')
    version = tag[1:]
    version_tuple(version)
    plugin = source / SLUG
    header = (plugin / (SLUG + '.php')).read_text()
    readme = (plugin / 'readme.txt').read_text()
    if not re.search(r'^ \* Version: ' + re.escape(version) + r'\s*$', header, re.M):
        raise ValueError('Git tag and plugin Version differ')
    if not re.search(r"define\('FRICA_ID_LOGIN_VERSION', '" + re.escape(version) + r"'\);", header):
        raise ValueError('Plugin version constant differs')
    if not re.search(r'^Stable tag: ' + re.escape(version) + r'\s*$', readme, re.M):
        raise ValueError('Git tag and readme Stable tag differ')
    return version


def github_json(repository, path):
    request = urllib.request.Request('https://api.github.com/repos/' + repository + path, headers={
        'Accept': 'application/vnd.github+json',
        'Authorization': 'Bearer ' + os.environ['GH_TOKEN'],
        'X-GitHub-Api-Version': '2022-11-28',
    })
    with urllib.request.urlopen(request, timeout=30) as response:
        return json.load(response)


def check_github_release(repository, tag, sha, validated_sha=''):
    release = github_json(repository, '/releases/tags/' + tag)
    if release.get('draft') or release.get('prerelease') or release.get('tag_name') != tag:
        raise ValueError('A published stable GitHub release is required')
    # The reusable caller has already passed both jobs; its encompassing run is
    # still in progress until this deployment finishes. Manual runs get no bypass.
    if validated_sha and validated_sha == sha:
        return
    # Check runs on the exact tagged commit, not whichever main commit is newest.
    runs = github_json(repository, '/actions/workflows/wordpress-plugin.yml/runs?head_sha=' + sha + '&per_page=100')
    if not any(run.get('head_sha') == sha and run.get('conclusion') == 'success'
               and run.get('event') in ('push', 'workflow_dispatch')
               for run in runs.get('workflow_runs', [])):
        raise ValueError('The tagged commit must have a successful WordPress plugin CI run')


def svn(*args):
    result = subprocess.run(['svn', '--non-interactive', '--no-auth-cache', *args],
                            capture_output=True, text=True, timeout=120)
    if result.returncode:
        raise ValueError('SVN read failed; check repository availability and network access. ' + result.stderr.strip())
    return result.stdout


def snapshot(directory):
    files = {}
    for path in sorted(directory.rglob('*')):
        if path.is_symlink():
            raise ValueError('Symlinks are not allowed in the deployment package')
        if path.is_file():
            files[path.relative_to(directory).as_posix()] = hashlib.sha256(path.read_bytes()).hexdigest()
    return files


def check_svn(plugin, version, url=SVN_URL):
    # Listing tags first distinguishes a missing version from an unavailable repository.
    document = ET.fromstring(svn('list', '--xml', url + '/tags/'))
    tags = [node.text for node in document.findall('.//entry[@kind="dir"]/name')]
    numeric = [tuple(map(int, tag.split('.'))) for tag in tags if re.fullmatch(r'\d+(?:\.\d+)+', tag)]
    candidate = version_tuple(version)
    if any(tag > candidate for tag in numeric):
        raise ValueError('Refusing to replace a newer SVN release with an older version')
    if version in tags:
        with tempfile.TemporaryDirectory() as temp:
            exported = Path(temp) / 'published'
            svn('export', url + '/tags/' + version, str(exported))
            if snapshot(exported) != snapshot(plugin):
                raise ValueError('Existing SVN tag has different files; increment the plugin version')
        return False
    # A newer trunk must not be rolled back even if it has not been tagged yet.
    trunk = ET.fromstring(svn('list', '--xml', url + '/trunk/'))
    if any(node.text == 'readme.txt' for node in trunk.findall('.//entry/name')):
        readme = svn('cat', url + '/trunk/readme.txt')
        stable = re.search(r'^Stable tag:\s*(\S+)', readme, re.M)
        if not stable or stable.group(1).lower() == 'trunk':
            raise ValueError('SVN trunk has no numeric stable tag; inspect it before publishing')
        if not re.fullmatch(r'\d+(?:\.\d+)+', stable.group(1)):
            raise ValueError('SVN trunk has an unrecognized stable tag')
        if tuple(map(int, stable.group(1).split('.'))) > candidate:
            raise ValueError('Refusing to roll back a newer SVN trunk')
    return True


def main():
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument('--source', type=Path, required=True)
    parser.add_argument('--tag', required=True)
    parser.add_argument('--sha', required=True)
    parser.add_argument('--validated-sha', default='')
    args = parser.parse_args()
    try:
        version = validate_version(args.source, args.tag)
        check_github_release(os.environ['GITHUB_REPOSITORY'], args.tag, args.sha, args.validated_sha)
        publish = check_svn(args.source / SLUG, version)
    except (ValueError, OSError, subprocess.TimeoutExpired, ET.ParseError) as error:
        raise SystemExit(str(error)) from error
    print('version=' + version)
    print('publish=' + str(publish).lower())


if __name__ == '__main__':
    main()

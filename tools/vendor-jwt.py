#!/usr/bin/env python3
"""Rebuild the isolated JWT dependency from an immutable upstream commit."""
import hashlib
import json
import pathlib
import urllib.request

ROOT = pathlib.Path(__file__).resolve().parents[1] / 'frica-id-login' / 'vendor' / 'php-jwt'
COMMIT = '0501f5729b9e2448516674aff07c7d1727869b8e'  # firebase/php-jwt v7.2.1
FILES = ['JWT.php', 'JWK.php', 'Key.php', 'BeforeValidException.php', 'ExpiredException.php', 'SignatureInvalidException.php', 'JWTExceptionWithPayloadInterface.php']
ROOT.mkdir(parents=True, exist_ok=True)
manifest = {'package': 'firebase/php-jwt', 'version': '7.2.1', 'commit': COMMIT,
            'source': 'https://github.com/googleapis/php-jwt', 'license': 'BSD-3-Clause',
            'modification': 'Namespace Firebase\\JWT replaced with FricaID\\Vendor\\Firebase\\JWT; no functional changes.', 'files': {}}
for name in FILES + ['LICENSE']:
    upstream = ('src/' if name.endswith('.php') else '') + name
    data = urllib.request.urlopen(f'https://raw.githubusercontent.com/googleapis/php-jwt/{COMMIT}/{upstream}', timeout=45).read()
    manifest['files'][name] = {'upstream_sha256': hashlib.sha256(data).hexdigest()}
    if name.endswith('.php'):
        data = data.replace(b'Firebase\\JWT', b'FricaID\\Vendor\\Firebase\\JWT')
    manifest['files'][name]['bundled_sha256'] = hashlib.sha256(data).hexdigest()
    (ROOT / name).write_bytes(data)
(ROOT / 'upstream.json').write_text(json.dumps(manifest, indent=2) + '\n')
print('Vendored', manifest['package'], manifest['version'], 'at', COMMIT)

#!/usr/bin/env python3
"""Build a reproducible WordPress-uploadable ZIP; excludes tests and development tools."""
import hashlib
import json
import pathlib
import re
import sys
import zipfile

ROOT = pathlib.Path(__file__).resolve().parents[1]
PLUGIN = ROOT / 'frica-id-login'
version = re.search(r' \* Version: ([0-9]+\.[0-9]+\.[0-9]+)', (PLUGIN / 'frica-id-login.php').read_text()).group(1)
OUTPUT = pathlib.Path(sys.argv[1] if len(sys.argv) > 1 else ROOT / 'dist' / f'frica-id-login-{version}.zip')
manifest = json.loads((PLUGIN / 'vendor/php-jwt/upstream.json').read_text())
for name, hashes in manifest['files'].items():
    actual = hashlib.sha256((PLUGIN / 'vendor/php-jwt' / name).read_bytes()).hexdigest()
    if actual != hashes['bundled_sha256']:
        raise SystemExit(f'Dependency integrity check failed: {name}')
OUTPUT.parent.mkdir(parents=True, exist_ok=True)
with zipfile.ZipFile(OUTPUT, 'w', zipfile.ZIP_DEFLATED, compresslevel=9) as archive:
    for path in sorted(PLUGIN.rglob('*')):
        if path.is_file():
            name = str(path.relative_to(ROOT))
            info = zipfile.ZipInfo(name, (2026, 10, 25, 0, 0, 0))
            info.compress_type = zipfile.ZIP_DEFLATED
            info.external_attr = 0o100644 << 16
            archive.writestr(info, path.read_bytes())
print(OUTPUT.resolve())
print('SHA256:', hashlib.sha256(OUTPUT.read_bytes()).hexdigest())

from __future__ import annotations
import base64, hashlib, hmac, http.cookiejar, json, re, struct, subprocess, time, urllib.parse, urllib.request
from pathlib import Path
from typing import Any

ROOT = Path(__file__).resolve().parent
REPO_ROOT = ROOT.parent
BASE_URL = 'http://localhost/kodus'
PASSWORD = 'TempPass123!'
YEAR = '2026'
TEST_PREFIX = 'ZZ EXEC TEST'


def run_cmd(cmd, stdin=None):
    r = subprocess.run(cmd, input=stdin, capture_output=True, text=True, cwd=str(REPO_ROOT), check=False)
    if r.returncode != 0:
        raise RuntimeError(r.stderr.strip() or r.stdout.strip() or 'command failed')
    return r.stdout


def php(code: str) -> str:
    return run_cmd(['php'], f"<?php\n{code}\n?>\n")


def php_json(code: str) -> Any:
    return json.loads(php(code))


def parse_csrf(html: str) -> str:
    for p in [r'name="csrf_token"\s+value="([^"]+)"', r'window\.KODUS_CSRF_TOKEN\s*=\s*"([^"]+)"', r"window\.KODUS_CSRF_TOKEN\s*=\s*'([^']+)'"]:
        m = re.search(p, html)
        if m:
            return m.group(1)
    raise RuntimeError('csrf not found')


def hotp(secret: str, counter: int, digits: int = 6) -> str:
    key = base64.b32decode(secret.upper() + '=' * ((8 - len(secret) % 8) % 8))
    digest = hmac.new(key, struct.pack('>Q', counter), hashlib.sha1).digest()
    offset = digest[-1] & 15
    code = struct.unpack('>I', digest[offset:offset+4])[0] & 0x7fffffff
    return str(code % (10 ** digits)).zfill(digits)


def totp(secret: str) -> str:
    return hotp(secret, int(time.time()) // 30)


def multipart(fields, files):
    b = '----KODUS' + hashlib.md5(str(time.time()).encode()).hexdigest()
    body = bytearray()
    for k, v in fields.items():
        vals = v if isinstance(v, list) else [v]
        for item in vals:
            body.extend(f'--{b}\r\nContent-Disposition: form-data; name="{k}"\r\n\r\n{item}\r\n'.encode())
    for k, (name, content, ctype) in files.items():
        body.extend(f'--{b}\r\nContent-Disposition: form-data; name="{k}"; filename="{name}"\r\nContent-Type: {ctype}\r\n\r\n'.encode())
        body.extend(content); body.extend(b'\r\n')
    body.extend(f'--{b}--\r\n'.encode())
    return bytes(body), b


class Client:
    def __init__(self):
        self.jar = http.cookiejar.CookieJar()
        self.opener = urllib.request.build_opener(urllib.request.HTTPCookieProcessor(self.jar))
        self.last_url = ''
    def req(self, path, method='GET', data=None, headers=None):
        url = path if path.startswith('http') else f"{BASE_URL}/{path.lstrip('/')}"
        payload = urllib.parse.urlencode(data, doseq=True).encode() if isinstance(data, dict) else data
        hdrs = {'User-Agent': 'Codex Executor/1.0'}
        if headers: hdrs.update(headers)
        req = urllib.request.Request(url, data=payload, headers=hdrs, method=method.upper())
        try:
            resp = self.opener.open(req, timeout=25)
            self.last_url = resp.geturl()
            return resp.status, resp.read().decode('utf-8', errors='replace'), self.last_url
        except urllib.error.HTTPError as e:
            self.last_url = e.geturl()
            return e.code, e.read().decode('utf-8', errors='replace'), self.last_url
    def login(self, username, password=PASSWORD):
        self.req('select_year.php', 'POST', {'year': YEAR})
        csrf = parse_csrf(self.req('')[1])
        self.req('login.php', 'POST', {'csrf_token': csrf, 'username': username, 'password': password})
    def csrf(self, path='home'):
        return parse_csrf(self.req(path)[1])


def result(status, remarks, dev=''):
    return {'Test Status': status, 'Remarks': remarks, 'Developer Comments': dev, 'Assigned Developer': 'TBD', 'Dev Status': 'Fixed' if status == 'Passed' else 'Pending', 'Resolved By': 'N/A'}

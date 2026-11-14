"""Standard-library HTTP integration check. Starts an isolated PHP demo server."""
import http.cookiejar
import os
from pathlib import Path
import re
import socket
import subprocess
import tempfile
import time
import urllib.error
import urllib.parse
import urllib.request

ROOT = Path(__file__).resolve().parents[1]
PHP = os.environ.get('PHP_BINARY', 'php')

with tempfile.TemporaryDirectory() as directory:
    env = {**os.environ, 'DB_PATH': f'{directory}/test.sqlite', 'UPLOAD_DIR': f'{directory}/documents', 'SESSION_PATH': f'{directory}/sessions'}
    subprocess.run([PHP, 'bin/seed.php'], cwd=ROOT, env=env, check=True, stdout=subprocess.DEVNULL)
    with socket.socket() as sock:
        sock.bind(('127.0.0.1', 0))
        port = sock.getsockname()[1]
    base = f'http://127.0.0.1:{port}'
    log = open(f'{directory}/server.log', 'w+')
    server = subprocess.Popen([PHP, '-S', f'127.0.0.1:{port}', '-t', 'public', 'public/router.php'], cwd=ROOT, env=env, stdout=log, stderr=log)
    try:
        for _ in range(100):
            try:
                urllib.request.urlopen(base+'/?page=health', timeout=1)
                break
            except (urllib.error.URLError, TimeoutError):
                time.sleep(.05)
        else:
            raise RuntimeError('PHP server did not start')

        client = urllib.request.build_opener(urllib.request.HTTPCookieProcessor(http.cookiejar.CookieJar()))
        def get(path):
            return client.open(base+path).read().decode()
        def post(path, data):
            return client.open(base+path, urllib.parse.urlencode(data).encode()).read().decode()
        def csrf(html):
            return re.search(r'name="csrf" value="([a-f0-9]+)"', html).group(1)
        def expect_error(status, action):
            try:
                action()
            except urllib.error.HTTPError as error:
                assert error.code == status, error.code
            else:
                raise AssertionError(f'Expected HTTP {status}')

        html = get('/?page=login')
        html = post('/?page=login', {'csrf': csrf(html), 'email': 'student@example.test', 'password': 'DemoPass123!'})
        assert 'Your research workspace' in html, html[:5000]
        expect_error(403, lambda: post('/?page=create', {'title':'CSRF attempt'}))
        expect_error(404, lambda: get('/?page=detail&id=3'))
        token = csrf(html)
        assert 'New thesis proposal' in get('/?page=create')
        html = post('/?page=create', {'csrf':token,'title':'HTTP integration thesis','abstract':'A sufficiently detailed abstract for the integration test.','advisor_id':3})
        thesis_id = re.search(r'page=edit&amp;id=(\d+)|page=edit&id=(\d+)',html)
        thesis_id = next(value for value in thesis_id.groups() if value)
        assert 'HTTP integration thesis' in get(f'/?page=edit&id={thesis_id}')
        html = post(f'/?page=comment&id={thesis_id}',{'csrf':token,'body':'<script>unsafe()</script>'})
        assert '&lt;script&gt;unsafe()&lt;/script&gt;' in html and '<script>unsafe()' not in html
        boundary = 'thesisflow-test-boundary'
        payload = (f'--{boundary}\r\nContent-Disposition: form-data; name="csrf"\r\n\r\n{token}\r\n'
                   f'--{boundary}\r\nContent-Disposition: form-data; name="document"; filename="paper.pdf"\r\nContent-Type: application/pdf\r\n\r\n').encode()
        pdf = b'%PDF-1.4\n1 0 obj\n<< /Type /Catalog >>\nendobj\n%%EOF\n'
        payload += pdf + f'\r\n--{boundary}--\r\n'.encode()
        req = urllib.request.Request(base+f'/?page=upload&id={thesis_id}', data=payload, headers={'Content-Type':f'multipart/form-data; boundary={boundary}'})
        html = client.open(req).read().decode()
        assert 'PDF uploaded' in html
        document_id = re.search(r'page=download&id=(\d+)',html).group(1)
        assert client.open(base+f'/?page=download&id={document_id}').read() == pdf
        expect_error(404,lambda: get('/storage/documents/paper.pdf'))
        html = post('/?page=logout', {'csrf':token})
        html = post('/?page=login',{'csrf':csrf(html),'email':'student2@example.test','password':'DemoPass123!'})
        expect_error(404,lambda:get(f'/?page=download&id={document_id}'))
        print('PASS HTTP: login, CSRF, ownership, creation, escaped comments, PDF upload/download, private storage, logout')
    except Exception:
        log.flush(); log.seek(0); print(log.read()); raise
    finally:
        server.terminate()
        server.wait(timeout=10)
        log.close()

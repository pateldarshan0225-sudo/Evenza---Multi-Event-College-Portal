"""Read-only HTTP, access-guard and local asset checks. Never submits forms."""
import json
import re
import urllib.request
import urllib.parse
from html.parser import HTMLParser
from pathlib import Path

BASE = 'http://localhost/Evenza/'
class Assets(HTMLParser):
    def __init__(self):
        super().__init__()
        self.urls = []
    def handle_starttag(self, tag, attrs):
        attrs = dict(attrs)
        url = attrs.get('src') if tag in ('img', 'script') else attrs.get('href') if tag == 'link' and attrs.get('rel') == 'stylesheet' else None
        if url:
            self.urls.append(url)

results = []
assets = set()
public = ['index', 'about', 'categories', 'colleges', 'contact', 'events', 'login', 'register', 'register_college', 'register_university']
pages = ['Frontend/' + p + '.php' for p in public]
pages += ['Frontend/events.php?search=no_such_event_audit', 'Frontend/colleges.php?search=no_such_college_audit', 'Frontend/event_detail.php?id=-1']
for page in pages:
    with urllib.request.urlopen(BASE + page, timeout=15) as r:
        body = r.read().decode('utf-8', errors='replace')
        results.append({'page': page, 'status': r.status, 'php_error': bool(re.search(r'Fatal error|Parse error|Warning:|Database Connection Failed', body))})
        parser = Assets()
        parser.feed(body)
        assets.update(urllib.parse.urljoin(r.url, u) for u in parser.urls if not u.startswith(('data:', 'https:')))
        if page == 'Frontend/events.php':
            event_ids = list(dict.fromkeys(re.findall(r'event_detail\.php\?id=(\d+)', body)))[:2]
for event_id in event_ids:
    with urllib.request.urlopen(BASE + 'Frontend/event_detail.php?id=' + event_id, timeout=15) as r:
        body = r.read().decode('utf-8', errors='replace')
        results.append({'page': 'event_detail.php?id=' + event_id, 'status': r.status, 'php_error': bool(re.search(r'Fatal error|Parse error|Warning:', body))})
for folder, names in {'Admin': ['Dashboard','Colleges','all_events','allregistrations','allstudents','allteams','alluniversity','change_password','event_cat','teamdetails'], 'Student': ['Dashboard','MyEvents','MyTeams','Profile'], 'Organizer': ['Dashboard','Events','Registrations','Students','Profile']}.items():
    for name in names:
        with urllib.request.urlopen(BASE + folder + '/' + name + '.php', timeout=15) as r:
            results.append({'page': folder + '/' + name + '.php', 'unauthenticated_redirect': urllib.parse.urlparse(r.url).path.endswith('/Frontend/login.php')})
for url in sorted(assets):
    try:
        with urllib.request.urlopen(url, timeout=15) as r:
            results.append({'asset': urllib.parse.urlparse(url).path, 'status': r.status})
    except Exception as error:
        results.append({'asset': urllib.parse.urlparse(url).path, 'error': str(error)})
Path('tools/http-audit-results.json').write_text(json.dumps(results, indent=2), encoding='utf-8')
failed = [r for r in results if r.get('php_error') or r.get('error') or r.get('unauthenticated_redirect') is False]
print(json.dumps({'checks': len(results), 'failures': failed}, indent=2))

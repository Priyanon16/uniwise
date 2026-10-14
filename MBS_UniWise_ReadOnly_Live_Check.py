#!/usr/bin/env python3
"""MBS UniWise -- read-only smoke checks on the user's own VPS.
Does not write CSV/DB, restart services, or reveal API credentials.
Run: python3 MBS_UniWise_ReadOnly_Live_Check.py
Override HTTP base with MBS_CHECK_BASE_URL if Apache differs.
"""
import csv
import json
import os
import subprocess
from pathlib import Path
from urllib.error import HTTPError, URLError
from urllib.parse import urlencode
from urllib.request import Request, urlopen

BASE = os.environ.get('MBS_CHECK_BASE_URL', 'http://127.0.0.1').rstrip('/')
DATA = Path(os.environ.get('MBS_CHECK_CSV', '/var/www/html/uniwise/external_retriever/data/dify_segments.csv'))

def req(path, qs=None, root=False):
    url = (BASE + path) if not root else path
    if qs:
        url += '?' + urlencode(qs)
    try:
        with urlopen(Request(url, headers={'Accept':'application/json'}), timeout=20) as r:
            raw = r.read(3_000_000)
            status = r.status
        doc = json.loads(raw)
        success = doc.get('success') if isinstance(doc, dict) else None
        c = doc.get('count', doc.get('total', '?')) if isinstance(doc, dict) else '?'
        top = ','.join(doc.keys())[:130] if isinstance(doc,dict) else 'array'
        return f'HTTP={status}, success={success}, count={c}, keys={top}'
    except HTTPError as e:
        return f'HTTP={e.code} ERROR'
    except (URLError,TimeoutError) as e:
        return f'CONNECTION_ERROR: {type(e).__name__}'
    except (ValueError,UnicodeError) as e:
        return f'JSON_ERROR: {type(e).__name__}'

print('=== MBS UniWise Read-only Live Audit ===')
checks = [
    ('PROGRAMS', '/uniwise/api/programs.php', {'topic':'major_list','degree_level':'bachelor'}),
    ('ADMISSION', '/uniwise/api/admission.php', {'mode':'events'}),
    ('CALENDAR', '/uniwise/api/calendar.php', {'academic_year':'2569'}),
    ('INTERNSHIP', '/uniwise/api/internship.php', {'section':'documents','type':'coop'}),
    ('CONTACT', '/uniwise/api/contact.php', None),
    ('ADVISOR', '/uniwise/api/advisor.php', {'academic_year':'2569','program':'คอมพิวเตอร์ธุรกิจ'}),
]
for label,path,q in checks:
    print(label + ':', req(path,q))
print('PERSONNEL_EXTERNAL:', req('https://mbs.msu.ac.th/th/api/people.php', {'type':'academic','group_id':'4'}, root=True))
print('RETRIEVER_HEALTH:', req('http://127.0.0.1:18080/health', root=True))
try:
    with DATA.open(encoding='utf-8-sig', newline='') as f:
        rows = list(csv.DictReader(f))
    ids = [r.get('segment_id','').strip() for r in rows]
    print('DOCUMENT_CSV:', 'rows='+str(len(rows)), 'unique_ids='+str(len(set(ids))),
        'empty_ids='+str(sum(not i for i in ids)),
        'empty_content='+str(sum(not r.get('content','').strip() for r in rows)))
except (OSError,csv.Error) as e:
    print('DOCUMENT_CSV: ERROR:',type(e).__name__)

# Dashboard uses these tables through the same api/db.php as the public API.
# Read-only SELECT COUNT(*); does not expose MySQL password or row-level data.
php_source = r'''
require "/var/www/html/uniwise/api/db.php";
$tables = ["academic_programs","admission_schedules","academic_calendar_events",
 "faculty_contacts","internship_types","internship_periods","internship_steps",
 "internship_document_requirements","application_periods"];
$out = [];
foreach ($tables as $t) {
    try { $out[$t] = (int)$pdo->query("SELECT COUNT(*) FROM `".$t."`")->fetchColumn(); }
    catch (Throwable $e) { $out[$t] = "QUERY_ERROR"; }
}
echo json_encode($out);
'''
try:
    command = ['php', '-r', php_source]
    if os.geteuid() == 0:
        command = ['runuser', '-u', 'www-data', '--'] + command
    result = subprocess.run(command, capture_output=True, text=True, timeout=30)
    db_counts = json.loads(result.stdout) if result.returncode == 0 else None
    if isinstance(db_counts, dict):
        print('DASHBOARD_MYSQL_COUNTS:', json.dumps(db_counts, ensure_ascii=False, sort_keys=True))
    else:
        print('DASHBOARD_MYSQL_COUNTS: CHECK_ERROR (no credentials shown)')
except (OSError, subprocess.TimeoutExpired, ValueError):
    print('DASHBOARD_MYSQL_COUNTS: CHECK_ERROR (no credentials shown)')

print('Note: HTTP success alone does not prove response correctness or full DB/Workflow equivalence.')
print('No DB writes, no segment modifications, and no sensitive values printed.')

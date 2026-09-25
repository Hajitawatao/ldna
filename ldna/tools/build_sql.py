"""
Writes database/seed.sql from backend/seed/*.json.
Usage: python3 tools/build_sql.py   (from the project root)
"""
import json

S = lambda n: json.load(open(f'backend/seed/{n}.json', encoding='utf-8'))

def q(v):
    if v is None: return 'NULL'
    if isinstance(v, bool): return '1' if v else '0'
    if isinstance(v, (int, float)): return str(v)
    if isinstance(v, (list, dict)): v = json.dumps(v, ensure_ascii=False)
    return "'" + str(v).replace('\\', '\\\\').replace("'", "''") + "'"

def insert(table, cols, rows, chunk=200):
    if not rows: return ''
    out = []
    for i in range(0, len(rows), chunk):
        vals = ',\n'.join('(' + ','.join(q(v) for v in r) + ')' for r in rows[i:i + chunk])
        out.append(f"INSERT INTO {table} ({','.join(cols)}) VALUES\n{vals};")
    return '\n'.join(out)

lv, d, o, p, c, t = S('levels'), S('divisions'), S('offices'), S('positions'), S('competencies'), S('trainings')
pr = S('profiles')
parts = ['SET NAMES utf8mb4;', 'START TRANSACTION;',
    insert('assessment_levels', ['level', 'label', 'min_sg', 'max_sg'], [(x['level'], x['label'], x['min_sg'], x['max_sg']) for x in lv]),
    insert('divisions', ['id', 'code', 'name'], [(x['id'], x['code'], x['name']) for x in d]),
    insert('offices', ['id', 'division_id', 'area', 'name', 'portal_code', 'source'],
           [(x['id'], x['division_id'], x['area'], x['name'], x['portal_code'], x.get('source', 'added')) for x in o]),
    insert('positions', ['id', 'title', 'salary_grade', 'current', 'portal_code'],
           [(x['id'], x['title'], x.get('salary_grade'), x.get('current', True), x['portal_code']) for x in p]),
    insert('competencies', ['id', 'name', 'category', 'definition'], [(x['id'], x['name'], x['category'], x['definition']) for x in c]),
    insert('competency_levels', ['competency_id', 'level', 'description', 'indicators', 'verification'],
           [(x['id'], int(l), v['description'], v['indicators'], v['verification']) for x in c for l, v in sorted(x['levels'].items())]),
    insert('position_profile', ['id', 'position_id', 'office_id', 'source'], [(x['id'], x['position_id'], x['office_id'], x['source']) for x in pr]),
    insert('position_profile_competency', ['profile_id', 'competency_id', 'level'],
           [(x['id'], int(cid), lvl) for x in pr for cid, lvl in x['standards'].items()]),
    insert('trainings', ['id', 'title', 'level', 'all_positions', 'mode', 'type', 'provider', 'hours', 'description'],
           [(x['id'], x['title'], x['level'], x['all_positions'], x['mode'], x['type'], x['provider'], x['hours'], x['description']) for x in t]),
    insert('training_competency', ['training_id', 'competency_id'], [(x['id'], int(cid)) for x in t for cid in x['competency_ids']]),
    insert('training_area', ['training_id', 'office_id'], [(x['id'], oid) for x in t for oid in x['office_ids']]),
    insert('training_position', ['training_id', 'position_id'], [(x['id'], pid) for x in t for pid in x.get('position_ids', [])]),
    'COMMIT;']
open('database/seed.sql', 'w', encoding='utf-8').write('\n\n'.join(x for x in parts if x) + '\n')
print('trainings', len(t), '| training_area', sum(len(x['office_ids']) for x in t), '| training_position', sum(len(x.get('position_ids', [])) for x in t))

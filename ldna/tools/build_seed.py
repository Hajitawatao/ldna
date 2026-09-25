"""
Builds backend/seed/*.json from:
  - Level 3 Hospitals 2023 Competency Map (.xlsm)  -> competencies, positions, offices, profiles
  - Competency Catalog for Regional Offices (PDF)   -> level descriptors (pre-extracted to catalog.json)
Sample employees, assessments and trainings are generated for the UI prototype.
Usage: python3 tools/build_seed.py <map.xlsm> <catalog.json> <out_dir>
"""
import sys, json, re, random
import openpyxl

xlsm, catalog_path, out = sys.argv[1], sys.argv[2], sys.argv[3]
norm = lambda s: re.sub(r'[^a-z]', '', s.lower())
catalog = json.load(open(catalog_path))
cat_by_norm = {norm(k): v for k, v in catalog.items()}

# Salary grades for the sample positions. CETAR fills in the rest from the staffing pattern.
SALARY_GRADES = {
    'Chief of Medical Professional Staff II': 25, 'Chief Administrative Officer': 24,
    'Administrative Officer IV': 22, 'Supervising Administrative Officer': 22,
    'Administrative Assistant II': 8, 'Administrative Assistant I': 7, 'Administrative Aide VI': 6,
}

NAME_FIXES = {'Enterprice Resource Planning': 'Enterprise Resource Planning'}
OFFICE_FIXES = {'MS (Radiology': 'MS (Radiology)'}
# Divisions of the hospital. The 2023 map only carries a prefix (MS, NS, HOPSS, FS, OMCC),
# so each prefix is mapped to its division here. "Allied" has no prefix in the map yet -
# areas such as Radiology, Pharmacy and Nutrition & Dietetics are still filed under MS.
# Official R1MC divisions (tools/reference/R1MC_Divisions_and_Areas_2026.xlsx). The 2023 map only has
# prefixes (MS, NS, HOPSS, FS, OMCC), mapped to these divisions here.
DIVISIONS = [
    {'id': 1, 'code': 'HOPSS', 'name': 'HOSPITAL OPERATIONS AND PATIENT SUPPORT SERVICE', 'prefixes': ['HOPSS', 'PETRO']},
    {'id': 2, 'code': 'MS', 'name': 'MEDICAL SERVICE', 'prefixes': ['MS']},
    {'id': 3, 'code': 'NS', 'name': 'NURSING SERVICE', 'prefixes': ['NS']},
    {'id': 4, 'code': 'FS', 'name': 'FINANCE SERVICE', 'prefixes': ['FS', 'FINANCE']},
    {'id': 5, 'code': 'AHPS', 'name': 'ALLIED HEALTH PROFESSIONAL SERVICE', 'prefixes': ['ALLIED', 'AHPS']},
    {'id': 6, 'code': 'OCH', 'name': 'OFFICE OF THE CHIEF OF HOSPITAL', 'prefixes': ['OCH', 'MCCO', 'OMCC']},
]
PREFIX_TO_DIVISION = {p: d['id'] for d in DIVISIONS for p in d['prefixes']}

CATEGORY = {'CORE COMPETENCIES': 'core', 'ORGANIZATIONAL COMPETENCIES': 'organizational',
            'LEADERSHIP COMPETENCIES': 'leadership', 'TECHNICAL COMPETENCIES': 'technical'}

wb = openpyxl.load_workbook(xlsm, read_only=True, data_only=True)
ws = wb['Level 3 Hospitals']
rows = list(ws.iter_rows(min_row=1, max_row=107, values_only=True))

# --- competencies ---------------------------------------------------------
competencies, row_to_comp, section = [], {}, None
for idx, r in enumerate(rows, start=1):
    label = (r[1] or '').strip() if isinstance(r[1], str) else r[1]
    if label in CATEGORY:
        section = CATEGORY[label]; continue
    if section and r[0] is not None and label:
        name = NAME_FIXES.get(label, label)
        src = cat_by_norm.get(norm(name))
        comp = {
            'id': len(competencies) + 1,
            'name': name,
            'category': section,
            'definition': src['definition'] if src else None,
            'levels': {str(k): v for k, v in src['levels'].items()} if src else {},
        }
        competencies.append(comp); row_to_comp[idx] = comp['id']

# --- offices, positions, profiles -----------------------------------------
def split_office(name):
    """'HOPSS (HRM)' -> division prefix 'HOPSS', area 'HRM'."""
    m = re.match(r'^(.*?)\s*\((.*)\)\s*$', name)
    prefix, area = (m.group(1).strip(), m.group(2).strip()) if m else (name.strip(), None)
    division_id = PREFIX_TO_DIVISION.get(prefix.upper())
    return prefix, area or prefix, division_id

offices, positions, profiles = {}, {}, []
ncols = len(rows[1])
for c in range(2, ncols):
    pos = rows[1][c]; off = rows[2][c]
    if not pos: continue
    pos = ' '.join(str(pos).split()); off = ' '.join(str(off).split())
    off = OFFICE_FIXES.get(off, off)
    if pos not in positions:
        positions[pos] = {'id': len(positions) + 1, 'title': pos, 'portal_code': None, 'salary_grade': SALARY_GRADES.get(pos)}
    if off not in offices:
        prefix, area, division_id = split_office(off)
        # Two labels can resolve to the same division + area (e.g. "PETRO" and "HOPSS (PETRO)").
        # They are one office, so reuse it rather than create a duplicate.
        same = next((x for x in offices.values() if x['division_id'] == division_id
                     and x['area'].lower() == area.lower()), None)
        offices[off] = same or {'id': len({v['id'] for v in offices.values()}) + 1, 'name': off, 'area': area,
                                'division_id': division_id, 'division_prefix': prefix, 'portal_code': None}
    standards = {}
    for ridx, cid in row_to_comp.items():
        v = rows[ridx - 1][c]
        if v not in (None, ''):
            standards[str(cid)] = int(v)
    profiles.append({'id': len(profiles) + 1, 'position_id': positions[pos]['id'],
                     'office_id': offices[off]['id'], 'standards': standards, 'source': 'map-2023'})

positions = list(positions.values())

# Position level (1..4) = the core-competency level the 2023 map requires for the position.
# Every position has the same core level in every area it appears in, so it is stored once per position.
core_ids = {c['id'] for c in competencies if c['category'] == 'core'}
for p in positions:
    levels = {lvl for pr in profiles if pr['position_id'] == p['id'] for cid, lvl in pr['standards'].items() if int(cid) in core_ids}
    assert len(levels) <= 1, f"{p['title']} has different core levels: {levels}"
    p['level'] = levels.pop() if levels else None
off_id = {label: o['id'] for label, o in offices.items()}          # every label, incl. merged ones
offices = list({o['id']: o for o in offices.values()}.values())    # unique offices
pos_id = {p['title']: p['id'] for p in positions}

# --- official R1MC reference: areas per division, and current plantilla positions with SG -------------
REF = 'tools/reference'
DIV_BY_NAME = {d['name']: d['id'] for d in DIVISIONS}
for o in offices:
    o['source'] = 'map-2023'
wb_areas = openpyxl.load_workbook(f'{REF}/R1MC_Divisions_and_Areas_2026.xlsx', read_only=True, data_only=True)
official_areas = [(str(d).strip(), ' '.join(str(a).split())) for d, a in wb_areas.active.iter_rows(min_row=2, values_only=True) if d and a]
for dname, area in official_areas:
    did = DIV_BY_NAME[dname]
    same = next((o for o in offices if o['division_id'] == did and o['area'].lower() == area.lower()), None)
    if same:                                   # a 2023 label that is already an official area (e.g. DERMATOLOGY)
        same.update({'area': area, 'source': 'official-2026'})
        continue
    offices.append({'id': max(o['id'] for o in offices) + 1, 'name': f"{[d['code'] for d in DIVISIONS if d['id'] == did][0]} ({area})",
                    'area': area, 'division_id': did, 'division_prefix': '', 'portal_code': None, 'source': 'official-2026'})

def norm_title(t):
    t = t.lower().replace('systems', 'system').replace('therapy', 'therapist').replace('&', 'and')
    return re.sub(r'[^a-z0-9]', '', t)
wb_pos = openpyxl.load_workbook(f'{REF}/R1MC_Current_Plantilla_Positions.xlsx', read_only=True, data_only=True)
current = [(' '.join(str(r[0]).split()), r[1], r[3]) for r in wb_pos['Current R1MC'].iter_rows(min_row=7, values_only=True)
           if r[0] and r[0] != 'Current position title']
by_norm = {norm_title(p['title']): p for p in positions}
for p in positions:
    p['current'] = False
for title, sg, ref_title in current:
    p = by_norm.get(norm_title(title)) or by_norm.get(norm_title(ref_title or ''))
    if p:                                      # keep its id (profiles use it) and its level; take the official title and SG
        p.update({'title': title, 'salary_grade': sg if isinstance(sg, int) else None, 'current': True})
    else:
        positions.append({'id': max(x['id'] for x in positions) + 1, 'title': title, 'portal_code': None,
                          'salary_grade': sg if isinstance(sg, int) else None, 'level': None, 'current': True})
pos_by_title = {p['title'].lower(): p['id'] for p in positions}
off_official = {(o['division_id'], o['area'].lower()): o['id'] for o in offices if o['source'] == 'official-2026'}
def OA(division_code, *areas):
    did = next(d['id'] for d in DIVISIONS if d['code'] == division_code)
    return [off_official[(did, a.lower())] for a in areas]
comp_id = {c['name']: c['id'] for c in competencies}
def profile_for(p, o):
    return next(x for x in profiles if x['position_id'] == pos_id[p] and x['office_id'] == off_id[o])

# --- sample employees (stand-in for the employee portal) ------------------
random.seed(7)
# Hospital ID numbers: 8 digits, year hired + 4-digit sequence (e.g. 20260245).
# Must match mock-portal/data/employees.json.
def ID_NUMBER(i):
    return f"{[2004, 2011, 2015, 2019, 2013, 2008, 2021, 2006, 2010, 2002, 2017, 1998, 2023][(i - 1) % 13]}{(i * 137) % 9000 + 1000:04d}"

EMP = [
    ('Santos', 'Maria', 'C.', 'Chief of Medical Professional Staff II', 'MS (OMPS)'),
    ('Dela Cruz', 'Juan', 'V.', 'Administrative Officer IV', 'HOPSS (HRM)'),
    ('Rivera', 'Jose', 'D.', 'Administrative Assistant I', 'HOPSS (MM)'),
    ('Castillo', 'Andres', 'M.', 'Administrative Assistant I', 'MS (HIM)'),
    ('Navarro', 'Bea', 'S.', 'Administrative Assistant I', 'HOPSS (Procurement)'),
    ('Ramos', 'Lea', 'P.', 'Administrative Assistant II', 'FS (Cash Operations)'),
    ('Flores', 'Mark', 'A.', 'Administrative Assistant II', 'HOPSS (HRM)'),
    ('Mendoza', 'Liza', 'R.', 'Supervising Administrative Officer', 'HOPSS (HRM)'),
    ('Reyes', 'Paolo', 'S.', 'Supervising Administrative Officer', 'FS (Budget)'),
    ('Bautista', 'Carla', 'T.', 'Chief Administrative Officer', 'HOPSS (OAO)'),
    ('Villanueva', 'Ramon', 'P.', 'Administrative Aide VI', 'HOPSS (EFM)'),
    ('Aquino', 'Grace', 'L.', 'Chief of Medical Professional Staff II', 'OMCC'),
]
employees = []
for i, (sur, first, mid, pos, off) in enumerate(EMP, start=1):
    employees.append({'id': ID_NUMBER(i), 'surname': sur, 'first_name': first, 'middle_initial': mid,
                      'employment_status': 'Regular' if i % 5 else 'Contractual',
                      'position_id': pos_id[pos], 'office_id': off_id[off]})

assessments = []
for e in employees[1:9]:  # leave a few unassessed
    prof = next(p for p in profiles if p['position_id'] == e['position_id'] and p['office_id'] == e['office_id'])
    ratings = {}
    for cid, std in prof['standards'].items():
        ratings[cid] = max(1, min(4, std + random.choice([-2, -1, -1, -1, 0, 0, 1])))
    assessments.append({'id': len(assessments) + 1, 'employee_id': e['id'], 'cycle_year': 2026,
                        'position_id': e['position_id'], 'office_id': e['office_id'], 'profile_id': prof['id'],
                        'ratings': ratings, 'submitted_at': f'2026-0{random.randint(6,8)}-{random.randint(10,28)}T09:00:00+08:00'})

# --- sample training library ----------------------------------------------
# Every training has:
#   category  general  - open to everyone
#             area     - only for employees in the listed areas (e.g. Billing, HRM)
#             position - only for employees in the listed plantilla positions
#   level     1..4, the "level of assessment": the minimum POSITION LEVEL needed to enrol.
#             An employee's position level is the core-competency level the 2023 map requires
#             for their position (e.g. Administrative Officer V = 3), so a level-3 employee can
#             enrol in level 1, 2 and 3 trainings but not level 4.
def C(*names): return [comp_id[n] for n in names]
def O(*names): return [off_id[n] for n in names]
def P(*titles): return [pos_id[t] for t in titles]
T = [
    # title, mode, type, provider, hours, level, competencies, category, areas, positions
    ('Values Restoration Program', 'non-formal', 'In-house training', 'CSC / CETAR', 16, 1, C('Exemplifying Integrity', 'Professionalism'), 'general', [], []),
    ('Customer Service Excellence Workshop', 'non-formal', 'In-house training', 'CETAR', 8, 1, C('Service Excellence', 'Patient-Centered Care'), 'general', [], []),
    ('Effective Business Writing and Communication', 'non-formal', 'External training', 'Civil Service Institute', 24, 2, C('Effective Communication Skills', 'Technical Writing'), 'general', [], []),
    ('Interpersonal Skills and Workplace Relations', 'non-formal', 'In-house training', 'CETAR', 8, 1, C('Effective Interpersonal Skills'), 'general', [], []),
    ('Orientation on DOH Vision, Mission and Structure', 'non-formal', 'In-house training', 'CETAR', 4, 1, C('Organizational Awareness and Commitment'), 'general', [], []),
    ('Design Thinking for Public Service Innovation', 'non-formal', 'eLearning / webinar', 'CSC LEAP', 12, 2, C('Promoting Innovation'), 'general', [], []),
    ('Leadership and Management Training', 'non-formal', 'In-house training', 'CETAR', 40, 3,
     C('Building Collaborative and Inclusive Working Relationship', 'Leading Change', 'Managing Performance and Coaching for Results',
       'Thinking Strategically and Creatively', 'Organizational Awareness and Commitment', 'Diversity Management'), 'general', [], []),
    ('Coaching on the job from immediate supervisor', 'informal', 'Coaching', 'Immediate supervisor', None, 1,
     C('Managing Performance and Coaching for Results', 'Managing Work', 'Records Management'), 'general', [], []),
    ('Master in Public Administration', 'formal', 'Graduate degree', 'Accredited university', None, 3,
     C('Organizational Awareness and Commitment', 'Management Acumen', 'Policy Development'), 'general', [], []),
    ('Master in Hospital Administration', 'formal', 'Graduate degree', 'Accredited university', None, 4,
     C('Management Acumen', 'Planning, Organizing and Delivering'), 'general', [], []),
    ('Basic Records Management', 'non-formal', 'In-house training', 'CETAR', 8, 1, C('Records Management', 'Data Recording and Reporting'), 'general', [], []),
    ('Spreadsheets for Office Reports', 'non-formal', 'eLearning / webinar', 'CETAR', 6, 1, C('Computer Skills', 'Data Recording and Reporting'), 'general', [], []),
    ('Training of Trainers', 'non-formal', 'In-house training', 'CETAR', 24, 3, C('Learning Facilitation', 'Training Program Administration'), 'general', [], []),
    ('Gender and Development and Diversity in the Workplace', 'non-formal', 'In-house training', 'GAD Focal Point', 8, 1, C('Diversity Management'), 'general', [], []),
    ('Health Promotion and Advocacy', 'non-formal', 'eLearning / webinar', 'DOH Academy', 6, 1, C('Advocating Public Health', 'Health Promotion and Health Education'), 'general', [], []),
    ('Media and Risk Communication for Health', 'non-formal', 'External training', 'DOH Central Office', 16, 3, C('Media and Public Relations'), 'general', [], []),
    ('Patient Safety and Quality of Care', 'non-formal', 'In-house training', 'CETAR', 8, 1,
     C('Patient-Centered Care', 'Respecting and Caring for Patients', 'Achieving High Standards'), 'general', [], []),
    # area trainings
    ('Shadowing under the Chief Administrative Officer', 'informal', 'Shadowing', 'Office of the CAO', None, 3,
     C('Management Acumen', 'Planning, Organizing and Delivering'), 'area', OA('HOPSS', 'CHIEF ADMINISTRATIVE OFFICE'), []),
    ('Government Procurement Reform Act (RA 9184)', 'non-formal', 'External training', 'GPPB-TSO', 24, 2,
     C('Procurement Planning and Management', 'Contract Management'), 'area', OA('HOPSS', 'INTEGRATED PROCUREMENT AND AWARDS DEPARTMENT'), []),
    ('Updates on the Government Accounting Manual', 'non-formal', 'External training', 'COA', 16, 2,
     C('Accounting Proficiency', 'Government Accounting and Budgeting'), 'area', OA('FS', 'ACCOUNTING'), []),
    ('Budget Preparation Workshop', 'non-formal', 'In-house training', 'Budget Section', 16, 2,
     C('Preparation of Budget Plans and Annual Budget Submissions', 'Government Accounting and Budgeting'), 'area', OA('FS', 'BUDGET'), []),
    ('Cash Handling and Internal Control', 'non-formal', 'In-house training', 'Cash Operations', 8, 1, C('Cash Management'), 'area', OA('FS', 'CASH OPERATION SECTION'), []),
    ('Billing Computation and Claims Accuracy', 'non-formal', 'In-house training', 'Billing Section', 8, 1,
     C('Data Recording and Reporting'), 'area', OA('FS', 'BILLING', 'CLAIMS'), []),
    ('Recruitment, Selection and Placement (PRIME-HRM)', 'non-formal', 'External training', 'CSC', 24, 2,
     C('Manpower Acquisition and Development', 'Benefits, Compensation and Welfare Management'), 'area', OA('HOPSS', 'HUMAN RESOURCE MANAGEMENT OFFICE'), []),
    ('Equipment Preventive Maintenance', 'non-formal', 'In-house training', 'EFM Section', 16, 1,
     C('Facility and Equipment Maintenance', 'Occupational Safety and Health Knowledge'), 'area', OA('HOPSS', 'ENGINEERING-BUILDING MAINTENANCE', 'ENGINEERING-INDUSTRIAL', 'ENGINEERING-INFRASTRUCTURE'), []),
    # plantilla-position trainings
    ('Computer Hardware Servicing NC II', 'non-formal', 'External training', 'TESDA-accredited center', 80, 1,
     C('Computer Skills'), 'position', [], P('Computer Maintenance Technologist II', 'Computer Maintenance Technologist III')),
    ('Basic Life Support for Nursing Staff', 'non-formal', 'In-house training', 'CETAR', 8, 1,
     C('Nursing Care', 'Patient-Centered Care'), 'position', [], P('Nurse I', 'Nurse II', 'Nursing Attendant I', 'Nursing Attendant II')),
]
# Stored as: level 1-4 (required), all_positions or position_ids (required), office_ids (optional).
# Assessment levels and the salary grades each covers (editable in the app: Library -> Level legend).
# These bands agree with the 2023 DOH map's core-competency level for 83 of the 87 positions it covers.
LEVELS = [{'level': 1, 'label': 'Basic', 'min_sg': 1, 'max_sg': 10},
          {'level': 2, 'label': 'Intermediate', 'min_sg': 11, 'max_sg': 17},
          {'level': 3, 'label': 'Advanced', 'min_sg': 18, 'max_sg': 23},
          {'level': 4, 'label': 'Expert', 'min_sg': 24, 'max_sg': 33}]
trainings = [{'id': i, 'title': t[0], 'mode': t[1], 'type': t[2], 'provider': t[3], 'hours': t[4], 'level': t[5],
              'competency_ids': t[6], 'all_positions': not t[9], 'position_ids': t[9], 'office_ids': t[8], 'description': '',
              # Competency standard: the level an employee must already have to take the training.
              # Sample values: one level below the training's own level (Level 1 trainings: 1).
              'standards': {str(cid): max(1, t[5] - 1) for cid in t[6]}}
             for i, t in enumerate(T, start=1)]

# --- sample position profiles on the official areas (CETAR creates the real ones in the app) ---------
# Each copies the competencies of the matching 2023-map profile, so the sample employees can be assessed.
SAMPLE_PROFILES = [  # (position title, division code, official area, 2023 label to copy from)
    ('Chief of Medical Professional Staff II', 'MS', 'CHIEF OF MEDICAL PROFESSIONAL STAFF', 'MS (OMPS)'),
    ('Chief of Medical Professional Staff II', 'OCH', 'MEDICAL CENTER CHIEF OFFICE', 'OMCC'),
    ('Administrative Officer IV', 'HOPSS', 'HUMAN RESOURCE MANAGEMENT OFFICE', 'HOPSS (HRM)'),
    ('Administrative Assistant I', 'HOPSS', 'MATERIALS MANAGEMENT', 'HOPSS (MM)'),
    ('Administrative Assistant I', 'AHPS', 'HEALTH INFORMATION MANAGEMENT SYSTEM (MEDICAL RECORDS)', 'MS (HIM)'),
    ('Administrative Assistant I', 'HOPSS', 'INTEGRATED PROCUREMENT AND AWARDS DEPARTMENT', 'HOPSS (Procurement)'),
    ('Administrative Assistant I', 'HOPSS', 'SECURITY OFFICE', 'HOPSS (MM)'),
    ('Administrative Assistant II', 'FS', 'CASH OPERATION SECTION', 'FS (Cash Operations)'),
    ('Administrative Assistant II', 'HOPSS', 'HUMAN RESOURCE MANAGEMENT OFFICE', 'HOPSS (HRM)'),
    ('Supervising Administrative Officer', 'HOPSS', 'HUMAN RESOURCE MANAGEMENT OFFICE', 'HOPSS (HRM)'),
    ('Supervising Administrative Officer', 'FS', 'BUDGET', 'FS (Budget)'),
    ('Chief Administrative Officer', 'HOPSS', 'CHIEF ADMINISTRATIVE OFFICE', 'HOPSS (OAO)'),
    ('Administrative Aide VI', 'HOPSS', 'ENGINEERING-BUILDING MAINTENANCE', 'HOPSS (EFM)'),
]
for title, dcode, area, legacy in SAMPLE_PROFILES:
    pid = pos_id[title]
    oid = OA(dcode, area)[0]
    if any(p['position_id'] == pid and p['office_id'] == oid for p in profiles):
        continue                     # a 2023 profile already sits on this (merged) official area
    src = next(p for p in profiles if p['position_id'] == pid and p['office_id'] == off_id[legacy])
    profiles.append({'id': max(p['id'] for p in profiles) + 1, 'position_id': pid, 'office_id': oid,
                     'standards': dict(src['standards']), 'source': 'cetar'})

for name, data in [('divisions', [{k: v for k, v in d.items() if k != 'prefixes'} for d in DIVISIONS]),
                   ('competencies', competencies), ('positions', positions), ('offices', offices),
                   ('profiles', profiles), ('employees', employees), ('assessments', assessments), ('trainings', trainings), ('enrollments', []), ('levels', LEVELS)]:
    json.dump(data, open(f'{out}/{name}.json', 'w'), ensure_ascii=False, indent=1)
print(len(DIVISIONS), 'divisions;', len(competencies), 'competencies,', sum(1 for c in competencies if c['levels']), 'with descriptors;',
      len(positions), 'positions;', len(offices), 'offices;', len(profiles), 'profiles;',
      len(employees), 'employees;', len(assessments), 'assessments;', len(trainings), 'trainings')

# Version of the reference lists. backend/lib/store.php re-syncs an existing data folder when this changes.
import hashlib, datetime
digest = hashlib.sha1(b''.join(open(f'{out}/{n}.json', 'rb').read() for n in ['divisions', 'offices', 'positions', 'competencies', 'levels'])).hexdigest()[:8]
open(f'{out}/VERSION', 'w').write(f"{datetime.date.today().isoformat()}-{digest}\n")

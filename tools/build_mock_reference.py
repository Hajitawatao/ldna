"""
Writes mock-portal/data/reference.json from the same R1MC reference files the LDNA seed uses,
so the mock portal and LDNA always offer identical divisions, areas and positions.
Usage: python3 tools/build_mock_reference.py   (from the project root)
"""
import json, openpyxl

REF = 'tools/reference'
DIVS = [('HOPSS', 'HOSPITAL OPERATIONS AND PATIENT SUPPORT SERVICE'), ('MS', 'MEDICAL SERVICE'), ('NS', 'NURSING SERVICE'),
        ('FS', 'FINANCE SERVICE'), ('AHPS', 'ALLIED HEALTH PROFESSIONAL SERVICE'), ('OCH', 'OFFICE OF THE CHIEF OF HOSPITAL')]
code = {n: c for c, n in DIVS}
areas = {}
for d, a in openpyxl.load_workbook(f'{REF}/R1MC_Divisions_and_Areas_2026.xlsx', data_only=True).active.iter_rows(min_row=2, values_only=True):
    if d and a:
        areas.setdefault(code[str(d).strip()], []).append(' '.join(str(a).split()))
ws = openpyxl.load_workbook(f'{REF}/R1MC_Current_Plantilla_Positions.xlsx', data_only=True)['Current R1MC']
positions = [{'title': ' '.join(r[0].split()), 'salary_grade': r[1] if isinstance(r[1], int) else None}
             for r in ws.iter_rows(min_row=7, values_only=True) if r[0] and r[0] != 'Current position title']
json.dump({'divisions': [{'code': c, 'name': n, 'areas': areas.get(c, [])} for c, n in DIVS], 'positions': positions},
          open('mock-portal/data/reference.json', 'w'), indent=1)
print(sum(len(v) for v in areas.values()), 'areas,', len(positions), 'positions')

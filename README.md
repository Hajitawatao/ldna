# Learning and Development Needs Assessment (LDNA)

Vue 3 + Vite + Tailwind CSS v4 frontend, plain-PHP JSON backend.
CETAR training library and LDNA self-assessment, to be connected to the employee portal.
Competency data is real (imported from the 2023 competency map and the DOH competency catalog).
Employees, assessments and trainings are placeholder data until CETAR supplies the real lists.

## Run without Node.js (locked-down PC)

If Node is blocked (Application Control policy, no admin rights), run everything on PHP alone.
`frontend/dist` is already built and included.

- Windows: double-click **start.bat**
- Mac/Linux: `./start.sh`

Or by hand, from the project root:

```bash
php -S 0.0.0.0:5177 server.php     # app + API
php -S 0.0.0.0:8100 -t mock-portal # mock employee portal
```

`server.php` serves the built app and routes `/api/*` to `backend/api/*`. Port 5177 and 8100 are
reachable on the LAN. The only limit: changing the UI needs `npm run build` on a machine that can
run Node; copy the new `frontend/dist` over afterwards.

## Run (one command)

From the project root:

```bash
npm run dev
```

(Node.js route, for development.) That installs frontend packages if needed, starts the PHP API on port 8000, starts Vite on port 5177 (reachable on the LAN), and opens the browser. Ctrl+C stops both.

Requires PHP 8.1+ and Node.js 20+ on your PATH (`php -v` and `node -v` must work in the terminal).

- PHP not on PATH (common with XAMPP on Windows)? Point to it: `set PHP_BIN=C:\xampp\php\php.exe` (cmd) or `$env:PHP_BIN="C:\xampp\php\php.exe"` (PowerShell), then `npm run dev`.
- Backend already served by XAMPP/Laragon? Create `frontend/.env.local` with `VITE_API_TARGET=http://localhost/ldna/backend` (your path) and Vite won't start its own PHP.

## Pages

| Page | What it does |
|---|---|
| Dashboard | Employees, enrolments, most enrolled trainings, employees by level, enrolments by area |
| Competency Dictionary | All 100 competencies with definition and Basic–Expert level descriptors |
| Positions | Current plantilla positions with their salary grade and resulting assessment level; editable level legend |
| Library | Trainings by category (General / Area / Position) with level; filter by level, area, mode; **Add**, **Edit**, **Delete** |
| Assessment | Employee enters their ID number: their portal details and photo appear, then **Start assessment** lists the trainings open to them (salary grade, position, area) and they enrol |

## Rules implemented

- **Assessment levels.** Every training has a level 1-4. The **level legend** says which salary grades each level
  covers (default: Level 1 = SG 1-10, Level 2 = SG 11-17, Level 3 = SG 18-23, Level 4 = SG 24-33). CETAR can edit the
  ranges in the app (Library or Positions → Level legend → Edit); they must cover SG 1-33 without gaps.
- **An employee's level comes from their salary grade** in the employee portal (the official R1MC position list).
- **Enrolment gate**, checked before anything is saved: 1. employee level >= training level, 2. the position is
  allowed (all positions, or the listed ones), 3. if the training lists areas, the employee's area is one of them.
  Refusals come back with the reason. Enrolments are stored as `training_session` rows.
- **Assessment page:** the employee enters their ID, sees their portal details and level, presses **Start
  assessment** and enrols in the trainings open to them.
- **Position profiles (Positions → Positions by area):** choose a position to see the areas it's in, then an area to
  see its competencies and standard levels. CETAR adds and edits these: the same position can differ by area (e.g.
  ADMINISTRATIVE ASSISTANT II in MANAGEMENT INFORMATION SERVICES vs SECURITY OFFICE). "Add another area" / "Copy to
  another area" start from an existing area's competencies.
- **Matching the portal:** an employee's portal position + area is looked up among the position profiles.
- **Enrol:** trainings open to the employee are those their assessment level (salary grade), position and area allow.
  Clicking **Enroll** opens a self-assessment of **their position profile's competencies** (Competency Dictionary
  Level 1-4 descriptions; standards hidden until submitted). On submit the tally is shown like the DOH tally sheet:
  standard, actual, **gap = standard minus actual** (positive = below standard / needs an intervention, 0 = met,
  negative = above). The request is saved as pending. If CETAR hasn't set up the employee's position in their area yet,
  they can still enrol (eligibility is only level, position and area); the request is sent without a self-assessment
  and CETAR sees that when validating.
- Trainings may list the competencies they cover (optional); CETAR sees them highlighted in the tally.
- **CETAR validation:** the **Enrollment Requests** page lists pending requests with the tally; CETAR approves, or
  rejects with a note the employee sees. A rejected employee can apply again.
- The 2023 DOH competency map is kept only as a source to copy from when CETAR creates a position profile.

## Data

`backend/seed/*.json` is generated by `tools/build_seed.py` from the Excel competency map and the PDF catalog.
Working copies are written to `backend/data/` on first request. **Delete `backend/data/` to reset.**

Sample data (not real): `employees.json` (stand-in for the portal), `assessments.json`, `trainings.json`.

Divisions were derived from the prefix in the Excel labels (`HOPSS (HRM)` -> HOPSS + HRM):
MS -> Medical Service, NS -> Nursing Service, FS -> Finance Service, OMCC -> MCCO, PETRO -> HOPSS.
**No area is filed under Allied Health Service yet**, because the 2023 map keeps Radiology, Pharmacy,
ACL and Nutrition & Dietetics under MS. Move them once the official division list arrives.

Cleaned on import: "Enterprice" → "Enterprise", "MS (Radiology" → "MS (Radiology)", trailing spaces.
Level descriptors exist for 41 of 100 competencies (the rest show DOH's generic level definitions).
Catalog names that differ from the map (e.g. Computer Literacy vs Computer Skills) were **not** merged.

## For the portal connection (MySQL later)

Only `backend/lib/store.php` and the endpoint queries need to change; the JSON each endpoint returns stays the same.

| Endpoint | Method | Purpose |
|---|---|---|
| `reference.php` | GET | competencies, positions, offices |
| `portal-reference.php` | GET | positions and divisions/areas exactly as the portal names them |
| `trainings.php` | GET / POST / PUT / DELETE | library (level, positions, optional areas, competencies) |
| `positions.php` | GET / POST / PUT / DELETE | positions and their salary grade |
| `enrollments.php` | GET / POST / DELETE | catalogue with eligibility for one employee; enrol (gate-checked); cancel |
| `employees.php[?q=]` | GET | employee roster from the portal; `?q=` returns up to 8 matches by ID number or name |
| `stats.php` | GET | dashboard (enrolments) |
| `levels.php` | GET / PUT | level legend: SG range per assessment level |
| `profiles.php` | GET / POST / PUT / DELETE | position profiles: position + area + designated competencies and levels |
| `enrollment-review.php` | GET / POST | CETAR: list requests by status; approve or reject (note required to reject) |
| `photo.php?employee_id=` | GET | employee photo, fetched from the portal's `photo_url` on the server side |

### MySQL schema

`database/schema.sql` creates the tables; `database/seed.sql` loads the level legend, divisions, areas, positions,
competency dictionary and trainings (regenerate with `python3 tools/build_sql.py`).
Pivot tables: `training_area`, `training_position`, `training_competency`, and `training_session` (enrolments).
`v_position_level` gives each position's assessment level from its salary grade.

### Official reference lists

`tools/reference/` holds the R1MC lists the system is built on:
- `R1MC_Divisions_and_Areas_2026.xlsx` — 6 divisions and their 119 areas (HOPSS, MS, NS, FS, AHPS, OCH).
- `R1MC_Current_Plantilla_Positions.xlsx` — 139 current plantilla positions with their salary grade.

`tools/build_seed.py` loads them into `backend/seed/` and `tools/build_mock_reference.py` writes the mock portal's
copy (`mock-portal/data/reference.json`), so LDNA and the mock portal always offer the same divisions, areas and
positions. `backend/seed/VERSION` changes whenever the lists change; an existing `backend/data` folder is then
re-synced automatically on the next request (trainings, position profiles and enrolments are re-linked by name).
The sidebar shows the app build date and the lists version, to confirm which version is running.

### Employee portal

The system never reads employees from its own files. `backend/lib/portal.php` is the only file that talks to the portal, and `backend/config.php` decides where it reads from:

- `driver = http` (default) calls `PORTAL_URL`, which in staging is the mock portal in `/mock-portal` on port 8100. That mock has its own admin screen at <http://127.0.0.1:8100/> for adding plantilla items and employees.
- `driver = mysql` reads the portal database directly using `PORTAL_DSN` and a query (a view such as `vw_ldna_employees`).

Going live is a config change: point `PORTAL_URL` at the real portal API, or switch the driver to `mysql`. The mock portal is then never started. See `mock-portal/README.md` for the employee shape the real portal must return.

Employees whose area isn't in the official area list can still enrol in trainings that aren't limited to an area.

Photos: the portal supplies `photo_url` per employee (absolute, or relative to `PORTAL_URL`).
LDNA's browser pages never load it directly; `backend/api/photo.php` downloads it server-side
(with `PORTAL_AUTH` if set), so LAN PCs don't need access to the portal and portal credentials stay on the server.
Employees without a photo show their initials.

Keys the portal must supply: employee ID, position, area, plantilla item number, salary grade, appointment.
Non-plantilla staff send `"plantilla": null`. LDNA also still accepts the older flat fields. `plantilla_slot` is internal and never shown in LDNA. Positions and areas have a `portal_code` field for matching the portal's codes to the map's names (e.g. portal "MS" → map "MS (OMPS)").

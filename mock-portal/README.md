# Mock employee portal (staging only)

Stands in for the hospital employee portal so the LDNA system can be built and tested against a
real HTTP call. It has its own small admin screen for plantilla items and employees.

## Run

Started automatically by `npm run dev` from the project root. It listens on `0.0.0.0`, so other
PCs on the hospital LAN can open it at `http://<this-pc-ip>:8100/`. The terminal prints the link.

On its own:

```bash
php -S 0.0.0.0:8100 -t mock-portal     # reachable on the LAN
php -S 127.0.0.1:8100 -t mock-portal   # this computer only
```

Set `PORTAL_BIND=127.0.0.1` before `npm run dev` to keep it local.

- Admin screen: `http://<this-pc-ip>:8100/`
- API:
  - `/api/employees.php` - every employee; `?employee_id=` for one
  - `/api/reference.php` - lists: divisions (each with its areas), positions (with salary grade), plantilla items
  - `/api/plantilla.php` - plantilla items with every slot and its occupant
  - `/api/photo.php?employee_id=` - the photo

**No login.** Anyone who can reach port 8100 can add, edit and delete records here. That is fine for
a closed staging test; do not put real personnel data in it.

## What you can do

**Plantilla items tab** — the staffing pattern. Add, edit or delete an item: item number, position
title, salary grade, division and area. One position can have several items in the same area: when
adding, type each item number on its own line and they are all created with the same details. The
"Items per position and area" table shows how many items each has, and how many are filled. Items show as Filled or Vacant with the occupant's name. An occupied
item can't be deleted. Renaming an item number moves its occupant along with it.

**Employees tab** — add, edit or delete people. The **ID number** is entered when you add someone and
can't be changed afterwards, because it links that person's assessments in LDNA. A search box filters
by ID number, name, position or area. Assign a plantilla item and the position, area and
salary grade come from that item; one item can hold only one person. Leave the item blank for job
order or contract of service staff and type their position, area and salary grade instead.
Assigning a plantilla item means choosing a slot, e.g. *Administrative Assistant I #2*. Occupied
slots are greyed out.

**Photos** — upload a JPG, PNG or WebP (up to 2 MB) when adding or editing an employee, or tick
"Remove photo". Files are stored in `photos/` as `<ID number>.<ext>` and served at
`/api/photo.php?employee_id=...`. The roster includes each employee's `photo_url` (null if none).

Changes are saved to `data/plantilla_items.json` and `data/employees.json` and show up in the LDNA
system on the next page load.

The **area** must match the Competency Map exactly, e.g. `MS (OMPS)`. If it doesn't, LDNA marks the
employee "Not in map" instead of guessing.

## Contract

The employee shape below is what the real portal must return. Position, plantilla, division and area
are grouped into their own arrays, so `$e['plantilla']['item_name']` or `employee.area.name` read directly.
`plantilla` is null for job order and contract of service staff; `plantilla.item_name` is the official
DOH item name, shared by all its slots; `plantilla.slot` is internal; LDNA uses that to exclude them from interventions that require
a plantilla item.

```json
{
  "employee_id": "20041137",
  "surname": "Santos",
  "first_name": "Maria",
  "middle_initial": "C.",
  "employment_status": "Regular",
  "appointment": "Permanent",
  "photo_url": "http://portal.example/api/photo.php?employee_id=20041137",
  "position": { "title": "Chief of Medical Professional Staff II", "salary_grade": 25 },
  "plantilla": { "item_id": 1, "item_name": "OSEC-DOHB-CMPS2-1-2019", "slot": 1 },
  "division": { "code": "MS", "name": "Medical Service" },
  "area": { "name": "OMPS" }
}
```

To switch to the real portal, change `PORTAL_URL` (or the driver) in `backend/config.php`.
Nothing else in the LDNA system needs to change.

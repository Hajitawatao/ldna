<?php
/**
 * Staging mock portal - admin screen for plantilla items and employees.
 * Plain PHP, no build step. Open http://<this-pc>:8100/
 * Exists only so staging has realistic data; the real portal replaces it.
 */
declare(strict_types=1);

require __DIR__ . '/lib/data.php';

$tab = $_GET['tab'] ?? 'items';
$notice = $_GET['notice'] ?? '';
$error = '';

function redirect(string $tab, string $notice): never
{
    header('Location: ?tab=' . urlencode($tab) . '&notice=' . urlencode($notice));
    exit;
}
function post(string $key): string
{
    return trim((string) ($_POST[$key] ?? ''));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = post('action');
    $items = mp_load('plantilla_items');
    $emps = mp_load('employees');
    $occ = mp_occupancy($emps);

    // ---- plantilla items ------------------------------------------------
    if ($action === 'save_item') {
        $id = (int) post('id');
        $qty = (int) post('quantity');
        $current = $id ? (array_column($items, null, 'id')[$id] ?? null) : null;
        $row = [
            // Official DOH item name: set once, never edited afterwards.
            'item_name' => $current ? $current['item_name'] : post('item_name'),
            'title' => post('title'),
            'salary_grade' => mp_position_sg(post('title')),     // from the official position list
            'division_code' => post('division_code'),
            'division_name' => mp_division_name(post('division_code')),
            'area_name' => post('area_name'),
            'quantity' => $qty,
        ];
        $highestUsed = $id && !empty($occ[$id]) ? max(array_keys($occ[$id])) : 0;
        if ($row['item_name'] === '') {
            $error = 'Enter the plantilla item name.';
        } elseif ($row['title'] === '' || $row['division_code'] === '' || $row['area_name'] === '') {
            $error = 'Position, division and area are required.';
        } elseif (!in_array($row['title'], array_column(REFERENCE['positions'], 'title'), true)) {
            $error = 'Choose a position from the official list.';
        } elseif (!in_array($row['area_name'], mp_areas($row['division_code']), true)) {
            $error = "{$row['area_name']} is not an area of {$row['division_code']}.";
        } elseif ($qty < 1 || $qty > 200) {
            $error = 'Quantity must be between 1 and 200.';
        } elseif ($qty < $highestUsed) {
            $error = "Slot #{$highestUsed} is occupied, so the quantity can't go below {$highestUsed}. Move that employee first.";
        } else {
            foreach ($items as $i) {
                if ($i['id'] !== $id && strcasecmp($i['item_name'], $row['item_name']) === 0) {
                    $error = "Plantilla item {$row['item_name']} already exists. Edit it and raise the quantity instead.";
                    break;
                }
                if ($i['id'] !== $id && strcasecmp($i['title'], $row['title']) === 0
                    && $i['division_code'] === $row['division_code'] && strcasecmp($i['area_name'], $row['area_name']) === 0) {
                    $error = "{$row['title']} already exists in {$row['division_code']} {$row['area_name']}. Edit it and raise the quantity instead.";
                }
            }
        }
        if (!$error) {
            if ($id) {
                foreach ($items as $k => $i) if ($i['id'] === $id) $items[$k] = ['id' => $id] + $row;
                $msg = 'Plantilla item saved.';
            } else {
                $items[] = ['id' => $items ? max(array_column($items, 'id')) + 1 : 1] + $row;
                $msg = $qty === 1 ? 'Plantilla item added.' : "Plantilla item added with {$qty} slots.";
            }
            mp_save('plantilla_items', $items);
            redirect('items', $msg);
        }
    }

    if ($action === 'delete_item') {
        $id = (int) post('id');
        if (!empty($occ[$id])) {
            $error = 'Some slots of that item are occupied. Move those employees first.';
        } else {
            mp_save('plantilla_items', array_filter($items, fn ($i) => $i['id'] !== $id));
            redirect('items', 'Plantilla item deleted.');
        }
    }

    // ---- employees ------------------------------------------------------
    if ($action === 'save_employee') {
        $original = post('original_employee_id');
        $id = $original ?: post('employee_id');
        [$itemId, $slot] = array_map('intval', explode(':', post('slot') ?: '0:0') + [0, 0]);
        $itemsById = array_column($items, null, 'id');
        $row = [
            'employee_id' => $id,
            'surname' => post('surname'),
            'first_name' => post('first_name'),
            'middle_initial' => post('middle_initial'),
            'appointment' => post('appointment'),
            'item_id' => $itemId ?: null,
            'slot' => $itemId ? $slot : null,
            // Used only when the employee holds no plantilla item (job order, contract of service)
            'position_title' => $itemId ? null : post('position_title'),
            'salary_grade' => $itemId ? null : mp_position_sg(post('position_title')),
            'division_code' => $itemId ? null : post('division_code'),
            'division_name' => $itemId ? null : mp_division_name(post('division_code')),
            'area_name' => $itemId ? null : post('area_name'),
        ];
        $holder = $itemId ? ($occ[$itemId][$slot] ?? null) : null;

        if ($id === '') {
            $error = 'Enter the employee ID number.';
        } elseif (!$original && !ctype_digit($id)) {
            $error = 'The ID number should contain numbers only, e.g. 20260245.';
        } elseif (!$original && in_array($id, array_column($emps, 'employee_id'), true)) {
            $error = "ID number {$id} is already used by another employee.";
        } elseif ($row['surname'] === '' || $row['first_name'] === '') {
            $error = 'Surname and first name are required.';
        } elseif ($itemId && (!isset($itemsById[$itemId]) || $slot < 1 || $slot > $itemsById[$itemId]['quantity'])) {
            $error = 'That plantilla slot no longer exists.';
        } elseif ($holder && $holder['employee_id'] !== $id) {
            $error = 'That slot is already occupied by ' . $holder['surname'] . ', ' . $holder['first_name'] . '.';
        } elseif (!$itemId && ($row['position_title'] === '' || $row['division_code'] === '' || $row['area_name'] === '')) {
            $error = 'Choose a plantilla item, or choose a position, division and area for non-plantilla staff.';
        } elseif (!$itemId && !in_array($row['area_name'], mp_areas($row['division_code']), true)) {
            $error = "{$row['area_name']} is not an area of {$row['division_code']}.";
        }
        if (!$error && isset($_FILES['photo'])) {
            $error = mp_store_photo($id, $_FILES['photo']) ?? '';
        }
        if (!$error) {
            if (post('remove_photo') === '1') mp_delete_photo($id);
            $found = false;
            foreach ($emps as $k => $e) {
                if ($e['employee_id'] === $id) { $emps[$k] = $row; $found = true; }
            }
            if (!$found) $emps[] = $row;
            mp_save('employees', $emps);
            redirect('employees', 'Employee saved.');
        }
    }

    if ($action === 'delete_employee') {
        mp_delete_photo(post('employee_id'));
        mp_save('employees', array_filter($emps, fn ($e) => $e['employee_id'] !== post('employee_id')));
        redirect('employees', 'Employee deleted.');
    }
}

$items = mp_load('plantilla_items');
usort($items, fn ($a, $b) => [$a['division_code'], $a['area_name'], $a['title']] <=> [$b['division_code'], $b['area_name'], $b['title']]);
$itemsById = array_column($items, null, 'id');
$employees = mp_load('employees');
usort($employees, fn ($a, $b) => [$a['surname'], $a['first_name']] <=> [$b['surname'], $b['first_name']]);
$occ = mp_occupancy($employees);
$editItem = isset($_GET['edit_item']) ? ($itemsById[(int) $_GET['edit_item']] ?? null) : null;
$editEmployee = null;
foreach ($employees as $e) if ($e['employee_id'] === ($_GET['edit_employee'] ?? '')) $editEmployee = $e;
$h = fn ($v) => htmlspecialchars((string) $v, ENT_QUOTES);
$totalSlots = array_sum(array_column($items, 'quantity'));
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Mock Employee Portal (staging)</title>
<style>
  :root { --ink:#13261f; --line:#d8ded9; --paper:#f5f7f4; --brand:#1f765b; --muted:#5c6b64; }
  * { box-sizing: border-box; }
  body { margin:0; font:15px/1.5 "Segoe UI",system-ui,sans-serif; background:var(--paper); color:var(--ink); }
  header { background:var(--ink); color:#fff; padding:14px 20px; display:flex; align-items:center; gap:12px; flex-wrap:wrap; }
  header h1 { font-size:17px; margin:0; font-weight:600; }
  .pill { background:#fbbf2426; color:#fbbf24; font-size:11px; font-weight:700; letter-spacing:.06em; padding:3px 9px; border-radius:99px; }
  header a { color:#cbd5cd; font-size:13px; margin-left:auto; }
  main { max-width:1100px; margin:0 auto; padding:20px; }
  nav { display:flex; gap:8px; margin-bottom:18px; }
  nav a { padding:8px 14px; border-radius:8px; text-decoration:none; color:var(--muted); font-weight:500; }
  nav a.on { background:var(--ink); color:#fff; }
  .msg { padding:10px 14px; border-radius:8px; margin-bottom:16px; }
  .ok { background:#e7f3ed; color:#14603f; } .bad { background:#fdeaea; color:#9b1c1c; }
  .card { background:#fff; border:1px solid var(--line); border-radius:12px; padding:18px; margin-bottom:18px; }
  h2 { font-size:15px; margin:0 0 12px; }
  table { width:100%; border-collapse:collapse; font-size:14px; }
  th { text-align:left; color:var(--muted); font-weight:500; border-bottom:1px solid var(--line); padding:8px 10px; }
  td { padding:9px 10px; border-bottom:1px solid #eef1ef; vertical-align:top; }
  tr:last-child td { border-bottom:0; }
  .tag { font-size:12px; padding:2px 8px; border-radius:99px; white-space:nowrap; }
  .filled { background:#e7f3ed; color:#14603f; } .vacant { background:#fef3c7; color:#92400e; }
  form.grid { display:grid; gap:12px; grid-template-columns:repeat(auto-fit,minmax(200px,1fr)); }
  label { display:block; font-size:13px; color:var(--muted); margin-bottom:4px; }
  input, select { width:100%; padding:8px 10px; border:1px solid var(--line); border-radius:8px; font:inherit; background:#fff; }
  input[type=file] { padding:6px; }
  .actions { grid-column:1/-1; display:flex; gap:10px; align-items:center; flex-wrap:wrap; }
  button { font:inherit; padding:9px 16px; border-radius:8px; border:0; background:var(--brand); color:#fff; font-weight:600; cursor:pointer; }
  button.ghost { background:#fff; border:1px solid var(--line); color:var(--ink); font-weight:500; }
  button.danger { background:none; color:#b91c1c; padding:0; font-weight:500; }
  .muted { color:var(--muted); font-size:13px; }
  .row-actions { display:flex; gap:12px; }
  .slots { display:flex; flex-wrap:wrap; gap:6px; margin-top:6px; }
  .slot { font-size:12px; border:1px solid var(--line); border-radius:8px; padding:3px 8px; background:#fafbfa; }
  .slot b { color:var(--muted); font-weight:600; margin-right:4px; }
  .slot.open { border-style:dashed; color:#92400e; }
  .avatar { width:36px; height:36px; border-radius:50%; object-fit:cover; flex-shrink:0; background:#e7f3ed; }
  .avatar.initials { display:inline-grid; place-items:center; font-size:12px; font-weight:600; color:#14603f; }
  .slotnos { grid-column:1/-1; display:grid; gap:8px; grid-template-columns:repeat(auto-fill,minmax(230px,1fr)); }
  @media (max-width:640px) { table, thead, tbody, tr, th, td { display:block; } th { display:none; } td { border:0; padding:3px 0; } tr { border-bottom:1px solid var(--line); padding:12px 0; } }
</style>
</head>
<body>
<header>
  <h1>Mock Employee Portal</h1>
  <span class="pill">STAGING</span>
  <a href="api/employees.php" target="_blank">View API output</a>
</header>
<main>
  <p class="muted">Stand-in for the hospital employee portal. The LDNA system reads this data over HTTP, so whatever you add here shows up there. Replaced by the real portal in production.</p>

  <nav>
    <a class="<?= $tab === 'items' ? 'on' : '' ?>" href="?tab=items">Plantilla items (<?= count($items) ?> · <?= $totalSlots ?> slots)</a>
    <a class="<?= $tab === 'employees' ? 'on' : '' ?>" href="?tab=employees">Employees (<?= count($employees) ?>)</a>
  </nav>

  <?php if ($notice): ?><p class="msg ok"><?= $h($notice) ?></p><?php endif; ?>
  <?php if ($error): ?><p class="msg bad"><?= $h($error) ?></p><?php endif; ?>

  <?php if ($tab === 'items'): ?>
    <div class="card">
      <h2><?= $editItem ? 'Edit plantilla item' : 'Add plantilla item' ?></h2>
      <form class="grid" method="post">
        <input type="hidden" name="action" value="save_item">
        <input type="hidden" name="id" value="<?= $h($editItem['id'] ?? '') ?>">
        <div>
          <label>Plantilla item name <span class="muted">(official DOH name)</span></label>
          <?php if ($editItem): ?>
            <input value="<?= $h($editItem['item_name']) ?>" disabled title="The official DOH item name can't be changed.">
          <?php else: ?>
            <input name="item_name" required placeholder="OSEC-DOHB-SADOF-9-1998" value="<?= $h($_POST['item_name'] ?? '') ?>">
          <?php endif; ?>
        </div>
        <div style="grid-column:span 2">
          <label>Position <span class="muted">(official list; salary grade fills in)</span></label>
          <?php $selTitle = $editItem['title'] ?? $_POST['title'] ?? ''; ?>
          <select name="title" required onchange="document.getElementById('sg-item').value=this.selectedOptions[0].dataset.sg||''">
            <option value="">Choose a position</option>
            <?php foreach (REFERENCE['positions'] as $p): ?>
              <option value="<?= $h($p['title']) ?>" data-sg="<?= $h($p['salary_grade']) ?>" <?= $selTitle === $p['title'] ? 'selected' : '' ?>><?= $h($p['title']) ?> (SG <?= $h($p['salary_grade'] ?? '?') ?>)</option>
            <?php endforeach; ?>
          </select>
        </div>
        <div><label>Salary grade</label><input id="sg-item" value="<?= $h(mp_position_sg($selTitle) ?? '') ?>" disabled></div>
        <div>
          <label>Division</label>
          <select name="division_code" required data-areas-for="area-item">
            <option value="">Choose division</option>
            <?php foreach (DIVISIONS as $d): ?>
              <option value="<?= $h($d['code']) ?>" <?= ($editItem['division_code'] ?? $_POST['division_code'] ?? '') === $d['code'] ? 'selected' : '' ?>><?= $h($d['name']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div style="grid-column:span 2">
          <label>Area</label>
          <select id="area-item" name="area_name" required data-selected="<?= $h($editItem['area_name'] ?? $_POST['area_name'] ?? '') ?>"></select>
        </div>
        <div><label>Quantity</label><input name="quantity" type="number" min="1" max="200" required value="<?= $h($editItem['quantity'] ?? $_POST['quantity'] ?? 1) ?>"></div>

        <div class="actions">
          <button type="submit"><?= $editItem ? 'Save changes' : 'Add item' ?></button>
          <?php if ($editItem): ?><a href="?tab=items"><button type="button" class="ghost">Cancel</button></a><?php endif; ?>
          <span class="muted">The official item name stays as is. The quantity creates slots named after the position (Supervising Administrative Officer #1, #2, ...), shown only on this screen.</span>
        </div>
      </form>
    </div>

    <div class="card">
      <table>
        <thead><tr><th>Plantilla item</th><th>SG</th><th>Division / Area</th><th>Qty</th><th>Slots</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($items as $i): $filled = count($occ[$i['id']] ?? []); ?>
          <tr>
            <td><strong><?= $h($i['item_name']) ?></strong><div class="muted"><?= $h($i['title']) ?></div></td>
            <td><?= $h($i['salary_grade']) ?></td>
            <td><?= $h($i['division_code']) ?><div class="muted"><?= $h($i['area_name']) ?></div></td>
            <td>
              <strong><?= $i['quantity'] ?></strong>
              <div class="muted"><?= $filled ?> filled</div>
            </td>
            <td>
              <div class="slots">
                <?php for ($n = 1; $n <= $i['quantity']; $n++): $who = $occ[$i['id']][$n] ?? null; ?>
                  <span class="slot <?= $who ? '' : 'open' ?>">
                    <b><?= $h(mp_slot_label($i, $n)) ?></b> <?= $who ? $h($who['surname'] . ', ' . $who['first_name']) : 'Vacant' ?>
                  </span>
                <?php endfor; ?>
              </div>
            </td>
            <td>
              <div class="row-actions">
                <a href="?tab=items&edit_item=<?= $i['id'] ?>">Edit</a>
                <form method="post" onsubmit="return confirm('Delete plantilla item <?= $h($i['item_name']) ?>?')">
                  <input type="hidden" name="action" value="delete_item">
                  <input type="hidden" name="id" value="<?= $i['id'] ?>">
                  <button class="danger" type="submit">Delete</button>
                </form>
              </div>
            </td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>

  <?php else: ?>
    <div class="card">
      <h2><?= $editEmployee ? 'Edit employee' : 'Add employee' ?></h2>
      <form class="grid" method="post" enctype="multipart/form-data">
        <input type="hidden" name="action" value="save_employee">
        <input type="hidden" name="original_employee_id" value="<?= $h($editEmployee['employee_id'] ?? '') ?>">
        <div>
          <label>ID number</label>
          <?php if ($editEmployee): ?>
            <input value="<?= $h($editEmployee['employee_id']) ?>" disabled title="The ID number links this person's assessments, so it can't be changed.">
          <?php else: ?>
            <input name="employee_id" required placeholder="e.g. 20260245" inputmode="numeric" pattern="[0-9]+" title="Numbers only" value="<?= $h($_POST['employee_id'] ?? '') ?>">
          <?php endif; ?>
        </div>
        <div><label>Surname</label><input name="surname" required value="<?= $h($editEmployee['surname'] ?? $_POST['surname'] ?? '') ?>"></div>
        <div><label>First name</label><input name="first_name" required value="<?= $h($editEmployee['first_name'] ?? $_POST['first_name'] ?? '') ?>"></div>
        <div><label>Middle initial</label><input name="middle_initial" value="<?= $h($editEmployee['middle_initial'] ?? $_POST['middle_initial'] ?? '') ?>"></div>
        <div>
          <label>Appointment</label>
          <select name="appointment">
            <?php foreach (['Permanent', 'Temporary', 'Casual', 'Contract of Service', 'Job Order'] as $a): ?>
              <option <?= ($editEmployee['appointment'] ?? '') === $a ? 'selected' : '' ?>><?= $a ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div style="grid-column:span 2">
          <label>Plantilla item</label>
          <select name="slot">
            <option value="">None (job order / contract of service)</option>
            <?php foreach ($items as $i): ?>
              <optgroup label="<?= $h($i['item_name'] . ' - ' . $i['title'] . ' (SG ' . $i['salary_grade'] . '), ' . $i['division_code'] . ' ' . $i['area_name']) ?>">
                <?php for ($n = 1; $n <= $i['quantity']; $n++):
                  $who = $occ[$i['id']][$n] ?? null;
                  $mine = $editEmployee && ($editEmployee['item_id'] ?? null) === $i['id'] && (int) ($editEmployee['slot'] ?? 0) === $n;
                  $taken = $who && !$mine; ?>
                  <option value="<?= $i['id'] ?>:<?= $n ?>" <?= $mine ? 'selected' : '' ?> <?= $taken ? 'disabled' : '' ?>>
                    <?= $h(mp_slot_label($i, $n)) ?><?= $taken ? ' - ' . $h($who['surname']) : ($mine ? '' : ' - vacant') ?>
                  </option>
                <?php endfor; ?>
              </optgroup>
            <?php endforeach; ?>
          </select>
        </div>
        <div>
          <label>Position <span class="muted">(only if no item)</span></label>
          <select name="position_title">
            <option value="">-</option>
            <?php foreach (REFERENCE['positions'] as $p): ?>
              <option value="<?= $h($p['title']) ?>" <?= ($editEmployee['position_title'] ?? '') === $p['title'] ? 'selected' : '' ?>><?= $h($p['title']) ?> (SG <?= $h($p['salary_grade'] ?? '?') ?>)</option>
            <?php endforeach; ?>
          </select>
        </div>
        <div>
          <label>Division <span class="muted">(only if no item)</span></label>
          <select name="division_code" data-areas-for="area-emp">
            <option value="">-</option>
            <?php foreach (DIVISIONS as $d): ?>
              <option value="<?= $h($d['code']) ?>" <?= ($editEmployee['division_code'] ?? '') === $d['code'] ? 'selected' : '' ?>><?= $h($d['name']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div><label>Area <span class="muted">(only if no item)</span></label><select id="area-emp" name="area_name" data-selected="<?= $h($editEmployee['area_name'] ?? '') ?>"></select></div>
        <div>
          <label>Photo <span class="muted">(JPG, PNG or WebP, up to 2 MB)</span></label>
          <?php if ($editEmployee && ($p = mp_photo_path($editEmployee['employee_id']))): ?>
            <div style="display:flex;gap:10px;align-items:center;margin-bottom:6px">
              <img src="api/photo.php?employee_id=<?= urlencode($editEmployee['employee_id']) ?>&v=<?= filemtime($p) ?>" alt="" class="avatar" style="width:44px;height:44px">
              <label style="display:flex;gap:6px;align-items:center;margin:0"><input type="checkbox" name="remove_photo" value="1" style="width:auto"> Remove photo</label>
            </div>
          <?php endif; ?>
          <input type="file" name="photo" accept="image/jpeg,image/png,image/webp">
        </div>
        <div class="actions">
          <button type="submit"><?= $editEmployee ? 'Save changes' : 'Add employee' ?></button>
          <?php if ($editEmployee): ?><a href="?tab=employees"><button type="button" class="ghost">Cancel</button></a><?php endif; ?>
          <span class="muted">With a plantilla item, the position, division, area and salary grade come from the item.</span>
        </div>
      </form>
    </div>

    <div class="card">
      <input type="search" placeholder="Search by ID number, name, position or area" style="margin-bottom:12px"
             oninput="const q=this.value.toLowerCase();document.querySelectorAll('#emp-table tbody tr').forEach(r=>r.style.display=r.textContent.toLowerCase().includes(q)?'':'none')">
      <table id="emp-table">
        <thead><tr><th>ID number</th><th>Employee</th><th>Position / Area</th><th>Plantilla</th><th>Appointment</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($employees as $e):
          $rec = mp_employee_record($e, $itemsById);
          $item = $rec['plantilla'] ? $itemsById[$rec['plantilla']['item_id']] : null; ?>
          <tr>
            <td>
              <div style="display:flex;gap:10px;align-items:center">
                <?php if ($p = mp_photo_path($rec['employee_id'])): ?>
                  <img src="api/photo.php?employee_id=<?= urlencode($rec['employee_id']) ?>&v=<?= filemtime($p) ?>" alt="" class="avatar">
                <?php else: ?>
                  <span class="avatar initials"><?= $h(substr($rec['first_name'], 0, 1) . substr($rec['surname'], 0, 1)) ?></span>
                <?php endif; ?>
                <strong><?= $h($rec['employee_id']) ?></strong>
              </div>
            </td>
            <td><?= $h($rec['surname']) ?>, <?= $h($rec['first_name']) ?> <?= $h($rec['middle_initial']) ?></td>
            <td><?= $h($rec['position']['title']) ?><div class="muted"><?= $h($rec['division']['code']) ?> - <?= $h($rec['area']['name']) ?></div></td>
            <td>
              <?php if ($item): ?>
                <?= $h($item['item_name']) ?>
                <div class="muted"><?= $h(mp_slot_label($item, $rec['plantilla']['slot'])) ?> · SG <?= $h($rec['position']['salary_grade']) ?></div>
              <?php else: ?>
                <span class="tag vacant">No item</span>
              <?php endif; ?>
            </td>
            <td><?= $h($rec['appointment']) ?></td>
            <td>
              <div class="row-actions">
                <a href="?tab=employees&edit_employee=<?= urlencode($rec['employee_id']) ?>">Edit</a>
                <form method="post" onsubmit="return confirm('Delete <?= $h($rec['surname']) ?>?')">
                  <input type="hidden" name="action" value="delete_employee">
                  <input type="hidden" name="employee_id" value="<?= $h($rec['employee_id']) ?>">
                  <button class="danger" type="submit">Delete</button>
                </form>
              </div>
            </td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</main>
<script>
  // Areas depend on the division: fill each area <select> from the official list.
  const AREAS = <?= json_encode(array_column(REFERENCE['divisions'], 'areas', 'code'), JSON_UNESCAPED_SLASHES) ?>;
  document.querySelectorAll('[data-areas-for]').forEach((div) => {
    const area = document.getElementById(div.dataset.areasFor)
    const fill = (keep) => {
      const list = AREAS[div.value] || []
      area.innerHTML = '<option value="">' + (div.value ? 'Choose an area' : 'Choose a division first') + '</option>' +
        list.map((a) => `<option ${a === keep ? 'selected' : ''}>${a.replace(/&/g, '&amp;').replace(/</g, '&lt;')}</option>`).join('')
    }
    fill(area.dataset.selected)
    div.addEventListener('change', () => fill(''))
  })
</script>
</body>
</html>

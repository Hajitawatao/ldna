<script setup>
import { ref, computed, reactive, watch } from 'vue'
import Icon from '../components/Icon.vue'
import AppModal from '../components/AppModal.vue'
import SelectField from '../components/SelectField.vue'
import ErrorState from '../components/ErrorState.vue'
import PositionsPanel from '../components/PositionsPanel.vue'
import EmptyState from '../components/EmptyState.vue'
import { useApiGet, apiPost, apiPut, apiDelete } from '../composables/useApi'
import { useReference } from '../composables/useReference'
import { useLayout } from '../composables/useLayout'
import { CATEGORIES, categoryClass, LEVELS, levelLabel } from '../lib/format'

const ref_ = useReference()
const { divisions, competencies, positions, offices, compById, posById, officeById, divisionById, officialOffices } = ref_
const { data, loading, error, load } = useApiGet('profiles.php')
const portalRef = useApiGet('portal-reference.php')
const trainingsReq = useApiGet('trainings.php')
const { searchQuery } = useLayout()

const view = ref('profiles')   // 'profiles' | 'positions'

// ---------------------------------------------------------------- list
const divisionFilter = ref('')
const officeFilter = ref('')
const sourceFilter = ref('')
const divisionOptions = computed(() => divisions.value.map((d) => ({ value: String(d.id), label: `${d.code} — ${d.name}` })))
const officeOptions = computed(() => offices.value
  .filter((o) => !divisionFilter.value || String(o.division_id) === divisionFilter.value)
  .sort((a, b) => (a.area ?? '').localeCompare(b.area ?? ''))
  .map((o) => ({ value: String(o.id), label: o.area })))
const sourceOptions = [{ value: 'map-2023', label: 'Original 2023 map' }, { value: 'added', label: 'Added by CETAR' }]
watch(divisionFilter, () => { officeFilter.value = '' })

function describe(p) {
  const office = officeById.value[p.office_id]
  return {
    ...p,
    position: posById.value[p.position_id]?.title ?? '?',
    area: office?.area ?? '?',
    division: divisionById.value[office?.division_id]?.code ?? '—',
    divisionId: office?.division_id,
  }
}
const rows = computed(() => {
  const q = searchQuery.value.trim().toLowerCase()
  return (data.value?.profiles ?? []).map(describe)
    .filter((p) => (!divisionFilter.value || String(p.divisionId) === divisionFilter.value) &&
      (!officeFilter.value || String(p.office_id) === officeFilter.value) &&
      (!sourceFilter.value || p.source === sourceFilter.value) &&
      (!q || [p.position, p.area, p.division].join(' ').toLowerCase().includes(q)))
    .sort((a, b) => a.position.localeCompare(b.position) || a.division.localeCompare(b.division) || a.area.localeCompare(b.area))
})
const countBy = (standards, key) => Object.keys(standards).filter((cid) => compById.value[cid]?.category === key).length
function grouped(standards) {
  return CATEGORIES.map((cat) => ({
    ...cat,
    items: Object.entries(standards)
      .map(([cid, level]) => ({ comp: compById.value[cid], level }))
      .filter((x) => x.comp?.category === cat.key)
      .sort((a, b) => a.comp.name.localeCompare(b.comp.name)),
  })).filter((g) => g.items.length)
}

// Does this position / division+area exist in the employee portal? (the assessment matches on these)
const portalPositions = computed(() => new Set((portalRef.data.value?.positions ?? []).map((x) => x.toLowerCase())))
const portalAreas = computed(() => {
  const map = {}
  for (const d of portalRef.data.value?.divisions ?? []) map[d.code] = new Set((d.areas ?? []).map((a) => a.toLowerCase()))
  return map
})
const inPortal = (position, divisionCode, area) => ({
  position: portalPositions.value.has((position ?? '').trim().toLowerCase()),
  area: !!portalAreas.value[divisionCode]?.has((area ?? '').trim().toLowerCase()),
})

const notice = ref('')
function flash(msg) { notice.value = msg; setTimeout(() => (notice.value = ''), 5000) }

// ---------------------------------------------------------------- detail
const selected = ref(null)
const detailError = ref('')
const trainingById = computed(() => Object.fromEntries((trainingsReq.data.value?.trainings ?? []).map((t) => [t.id, t])))
const pickTraining = ref('')
const busy = ref(false)

async function setDesignated(ids) {
  busy.value = true; detailError.value = ''
  try {
    const res = await apiPost('profile-trainings.php', { profile_id: selected.value.id, training_ids: ids })
    selected.value.designated_training_ids = res.profile.designated_training_ids
    const row = data.value.profiles.find((p) => p.id === selected.value.id)
    if (row) row.designated_training_ids = res.profile.designated_training_ids
  } catch (e) { detailError.value = e.message } finally { busy.value = false }
}
function addDesignated() {
  if (!pickTraining.value) return
  setDesignated([...(selected.value.designated_training_ids ?? []), Number(pickTraining.value)])
  pickTraining.value = ''
}
const removeDesignated = (id) => setDesignated((selected.value.designated_training_ids ?? []).filter((x) => x !== id))

async function removeProfile() {
  if (!confirm(`Delete the profile ${selected.value.position} · ${selected.value.division} · ${selected.value.area}?`)) return
  busy.value = true; detailError.value = ''
  try {
    await apiDelete(`profiles.php?id=${selected.value.id}`)
    data.value.profiles = data.value.profiles.filter((p) => p.id !== selected.value.id)
    flash(`Deleted ${selected.value.position} · ${selected.value.division} · ${selected.value.area}.`)
    selected.value = null
  } catch (e) { detailError.value = e.message } finally { busy.value = false }
}

// ---------------------------------------------------------------- add / edit form
const editing = ref(null)      // null = closed, {} = add, profile = edit
const saving = ref(false)
const formError = ref('')
const fieldErrors = ref({})
const form = reactive({ position: '', salaryGrade: '', positionLevel: 1, divisionId: '', area: '', copyFrom: '', standards: {} })
const pickCompetency = ref('')

const formDivision = computed(() => divisionById.value[form.divisionId])
const matchedPosition = computed(() => positions.value.find((p) => p.title.toLowerCase() === form.position.trim().toLowerCase()))
const portalCheck = computed(() => inPortal(form.position, formDivision.value?.code, form.area))
// Area dropdown: every official area, grouped by division (only the chosen division's once one is picked).
// When editing an original 2023 profile, its old area label is kept as an extra choice.
const areaGroups = computed(() => divisions.value
  .filter((d) => !form.divisionId || String(d.id) === String(form.divisionId))
  .map((d) => {
    const areas = officialOffices.value.filter((o) => o.division_id === d.id).map((o) => o.area)
    const legacy = editing.value?.id && String(form.divisionId) === String(d.id) && form.area && !areas.includes(form.area) ? [form.area] : []
    return { division: d, areas: [...legacy, ...areas], legacy }
  })
  .filter((g) => g.areas.length))
const areaChoice = computed({
  get: () => (form.divisionId && form.area ? `${form.divisionId}|${form.area}` : ''),
  set: (v) => {
    const i = v.indexOf('|')
    if (i < 0) { form.area = ''; return }
    form.divisionId = v.slice(0, i)
    form.area = v.slice(i + 1)
  },
})
function onDivisionChange() {
  // keep the area only if it belongs to the newly chosen division
  const ok = officialOffices.value.some((o) => String(o.division_id) === String(form.divisionId) && o.area === form.area)
  if (!ok) form.area = ''
}
const positionSuggestions = computed(() => [...new Set([...(portalRef.data.value?.positions ?? []), ...positions.value.map((p) => p.title)])].sort())

function openAdd() {
  Object.assign(form, { position: '', salaryGrade: '', positionLevel: 1, divisionId: divisionFilter.value, area: '', copyFrom: '', standards: {} })
  competencies.value
    .filter((c) => c.category === 'core' || (c.category === 'organizational' && c.name !== 'Promoting Innovation'))
    .forEach((c) => { form.standards[c.id] = 1 })
  formError.value = ''; fieldErrors.value = {}
  editing.value = {}
}
function openEdit(p) {
  Object.assign(form, { position: p.position, salaryGrade: '', positionLevel: 1, divisionId: String(p.divisionId ?? ''), area: p.area,
    copyFrom: '', standards: { ...p.standards } })
  formError.value = ''; fieldErrors.value = {}
  editing.value = p
  selected.value = null
}
function copyStandards() {
  const p = data.value.profiles.find((x) => String(x.id) === form.copyFrom)
  if (p) form.standards = { ...p.standards }
}
const available = computed(() => CATEGORIES.map((cat) => ({
  ...cat, items: competencies.value.filter((c) => c.category === cat.key && !(c.id in form.standards)),
})))
function addCompetency() {
  if (pickCompetency.value) form.standards[pickCompetency.value] = 1
  pickCompetency.value = ''
}

async function save() {
  saving.value = true; formError.value = ''; fieldErrors.value = {}
  const body = {
    position_title: form.position, salary_grade: form.salaryGrade, position_level: form.positionLevel,
    division_id: form.divisionId ? Number(form.divisionId) : null, area_name: form.area, standards: form.standards,
  }
  try {
    const isEdit = !!editing.value?.id
    const res = isEdit ? await apiPut('profiles.php', { id: editing.value.id, ...body }) : await apiPost('profiles.php', body)
    ref_.setPositions(res.positions); ref_.setOffices(res.offices)
    if (isEdit) {
      const i = data.value.profiles.findIndex((p) => p.id === res.profile.id)
      data.value.profiles.splice(i, 1, res.profile)
    } else {
      data.value.profiles.push(res.profile)
    }
    editing.value = null
    flash(`${isEdit ? 'Saved' : 'Added'} ${form.position} · ${formDivision.value?.code} · ${form.area}.`)
  } catch (e) {
    formError.value = e.message; fieldErrors.value = e.fields ?? {}
  } finally {
    saving.value = false
  }
}
</script>

<template>
  <section>
    <div class="mb-4 flex flex-wrap gap-1 rounded-lg border border-ink-900/10 bg-white p-1 dark:border-white/8 dark:bg-ink-900" role="tablist">
      <button v-for="v in [{ key: 'profiles', label: 'Profiles' }, { key: 'positions', label: 'Positions & salary grades' }]" :key="v.key" type="button" role="tab"
              :aria-selected="view === v.key" class="rounded-md px-4 py-2 text-sm font-medium"
              :class="view === v.key ? 'bg-ink-900 text-white dark:bg-brand-600' : 'text-ink-600 hover:bg-paper dark:text-stone-400 dark:hover:bg-white/6'"
              @click="view = v.key">{{ v.label }}</button>
    </div>

    <PositionsPanel v-if="view === 'positions'" />

    <template v-else>
    <div class="mb-5 flex flex-wrap items-end justify-between gap-3">
      <div>
        <h2 class="text-xl font-semibold text-ink-900 dark:text-white">Position profiles</h2>
        <p class="mt-0.5 max-w-2xl text-sm text-ink-600 dark:text-stone-400">
          A profile is a <strong>position + division + area</strong>. An employee is assessed with the profile whose three values match their data in the employee portal exactly.
        </p>
      </div>
      <button type="button" class="btn-primary" @click="openAdd"><Icon name="plus" :size="18" :stroke-width="2" /> Add profile</button>
    </div>

    <p v-if="notice" role="status" class="mb-4 flex items-center gap-2 rounded-lg bg-brand-50 px-4 py-2.5 text-sm text-brand-700 dark:bg-brand-700/25 dark:text-brand-200">
      <Icon name="check" :size="18" /> {{ notice }}
    </p>

    <div class="mb-4 grid gap-3 sm:grid-cols-2 lg:grid-cols-4 lg:items-end">
      <SelectField v-model="divisionFilter" label="Division" placeholder="All divisions" :options="divisionOptions" />
      <SelectField v-model="officeFilter" label="Area" placeholder="All areas" :options="officeOptions" />
      <SelectField v-model="sourceFilter" label="Source" placeholder="All profiles" :options="sourceOptions" />
      <p class="text-sm text-ink-600 lg:text-right dark:text-stone-400">{{ rows.length }} profile{{ rows.length === 1 ? '' : 's' }}</p>
    </div>

    <ErrorState v-if="error" :message="error" @retry="load" />
    <div v-else-if="loading" class="space-y-2" aria-busy="true">
      <div v-for="n in 8" :key="n" class="h-12 animate-pulse rounded-lg bg-ink-900/6 dark:bg-white/6" />
    </div>
    <EmptyState v-else-if="!rows.length" icon="map" title="No profiles match" body="Try another filter or search term, or add the position." />

    <div v-else class="card overflow-x-auto">
      <table class="w-full min-w-[48rem] text-left text-sm">
        <thead class="border-b border-ink-900/10 text-ink-600 dark:border-white/8 dark:text-stone-400">
          <tr>
            <th scope="col" class="px-4 py-3 font-medium">Position</th>
            <th scope="col" class="px-4 py-3 font-medium">Division</th>
            <th scope="col" class="px-4 py-3 font-medium">Area</th>
            <th scope="col" class="px-4 py-3 font-medium">Competencies</th>
            <th scope="col" class="px-4 py-3"><span class="sr-only">Actions</span></th>
          </tr>
        </thead>
        <tbody class="divide-y divide-ink-900/8 dark:divide-white/6">
          <tr v-for="p in rows" :key="p.id" class="cursor-pointer hover:bg-paper/70 dark:hover:bg-white/3" @click="selected = p">
            <td class="px-4 py-3 font-medium text-ink-900 dark:text-white">
              {{ p.position }}
              <span v-if="posById[p.position_id]?.salary_grade" class="ml-2 text-xs font-normal text-ink-600 dark:text-stone-500">SG {{ posById[p.position_id].salary_grade }}</span>
              <span v-if="p.designated_training_ids?.length" class="ml-2 rounded-full bg-violet-50 px-2 py-0.5 text-xs font-medium text-violet-800 dark:bg-violet-500/15 dark:text-violet-300">{{ p.designated_training_ids.length }} designated</span>
              <span v-if="p.source === 'added'" class="ml-2 rounded-full bg-sky-50 px-2 py-0.5 text-xs font-medium text-sky-800 dark:bg-sky-500/15 dark:text-sky-300">Added</span>
            </td>
            <td class="px-4 py-3 text-ink-600 dark:text-stone-400">{{ p.division }}</td>
            <td class="px-4 py-3 text-ink-800 dark:text-stone-300">{{ p.area }}</td>
            <td class="px-4 py-3">
              <span class="tabular-nums text-ink-900 dark:text-stone-100">{{ Object.keys(p.standards).length }}</span>
              <span class="ml-2 text-xs text-ink-600 dark:text-stone-500">
                {{ countBy(p.standards, 'technical') }} technical<template v-if="countBy(p.standards, 'leadership')"> · {{ countBy(p.standards, 'leadership') }} leadership</template>
              </span>
            </td>
            <td class="px-4 py-3 text-right whitespace-nowrap">
              <button type="button" class="mr-3 text-sm font-medium text-brand-600 hover:underline dark:text-brand-300" @click.stop="selected = p">View</button>
              <button type="button" class="text-sm font-medium text-brand-600 hover:underline dark:text-brand-300" @click.stop="openEdit(p)">Edit</button>
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    </template>

    <!-- Detail -->
    <AppModal :open="!!selected" :title="selected?.position ?? ''" size="lg" @close="selected = null">
      <template #subtitle><p class="text-sm text-ink-600 dark:text-stone-400">{{ selected?.division }} · {{ selected?.area }}<template v-if="selected?.source === 'map-2023'"> · original 2023 map</template></p></template>
      <div v-if="selected" class="space-y-5">
        <p v-if="detailError" role="alert" class="rounded-lg bg-red-50 px-3 py-2 text-sm text-red-800 dark:bg-red-950/40 dark:text-red-200">{{ detailError }}</p>

        <div class="rounded-lg border border-violet-200 bg-violet-50/50 p-4 dark:border-violet-500/25 dark:bg-violet-500/5">
          <h3 class="text-sm font-semibold">Designated trainings</h3>
          <p class="mt-0.5 text-xs text-ink-600 dark:text-stone-400">Suggested to everyone assessed under this profile, on top of the trainings picked automatically from their gaps.</p>
          <div v-if="selected.designated_training_ids?.length" class="mt-3 flex flex-wrap gap-1.5">
            <span v-for="id in selected.designated_training_ids" :key="id" class="inline-flex items-center gap-1 rounded-md bg-white py-0.5 pr-1 pl-2 text-sm ring-1 ring-violet-200 dark:bg-ink-900 dark:ring-violet-500/30">
              {{ trainingById[id]?.title ?? `Training ${id}` }}
              <button type="button" class="rounded p-0.5 hover:bg-ink-900/10 dark:hover:bg-white/10" :aria-label="`Remove ${trainingById[id]?.title}`" :disabled="busy" @click="removeDesignated(id)"><Icon name="x" :size="14" /></button>
            </span>
          </div>
          <div class="mt-3 flex gap-2">
            <select v-model="pickTraining" class="field h-9" aria-label="Training to designate">
              <option value="">Choose a training from the library</option>
              <option v-for="t in (trainingsReq.data.value?.trainings ?? []).filter((t) => !(selected.designated_training_ids ?? []).includes(t.id))" :key="t.id" :value="t.id">{{ t.title }}</option>
            </select>
            <button type="button" class="btn-secondary h-9 shrink-0" :disabled="!pickTraining || busy" @click="addDesignated"><Icon name="plus" :size="16" /> Add</button>
          </div>
        </div>

        <div v-for="g in grouped(selected.standards)" :key="g.key">
          <h3 class="mb-2 flex items-center gap-2 text-sm font-semibold">
            <span class="rounded-full px-2 py-0.5 text-xs font-medium" :class="categoryClass[g.key]">{{ g.label }}</span>
            <span class="text-ink-600 dark:text-stone-400">{{ g.items.length }}</span>
          </h3>
          <ul class="divide-y divide-ink-900/8 rounded-lg border border-ink-900/10 dark:divide-white/6 dark:border-white/8">
            <li v-for="it in g.items" :key="it.comp.id" class="flex items-center justify-between gap-3 px-3 py-2 text-sm">
              <span>{{ it.comp.name }}</span>
              <span class="shrink-0 text-ink-600 dark:text-stone-400"><span class="font-semibold text-ink-900 tabular-nums dark:text-white">{{ it.level }}</span> · {{ levelLabel(it.level) }}</span>
            </li>
          </ul>
        </div>
      </div>
      <template #footer>
        <button type="button" class="mr-auto text-sm font-medium text-red-700 hover:underline dark:text-red-300" :disabled="busy" @click="removeProfile">Delete profile</button>
        <button type="button" class="btn-secondary" @click="selected = null">Close</button>
        <button type="button" class="btn-primary" @click="openEdit(selected)"><Icon name="settings" :size="16" /> Edit</button>
      </template>
    </AppModal>

    <!-- Add / edit -->
    <AppModal :open="!!editing" :title="editing?.id ? 'Edit profile' : 'Add profile'" size="lg" @close="editing = null">
      <template #subtitle>
        <p class="text-sm text-ink-600 dark:text-stone-400">Enter the position, division and area exactly as the employee portal has them. Employees with those three values are assessed with this profile.</p>
      </template>
      <form id="profile-form" class="space-y-5" @submit.prevent="save">
        <p v-if="formError" role="alert" class="rounded-lg bg-red-50 px-3 py-2 text-sm text-red-800 dark:bg-red-950/40 dark:text-red-200">{{ formError }}</p>

        <div class="grid gap-4 sm:grid-cols-2">
          <div class="sm:col-span-2">
            <label for="pf-position" class="field-label">Position</label>
            <input id="pf-position" v-model="form.position" list="pf-positions" required class="field" placeholder="e.g. Information Systems Analyst II" autocomplete="off" />
            <datalist id="pf-positions"><option v-for="t in positionSuggestions" :key="t" :value="t" /></datalist>
            <p v-if="form.position" class="mt-1 text-xs" :class="portalCheck.position ? 'text-brand-700 dark:text-brand-300' : 'text-amber-700 dark:text-amber-300'">
              {{ portalCheck.position ? 'Found in the employee portal.' : 'Not found in the employee portal yet. Check the spelling, or employees won\'t match.' }}
            </p>
            <p v-if="fieldErrors.position" class="field-error">{{ fieldErrors.position }}</p>
          </div>
          <div class="sm:col-span-2">
            <label for="pf-division" class="field-label">Division</label>
            <select id="pf-division" v-model="form.divisionId" required class="field" @change="onDivisionChange">
              <option value="">All divisions</option>
              <option v-for="d in divisions" :key="d.id" :value="String(d.id)">{{ d.name }}</option>
            </select>
            <p v-if="fieldErrors.division" class="field-error">{{ fieldErrors.division }}</p>
          </div>
          <div class="sm:col-span-2">
            <label for="pf-area" class="field-label">Area</label>
            <select id="pf-area" v-model="areaChoice" required class="field">
              <option value="">{{ form.divisionId ? 'Choose an area' : 'Choose an area (any division)' }}</option>
              <optgroup v-for="g in areaGroups" :key="g.division.id" :label="g.division.name">
                <option v-for="a in g.areas" :key="a" :value="`${g.division.id}|${a}`">{{ a }}{{ g.legacy.includes(a) ? ' (2023 map label)' : '' }}</option>
              </optgroup>
            </select>
            <p class="mt-1 text-xs text-ink-600 dark:text-stone-500">Official R1MC areas. Picking an area also sets its division.</p>
            <p v-if="fieldErrors.office" class="field-error">{{ fieldErrors.office }}</p>
          </div>
          <template v-if="form.position && !matchedPosition">
            <div>
              <label for="pf-plevel" class="field-label">Competency level <span class="font-normal text-ink-600 dark:text-stone-500">(new position, optional)</span></label>
              <select id="pf-plevel" v-model.number="form.positionLevel" class="field">
                <option v-for="l in LEVELS" :key="l.value" :value="l.value">Level {{ l.value }} · {{ l.label }}</option>
              </select>
              <p v-if="fieldErrors.position_level" class="field-error">{{ fieldErrors.position_level }}</p>
            </div>
            <div>
              <label for="pf-sg" class="field-label">Salary grade <span class="font-normal text-ink-600 dark:text-stone-500">(new position)</span></label>
              <input id="pf-sg" v-model="form.salaryGrade" type="number" min="1" max="33" class="field" />
            </div>
          </template>
        </div>

        <div v-if="!editing?.id">
          <label for="pf-copy" class="field-label">Start from an existing profile <span class="font-normal text-ink-600 dark:text-stone-500">(optional)</span></label>
          <div class="flex gap-2">
            <select id="pf-copy" v-model="form.copyFrom" class="field">
              <option value="">Choose a profile to copy its competencies</option>
              <option v-for="p in (data?.profiles ?? []).map(describe)" :key="p.id" :value="String(p.id)">{{ p.position }} · {{ p.division }} · {{ p.area }}</option>
            </select>
            <button type="button" class="btn-secondary shrink-0" :disabled="!form.copyFrom" @click="copyStandards">Copy</button>
          </div>
        </div>

        <div>
          <p class="field-label">Required competencies <span class="font-normal text-ink-600 dark:text-stone-500">({{ Object.keys(form.standards).length }})</span></p>
          <p v-if="fieldErrors.standards" class="field-error mb-2">{{ fieldErrors.standards }}</p>
          <div class="space-y-4">
            <div v-for="g in grouped(form.standards)" :key="g.key">
              <p class="mb-1.5"><span class="rounded-full px-2 py-0.5 text-xs font-medium" :class="categoryClass[g.key]">{{ g.label }}</span></p>
              <ul class="divide-y divide-ink-900/8 rounded-lg border border-ink-900/10 dark:divide-white/6 dark:border-white/8">
                <li v-for="it in g.items" :key="it.comp.id" class="flex items-center gap-3 px-3 py-2 text-sm">
                  <span class="min-w-0 flex-1">{{ it.comp.name }}</span>
                  <select v-model.number="form.standards[it.comp.id]" class="field h-9 w-40" :aria-label="`Required level for ${it.comp.name}`">
                    <option v-for="l in LEVELS" :key="l.value" :value="l.value">{{ l.value }} · {{ l.label }}</option>
                  </select>
                  <button type="button" class="rounded-md p-1.5 text-ink-600 hover:bg-ink-900/5 dark:text-stone-400 dark:hover:bg-white/8" :aria-label="`Remove ${it.comp.name}`" @click="delete form.standards[it.comp.id]">
                    <Icon name="x" :size="16" />
                  </button>
                </li>
              </ul>
            </div>
          </div>
          <div class="mt-3 flex gap-2">
            <select v-model="pickCompetency" class="field" aria-label="Competency to add">
              <option value="">Add a competency</option>
              <optgroup v-for="g in available" :key="g.key" :label="g.label">
                <option v-for="c in g.items" :key="c.id" :value="c.id">{{ c.name }}</option>
              </optgroup>
            </select>
            <button type="button" class="btn-secondary shrink-0" :disabled="!pickCompetency" @click="addCompetency"><Icon name="plus" :size="16" /> Add</button>
          </div>
        </div>
      </form>
      <template #footer>
        <button type="button" class="btn-secondary" @click="editing = null">Cancel</button>
        <button type="submit" form="profile-form" class="btn-primary" :disabled="saving">{{ saving ? 'Saving…' : editing?.id ? 'Save changes' : 'Save profile' }}</button>
      </template>
    </AppModal>
  </section>
</template>

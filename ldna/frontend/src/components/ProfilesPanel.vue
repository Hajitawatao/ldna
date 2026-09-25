<script setup>
import { ref, computed, reactive, watch } from 'vue'
import Icon from './Icon.vue'
import AppModal from './AppModal.vue'
import ErrorState from './ErrorState.vue'
import EmptyState from './EmptyState.vue'
import TallyTable from './TallyTable.vue'
import { useApiGet, apiPost, apiPut, apiDelete } from '../composables/useApi'
import { useReference } from '../composables/useReference'
import { useLayout } from '../composables/useLayout'
import { CATEGORIES, LEVELS, categoryClass, levelLabel } from '../lib/format'

// Position profiles: which competencies (and levels) a plantilla position has in a specific area.
// ADMINISTRATIVE ASSISTANT II in MIS and in SECURITY OFFICE are two different profiles.
const { divisions, competencies, compById, posById, officeById, divisionById, officialOffices, currentPositions, levelText } = useReference()
const { data, loading, error, load } = useApiGet('profiles.php')
const { searchQuery } = useLayout()

const isLive = (p) => officeById.value[p.office_id]?.source === 'official-2026'
const describe = (p) => {
  const o = officeById.value[p.office_id]
  const pos = posById.value[p.position_id]
  return { ...p, position: pos?.title ?? '?', sg: pos?.salary_grade, area: o?.area ?? '?',
    divisionId: o?.division_id, division: divisionById.value[o?.division_id]?.name ?? '' }
}
const divisionFilter = ref('')
const areaFilter = ref('')
const areaOptions = computed(() => officialOffices.value.filter((o) => !divisionFilter.value || String(o.division_id) === divisionFilter.value))
watch(divisionFilter, () => { areaFilter.value = '' })
const rows = computed(() => {
  const q = searchQuery.value.trim().toLowerCase()
  return (data.value?.profiles ?? []).filter(isLive).map(describe)
    .filter((p) => (!divisionFilter.value || String(p.divisionId) === divisionFilter.value) &&
      (!areaFilter.value || String(p.office_id) === areaFilter.value) &&
      (!q || [p.position, p.area, p.division].join(' ').toLowerCase().includes(q)))
    .sort((a, b) => a.position.localeCompare(b.position) || a.area.localeCompare(b.area))
})
// One block per position, with each of its areas under it
const groupsByPosition = computed(() => {
  const g = []
  for (const r of rows.value) {
    let x = g.find((y) => y.positionId === r.position_id)
    if (!x) g.push((x = { positionId: r.position_id, position: r.position, sg: r.sg, rows: [] }))
    x.rows.push(r)
  }
  return g
})
// Master-detail: pick a position, then one of its areas, to see that area's competency standards
const selectedPositionId = ref(null)
const selectedProfileId = ref(null)
const selectedGroup = computed(() => groupsByPosition.value.find((g) => g.positionId === selectedPositionId.value) ?? null)
const selectedProfile = computed(() => selectedGroup.value?.rows.find((r) => r.id === selectedProfileId.value) ?? selectedGroup.value?.rows[0] ?? null)
function pickPosition(g) { selectedPositionId.value = g.positionId; selectedProfileId.value = g.rows[0]?.id ?? null }
const standardRows = (p) => Object.entries(p.standards).map(([cid, lvl]) => ({ competency_id: Number(cid), standard: lvl }))
const countBy = (standards, key) => Object.keys(standards).filter((cid) => compById.value[cid]?.category === key).length
function grouped(standards) {
  return CATEGORIES.map((cat) => ({
    ...cat,
    items: Object.entries(standards).map(([cid, level]) => ({ comp: compById.value[cid], level }))
      .filter((x) => x.comp?.category === cat.key).sort((a, b) => a.comp.name.localeCompare(b.comp.name)),
  })).filter((g) => g.items.length)
}

// ---------- add / edit
const editing = ref(null)
const form = reactive({ id: null, positionId: '', divisionId: '', officeId: '', standards: {}, copyFrom: '' })
const saving = ref(false)
const formError = ref('')
const fieldErrors = ref({})
const pickCompetency = ref('')
const formAreas = computed(() => officialOffices.value.filter((o) => !form.divisionId || String(o.division_id) === String(form.divisionId)))
const areaGroups = computed(() => divisions.value.map((d) => ({ division: d, areas: formAreas.value.filter((o) => o.division_id === d.id) })).filter((g) => g.areas.length))
function onAreaChange() {
  const o = officeById.value[form.officeId]
  if (o) form.divisionId = String(o.division_id)
}
function onDivisionChange() {
  if (form.officeId && String(officeById.value[form.officeId]?.division_id) !== String(form.divisionId)) form.officeId = ''
}
const copySources = computed(() => (data.value?.profiles ?? []).map(describe).map((p) => ({
  id: p.id, label: `${p.position} · ${p.area}${isLive(p) ? '' : ' (2023 map)'}`,
})).sort((a, b) => a.label.localeCompare(b.label)))
function copyStandards() {
  const p = data.value.profiles.find((x) => String(x.id) === form.copyFrom)
  if (p) form.standards = { ...p.standards }
}
const available = computed(() => CATEGORIES.map((cat) => ({ ...cat, items: competencies.value.filter((c) => c.category === cat.key && !(c.id in form.standards)) })))
function addCompetency() { if (pickCompetency.value) form.standards[pickCompetency.value] = 1; pickCompetency.value = '' }

function open(p = null) {
  Object.assign(form, p
    ? { id: p.id, positionId: String(p.position_id), divisionId: String(p.divisionId ?? ''), officeId: String(p.office_id), standards: { ...p.standards }, copyFrom: '' }
    : { id: null, positionId: '', divisionId: divisionFilter.value, officeId: areaFilter.value, standards: {}, copyFrom: '' })
  formError.value = ''; fieldErrors.value = {}
  editing.value = p ?? {}
}
function openNewArea(p) {
  Object.assign(form, { id: null, positionId: String(p.position_id), divisionId: '', officeId: '', standards: { ...p.standards }, copyFrom: '' })
  formError.value = ''; fieldErrors.value = {}
  editing.value = { newArea: true, from: p }
}
async function save() {
  saving.value = true; formError.value = ''; fieldErrors.value = {}
  const body = { position_id: Number(form.positionId), office_id: Number(form.officeId), standards: form.standards }
  try {
    const res = form.id ? await apiPut('profiles.php', { id: form.id, ...body }) : await apiPost('profiles.php', body)
    await load()
    selectedPositionId.value = res.profile.position_id
    selectedProfileId.value = res.profile.id
    editing.value = null
  } catch (e) { formError.value = e.message; fieldErrors.value = e.fields ?? {} } finally { saving.value = false }
}
async function remove() {
  if (!confirm('Delete this position profile?')) return
  saving.value = true
  try { await apiDelete(`profiles.php?id=${form.id}`); await load(); editing.value = null } catch (e) { formError.value = e.message } finally { saving.value = false }
}
</script>

<template>
  <div>
    <div class="mb-4 flex flex-wrap items-end justify-between gap-3">
      <p class="max-w-2xl text-sm text-ink-600 dark:text-stone-400">
        Choose a position to see the areas it's in, then an area to see its competencies and <strong>standard levels</strong>. When an employee
        in that position and area enrolls, they rate their actual level on these; gap = standard minus actual.
      </p>
      <button type="button" class="btn-primary" @click="open()"><Icon name="plus" :size="18" :stroke-width="2" /> Add position</button>
    </div>

    <div class="mb-4 grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
      <label class="block"><span class="mb-1 block text-xs font-medium text-ink-600 dark:text-stone-400">Division</span>
        <select v-model="divisionFilter" class="field"><option value="">All divisions</option><option v-for="d in divisions" :key="d.id" :value="String(d.id)">{{ d.name }}</option></select>
      </label>
      <label class="block"><span class="mb-1 block text-xs font-medium text-ink-600 dark:text-stone-400">Area</span>
        <select v-model="areaFilter" class="field"><option value="">All areas</option><option v-for="o in areaOptions" :key="o.id" :value="String(o.id)">{{ o.area }}</option></select>
      </label>
      <p class="self-end text-sm text-ink-600 lg:text-right dark:text-stone-400">{{ rows.length }} profile{{ rows.length === 1 ? '' : 's' }}</p>
    </div>

    <ErrorState v-if="error" :message="error" @retry="load" />
    <div v-else-if="loading" class="space-y-2"><div v-for="n in 6" :key="n" class="h-12 animate-pulse rounded-lg bg-ink-900/6 dark:bg-white/6" /></div>
    <EmptyState v-else-if="!rows.length" icon="users" title="No position profiles here" body="Add a position to designate its competencies in this area." />
    <div v-else class="grid gap-4 lg:grid-cols-[20rem_1fr]">
      <!-- positions -->
      <nav class="card max-h-[70vh] overflow-y-auto p-2" aria-label="Positions">
        <button v-for="g in groupsByPosition" :key="g.positionId" type="button"
                class="flex w-full items-center justify-between gap-2 rounded-lg px-3 py-2.5 text-left text-sm"
                :class="selectedPositionId === g.positionId ? 'bg-ink-900 text-white dark:bg-brand-600' : 'hover:bg-paper dark:hover:bg-white/6'"
                @click="pickPosition(g)">
          <span class="min-w-0"><span class="block truncate font-medium">{{ g.position }}</span><span class="text-xs opacity-70">SG {{ g.sg ?? '—' }}</span></span>
          <span class="shrink-0 rounded-full px-2 py-0.5 text-xs" :class="selectedPositionId === g.positionId ? 'bg-white/15' : 'bg-ink-900/5 dark:bg-white/8'">{{ g.rows.length }} area{{ g.rows.length === 1 ? '' : 's' }}</span>
        </button>
      </nav>

      <!-- areas of the selected position, and the selected area's competencies -->
      <div v-if="selectedGroup" class="card p-5">
        <div class="flex flex-wrap items-start justify-between gap-3">
          <div>
            <h3 class="text-lg font-semibold text-ink-900 dark:text-white">{{ selectedGroup.position }}</h3>
            <p class="text-sm text-ink-600 dark:text-stone-400">SG {{ selectedGroup.sg ?? '—' }} · available in {{ selectedGroup.rows.length }} area{{ selectedGroup.rows.length === 1 ? '' : 's' }}</p>
          </div>
          <button type="button" class="btn-secondary h-9" @click="openNewArea(selectedProfile)"><Icon name="plus" :size="16" /> Add another area</button>
        </div>
        <div class="mt-4 flex flex-wrap gap-2" role="tablist" aria-label="Areas">
          <button v-for="p in selectedGroup.rows" :key="p.id" type="button" role="tab" :aria-selected="selectedProfile?.id === p.id"
                  class="rounded-lg border px-3 py-2 text-left text-sm"
                  :class="selectedProfile?.id === p.id ? 'border-brand-500 bg-brand-50 dark:bg-brand-700/20' : 'border-ink-900/12 hover:border-ink-900/25 dark:border-white/10'"
                  @click="selectedProfileId = p.id">
            <span class="block text-[0.65rem] font-semibold tracking-wide text-ink-600 uppercase dark:text-stone-400">{{ p.division }}</span>{{ p.area }}
          </button>
        </div>
        <div v-if="selectedProfile" class="mt-5">
          <div class="mb-2 flex flex-wrap items-center justify-between gap-2">
            <p class="text-sm font-semibold">Competencies in {{ selectedProfile.area }}</p>
            <div class="flex gap-2">
              <button type="button" class="btn-secondary h-9" @click="openNewArea(selectedProfile)">Copy to another area</button>
              <button type="button" class="btn-primary h-9" @click="open(selectedProfile)">Edit</button>
            </div>
          </div>
          <TallyTable :rows="standardRows(selectedProfile)" :show-actual="false" />
          <p class="mt-2 text-xs text-ink-600 dark:text-stone-400">Employees in this position and area rate their actual level on these when they enroll; gap = standard minus actual.</p>
        </div>
      </div>
      <EmptyState v-else icon="users" title="Choose a position" body="Its areas and the competencies set for each area appear here." />
    </div>

    <AppModal :open="!!editing" :title="form.id ? 'Edit position' : editing?.newArea ? 'Add another area for this position' : 'Add position'" size="lg" @close="editing = null">
      <template #subtitle>
        <p v-if="editing?.newArea" class="text-sm text-ink-600 dark:text-stone-400">Same position, different area. The competencies of {{ editing.from.area }} are copied below; change them for the new area.</p>
        <p v-else class="text-sm text-ink-600 dark:text-stone-400">Choose the plantilla position and its area, then designate the competencies it has there.</p>
      </template>
      <form id="profile-form" class="space-y-5" @submit.prevent="save">
        <p v-if="formError" role="alert" class="rounded-lg bg-red-50 px-3 py-2 text-sm text-red-800 dark:bg-red-950/40 dark:text-red-200">{{ formError }}</p>
        <div>
          <label for="pp-pos" class="field-label">Plantilla position</label>
          <select id="pp-pos" v-model="form.positionId" required class="field">
            <option value="">Choose a position</option>
            <option v-for="p in currentPositions" :key="p.id" :value="String(p.id)">{{ p.title }} (SG {{ p.salary_grade ?? '?' }})</option>
          </select>
          <p v-if="fieldErrors.position" class="field-error">{{ fieldErrors.position }}</p>
        </div>
        <div>
          <label for="pp-div" class="field-label">Division</label>
          <select id="pp-div" v-model="form.divisionId" class="field" @change="onDivisionChange">
            <option value="">All divisions</option>
            <option v-for="d in divisions" :key="d.id" :value="String(d.id)">{{ d.name }}</option>
          </select>
        </div>
        <div>
          <label for="pp-area" class="field-label">Area</label>
          <select id="pp-area" v-model="form.officeId" required class="field" @change="onAreaChange">
            <option value="">Choose an area</option>
            <optgroup v-for="g in areaGroups" :key="g.division.id" :label="g.division.name">
              <option v-for="o in g.areas" :key="o.id" :value="String(o.id)">{{ o.area }}</option>
            </optgroup>
          </select>
          <p v-if="fieldErrors.office" class="field-error">{{ fieldErrors.office }}</p>
        </div>
        <div>
          <label for="pp-copy" class="field-label">Start from another profile <span class="font-normal text-ink-600 dark:text-stone-500">(optional; includes the 2023 DOH map)</span></label>
          <div class="flex gap-2">
            <select id="pp-copy" v-model="form.copyFrom" class="field">
              <option value="">Choose a profile to copy its competencies</option>
              <option v-for="c in copySources" :key="c.id" :value="String(c.id)">{{ c.label }}</option>
            </select>
            <button type="button" class="btn-secondary shrink-0" :disabled="!form.copyFrom" @click="copyStandards">Copy</button>
          </div>
        </div>
        <div>
          <p class="field-label">Competencies of this position in this area <span class="font-normal text-ink-600 dark:text-stone-500">({{ Object.keys(form.standards).length }})</span></p>
          <p v-if="fieldErrors.standards" class="field-error mb-2">{{ fieldErrors.standards }}</p>
          <div class="space-y-4">
            <div v-for="g in grouped(form.standards)" :key="g.key">
              <p class="mb-1.5"><span class="rounded-full px-2 py-0.5 text-xs font-medium" :class="categoryClass[g.key]">{{ g.label }}</span></p>
              <ul class="divide-y divide-ink-900/8 rounded-lg border border-ink-900/10 dark:divide-white/6 dark:border-white/8">
                <li v-for="it in g.items" :key="it.comp.id" class="flex items-center gap-3 px-3 py-2 text-sm">
                  <span class="min-w-0 flex-1">{{ it.comp.name }}</span>
                  <select v-model.number="form.standards[it.comp.id]" class="field h-9 w-40" :aria-label="`Level for ${it.comp.name}`">
                    <option v-for="l in LEVELS" :key="l.value" :value="l.value">{{ l.value }} · {{ l.label }}</option>
                  </select>
                  <button type="button" class="rounded-md p-1.5 text-ink-600 hover:bg-ink-900/5 dark:text-stone-400 dark:hover:bg-white/8" :aria-label="`Remove ${it.comp.name}`" @click="delete form.standards[it.comp.id]"><Icon name="x" :size="16" /></button>
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
        <button v-if="form.id" type="button" class="mr-auto text-sm font-medium text-red-700 hover:underline dark:text-red-300" :disabled="saving" @click="remove">Delete</button>
        <button v-if="form.id" type="button" class="btn-secondary" @click="openNewArea({ ...editing, position_id: Number(form.positionId), standards: { ...form.standards } })">Copy to another area</button>
        <button type="button" class="btn-secondary" @click="editing = null">Cancel</button>
        <button type="submit" form="profile-form" class="btn-primary" :disabled="saving">{{ saving ? 'Saving…' : 'Save' }}</button>
      </template>
    </AppModal>
  </div>
</template>

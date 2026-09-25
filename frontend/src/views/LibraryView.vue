<script setup>
import { ref, computed, reactive } from 'vue'
import Icon from '../components/Icon.vue'
import AppModal from '../components/AppModal.vue'
import SelectField from '../components/SelectField.vue'
import ErrorState from '../components/ErrorState.vue'
import EmptyState from '../components/EmptyState.vue'
import TrainingCard from '../components/TrainingCard.vue'
import LevelLegend from '../components/LevelLegend.vue'
import { useApiGet, apiPost, apiPut, apiDelete } from '../composables/useApi'
import { useReference } from '../composables/useReference'
import { useLayout } from '../composables/useLayout'
import { CATEGORIES, MODES, TRAINING_CATEGORIES } from '../lib/format'

const { competencies, officialOffices, currentPositions, compById, officeById, posById, divisionById, levels, areasByDivision } = useReference()
const { data, loading, error, load } = useApiGet('trainings.php')
const { searchQuery } = useLayout()

const tab = ref('general')
const levelFilter = ref('')
const levelOptions = computed(() => levels.value.map((l) => ({ value: String(l.level), label: `Level ${l.level} · SG ${l.min_sg}–${l.max_sg}` })))
const area = ref('')
const mode = ref('')
// The official R1MC areas (tools/reference), grouped under their division; no division codes in the labels
const areaOptions = computed(() => officialOffices.value.map((o) => ({ value: String(o.id), label: o.area, division: divisionById.value[o.division_id]?.name ?? 'Other' })))
const areaGroups = computed(() => {
  const g = {}
  for (const o of areaOptions.value) (g[o.division] ??= []).push(o)
  return Object.entries(g)
})
const modeOptions = MODES.map((m) => ({ value: m.key, label: m.label }))

const all = computed(() => data.value?.trainings ?? [])
const counts = computed(() => Object.fromEntries(TRAINING_CATEGORIES.map((c) => [c.key, all.value.filter((t) => (t.category ?? 'general') === c.key).length])))
const visible = computed(() => {
  const q = searchQuery.value.trim().toLowerCase()
  return all.value
    .filter((t) => (t.category ?? 'general') === tab.value)
    .filter((t) => !levelFilter.value || String(t.level) === levelFilter.value)
    .filter((t) => !mode.value || t.mode === mode.value)
    .filter((t) => !area.value || t.category !== 'area' || t.office_ids.includes(Number(area.value)))
    .filter((t) => !q || [t.title, t.provider, t.type, ...t.competency_ids.map((id) => compById.value[id]?.name ?? '')].join(' ').toLowerCase().includes(q))
    .sort((a, b) => a.level - b.level || a.title.localeCompare(b.title))
})

// ------------------------------------------------ add / edit / delete
const editing = ref(null)
const saving = ref(false)
const formError = ref('')
const fieldErrors = ref({})
const blank = () => ({ id: null, title: '', mode: 'non-formal', type: '', provider: '', hours: '', description: '', level: 1,
  all_positions: true, competency_ids: [], standards: {}, office_ids: [], position_ids: [] })
const form = reactive(blank())
const pick = reactive({ comp: '', area: '', position: '' })
const types = computed(() => [...new Set(all.value.map((t) => t.type).filter(Boolean))])

function openForm(t = null) {
  Object.assign(form, t
    ? { ...t, hours: t.hours ?? '', all_positions: t.all_positions !== false, competency_ids: [...t.competency_ids], standards: { ...(t.standards ?? {}) },
        office_ids: [...t.office_ids], position_ids: [...(t.position_ids ?? [])] }
    : { ...blank(), all_positions: tab.value !== 'position' })
  formError.value = ''; fieldErrors.value = {}
  editing.value = t ?? {}
}
const addTo = (list, key) => {
  const v = Number(pick[key])
  if (v && !form[list].includes(v)) {
    form[list].push(v)
    if (list === 'competency_ids') form.standards[v] = 1      // default required level
  }
  pick[key] = ''
}
const removeFrom = (list, id) => {
  form[list] = form[list].filter((x) => x !== id)
  if (list === 'competency_ids') delete form.standards[id]
}

async function save() {
  saving.value = true; formError.value = ''; fieldErrors.value = {}
  try {
    const res = form.id ? await apiPut('trainings.php', form) : await apiPost('trainings.php', form)
    const list = data.value.trainings
    const i = list.findIndex((t) => t.id === res.training.id)
    if (i >= 0) list.splice(i, 1, { ...list[i], ...res.training }); else list.push({ ...res.training, enrolled: 0 })
    tab.value = res.training.category
    editing.value = null
  } catch (e) {
    formError.value = e.message; fieldErrors.value = e.fields ?? {}
  } finally {
    saving.value = false
  }
}
async function remove() {
  if (!confirm(`Delete "${form.title}"?`)) return
  saving.value = true; formError.value = ''
  try {
    await apiDelete(`trainings.php?id=${form.id}`)
    data.value.trainings = data.value.trainings.filter((t) => t.id !== form.id)
    editing.value = null
  } catch (e) { formError.value = e.message } finally { saving.value = false }
}
</script>

<template>
  <section>
    <div class="mb-5 flex flex-wrap items-end justify-between gap-3">
      <div>
        <h2 class="text-xl font-semibold text-ink-900 dark:text-white">Training library</h2>
        <p class="mt-0.5 max-w-2xl text-sm text-ink-600 dark:text-stone-400">
          Every training has an <strong>assessment level</strong> and the <strong>positions</strong> that can apply; <strong>areas</strong> are optional. Employees can enrol only if their level (from their salary grade), position and area all fit.
        </p>
      </div>
      <button type="button" class="btn-primary" @click="openForm()"><Icon name="plus" :size="18" :stroke-width="2" /> Add training</button>
    </div>

    <LevelLegend class="mb-4" editable />

    <div class="mb-4 flex flex-wrap gap-1 rounded-lg border border-ink-900/10 bg-white p-1 dark:border-white/8 dark:bg-ink-900" role="tablist">
      <button v-for="c in TRAINING_CATEGORIES" :key="c.key" type="button" role="tab" :aria-selected="tab === c.key"
              class="rounded-md px-4 py-2 text-sm font-medium"
              :class="tab === c.key ? 'bg-ink-900 text-white dark:bg-brand-600' : 'text-ink-600 hover:bg-paper dark:text-stone-400 dark:hover:bg-white/6'"
              @click="tab = c.key">
        {{ c.label }} <span class="ml-1 tabular-nums opacity-60">{{ counts[c.key] ?? 0 }}</span>
      </button>
    </div>

    <div class="mb-6 grid gap-3 sm:grid-cols-2 lg:grid-cols-4 lg:items-end">
      <SelectField v-model="levelFilter" label="Assessment level" placeholder="All levels" :options="levelOptions" />
      <label v-if="tab === 'area'" class="block min-w-0">
        <span class="mb-1 block text-xs font-medium text-ink-600 dark:text-stone-400">Area</span>
        <select v-model="area" class="field">
          <option value="">All areas</option>
          <optgroup v-for="[division, list] in areaGroups" :key="division" :label="division">
            <option v-for="o in list" :key="o.value" :value="o.value">{{ o.label }}</option>
          </optgroup>
        </select>
      </label>
      <SelectField v-model="mode" label="Mode" placeholder="All modes" :options="modeOptions" />
      <p class="text-sm text-ink-600 lg:col-start-4 lg:text-right dark:text-stone-400">{{ visible.length }} training{{ visible.length === 1 ? '' : 's' }}</p>
    </div>

    <ErrorState v-if="error" :message="error" @retry="load" />
    <div v-else-if="loading" class="grid gap-3 md:grid-cols-2 xl:grid-cols-3" aria-busy="true">
      <div v-for="n in 6" :key="n" class="h-40 animate-pulse rounded-lg bg-ink-900/6 dark:bg-white/6" />
    </div>
    <EmptyState v-else-if="!visible.length" icon="library" title="No trainings here yet" body="Change the filters, or add a training to this category." />
    <div v-else class="grid gap-3 md:grid-cols-2 xl:grid-cols-3">
      <TrainingCard v-for="t in visible" :key="t.id" :training="t">
        <div class="flex items-center justify-between text-sm">
          <span class="text-ink-600 dark:text-stone-400">{{ t.enrolled }} enrolled</span>
          <button type="button" class="font-medium text-brand-600 hover:underline dark:text-brand-300" @click="openForm(t)">Edit</button>
        </div>
      </TrainingCard>
    </div>

    <AppModal :open="!!editing" :title="form.id ? 'Edit training' : 'Add training'" size="lg" @close="editing = null">
      <form id="training-form" class="space-y-5" @submit.prevent="save">
        <p v-if="formError" role="alert" class="rounded-lg bg-red-50 px-3 py-2 text-sm text-red-800 dark:bg-red-950/40 dark:text-red-200">{{ formError }}</p>
        <div>
          <label for="tf-title" class="field-label">Title</label>
          <input id="tf-title" v-model="form.title" required class="field" placeholder="e.g. Coding Fundamentals" />
          <p v-if="fieldErrors.title" class="field-error">{{ fieldErrors.title }}</p>
        </div>

        <div class="grid gap-4 sm:grid-cols-2">
          <div class="sm:col-span-2">
            <label for="tf-level" class="field-label">Assessment level <span class="text-red-700 dark:text-red-300">*</span></label>
            <select id="tf-level" v-model.number="form.level" required class="field sm:max-w-xs">
              <option v-for="l in levels" :key="l.level" :value="l.level">Level {{ l.level }} · {{ l.label }} (SG {{ l.min_sg }}–{{ l.max_sg }})</option>
            </select>
            <p class="mt-1 text-xs text-ink-600 dark:text-stone-500">Employees at this level or higher can enrol. Their level comes from their salary grade:</p>
            <LevelLegend class="mt-2" :highlight="form.level" />
            <p v-if="fieldErrors.level" class="field-error">{{ fieldErrors.level }}</p>
          </div>
        </div>

        <fieldset>
          <legend class="field-label">Positions that can apply <span class="text-red-700 dark:text-red-300">*</span></legend>
          <div class="flex flex-wrap gap-4 text-sm">
            <label class="flex items-center gap-2"><input v-model="form.all_positions" type="radio" :value="true" class="accent-brand-600" /> All positions</label>
            <label class="flex items-center gap-2"><input v-model="form.all_positions" type="radio" :value="false" class="accent-brand-600" /> Only these positions</label>
          </div>
          <p v-if="fieldErrors.position_ids" class="field-error mt-1">{{ fieldErrors.position_ids }}</p>
          <template v-if="!form.all_positions">
            <div v-if="form.position_ids.length" class="mt-2 flex flex-wrap gap-1.5">
              <span v-for="id in form.position_ids" :key="id" class="inline-flex items-center gap-1 rounded-md bg-violet-50 py-0.5 pr-1 pl-2 text-sm text-violet-900 dark:bg-violet-500/15 dark:text-violet-200">
                {{ posById[id]?.title }} <span class="text-xs opacity-70">SG {{ posById[id]?.salary_grade ?? '?' }}</span>
                <button type="button" class="rounded p-0.5 hover:bg-violet-900/10" :aria-label="`Remove ${posById[id]?.title}`" @click="removeFrom('position_ids', id)"><Icon name="x" :size="14" /></button>
              </span>
            </div>
            <div class="mt-2 flex gap-2">
              <select v-model="pick.position" class="field" aria-label="Position to add">
                <option value="">Choose a position</option>
                <option v-for="p in currentPositions" :key="p.id" :value="p.id">{{ p.title }} (SG {{ p.salary_grade ?? '?' }})</option>
              </select>
              <button type="button" class="btn-secondary shrink-0" :disabled="!pick.position" @click="addTo('position_ids', 'position')"><Icon name="plus" :size="16" /> Add</button>
            </div>
          </template>
        </fieldset>

        <div>
          <p class="field-label">Areas <span class="font-normal text-ink-600 dark:text-stone-500">(optional: leave empty for all areas; from the official R1MC area list)</span></p>
          <div v-if="form.office_ids.length" class="mb-2 space-y-2">
            <div v-for="g in areasByDivision(form.office_ids)" :key="g.division">
              <p class="mb-1 text-xs font-semibold tracking-wide text-ink-600 uppercase dark:text-stone-400">{{ g.division }}</p>
              <div class="flex flex-wrap gap-1.5">
                <span v-for="a in g.areas" :key="a.id" class="inline-flex items-center gap-1 rounded-md bg-sky-50 py-0.5 pr-1 pl-2 text-sm text-sky-900 dark:bg-sky-500/15 dark:text-sky-200">
                  {{ a.area }}
                  <button type="button" class="rounded p-0.5 hover:bg-sky-900/10" :aria-label="`Remove ${a.area}`" @click="removeFrom('office_ids', a.id)"><Icon name="x" :size="14" /></button>
                </span>
              </div>
            </div>
          </div>
          <div class="flex gap-2">
            <select v-model="pick.area" class="field" aria-label="Area to add">
              <option value="">Choose an area</option>
              <optgroup v-for="[division, list] in areaGroups" :key="division" :label="division">
                <option v-for="o in list" :key="o.value" :value="o.value">{{ o.label }}</option>
              </optgroup>
            </select>
            <button type="button" class="btn-secondary shrink-0" :disabled="!pick.area" @click="addTo('office_ids', 'area')"><Icon name="plus" :size="16" /> Add</button>
          </div>
        </div>

        <div class="grid gap-4 sm:grid-cols-2">
          <div>
            <label for="tf-mode" class="field-label">Mode</label>
            <select id="tf-mode" v-model="form.mode" class="field">
              <option v-for="m in MODES" :key="m.key" :value="m.key">{{ m.label }} — {{ m.hint }}</option>
            </select>
          </div>
          <div>
            <label for="tf-type" class="field-label">Type</label>
            <input id="tf-type" v-model="form.type" list="tf-types" class="field" placeholder="In-house training" />
            <datalist id="tf-types"><option v-for="t in types" :key="t" :value="t" /></datalist>
          </div>
          <div class="grid grid-cols-2 gap-3">
            <div><label for="tf-provider" class="field-label">Provider</label><input id="tf-provider" v-model="form.provider" class="field" /></div>
            <div><label for="tf-hours" class="field-label">Hours</label><input id="tf-hours" v-model="form.hours" type="number" min="1" class="field" /></div>
          </div>
        </div>

        <div>
          <p class="field-label">Competencies it covers <span class="font-normal text-ink-600 dark:text-stone-500">(optional: CETAR sees which of an employee's gaps it addresses)</span></p>
          <p v-if="fieldErrors.competency_ids" class="field-error mb-2">{{ fieldErrors.competency_ids }}</p>
          <ul v-if="form.competency_ids.length" class="mb-2 divide-y divide-ink-900/8 rounded-lg border border-ink-900/10 dark:divide-white/6 dark:border-white/8">
            <li v-for="id in form.competency_ids" :key="id" class="flex items-center gap-3 px-3 py-2 text-sm">
              <span class="min-w-0 flex-1">{{ compById[id]?.name }} <span class="text-xs text-ink-600 dark:text-stone-400">{{ compById[id]?.category }}</span></span>
              <button type="button" class="rounded-md p-1.5 text-ink-600 hover:bg-ink-900/5 dark:text-stone-400 dark:hover:bg-white/8" :aria-label="`Remove ${compById[id]?.name}`" @click="removeFrom('competency_ids', id)"><Icon name="x" :size="16" /></button>
            </li>
          </ul>
          <div class="flex gap-2">
            <select v-model="pick.comp" class="field" aria-label="Competency to link">
              <option value="">Choose a competency</option>
              <optgroup v-for="cat in CATEGORIES" :key="cat.key" :label="cat.label">
                <option v-for="c in competencies.filter((x) => x.category === cat.key && !form.competency_ids.includes(x.id))" :key="c.id" :value="c.id">{{ c.name }}</option>
              </optgroup>
            </select>
            <button type="button" class="btn-secondary shrink-0" :disabled="!pick.comp" @click="addTo('competency_ids', 'comp')"><Icon name="plus" :size="16" /> Link</button>
          </div>
        </div>

        <div>
          <label for="tf-desc" class="field-label">Description <span class="font-normal text-ink-600 dark:text-stone-500">(optional)</span></label>
          <textarea id="tf-desc" v-model="form.description" rows="3" class="field h-auto py-2" />
        </div>
      </form>
      <template #footer>
        <button v-if="form.id" type="button" class="mr-auto text-sm font-medium text-red-700 hover:underline dark:text-red-300" :disabled="saving" @click="remove">Delete training</button>
        <button type="button" class="btn-secondary" @click="editing = null">Cancel</button>
        <button type="submit" form="training-form" class="btn-primary" :disabled="saving">{{ saving ? 'Saving…' : 'Save training' }}</button>
      </template>
    </AppModal>
  </section>
</template>

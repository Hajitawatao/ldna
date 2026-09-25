<script setup>
import { ref, computed, reactive } from 'vue'
import Icon from './Icon.vue'
import AppModal from './AppModal.vue'
import ErrorState from './ErrorState.vue'
import LevelLegend from './LevelLegend.vue'
import { useApiGet, apiPost, apiPut, apiDelete } from '../composables/useApi'
import { useReference } from '../composables/useReference'
import { useLayout } from '../composables/useLayout'

// Plantilla positions and their salary grade. The SG decides the employee's assessment level.
const ref_ = useReference()
const { levels, levelText } = ref_
const { data, loading, error, load } = useApiGet('positions.php')
const portalRef = useApiGet('portal-reference.php')
const { searchQuery } = useLayout()
const levelFilter = ref('')
const currentOnly = ref(true)

const rows = computed(() => {
  const q = searchQuery.value.trim().toLowerCase()
  return (data.value?.positions ?? [])
    .filter((p) => (!currentOnly.value || p.current !== false) &&
      (!levelFilter.value || String(p.assessment_level) === levelFilter.value) &&
      (!q || p.title.toLowerCase().includes(q)))
    .sort((a, b) => a.title.localeCompare(b.title))
})
const inPortal = (title) => (portalRef.data.value?.positions ?? []).some((t) => t.toLowerCase() === title.trim().toLowerCase())

const editing = ref(null)
const form = reactive({ id: null, title: '', salary_grade: '' })
const saving = ref(false)
const formError = ref('')
function open(p = null) {
  Object.assign(form, p ? { id: p.id, title: p.title, salary_grade: p.salary_grade ?? '' } : { id: null, title: '', salary_grade: '' })
  formError.value = ''
  editing.value = p ?? {}
}
const formLevel = computed(() => levels.value.find((l) => Number(form.salary_grade) >= l.min_sg && Number(form.salary_grade) <= l.max_sg)?.level ?? null)
async function save() {
  saving.value = true; formError.value = ''
  try {
    const res = form.id ? await apiPut('positions.php', form) : await apiPost('positions.php', form)
    ref_.setPositions(res.positions)
    await load()
    editing.value = null
  } catch (e) { formError.value = e.message } finally { saving.value = false }
}
async function remove() {
  if (!confirm(`Delete the position "${form.title}"?`)) return
  saving.value = true; formError.value = ''
  try {
    await apiDelete(`positions.php?id=${form.id}`)
    await load(); ref_.reload()
    editing.value = null
  } catch (e) { formError.value = e.message } finally { saving.value = false }
}
</script>

<template>
  <div>
    <p class="mb-4 max-w-3xl text-sm text-ink-600 dark:text-stone-400">
      Current R1MC plantilla positions and their <strong>salary grade</strong>. An employee's assessment level comes from their salary grade, using the level legend:
    </p>
    <LevelLegend class="mb-5" editable />

    <div class="mb-4 flex flex-wrap items-end justify-between gap-3">
      <div class="flex flex-wrap items-end gap-3">
        <label class="block">
          <span class="mb-1 block text-xs font-medium text-ink-600 dark:text-stone-400">Assessment level</span>
          <select v-model="levelFilter" class="field h-10 w-56">
            <option value="">All levels</option>
            <option v-for="l in levels" :key="l.level" :value="String(l.level)">Level {{ l.level }} · SG {{ l.min_sg }}–{{ l.max_sg }}</option>
          </select>
        </label>
        <label class="flex h-10 items-center gap-2 text-sm text-ink-700 dark:text-stone-300">
          <input v-model="currentOnly" type="checkbox" class="size-4 accent-brand-600" /> Current plantilla positions only
        </label>
      </div>
      <button type="button" class="btn-secondary" @click="open()"><Icon name="plus" :size="18" :stroke-width="2" /> Add plantilla title</button>
    </div>

    <ErrorState v-if="error" :message="error" @retry="load" />
    <div v-else-if="loading" class="space-y-2"><div v-for="n in 6" :key="n" class="h-11 animate-pulse rounded-lg bg-ink-900/6 dark:bg-white/6" /></div>
    <div v-else class="card overflow-x-auto">
      <table class="w-full min-w-[36rem] text-left text-sm">
        <thead class="border-b border-ink-900/10 text-ink-600 dark:border-white/8 dark:text-stone-400">
          <tr>
            <th scope="col" class="px-4 py-3 font-medium">Position</th>
            <th scope="col" class="px-4 py-3 font-medium">SG</th>
            <th scope="col" class="px-4 py-3 font-medium">Assessment level</th>
            <th scope="col" class="px-4 py-3 font-medium">Trainings</th>
            <th scope="col" class="px-4 py-3"><span class="sr-only">Edit</span></th>
          </tr>
        </thead>
        <tbody class="divide-y divide-ink-900/8 dark:divide-white/6">
          <tr v-for="p in rows" :key="p.id" class="hover:bg-paper/70 dark:hover:bg-white/3">
            <td class="px-4 py-3 font-medium text-ink-900 dark:text-white">
              {{ p.title }}
              <span v-if="p.current === false" class="ml-2 rounded-full bg-ink-900/5 px-2 py-0.5 text-xs font-normal text-ink-600 dark:bg-white/8 dark:text-stone-400">not in current list</span>
            </td>
            <td class="px-4 py-3 font-semibold tabular-nums">{{ p.salary_grade ?? '—' }}</td>
            <td class="px-4 py-3">
              <span v-if="p.assessment_level" class="rounded-full bg-ink-900/5 px-2.5 py-0.5 text-xs font-semibold dark:bg-white/8">{{ levelText(p.assessment_level) }}</span>
              <span v-else class="text-xs text-amber-700 dark:text-amber-300">No SG</span>
            </td>
            <td class="px-4 py-3 text-xs text-ink-600 dark:text-stone-400">{{ p.trainings }} position-specific</td>
            <td class="px-4 py-3 text-right"><button type="button" class="text-sm font-medium text-brand-600 hover:underline dark:text-brand-300" @click="open(p)">Edit</button></td>
          </tr>
        </tbody>
      </table>
    </div>

    <AppModal :open="!!editing" :title="form.id ? 'Edit position' : 'Add position'" @close="editing = null">
      <form id="position-form" class="space-y-4" @submit.prevent="save">
        <p v-if="formError" role="alert" class="rounded-lg bg-red-50 px-3 py-2 text-sm text-red-800 dark:bg-red-950/40 dark:text-red-200">{{ formError }}</p>
        <div>
          <label for="ps-title" class="field-label">Position</label>
          <input id="ps-title" v-model="form.title" list="ps-titles" required class="field" placeholder="As written in the employee portal" autocomplete="off" />
          <datalist id="ps-titles"><option v-for="t in portalRef.data.value?.positions ?? []" :key="t" :value="t" /></datalist>
          <p v-if="form.title" class="mt-1 text-xs" :class="inPortal(form.title) ? 'text-brand-700 dark:text-brand-300' : 'text-amber-700 dark:text-amber-300'">
            {{ inPortal(form.title) ? 'Found in the employee portal.' : 'Not found in the employee portal. Check the spelling, or employees won\'t match.' }}
          </p>
        </div>
        <div>
          <label for="ps-sg" class="field-label">Salary grade <span class="text-red-700 dark:text-red-300">*</span></label>
          <input id="ps-sg" v-model="form.salary_grade" type="number" min="1" max="33" required class="field sm:max-w-40" />
          <p v-if="formLevel" class="mt-1 text-xs text-ink-600 dark:text-stone-500">Assessment level: {{ levelText(formLevel) }}</p>
        </div>
      </form>
      <template #footer>
        <button v-if="form.id" type="button" class="mr-auto text-sm font-medium text-red-700 hover:underline dark:text-red-300" :disabled="saving" @click="remove">Delete position</button>
        <button type="button" class="btn-secondary" @click="editing = null">Cancel</button>
        <button type="submit" form="position-form" class="btn-primary" :disabled="saving">{{ saving ? 'Saving…' : 'Save' }}</button>
      </template>
    </AppModal>
  </div>
</template>

<script setup>
import { ref } from 'vue'
import Icon from './Icon.vue'
import AppModal from './AppModal.vue'
import { apiPut } from '../composables/useApi'
import { useReference } from '../composables/useReference'

// Which salary grades each assessment level covers. CETAR can adjust the bands.
defineProps({ editable: { type: Boolean, default: false }, highlight: { type: Number, default: null } })
const { levels, setLevels } = useReference()

const editing = ref(false)
const draft = ref([])
const error = ref('')
const saving = ref(false)
function open() {
  draft.value = levels.value.map((l) => ({ ...l }))
  error.value = ''
  editing.value = true
}
// Keep the bands contiguous: each level starts right after the previous one ends
function chain(i) {
  for (let k = i + 1; k < draft.value.length; k++) draft.value[k].min_sg = Number(draft.value[k - 1].max_sg) + 1
}
async function save() {
  saving.value = true; error.value = ''
  try {
    const res = await apiPut('levels.php', { levels: draft.value })
    setLevels(res.levels)
    editing.value = false
  } catch (e) { error.value = e.message } finally { saving.value = false }
}
</script>

<template>
  <div class="rounded-lg border border-ink-900/10 bg-white p-3 dark:border-white/8 dark:bg-ink-900">
    <div class="mb-2 flex items-center justify-between gap-2">
      <p class="text-xs font-semibold tracking-wide text-ink-600 uppercase dark:text-stone-400">Level legend</p>
      <button v-if="editable" type="button" class="text-xs font-medium text-brand-600 hover:underline dark:text-brand-300" @click="open">Edit</button>
    </div>
    <ul class="grid gap-1.5 text-sm sm:grid-cols-2 lg:grid-cols-4">
      <li v-for="l in levels" :key="l.level" class="rounded-md px-2.5 py-1.5"
          :class="highlight === l.level ? 'bg-brand-50 ring-1 ring-brand-500 dark:bg-brand-700/25' : 'bg-ink-900/4 dark:bg-white/5'">
        <span class="font-semibold">Level {{ l.level }}</span> · {{ l.label }}
        <span class="block text-xs text-ink-600 tabular-nums dark:text-stone-400">SG {{ l.min_sg }}–{{ l.max_sg }}</span>
      </li>
    </ul>

    <AppModal :open="editing" title="Level legend" @close="editing = false">
      <template #subtitle><p class="text-sm text-ink-600 dark:text-stone-400">The salary grades each assessment level covers. Employees can take trainings at their level or below.</p></template>
      <p v-if="error" role="alert" class="mb-3 rounded-lg bg-red-50 px-3 py-2 text-sm text-red-800 dark:bg-red-950/40 dark:text-red-200">{{ error }}</p>
      <div class="space-y-3">
        <div v-for="(l, i) in draft" :key="l.level" class="grid grid-cols-[5rem_1fr_5rem_5rem] items-end gap-2">
          <p class="pb-2 text-sm font-semibold">Level {{ l.level }}</p>
          <label class="block"><span class="mb-1 block text-xs text-ink-600 dark:text-stone-400">Name</span><input v-model="l.label" class="field h-9" /></label>
          <label class="block"><span class="mb-1 block text-xs text-ink-600 dark:text-stone-400">From SG</span><input :value="l.min_sg" class="field h-9" disabled /></label>
          <label class="block"><span class="mb-1 block text-xs text-ink-600 dark:text-stone-400">To SG</span>
            <input v-model.number="l.max_sg" type="number" :min="l.min_sg" max="33" class="field h-9" :disabled="i === draft.length - 1" @input="chain(i)" />
          </label>
        </div>
        <p class="text-xs text-ink-600 dark:text-stone-500">Level 1 starts at SG 1 and Level 4 ends at SG 33; the start of each level follows the end of the one before.</p>
      </div>
      <template #footer>
        <button type="button" class="btn-secondary" @click="editing = false">Cancel</button>
        <button type="button" class="btn-primary" :disabled="saving" @click="save">{{ saving ? 'Saving…' : 'Save legend' }}</button>
      </template>
    </AppModal>
  </div>
</template>

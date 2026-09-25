<script setup>
import { ref, computed } from 'vue'
import Icon from './Icon.vue'
import { useApiGet, apiPost } from '../composables/useApi'
import { useReference } from '../composables/useReference'

// Lets CETAR assess an employee under a profile other than the one their plantilla
// position and area point to (e.g. a Nursing Attendant whose item is under HOPSS).
const props = defineProps({ employee: { type: Object, required: true } })
const emit = defineEmits(['changed'])

const { posById, officeById, officeLabel } = useReference()
const profilesReq = useApiGet('profiles.php')

const open = ref(false)
const query = ref('')
const profileId = ref('')
const reason = ref('')
const saving = ref(false)
const error = ref('')

const options = computed(() => (profilesReq.data.value?.profiles ?? [])
  .map((p) => ({ id: p.id, label: `${posById.value[p.position_id]?.title ?? '?'} · ${officeLabel(officeById.value[p.office_id])}` }))
  .sort((a, b) => a.label.localeCompare(b.label)))
const filtered = computed(() => {
  const q = query.value.trim().toLowerCase()
  return q ? options.value.filter((o) => o.label.toLowerCase().includes(q)).slice(0, 50) : options.value.slice(0, 50)
})

function start() {
  open.value = true
  query.value = props.employee.position ?? ''
  profileId.value = props.employee.assignment?.profile_id ? String(props.employee.assignment.profile_id) : ''
  reason.value = props.employee.assignment?.reason ?? ''
  error.value = ''
}
async function save() {
  saving.value = true; error.value = ''
  try {
    await apiPost('assignments.php', { employee_id: props.employee.employee_id, profile_id: Number(profileId.value), reason: reason.value })
    open.value = false
    emit('changed')
  } catch (e) {
    error.value = e.message
  } finally {
    saving.value = false
  }
}
async function remove() {
  saving.value = true; error.value = ''
  try {
    const res = await fetch(`/api/assignments.php?employee_id=${encodeURIComponent(props.employee.employee_id)}`, { method: 'DELETE' })
    if (!res.ok) throw new Error('Could not remove the reassignment.')
    emit('changed')
  } catch (e) {
    error.value = e.message
  } finally {
    saving.value = false
  }
}
</script>

<template>
  <div class="rounded-lg border border-ink-900/10 p-4 dark:border-white/8">
    <div class="flex flex-wrap items-start gap-3">
      <div class="min-w-0 flex-1 text-sm">
        <p class="font-semibold text-ink-900 dark:text-white">Competency profile</p>
        <p class="mt-0.5 text-ink-700 dark:text-stone-300">
          <template v-if="employee.matched">Assessed as {{ employee.position }} · {{ employee.division_code }} · {{ employee.area }}</template>
          <template v-else>No profile: {{ employee.unmatched_message }}</template>
        </p>
        <p class="mt-0.5 text-xs text-ink-600 dark:text-stone-400">
          Portal plantilla: {{ employee.portal?.position }} · {{ employee.portal?.division_code }} · {{ employee.portal?.area }}
        </p>
        <p v-if="employee.assignment" class="mt-2 inline-flex items-start gap-1.5 rounded-md bg-sky-50 px-2 py-1 text-xs text-sky-900 dark:bg-sky-500/15 dark:text-sky-200">
          <Icon name="info" :size="14" class="mt-px" /> Reassigned by CETAR: {{ employee.assignment.reason }}
        </p>
      </div>
      <div v-if="!open" class="flex shrink-0 gap-2">
        <button type="button" class="btn-secondary h-9" @click="start">{{ employee.assignment ? 'Change' : 'Assess under another profile' }}</button>
        <button v-if="employee.assignment" type="button" class="btn-secondary h-9" :disabled="saving" @click="remove">Undo</button>
      </div>
    </div>

    <form v-if="open" class="mt-4 space-y-3" @submit.prevent="save">
      <div>
        <label class="field-label" for="ap-search">Profile to assess under</label>
        <input id="ap-search" v-model="query" type="search" class="field" placeholder="Search position, division or area" autocomplete="off" />
        <select v-model="profileId" class="field mt-2" size="6" required aria-label="Profile">
          <option v-for="o in filtered" :key="o.id" :value="String(o.id)">{{ o.label }}</option>
        </select>
        <p class="mt-1 text-xs text-ink-600 dark:text-stone-500">Not in the list? Add the profile in the Competency Map first.</p>
      </div>
      <div>
        <label class="field-label" for="ap-reason">Reason</label>
        <input id="ap-reason" v-model="reason" required class="field" placeholder="e.g. Works in NS Ward; plantilla item is under HOPSS" />
      </div>
      <p v-if="error" role="alert" class="text-sm text-red-700 dark:text-red-300">{{ error }}</p>
      <div class="flex gap-2">
        <button type="submit" class="btn-primary h-9" :disabled="saving || !profileId">{{ saving ? 'Saving…' : 'Save' }}</button>
        <button type="button" class="btn-secondary h-9" @click="open = false">Cancel</button>
      </div>
    </form>
  </div>
</template>

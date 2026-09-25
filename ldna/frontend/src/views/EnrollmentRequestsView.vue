<script setup>
import { ref, computed, watch } from 'vue'
import Icon from '../components/Icon.vue'
import AppModal from '../components/AppModal.vue'
import ErrorState from '../components/ErrorState.vue'
import EmptyState from '../components/EmptyState.vue'
import EmployeeAvatar from '../components/EmployeeAvatar.vue'
import TallyTable from '../components/TallyTable.vue'
import { apiGet, apiPost, useApiGet } from '../composables/useApi'
import { useReference } from '../composables/useReference'
import { useLayout } from '../composables/useLayout'
import { formatDate } from '../lib/format'

// CETAR validates enrolment requests: each shows the employee's tally (standard, actual, gap).
const { levelText } = useReference()
const { searchQuery } = useLayout()
const status = ref('pending')
const data = ref(null)
const loading = ref(false)
const error = ref('')

async function load() {
  loading.value = true; error.value = ''
  try { data.value = await apiGet(`enrollment-review.php?status=${status.value}`) } catch (e) { error.value = e.message } finally { loading.value = false }
}
watch(status, load, { immediate: true })

const rows = computed(() => {
  const q = searchQuery.value.trim().toLowerCase()
  return (data.value?.requests ?? []).filter((r) => !q ||
    [r.employee?.full_name, r.employee_id, r.training?.title, r.employee?.position, r.employee?.area].join(' ').toLowerCase().includes(q))
})
const tabs = computed(() => [
  { key: 'pending', label: 'Pending', count: data.value?.counts?.pending },
  { key: 'approved', label: 'Approved', count: data.value?.counts?.approved },
  { key: 'rejected', label: 'Rejected', count: data.value?.counts?.rejected },
  { key: 'all', label: 'All' },
])
const badge = {
  pending: 'bg-amber-50 text-amber-800 dark:bg-amber-500/15 dark:text-amber-300',
  approved: 'bg-brand-50 text-brand-700 dark:bg-brand-700/25 dark:text-brand-300',
  rejected: 'bg-red-50 text-red-700 dark:bg-red-500/15 dark:text-red-300',
}

const open = ref(null)
const trainingsReq = useApiGet('trainings.php')
const trainingComps = (r) => (trainingsReq.data.value?.trainings ?? []).find((t) => t.id === r.training?.id)?.competency_ids ?? []
const note = ref('')
const saving = ref(false)
const decideError = ref('')
function view(r) { open.value = r; note.value = r.review_note ?? ''; decideError.value = '' }
async function decide(decision) {
  saving.value = true; decideError.value = ''
  try {
    await apiPost('enrollment-review.php', { id: open.value.id, decision, note: note.value })
    open.value = null
    await load()
  } catch (e) { decideError.value = e.message } finally { saving.value = false }
}
</script>

<template>
  <section>
    <div class="mb-5">
      <h2 class="text-xl font-semibold text-ink-900 dark:text-white">Enrollment requests</h2>
      <p class="mt-0.5 max-w-2xl text-sm text-ink-600 dark:text-stone-400">
        Each request carries the employee's self-assessment tally (standard, actual, gap). Approve or reject it; a note is required to reject.
      </p>
    </div>

    <div class="mb-4 flex flex-wrap gap-1 rounded-lg border border-ink-900/10 bg-white p-1 dark:border-white/8 dark:bg-ink-900" role="tablist">
      <button v-for="t in tabs" :key="t.key" type="button" role="tab" :aria-selected="status === t.key"
              class="rounded-md px-4 py-2 text-sm font-medium"
              :class="status === t.key ? 'bg-ink-900 text-white dark:bg-brand-600' : 'text-ink-600 hover:bg-paper dark:text-stone-400 dark:hover:bg-white/6'"
              @click="status = t.key">
        {{ t.label }} <span v-if="t.count !== undefined" class="ml-1 tabular-nums opacity-60">{{ t.count }}</span>
      </button>
    </div>

    <ErrorState v-if="error" :message="error" @retry="load" />
    <div v-else-if="loading" class="space-y-2"><div v-for="n in 5" :key="n" class="h-14 animate-pulse rounded-lg bg-ink-900/6 dark:bg-white/6" /></div>
    <EmptyState v-else-if="!rows.length" icon="clipboard-check" title="No requests here" body="New requests appear when employees meet a training's standards and submit." />

    <div v-else class="card overflow-x-auto">
      <table class="w-full min-w-[46rem] text-left text-sm">
        <thead class="border-b border-ink-900/10 text-ink-600 dark:border-white/8 dark:text-stone-400">
          <tr>
            <th scope="col" class="px-4 py-3 font-medium">Employee</th>
            <th scope="col" class="px-4 py-3 font-medium">Training</th>
            <th scope="col" class="px-4 py-3 font-medium">Submitted</th>
            <th scope="col" class="px-4 py-3 font-medium">Status</th>
            <th scope="col" class="px-4 py-3"><span class="sr-only">Review</span></th>
          </tr>
        </thead>
        <tbody class="divide-y divide-ink-900/8 dark:divide-white/6">
          <tr v-for="r in rows" :key="r.id" class="cursor-pointer hover:bg-paper/70 dark:hover:bg-white/3" @click="view(r)">
            <td class="px-4 py-3">
              <div class="flex items-center gap-3">
                <EmployeeAvatar :employee="r.employee" :size="36" />
                <div class="min-w-0">
                  <p class="font-medium text-ink-900 dark:text-white">{{ r.employee?.full_name }}</p>
                  <p class="text-xs text-ink-600 dark:text-stone-400">{{ r.employee_id }} · {{ r.employee?.position }} · SG {{ r.salary_grade ?? '—' }}</p>
                </div>
              </div>
            </td>
            <td class="px-4 py-3">{{ r.training?.title }}<span class="block text-xs text-ink-600 dark:text-stone-400">{{ r.training ? levelText(r.training.level) : '' }}</span></td>
            <td class="px-4 py-3 text-ink-600 dark:text-stone-400">{{ formatDate(r.enrolled_at) }}</td>
            <td class="px-4 py-3"><span class="rounded-full px-2 py-0.5 text-xs font-medium capitalize" :class="badge[r.status]">{{ r.status }}</span></td>
            <td class="px-4 py-3 text-right"><button type="button" class="text-sm font-medium text-brand-600 hover:underline dark:text-brand-300" @click.stop="view(r)">Review</button></td>
          </tr>
        </tbody>
      </table>
    </div>

    <AppModal :open="!!open" :title="open?.training?.title ?? ''" size="lg" @close="open = null">
      <template #subtitle>
        <p class="text-sm text-ink-600 dark:text-stone-400">{{ open?.employee?.full_name }} · {{ open?.employee?.position }} · {{ open?.employee?.area }}</p>
      </template>
      <div v-if="open" class="space-y-4">
        <p class="text-sm text-ink-700 dark:text-stone-300">Self-assessment submitted {{ formatDate(open.enrolled_at) }}. Standards are those set for the employee's position in their area.</p>
        <TallyTable v-if="open.tally?.length" :rows="open.tally" :highlight="trainingComps(open)" />
        <p v-else class="rounded-lg bg-amber-50 px-3 py-2 text-sm text-amber-900 dark:bg-amber-500/10 dark:text-amber-200">
          No self-assessment: this position has no competency profile in this area yet (Positions → Add position).
        </p>
        <p v-if="open.reviewed_at" class="text-sm text-ink-600 dark:text-stone-400">
          {{ open.status === 'approved' ? 'Approved' : 'Rejected' }} {{ formatDate(open.reviewed_at) }}<template v-if="open.review_note">: {{ open.review_note }}</template>
        </p>
        <div>
          <label for="rv-note" class="field-label">Note to the employee <span class="font-normal text-ink-600 dark:text-stone-500">(required to reject)</span></label>
          <textarea id="rv-note" v-model="note" rows="2" class="field h-auto py-2" placeholder="e.g. Schedule is full this quarter; apply again in the next batch." />
        </div>
        <p v-if="decideError" role="alert" class="text-sm text-red-700 dark:text-red-300">{{ decideError }}</p>
      </div>
      <template #footer>
        <button type="button" class="btn-secondary" @click="open = null">Close</button>
        <button type="button" class="btn-secondary !text-red-700 dark:!text-red-300" :disabled="saving" @click="decide('reject')">Reject</button>
        <button type="button" class="btn-primary" :disabled="saving" @click="decide('approve')"><Icon name="check" :size="16" /> Approve</button>
      </template>
    </AppModal>
  </section>
</template>

<script setup>
import { ref, computed, watch } from 'vue'
import Icon from '../components/Icon.vue'
import AppModal from '../components/AppModal.vue'
import SelectField from '../components/SelectField.vue'
import ErrorState from '../components/ErrorState.vue'
import EmptyState from '../components/EmptyState.vue'
import GapTable from '../components/GapTable.vue'
import TrainingCard from '../components/TrainingCard.vue'
import EmployeeAvatar from '../components/EmployeeAvatar.vue'
import { useApiGet, apiGet } from '../composables/useApi'
import { useReference } from '../composables/useReference'
import { useLayout } from '../composables/useLayout'
import { gapClass, gapText, formatDate } from '../lib/format'

const { compById, divisions } = useReference()
const { data, loading, error, load } = useApiGet('gaps.php')
const { searchQuery } = useLayout()

const search = ref('')
const division = ref('')
const area = ref('')
const competency = ref('')
const status = ref('')
const rows = computed(() => data.value?.rows ?? [])

const divisionOptions = computed(() => divisions.value
  .filter((d) => rows.value.some((r) => r.employee.division_code === d.code))
  .map((d) => ({ value: d.code, label: `${d.code} — ${d.name}` })))
const areaOptions = computed(() => [...new Set(rows.value
  .filter((r) => !division.value || r.employee.division_code === division.value)
  .map((r) => r.employee.area))].filter(Boolean).sort().map((o) => ({ value: o, label: o })))
watch(division, () => { area.value = '' })
const competencyOptions = computed(() => {
  const ids = new Set(rows.value.flatMap((r) => r.gaps.filter((g) => g.gap > 0).map((g) => g.competency_id)))
  return [...ids].map((id) => ({ value: String(id), label: compById.value[id]?.name ?? String(id) })).sort((a, b) => a.label.localeCompare(b.label))
})
const statusOptions = [
  { value: 'gaps', label: 'With gaps' },
  { value: 'none', label: 'No gaps' },
  { value: 'pending', label: 'Not yet assessed' },
  { value: 'unmapped', label: 'Not in the map' },
]

const filtered = computed(() => {
  const terms = [search.value, searchQuery.value].map((t) => t.trim().toLowerCase()).filter(Boolean)
  const haystack = (r) => [r.employee.employee_id, r.employee.full_name, r.employee.first_name, r.employee.surname,
    r.employee.position, r.employee.division_code, r.employee.area, r.employee.plantilla_item_name].filter(Boolean).join(' ').toLowerCase()
  return rows.value
    .filter((r) =>
      (!division.value || r.employee.division_code === division.value) &&
      (!area.value || r.employee.area === area.value) &&
      (!competency.value || r.gaps.some((g) => String(g.competency_id) === competency.value && g.gap > 0)) &&
      (!status.value || (status.value === 'gaps' ? r.gap_count > 0
        : status.value === 'none' ? r.assessed && !r.gap_count
        : status.value === 'unmapped' ? !r.employee.matched
        : !r.assessed && r.employee.matched)) &&
      terms.every((t) => haystack(r).includes(t)))
    .sort((a, b) => b.max_gap - a.max_gap || b.gap_count - a.gap_count || a.employee.full_name.localeCompare(b.employee.full_name))
})
const topGaps = (r) => r.gaps.filter((g) => g.gap > 0).sort((a, b) => b.gap - a.gap).slice(0, 3)

// Detail
const detail = ref(null)
const detailLoading = ref(false)
const detailError = ref('')
async function open(r) {
  detail.value = { employee: r.employee }
  detailLoading.value = true; detailError.value = ''
  try {
    detail.value = await apiGet(`gaps.php?employee_id=${encodeURIComponent(r.employee.employee_id ?? r.employee.id)}`)
  } catch (e) {
    detailError.value = e.message
  } finally {
    detailLoading.value = false
  }
}
const detailGapIds = computed(() => (detail.value?.gaps ?? []).filter((g) => g.gap > 0).map((g) => g.competency_id))
</script>

<template>
  <section>
    <div class="mb-5">
      <h2 class="text-xl font-semibold text-ink-900 dark:text-white">Employee gaps</h2>
      <p class="mt-0.5 max-w-2xl text-sm text-ink-600 dark:text-stone-400">
        Gap is the required level minus the employee's self-rating. A positive gap means they are below standard and should be enrolled in a training.
      </p>
    </div>

    <label class="relative mb-3 block">
      <span class="sr-only">Search employees</span>
      <Icon name="search" :size="18" class="pointer-events-none absolute top-1/2 left-3 -translate-y-1/2 text-ink-600/70 dark:text-stone-500" />
      <input v-model="search" type="search" class="field h-11 pl-9" placeholder="Search by ID number, name, position, area or plantilla item" />
    </label>
    <div class="mb-4 grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
      <SelectField v-model="division" label="Division" placeholder="All divisions" :options="divisionOptions" />
      <SelectField v-model="area" label="Area" placeholder="All areas" :options="areaOptions" />
      <SelectField v-model="competency" label="Gap in competency" placeholder="Any competency" :options="competencyOptions" />
      <SelectField v-model="status" label="Status" placeholder="All employees" :options="statusOptions" />
    </div>

    <ErrorState v-if="error" :message="error" @retry="load" />
    <div v-else-if="loading" class="space-y-2" aria-busy="true">
      <div v-for="n in 6" :key="n" class="h-14 animate-pulse rounded-lg bg-ink-900/6 dark:bg-white/6" />
    </div>
    <EmptyState v-else-if="!filtered.length" icon="users" title="No employees match" body="Try different filters or search terms." />

    <div v-else class="card overflow-x-auto">
      <table class="w-full min-w-[52rem] text-left text-sm">
        <thead class="border-b border-ink-900/10 text-ink-600 dark:border-white/8 dark:text-stone-400">
          <tr>
            <th scope="col" class="px-4 py-3 font-medium">Employee</th>
            <th scope="col" class="px-4 py-3 font-medium">Position · Area</th>
            <th scope="col" class="px-4 py-3 text-center font-medium">Gaps</th>
            <th scope="col" class="px-4 py-3 font-medium">Largest gaps</th>
            <th scope="col" class="px-4 py-3"><span class="sr-only">Open</span></th>
          </tr>
        </thead>
        <tbody class="divide-y divide-ink-900/8 dark:divide-white/6">
          <tr v-for="r in filtered" :key="r.employee.id" class="cursor-pointer hover:bg-paper/70 dark:hover:bg-white/3" @click="open(r)">
            <td class="px-4 py-3">
              <div class="flex items-center gap-3">
              <EmployeeAvatar :employee="r.employee" :size="40" />
              <div class="min-w-0">
              <p class="font-medium text-ink-900 dark:text-white">{{ r.employee.full_name }}
              </p>
              <p class="text-xs font-medium tabular-nums text-ink-800 dark:text-stone-300">{{ r.employee.employee_id }}</p>
              <p class="text-xs text-ink-600 dark:text-stone-500">
                <template v-if="r.employee.is_plantilla">SG {{ r.employee.salary_grade }} · {{ r.employee.plantilla_item_name }}</template>
                <template v-else>{{ r.employee.appointment || r.employee.employment_status }}, no plantilla item</template>
              </p>
              </div>
              </div>
            </td>
            <td class="px-4 py-3 text-ink-800 dark:text-stone-300">
              {{ r.employee.position }}<br /><span class="text-ink-600 dark:text-stone-400">{{ r.employee.division_code }} · {{ r.employee.area }}</span>
            </td>
            <td class="px-4 py-3 text-center">
              <span v-if="!r.employee.matched" class="rounded-full bg-amber-50 px-2.5 py-0.5 text-xs font-medium text-amber-800 dark:bg-amber-500/15 dark:text-amber-300" :title="r.employee.unmatched_message">Not in map</span>
              <span v-else-if="!r.assessed" class="rounded-full bg-ink-900/5 px-2.5 py-0.5 text-xs font-medium text-ink-600 dark:bg-white/6 dark:text-stone-400">Not assessed</span>
              <span v-else class="font-semibold tabular-nums">{{ r.gap_count }}</span>
            </td>
            <td class="px-4 py-3">
              <div class="flex flex-wrap gap-1.5">
                <span v-for="g in topGaps(r)" :key="g.competency_id" class="inline-flex items-center gap-1.5 rounded-md bg-ink-900/5 py-0.5 pr-1 pl-2 text-xs dark:bg-white/6">
                  {{ compById[g.competency_id]?.name }}
                  <span class="rounded-full px-1.5 font-semibold ring-1 ring-inset" :class="gapClass(g.gap)">{{ gapText(g.gap) }}</span>
                </span>
                <span v-if="r.assessed && !r.gap_count" class="text-xs text-brand-700 dark:text-brand-300">Met all standards</span>
              </div>
            </td>
            <td class="px-4 py-3 text-right"><Icon name="chevron-right" :size="18" class="inline text-ink-600 dark:text-stone-500" /></td>
          </tr>
        </tbody>
      </table>
    </div>

    <AppModal :open="!!detail" :title="detail?.employee?.full_name ?? ''" size="xl" @close="detail = null">
      <template #subtitle>
        <p class="text-sm text-ink-600 dark:text-stone-400">
          {{ detail?.employee?.position }} · {{ detail?.employee?.office }}
          <template v-if="detail?.employee?.is_plantilla"> · SG {{ detail.employee.salary_grade }} · {{ detail.employee.plantilla_item_name }}</template>
          <template v-else-if="detail?.employee"> · {{ detail.employee.appointment }}, no plantilla item</template>
          <template v-if="detail?.submitted_at"> · Assessed {{ formatDate(detail.submitted_at) }}</template>
        </p>
      </template>
      <div v-if="detailLoading" class="space-y-2" aria-busy="true">
        <div v-for="n in 5" :key="n" class="h-10 animate-pulse rounded-lg bg-ink-900/6 dark:bg-white/6" />
      </div>
      <ErrorState v-else-if="detailError" :message="detailError" @retry="open({ employee: detail.employee })" />
      <EmptyState v-else-if="detail && !detail.employee.matched" icon="map" title="Not in the competency map"
        :body="`${detail.employee.unmatched_message} Add a profile in the Competency Map with this exact position, division and area.`" />
      <EmptyState v-else-if="detail && !detail.assessed" icon="clipboard-check" title="Not assessed yet" body="This employee has not submitted a self-assessment for this cycle." />
      <template v-else-if="detail">
        <div class="mb-4 flex items-center gap-4">
          <EmployeeAvatar :employee="detail.employee" :size="64" />
          <div class="text-sm">
            <p class="font-semibold tabular-nums">{{ detail.employee.employee_id }}</p>
            <p class="text-ink-600 dark:text-stone-400">{{ detail.employee.appointment }}<template v-if="detail.employee.is_plantilla"> · SG {{ detail.employee.salary_grade }}</template></p>
          </div>
        </div>
        <p v-if="detail.assessed_as && (detail.assessed_as.position !== detail.employee.position || detail.assessed_as.office !== detail.employee.office)"
           class="mb-4 flex items-start gap-2 rounded-lg bg-amber-50 px-3 py-2 text-sm text-amber-900 dark:bg-amber-500/10 dark:text-amber-200">
          <Icon name="warning" :size="18" class="mt-0.5 shrink-0" />
          Assessed as {{ detail.assessed_as.position }} in {{ detail.assessed_as.office }}. The employee has since moved, so a new assessment is due.
        </p>
        <GapTable :gaps="detail.gaps" />
        <h3 class="mt-6 mb-3 font-semibold">Recommended trainings</h3>
        <p v-if="!detail.recommendations.length" class="text-sm text-ink-600 dark:text-stone-400">No trainings in the library match this employee's gaps yet.</p>
        <div v-else class="grid gap-3 md:grid-cols-2">
          <TrainingCard v-for="t in detail.recommendations" :key="t.id" :training="t" :highlight="detailGapIds" :reason="t.reason" />
        </div>
      </template>
    </AppModal>
  </section>
</template>

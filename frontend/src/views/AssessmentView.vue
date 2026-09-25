<script setup>
import { ref, computed, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import Icon from '../components/Icon.vue'
import ErrorState from '../components/ErrorState.vue'
import EmptyState from '../components/EmptyState.vue'
import TrainingCard from '../components/TrainingCard.vue'
import EmployeeLookup from '../components/EmployeeLookup.vue'
import EmployeeAvatar from '../components/EmployeeAvatar.vue'
import LevelLegend from '../components/LevelLegend.vue'
import AppModal from '../components/AppModal.vue'
import CompetencyRating from '../components/CompetencyRating.vue'
import TallyTable from '../components/TallyTable.vue'
import { useReference } from '../composables/useReference'
import { apiGet, apiPost, apiDelete } from '../composables/useApi'
import { TRAINING_CATEGORIES, levelLabel, formatDate } from '../lib/format'

const route = useRoute()
const router = useRouter()
const employeeId = ref(route.query.employee ?? '')
watch(employeeId, (id) => router.replace({ query: id ? { employee: id } : {} }))

const { levelText } = useReference()
const state = ref(null)
const started = ref(route.query.start === '1')   // the trainings list opens after "Start assessment"
watch(employeeId, () => { started.value = false })
function start() {
  started.value = true
  router.replace({ query: { ...route.query, start: '1' } })
}
const loading = ref(false)
const error = ref('')
const tab = ref('general')
const onlyEligible = ref(true)
const busyId = ref(null)
const notice = ref('')
const actionError = ref({})

async function load() {
  if (!employeeId.value) { state.value = null; return }
  loading.value = true; error.value = ''
  try {
    state.value = await apiGet(`enrollments.php?employee_id=${encodeURIComponent(employeeId.value)}`)
  } catch (e) { error.value = e.message } finally { loading.value = false }
}
watch(employeeId, load, { immediate: true })

const catalog = computed(() => state.value?.catalog ?? [])
const counts = computed(() => Object.fromEntries(TRAINING_CATEGORIES.map((c) => [c.key,
  catalog.value.filter((t) => t.category === c.key && (!onlyEligible.value || t.eligible || t.enrolled)).length])))
const shown = computed(() => catalog.value
  .filter((t) => t.category === tab.value && (!onlyEligible.value || t.eligible || t.enrolled))
  .sort((a, b) => Number(b.eligible) - Number(a.eligible) || a.level - b.level || a.title.localeCompare(b.title)))
const enrolled = computed(() => catalog.value.filter((t) => t.enrolled || t.status === 'rejected'))
const myRequest = (t) => (state.value?.enrollments ?? []).filter((r) => r.training_id === t.id).at(-1)
const statusBadge = {
  pending: 'bg-amber-50 text-amber-800 dark:bg-amber-500/15 dark:text-amber-300',
  approved: 'bg-brand-50 text-brand-700 dark:bg-brand-700/25 dark:text-brand-300',
  rejected: 'bg-red-50 text-red-700 dark:bg-red-500/15 dark:text-red-300',
}

// Enrolling: the employee rates themselves (actual level) on the competencies of their position in their
// area (set by CETAR), then submits. The tally (standard, actual, gap) goes to CETAR, who validates it.
const rating = ref(null)            // the training being requested
const ratings = ref({})
const prefilled = ref({})
const ratingError = ref('')
const showMissing = ref(false)
const tally = ref(null)             // after submitting
const profileIds = computed(() => Object.keys(state.value?.profile?.standards ?? {}).map(Number))
function enroll(t) {
  actionError.value = {}
  rating.value = t
  tally.value = null
  const earlier = state.value?.latest_ratings ?? {}
  prefilled.value = Object.fromEntries(profileIds.value.filter((id) => earlier[id]).map((id) => [id, earlier[id]]))
  ratings.value = { ...prefilled.value }
  ratingError.value = ''
  showMissing.value = false
}
const unrated = computed(() => profileIds.value.filter((id) => !ratings.value[id]))
async function confirmEnroll() {
  const t = rating.value
  if (state.value?.profile && unrated.value.length) {
    showMissing.value = true
    ratingError.value = `Rate yourself on all ${profileIds.value.length} competencies first (${unrated.value.length} left).`
    document.getElementById(`rate-${unrated.value[0]}`)?.scrollIntoView({ behavior: 'smooth', block: 'center' })
    return
  }
  busyId.value = t.id; ratingError.value = ''
  try {
    const res = await apiPost('enrollments.php', { employee_id: employeeId.value, training_id: t.id, ratings: ratings.value })
    tally.value = res.tally
    t.enrolled = true
    t.status = 'pending'
    state.value.latest_ratings = { ...(state.value.latest_ratings ?? {}), ...ratings.value }
  } catch (e) {
    ratingError.value = e.message
  } finally { busyId.value = null }
}
async function cancel(t) {
  if (!confirm(`Cancel your enrolment in "${t.title}"?`)) return
  busyId.value = t.id
  try {
    await apiDelete(`enrollments.php?employee_id=${encodeURIComponent(employeeId.value)}&training_id=${t.id}`)
    t.enrolled = false
    t.status = null
  } catch (e) { actionError.value = { [t.id]: e.message } } finally { busyId.value = null }
}
</script>

<template>
  <section class="mx-auto max-w-6xl">
    <EmployeeLookup v-if="!employeeId" title="Assessment" subtitle="Enter your employee ID number, then choose your name from the list."
                    @select="(id) => (employeeId = id)" />

    <ErrorState v-else-if="error" :message="error" @retry="load" />
    <div v-else-if="loading || !state" class="space-y-3" aria-busy="true">
      <div class="h-24 animate-pulse rounded-xl bg-ink-900/6 dark:bg-white/6" />
      <div class="grid gap-3 md:grid-cols-3"><div v-for="n in 6" :key="n" class="h-40 animate-pulse rounded-lg bg-ink-900/6 dark:bg-white/6" /></div>
    </div>

    <template v-else>
      <div class="card mb-6 overflow-hidden">
        <div class="flex flex-wrap items-center gap-5 p-5 sm:p-6">
          <EmployeeAvatar :employee="state.employee" :size="96" />
          <div class="min-w-0 flex-1">
            <p class="text-xl font-semibold text-ink-900 dark:text-white">{{ state.employee.full_name }}</p>
            <p class="text-sm tabular-nums text-ink-600 dark:text-stone-400">ID {{ state.employee.employee_id }} · {{ state.employee.appointment || state.employee.employment_status }}</p>
          </div>
          <button type="button" class="text-sm font-medium text-brand-600 hover:underline dark:text-brand-300" @click="employeeId = ''">Not you? Change</button>
        </div>
        <dl class="grid gap-px border-t border-ink-900/8 bg-ink-900/8 text-sm sm:grid-cols-2 lg:grid-cols-3 dark:border-white/8 dark:bg-white/6">
          <div class="bg-white p-4 dark:bg-ink-900"><dt class="text-xs text-ink-600 dark:text-stone-400">Position</dt><dd class="mt-0.5 font-medium">{{ state.employee.portal?.position || state.employee.position }}</dd></div>
          <div class="bg-white p-4 dark:bg-ink-900"><dt class="text-xs text-ink-600 dark:text-stone-400">Salary grade</dt><dd class="mt-0.5 font-medium">{{ state.employee.salary_grade ?? '—' }}</dd></div>
          <div class="bg-white p-4 dark:bg-ink-900">
            <dt class="text-xs text-ink-600 dark:text-stone-400">Assessment level</dt>
            <dd class="mt-0.5 font-medium">{{ state.employee.assessment_level ? levelText(state.employee.assessment_level) : 'Unknown (no salary grade)' }}</dd>
          </div>
          <div class="bg-white p-4 dark:bg-ink-900"><dt class="text-xs text-ink-600 dark:text-stone-400">Division</dt><dd class="mt-0.5 font-medium">{{ state.employee.division_name || state.employee.division_code || '—' }}</dd></div>
          <div class="bg-white p-4 dark:bg-ink-900"><dt class="text-xs text-ink-600 dark:text-stone-400">Area</dt><dd class="mt-0.5 font-medium">{{ state.employee.portal?.area || state.employee.area || '—' }}</dd></div>
          <div class="bg-white p-4 dark:bg-ink-900"><dt class="text-xs text-ink-600 dark:text-stone-400">Plantilla item</dt><dd class="mt-0.5 font-medium break-all">{{ state.employee.plantilla_item_name || 'None' }}</dd></div>
        </dl>
        <div v-if="!started" class="flex flex-wrap items-center justify-between gap-3 border-t border-ink-900/8 p-5 dark:border-white/8">
          <p class="text-sm text-ink-600 dark:text-stone-400">The assessment lists the trainings open to you: your level (from your salary grade), your position and your area.</p>
          <button type="button" class="btn-primary" @click="start">
            <Icon name="clipboard-check" :size="18" /> Start assessment
          </button>
        </div>
      </div>

      <p v-if="!state.employee.salary_grade" class="mb-5 flex items-start gap-2 rounded-lg bg-amber-50 px-4 py-3 text-sm text-amber-900 dark:bg-amber-500/10 dark:text-amber-200">
        <Icon name="warning" :size="18" class="mt-0.5 shrink-0" /> The employee portal has no salary grade for you, so only Level 1 trainings are open to you.
      </p>
      <p v-else-if="!state.employee.office_id" class="mb-5 flex items-start gap-2 rounded-lg bg-sky-50 px-4 py-3 text-sm text-sky-900 dark:bg-sky-500/10 dark:text-sky-200">
        <Icon name="info" :size="18" class="mt-0.5 shrink-0" /> Your area isn't in the LDNA area list yet, so trainings limited to specific areas won't be open to you.
      </p>

      <template v-if="started">
      <LevelLegend class="mb-4" :highlight="state.employee.assessment_level" />
      <div v-if="enrolled.length" class="card mb-6 p-5">
        <h2 class="font-semibold text-ink-900 dark:text-white">My training requests · {{ state.cycle_year }}</h2>
        <ul class="mt-3 divide-y divide-ink-900/8 dark:divide-white/6">
          <li v-for="t in enrolled" :key="t.id" class="flex flex-wrap items-center gap-3 py-2.5 text-sm">
            <span class="rounded-full px-2 py-0.5 text-xs font-medium capitalize" :class="statusBadge[t.status ?? 'approved']">{{ t.status === 'pending' ? 'Pending approval' : t.status ?? 'approved' }}</span>
            <span class="min-w-0 flex-1 font-medium text-ink-900 dark:text-white">
              {{ t.title }}
              <span v-if="myRequest(t)?.review_note" class="block text-xs font-normal text-ink-600 dark:text-stone-400">CETAR: {{ myRequest(t).review_note }}</span>
            </span>
            <span class="text-ink-600 dark:text-stone-400">Level {{ t.level }} · {{ TRAINING_CATEGORIES.find((c) => c.key === t.category)?.label }}</span>
            <button v-if="t.status !== 'rejected'" type="button" class="font-medium text-red-700 hover:underline dark:text-red-300" :disabled="busyId === t.id" @click="cancel(t)">Cancel</button>
          </li>
        </ul>
      </div>

      <p v-if="notice" role="status" class="mb-4 flex items-center gap-2 rounded-lg bg-brand-50 px-4 py-2.5 text-sm text-brand-700 dark:bg-brand-700/25 dark:text-brand-200">
        <Icon name="check" :size="18" /> {{ notice }}
      </p>

      <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
        <div class="flex flex-wrap gap-1 rounded-lg border border-ink-900/10 bg-white p-1 dark:border-white/8 dark:bg-ink-900" role="tablist">
          <button v-for="c in TRAINING_CATEGORIES" :key="c.key" type="button" role="tab" :aria-selected="tab === c.key"
                  class="rounded-md px-4 py-2 text-sm font-medium"
                  :class="tab === c.key ? 'bg-ink-900 text-white dark:bg-brand-600' : 'text-ink-600 hover:bg-paper dark:text-stone-400 dark:hover:bg-white/6'"
                  @click="tab = c.key">
            {{ c.label }} <span class="ml-1 tabular-nums opacity-60">{{ counts[c.key] }}</span>
          </button>
        </div>
        <label class="flex items-center gap-2 text-sm text-ink-700 dark:text-stone-300">
          <input v-model="onlyEligible" type="checkbox" class="size-4 accent-brand-600" /> Only trainings I can enroll in
        </label>
      </div>

      <EmptyState v-if="!shown.length" icon="library" title="Nothing to show here"
                  :body="onlyEligible ? 'No trainings in this category match your level, position and area. Untick the filter to see why.' : 'No trainings in this category yet.'" />
      <div v-else class="grid gap-3 md:grid-cols-2 xl:grid-cols-3">
        <TrainingCard v-for="t in shown" :key="t.id" :training="{ ...t, eligibility_note: t.reasons?.join(' ') }">
          <p v-if="actionError[t.id]" role="alert" class="mb-2 text-xs text-red-700 dark:text-red-300">{{ actionError[t.id] }}</p>
          <p v-if="t.status === 'rejected'" class="mb-2 text-xs text-red-700 dark:text-red-300">CETAR rejected your earlier request. You can apply again.</p>
          <button v-if="t.enrolled && t.status === 'pending'" type="button" class="btn-secondary h-9 w-full" disabled>Pending CETAR approval</button>
          <button v-else-if="t.enrolled" type="button" class="btn-secondary h-9 w-full" disabled><Icon name="check" :size="16" /> Enrolled</button>
          <button v-else type="button" class="btn-primary h-9 w-full" :disabled="!t.eligible" @click="enroll(t)">{{ t.eligible ? 'Enroll' : 'Not eligible' }}</button>
        </TrainingCard>
      </div>
      </template>
    </template>

    <AppModal :open="!!rating" :title="rating ? `Enroll: ${rating.title}` : ''" size="lg" @close="rating = null">
      <template #subtitle>
        <p v-if="!tally" class="text-sm text-ink-600 dark:text-stone-400">
          Self-assessment for <strong>{{ state.employee.position }}</strong> · {{ state.employee.area }}. Read Level 1 to Level 4 for each
          competency and choose the level that describes what you actually do now. The standard levels are shown after you submit.
        </p>
        <p v-else class="text-sm text-ink-600 dark:text-stone-400">Your self-assessment tally. CETAR will review it and approve or reject your request.</p>
      </template>

      <p v-if="rating && !state.profile && !tally" class="flex items-start gap-2 rounded-lg bg-sky-50 px-4 py-3 text-sm text-sky-900 dark:bg-sky-500/10 dark:text-sky-200">
        <Icon name="info" :size="18" class="mt-0.5 shrink-0" />
        CETAR hasn't set the competencies for {{ state.employee.position }} in {{ state.employee.area }} yet, so there's no self-assessment to fill in.
        You can still submit this enrollment; CETAR will validate it.
      </p>
      <CompetencyRating v-else-if="rating && !tally" v-model="ratings" :competency-ids="profileIds" :prefilled="prefilled" :show-missing="showMissing" />
      <div v-else-if="tally" class="space-y-4">
        <p class="flex items-start gap-2 rounded-lg bg-brand-50 px-4 py-3 text-sm text-brand-800 dark:bg-brand-700/25 dark:text-brand-200">
          <Icon name="check" :size="18" :stroke-width="2" class="mt-0.5 shrink-0" /> Request submitted. CETAR will validate it.
        </p>
        <TallyTable v-if="tally.length" :rows="tally" :highlight="rating.competency_ids ?? []" />
      </div>

      <template #footer>
        <template v-if="!tally">
          <p v-if="ratingError" role="alert" class="mr-auto text-sm text-red-700 dark:text-red-300">{{ ratingError }}</p>
          <span v-else-if="state.profile" class="mr-auto text-sm text-ink-600 dark:text-stone-400">{{ profileIds.length - unrated.length }} of {{ profileIds.length }} rated</span>
          <button type="button" class="btn-secondary" @click="rating = null">Cancel</button>
          <button type="button" class="btn-primary" :disabled="busyId === rating?.id" @click="confirmEnroll">{{ busyId === rating?.id ? 'Submitting…' : 'Submit enrollment' }}</button>
        </template>
        <button v-else type="button" class="btn-primary" @click="rating = null">Close</button>
      </template>
    </AppModal>
  </section>
</template>

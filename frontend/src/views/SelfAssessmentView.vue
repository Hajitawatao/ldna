<script setup>
import { ref, computed, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import Icon from '../components/Icon.vue'
import ErrorState from '../components/ErrorState.vue'
import EmptyState from '../components/EmptyState.vue'
import GapTable from '../components/GapTable.vue'
import TrainingCard from '../components/TrainingCard.vue'
import EmployeeLookup from '../components/EmployeeLookup.vue'
import EmployeeAvatar from '../components/EmployeeAvatar.vue'
import { apiGet, apiPost } from '../composables/useApi'
import { useReference } from '../composables/useReference'
import { CATEGORIES, categoryClass, LEVELS, GENERIC_LEVELS, formatDate } from '../lib/format'

const route = useRoute()
const router = useRouter()
const { compById } = useReference()

// The employee identifies themselves by ID number. Once the portal is connected,
// this comes from the portal login instead.
const employeeId = ref(route.query.employee ?? '')
watch(employeeId, (id) => router.replace({ query: id ? { employee: id } : {} }))
watch(() => route.query.employee, (id) => { employeeId.value = id ?? '' })
function change() {
  employeeId.value = ''
  state.value = null
}

const state = ref(null)
const loading = ref(false)
const error = ref(null)
const ratings = ref({})
const editing = ref(false)
const submitting = ref(false)
const submitError = ref('')

async function load() {
  if (!employeeId.value) { state.value = null; return }
  loading.value = true; error.value = null; submitError.value = ''
  try {
    state.value = await apiGet(`self-assessment.php?employee_id=${encodeURIComponent(employeeId.value)}`)
    ratings.value = { ...(state.value.ratings ?? {}) }
    editing.value = !state.value.results
  } catch (e) {
    error.value = e.message
  } finally {
    loading.value = false
  }
}
watch(employeeId, load, { immediate: true })

const groups = computed(() => {
  const ids = state.value?.competency_ids ?? []
  return CATEGORIES.map((cat) => ({
    ...cat,
    items: ids.map((id) => compById.value[id]).filter((c) => c?.category === cat.key),
  })).filter((g) => g.items.length)
})
const total = computed(() => state.value?.competency_ids?.length ?? 0)
const rated = computed(() => (state.value?.competency_ids ?? []).filter((id) => ratings.value[id]).length)
const firstUnrated = () => state.value.competency_ids.find((id) => !ratings.value[id])

async function submit() {
  if (rated.value < total.value) {
    document.getElementById(`comp-${firstUnrated()}`)?.scrollIntoView({ behavior: 'smooth', block: 'center' })
    submitError.value = `Rate all ${total.value} competencies before submitting. ${total.value - rated.value} left.`
    return
  }
  submitting.value = true; submitError.value = ''
  try {
    const res = await apiPost('self-assessment.php', { employee_id: employeeId.value, ratings: ratings.value })
    state.value.results = res.results
    editing.value = false
    window.scrollTo({ top: 0, behavior: 'smooth' })
  } catch (e) {
    submitError.value = e.message
  } finally {
    submitting.value = false
  }
}

const summary = computed(() => {
  const g = state.value?.results?.gaps ?? []
  return { below: g.filter((x) => x.gap > 0).length, met: g.filter((x) => x.gap <= 0).length, total: g.length }
})
const gapIds = computed(() => (state.value?.results?.gaps ?? []).filter((g) => g.gap > 0).map((g) => g.competency_id))
</script>

<template>
  <section class="mx-auto max-w-4xl">
    <EmployeeLookup v-if="!employeeId" @select="(id) => (employeeId = id)" />

    <ErrorState v-else-if="error" :message="error" @retry="load" />
    <div v-else-if="loading || !state" class="space-y-3" aria-busy="true">
      <div class="h-24 animate-pulse rounded-xl bg-ink-900/6 dark:bg-white/6" />
      <div v-for="n in 3" :key="n" class="h-40 animate-pulse rounded-xl bg-ink-900/6 dark:bg-white/6" />
    </div>

    <template v-else>
      <!-- Employee -->
      <div class="card mb-6 flex flex-wrap items-center gap-4 p-5">
        <EmployeeAvatar :employee="state.employee" :size="56" />
        <div class="min-w-0 flex-1">
          <p class="font-semibold text-ink-900 dark:text-white">{{ state.employee.full_name }} <span class="ml-1 text-sm font-normal tabular-nums text-ink-600 dark:text-stone-400">{{ state.employee.employee_id }}</span></p>
          <p class="text-sm text-ink-600 dark:text-stone-400">{{ state.employee.position }} · {{ state.employee.division_code }} · {{ state.employee.area }}</p>
          <p v-if="state.employee.assignment" class="mt-1 text-xs text-sky-800 dark:text-sky-300">
            Assessed under this profile by CETAR. Your plantilla: {{ state.employee.portal?.position }} · {{ state.employee.portal?.division_code }} · {{ state.employee.portal?.area }}
          </p>
          <p class="mt-0.5 text-xs text-ink-600 dark:text-stone-500">
            <template v-if="state.employee.is_plantilla">{{ state.employee.plantilla_item_name }} · SG {{ state.employee.salary_grade }} · {{ state.employee.appointment }}</template>
            <template v-else>{{ state.employee.appointment || state.employee.employment_status }} · no plantilla item</template>
          </p>
        </div>
        <div class="flex flex-col items-end gap-1 text-right text-sm">
          <span v-if="state.results && !editing" class="text-ink-600 dark:text-stone-400">Submitted {{ formatDate(state.results.submitted_at) }}</span>
          <button type="button" class="font-medium text-brand-600 hover:underline dark:text-brand-300" @click="change">Not you? Change</button>
        </div>
      </div>

      <EmptyState v-if="!state.profile_found" icon="map" title="No competency profile for your position and area" :body="state.message" />

      <!-- Results -->
      <template v-else-if="state.results && !editing">
        <div class="mb-4 flex flex-wrap items-end justify-between gap-3">
          <div>
            <h2 class="text-xl font-semibold text-ink-900 dark:text-white">Your results</h2>
            <p class="mt-0.5 text-sm text-ink-600 dark:text-stone-400">Your self-rating compared with the standard for your position and area.</p>
          </div>
          <button type="button" class="btn-secondary" @click="editing = true"><Icon name="refresh" :size="16" /> Update my ratings</button>
        </div>
        <div class="mb-5 grid gap-3 sm:grid-cols-3">
          <div class="card p-4"><p class="text-sm text-ink-600 dark:text-stone-400">Competencies assessed</p><p class="mt-1 text-2xl font-semibold tabular-nums">{{ summary.total }}</p></div>
          <div class="card p-4"><p class="text-sm text-ink-600 dark:text-stone-400">Below standard</p><p class="mt-1 text-2xl font-semibold tabular-nums text-amber-700 dark:text-amber-300">{{ summary.below }}</p></div>
          <div class="card p-4"><p class="text-sm text-ink-600 dark:text-stone-400">Met or above</p><p class="mt-1 text-2xl font-semibold tabular-nums text-brand-700 dark:text-brand-300">{{ summary.met }}</p></div>
        </div>
        <GapTable :gaps="state.results.gaps" />

        <h3 class="mt-8 mb-1 text-lg font-semibold text-ink-900 dark:text-white">Suggested trainings</h3>
        <p class="mb-4 text-sm text-ink-600 dark:text-stone-400">CETAR will confirm which of these you will attend. Highlighted competencies are the ones you are below standard on.</p>
        <EmptyState v-if="!state.results.recommendations.length" icon="check" title="No trainings needed" body="You met the standard on every competency. You may still enrol in trainings if your head of office approves." />
        <div v-else class="grid gap-3 md:grid-cols-2">
          <TrainingCard v-for="t in state.results.recommendations" :key="t.id" :training="t" :highlight="gapIds" :reason="t.reason" />
        </div>
      </template>

      <!-- Rating form -->
      <template v-else>
        <div class="mb-5">
          <h2 class="text-xl font-semibold text-ink-900 dark:text-white">Self-assessment</h2>
          <p class="mt-1 text-sm text-ink-700 dark:text-stone-300">
            For each competency, read Level 1 to Level 4 in order. Choose the level whose description matches what you <em>actually do</em> at work, not what your position requires.
          </p>
        </div>

        <div class="space-y-8">
          <div v-for="g in groups" :key="g.key">
            <h3 class="mb-3 flex items-center gap-2">
              <span class="rounded-full px-2.5 py-0.5 text-xs font-medium" :class="categoryClass[g.key]">{{ g.label }}</span>
              <span class="text-sm text-ink-600 dark:text-stone-400">{{ g.items.length }} competenc{{ g.items.length === 1 ? 'y' : 'ies' }}</span>
            </h3>
            <div class="space-y-4">
              <fieldset v-for="c in g.items" :id="`comp-${c.id}`" :key="c.id" class="card scroll-mt-24 p-4 sm:p-5"
                        :class="submitError && !ratings[c.id] && 'ring-2 ring-amber-400'">
                <legend class="sr-only">{{ c.name }}</legend>
                <div class="flex items-start gap-3">
                  <div class="min-w-0 flex-1">
                    <p class="font-semibold text-ink-900 dark:text-white">{{ c.name }}</p>
                    <p v-if="c.definition" class="mt-0.5 text-sm text-ink-600 dark:text-stone-400">{{ c.definition }}</p>
                  </div>
                  <Icon v-if="ratings[c.id]" name="check" :size="20" :stroke-width="2" class="text-brand-600 dark:text-brand-300" />
                </div>
                <div class="mt-4 grid gap-2 sm:grid-cols-2 lg:grid-cols-4">
                  <label v-for="l in LEVELS" :key="l.value"
                         class="flex cursor-pointer flex-col rounded-lg border p-3 text-sm transition-colors has-[:focus-visible]:ring-2 has-[:focus-visible]:ring-brand-500"
                         :class="ratings[c.id] === l.value ? 'border-brand-500 bg-brand-50 dark:bg-brand-700/20' : 'border-ink-900/12 hover:border-ink-900/25 dark:border-white/10 dark:hover:border-white/25'">
                    <input v-model="ratings[c.id]" type="radio" :name="`c${c.id}`" :value="l.value" class="sr-only" />
                    <span class="text-xs font-semibold tracking-wide uppercase" :class="ratings[c.id] === l.value ? 'text-brand-700 dark:text-brand-300' : 'text-ink-600 dark:text-stone-400'">
                      {{ l.value }} · {{ l.label }}
                    </span>
                    <span class="mt-1 text-ink-800 dark:text-stone-200">{{ c.levels?.[l.value]?.description || GENERIC_LEVELS[l.value] }}</span>
                    <ul v-if="c.levels?.[l.value]?.indicators?.length" class="mt-2 list-disc space-y-0.5 pl-4 text-xs text-ink-600 marker:text-ink-600/40 dark:text-stone-400">
                      <li v-for="(ind, i) in c.levels[l.value].indicators" :key="i">{{ ind }}</li>
                    </ul>
                  </label>
                </div>
              </fieldset>
            </div>
          </div>
        </div>

        <!-- Sticky submit bar -->
        <div class="sticky bottom-3 z-10 mt-8 rounded-xl border border-ink-900/10 bg-white/95 px-4 py-3 shadow-lg backdrop-blur dark:border-white/10 dark:bg-ink-900/95">
          <div class="flex flex-wrap items-center gap-3">
            <div class="min-w-40 flex-1">
              <p class="text-sm font-medium"><span class="tabular-nums">{{ rated }}</span> of <span class="tabular-nums">{{ total }}</span> rated</p>
              <div class="mt-1.5 h-1.5 rounded-full bg-ink-900/8 dark:bg-white/8"><div class="h-1.5 rounded-full bg-brand-500 transition-[width]" :style="{ width: `${total ? (rated / total) * 100 : 0}%` }" /></div>
            </div>
            <button v-if="state.results" type="button" class="btn-secondary" @click="editing = false">Cancel</button>
            <button type="button" class="btn-primary" :disabled="submitting" @click="submit">{{ submitting ? 'Submitting…' : 'Submit assessment' }}</button>
          </div>
          <p v-if="submitError" role="alert" class="mt-2 text-sm text-red-700 dark:text-red-300">{{ submitError }}</p>
        </div>
      </template>
    </template>
  </section>
</template>

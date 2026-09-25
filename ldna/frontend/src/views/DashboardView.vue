<script setup>
import { computed } from 'vue'
import Icon from '../components/Icon.vue'
import ErrorState from '../components/ErrorState.vue'
import { useApiGet } from '../composables/useApi'
import { useReference } from '../composables/useReference'

const { data, loading, error, load } = useApiGet('stats.php')
const { levels } = useReference()

const cards = computed(() => {
  const t = data.value?.totals
  if (!t) return []
  return [
    { label: 'Employees in the portal', value: t.employees, sub: 'fetched from the employee portal', icon: 'users' },
    { label: 'Employees enrolled', value: t.enrolled_employees, sub: `${t.employees ? Math.round((t.enrolled_employees / t.employees) * 100) : 0}% of employees`, icon: 'clipboard-check' },
    { label: 'Pending requests', value: t.pending ?? 0, sub: `${t.approved ?? 0} approved this cycle`, icon: 'academic-cap' },
    { label: 'Trainings in library', value: t.trainings, sub: 'across all levels', icon: 'library' },
  ]
})
const maxTop = computed(() => Math.max(1, ...(data.value?.top_trainings ?? []).map((c) => c.enrollments)))
const maxArea = computed(() => Math.max(1, ...(data.value?.by_area ?? []).map((a) => a.enrollments)))
const areaGroups = computed(() => {
  const g = []
  for (const a of data.value?.by_area ?? []) {
    let x = g.find((y) => y.division === a.division)
    if (!x) g.push((x = { division: a.division, areas: [] }))
    x.areas.push(a)
  }
  return g
})
const byLevel = computed(() => levels.value.map((l) => ({ ...l, employees: data.value?.employees_by_level?.[l.level] ?? 0 })))
const maxLevel = computed(() => Math.max(1, ...byLevel.value.map((l) => l.employees)))
</script>

<template>
  <section>
    <div class="mb-5">
      <h2 class="text-xl font-semibold text-ink-900 dark:text-white">Training overview</h2>
      <p class="mt-0.5 text-sm text-ink-600 dark:text-stone-400">2026 cycle: enrolments made through the Assessment page.</p>
    </div>

    <ErrorState v-if="error" :message="error" class="mb-5" @retry="load" />
    <div v-if="loading" class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4" aria-busy="true">
      <div v-for="n in 4" :key="n" class="card h-32 animate-pulse" />
    </div>

    <template v-else-if="data">
      <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <article v-for="c in cards" :key="c.label" class="card p-5">
          <div class="flex items-start justify-between gap-3">
            <p class="text-sm font-medium text-ink-600 dark:text-stone-400">{{ c.label }}</p>
            <span class="grid size-9 place-items-center rounded-lg bg-brand-50 text-brand-600 dark:bg-brand-700/25 dark:text-brand-300"><Icon :name="c.icon" /></span>
          </div>
          <p class="mt-2 text-3xl font-semibold tracking-tight tabular-nums text-ink-900 dark:text-white">{{ c.value }}</p>
          <p class="mt-2 text-sm text-ink-600/80 dark:text-stone-500">{{ c.sub }}</p>
        </article>
      </div>

      <div class="mt-6 grid gap-4 lg:grid-cols-3">
        <article class="card p-5 lg:col-span-2">
          <h3 class="font-semibold text-ink-900 dark:text-white">Most enrolled trainings</h3>
          <p v-if="!data.top_trainings.length" class="mt-4 text-sm text-ink-600 dark:text-stone-400">No enrolments yet.</p>
          <ul v-else class="mt-4 space-y-3">
            <li v-for="t in data.top_trainings" :key="t.title">
              <div class="flex items-center justify-between gap-3 text-sm">
                <span class="min-w-0 truncate">{{ t.title }} <span class="text-xs text-ink-600 dark:text-stone-500">· Level {{ t.level }}</span></span>
                <span class="font-semibold tabular-nums">{{ t.enrollments }}</span>
              </div>
              <div class="mt-1.5 h-2 rounded-full bg-ink-900/6 dark:bg-white/6"><div class="h-2 rounded-full bg-brand-500" :style="{ width: `${(t.enrollments / maxTop) * 100}%` }" /></div>
            </li>
          </ul>
        </article>
        <article class="card p-5">
          <h3 class="font-semibold text-ink-900 dark:text-white">Employees by level</h3>
          <ul class="mt-4 space-y-3">
            <li v-for="l in byLevel" :key="l.level">
              <div class="flex items-center justify-between text-sm"><span>Level {{ l.level }} <span class="text-xs text-ink-600 dark:text-stone-500">SG {{ l.min_sg }}–{{ l.max_sg }}</span></span><span class="font-semibold tabular-nums">{{ l.employees }}</span></div>
              <div class="mt-1.5 h-2 rounded-full bg-ink-900/6 dark:bg-white/6"><div class="h-2 rounded-full bg-sky-500" :style="{ width: `${(l.employees / maxLevel) * 100}%` }" /></div>
            </li>
          </ul>
        </article>
        <article class="card p-5 lg:col-span-3">
          <h3 class="font-semibold text-ink-900 dark:text-white">Enrolments by area</h3>
          <p v-if="!data.by_area.length" class="mt-4 text-sm text-ink-600 dark:text-stone-400">No enrolments yet.</p>
          <div v-else class="mt-4 grid gap-x-8 gap-y-5 md:grid-cols-2">
            <div v-for="g in areaGroups" :key="g.division">
              <p class="mb-2 text-xs font-semibold tracking-wide text-ink-600 uppercase dark:text-stone-400">{{ g.division }}</p>
              <ul class="space-y-3">
                <li v-for="a in g.areas" :key="a.area">
                  <div class="flex items-center justify-between gap-3 text-sm"><span class="truncate">{{ a.area }}</span><span class="font-semibold tabular-nums">{{ a.enrollments }}</span></div>
                  <div class="mt-1.5 h-2 rounded-full bg-ink-900/6 dark:bg-white/6"><div class="h-2 rounded-full bg-amber-500" :style="{ width: `${(a.enrollments / maxArea) * 100}%` }" /></div>
                </li>
              </ul>
            </div>
          </div>
        </article>
      </div>
    </template>
  </section>
</template>

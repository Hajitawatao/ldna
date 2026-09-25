<script setup>
import { ref, computed } from 'vue'
import Icon from '../components/Icon.vue'
import ErrorState from '../components/ErrorState.vue'
import EmptyState from '../components/EmptyState.vue'
import { useReference } from '../composables/useReference'
import { useLayout } from '../composables/useLayout'
import { CATEGORIES, categoryClass, categoryLabel, LEVELS, GENERIC_LEVELS } from '../lib/format'

const { competencies, loading, error, reload } = useReference()
const { searchQuery } = useLayout()
const category = ref('all')
const open = ref(null)

const counts = computed(() => Object.fromEntries(CATEGORIES.map((c) => [c.key, competencies.value.filter((x) => x.category === c.key).length])))
const rows = computed(() => {
  const q = searchQuery.value.trim().toLowerCase()
  return competencies.value.filter((c) =>
    (category.value === 'all' || c.category === category.value) &&
    (!q || c.name.toLowerCase().includes(q) || (c.definition ?? '').toLowerCase().includes(q)))
})
const hasLevels = (c) => Object.keys(c.levels ?? {}).length > 0
</script>

<template>
  <section>
    <div class="mb-5 flex flex-wrap items-end justify-between gap-3">
      <div>
        <h2 class="text-xl font-semibold text-ink-900 dark:text-white">Competencies</h2>
        <p class="mt-0.5 max-w-2xl text-sm text-ink-600 dark:text-stone-400">Definitions and what each proficiency level looks like in practice. Open a competency to see its levels.</p>
      </div>
      <div class="flex flex-wrap gap-1 rounded-lg border border-ink-900/10 bg-white p-1 dark:border-white/8 dark:bg-ink-900" role="group" aria-label="Filter by category">
        <button v-for="c in [{ key: 'all', label: 'All' }, ...CATEGORIES]" :key="c.key" type="button"
                class="rounded-md px-3 py-1.5 text-sm font-medium" :aria-pressed="category === c.key"
                :class="category === c.key ? 'bg-ink-900 text-white dark:bg-brand-600' : 'text-ink-600 hover:bg-paper dark:text-stone-400 dark:hover:bg-white/6'"
                @click="category = c.key">
          {{ c.label }}<span v-if="c.key !== 'all'" class="ml-1 tabular-nums opacity-60">{{ counts[c.key] }}</span>
        </button>
      </div>
    </div>

    <ErrorState v-if="error" :message="error" @retry="reload" />
    <div v-else-if="loading && !competencies.length" class="space-y-2" aria-busy="true">
      <div v-for="n in 6" :key="n" class="h-14 animate-pulse rounded-lg bg-ink-900/6 dark:bg-white/6" />
    </div>
    <EmptyState v-else-if="!rows.length" icon="book-open" title="No competencies match" body="Try a different search term or category." />

    <ul v-else class="card divide-y divide-ink-900/8 overflow-hidden dark:divide-white/6">
      <li v-for="c in rows" :key="c.id">
        <button type="button" class="flex w-full items-start gap-4 px-4 py-3.5 text-left hover:bg-paper/70 dark:hover:bg-white/3"
                :aria-expanded="open === c.id" @click="open = open === c.id ? null : c.id">
          <div class="min-w-0 flex-1">
            <p class="font-medium text-ink-900 dark:text-white">{{ c.name }}</p>
            <p class="mt-0.5 line-clamp-1 text-sm text-ink-600 dark:text-stone-400">{{ c.definition ?? 'No definition in the source catalog yet.' }}</p>
          </div>
          <span class="mt-0.5 hidden shrink-0 rounded-full px-2.5 py-0.5 text-xs font-medium sm:inline" :class="categoryClass[c.category]">{{ categoryLabel(c.category) }}</span>
          <Icon name="chevron-down" :size="18" class="mt-1 shrink-0 text-ink-600 transition-transform dark:text-stone-400" :class="open === c.id && 'rotate-180'" />
        </button>

        <div v-if="open === c.id" class="border-t border-ink-900/8 bg-paper/60 px-4 py-4 dark:border-white/6 dark:bg-ink-950/40">
          <p v-if="c.definition" class="max-w-3xl text-sm text-ink-800 dark:text-stone-300">{{ c.definition }}</p>
          <p v-if="!hasLevels(c)" class="mt-3 flex items-start gap-2 rounded-lg bg-amber-50 px-3 py-2 text-sm text-amber-900 dark:bg-amber-500/10 dark:text-amber-200">
            <Icon name="info" :size="18" class="mt-0.5 shrink-0" />
            No level descriptors for this competency in the source catalog. The general DOH proficiency definitions are shown instead.
          </p>
          <div class="mt-4 grid gap-3 md:grid-cols-2 xl:grid-cols-4">
            <div v-for="l in LEVELS" :key="l.value" class="rounded-lg border border-ink-900/10 bg-white p-3.5 dark:border-white/8 dark:bg-ink-900">
              <p class="text-xs font-semibold tracking-wide text-ink-600 uppercase dark:text-stone-400">Level {{ l.value }} · {{ l.label }}</p>
              <template v-if="c.levels?.[l.value]">
                <p class="mt-1.5 text-sm font-medium text-ink-900 dark:text-white">{{ c.levels[l.value].description }}</p>
                <ul class="mt-2 list-disc space-y-1 pl-4 text-sm text-ink-700 marker:text-ink-600/40 dark:text-stone-300">
                  <li v-for="(ind, i) in c.levels[l.value].indicators" :key="i">{{ ind }}</li>
                </ul>
                <p v-if="c.levels[l.value].verification?.length" class="mt-2.5 text-xs text-ink-600 dark:text-stone-500">
                  <span class="font-medium">Verified by:</span> {{ c.levels[l.value].verification.join(', ') }}
                </p>
              </template>
              <p v-else class="mt-1.5 text-sm text-ink-700 dark:text-stone-300">{{ GENERIC_LEVELS[l.value] }}</p>
            </div>
          </div>
        </div>
      </li>
    </ul>
  </section>
</template>

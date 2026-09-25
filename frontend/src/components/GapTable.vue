<script setup>
import { computed } from 'vue'
import { useReference } from '../composables/useReference'
import { categoryClass, categoryLabel, gapClass, gapText, gapMeaning, levelLabel } from '../lib/format'

const props = defineProps({ gaps: { type: Array, required: true } })
const { compById } = useReference()
const rows = computed(() =>
  props.gaps
    .map((g) => ({ ...g, comp: compById.value[g.competency_id] }))
    .filter((g) => g.comp)
    .sort((a, b) => (b.gap ?? -9) - (a.gap ?? -9) || a.comp.name.localeCompare(b.comp.name)))
</script>

<template>
  <div class="overflow-x-auto rounded-lg border border-ink-900/10 dark:border-white/8">
    <table class="w-full min-w-[36rem] text-left text-sm">
      <thead class="border-b border-ink-900/10 bg-paper/60 text-ink-600 dark:border-white/8 dark:bg-ink-950/40 dark:text-stone-400">
        <tr>
          <th scope="col" class="px-3 py-2.5 font-medium">Competency</th>
          <th scope="col" class="px-3 py-2.5 text-center font-medium">Standard</th>
          <th scope="col" class="px-3 py-2.5 text-center font-medium">Self-rating</th>
          <th scope="col" class="px-3 py-2.5 font-medium">Gap</th>
        </tr>
      </thead>
      <tbody class="divide-y divide-ink-900/8 dark:divide-white/6">
        <tr v-for="g in rows" :key="g.competency_id">
          <td class="px-3 py-2.5">
            <span class="text-ink-900 dark:text-stone-100">{{ g.comp.name }}</span>
            <span class="ml-2 rounded-full px-1.5 py-px text-[0.7rem] font-medium" :class="categoryClass[g.comp.category]">{{ categoryLabel(g.comp.category) }}</span>
          </td>
          <td class="px-3 py-2.5 text-center tabular-nums" :title="levelLabel(g.standard)">{{ g.standard }}</td>
          <td class="px-3 py-2.5 text-center tabular-nums" :title="levelLabel(g.actual)">{{ g.actual ?? '—' }}</td>
          <td class="px-3 py-2.5 whitespace-nowrap">
            <span class="inline-flex min-w-9 justify-center rounded-full px-2 py-0.5 text-xs font-semibold tabular-nums ring-1 ring-inset" :class="gapClass(g.gap)">{{ gapText(g.gap) }}</span>
            <span class="ml-2 text-xs text-ink-600 dark:text-stone-400">{{ gapMeaning(g.gap) }}</span>
          </td>
        </tr>
      </tbody>
    </table>
  </div>
</template>

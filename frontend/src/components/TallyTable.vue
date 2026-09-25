<script setup>
import { computed } from 'vue'
import { useReference } from '../composables/useReference'
import { CATEGORIES, levelLabel } from '../lib/format'

// Layout of the DOH LDNA Self-Assessment Tally Sheet: Standard, Actual, Gap (standard minus actual).
const props = defineProps({
  rows: { type: Array, required: true },
  showActual: { type: Boolean, default: true },
  highlight: { type: Array, default: () => [] },       // competency ids to mark (e.g. covered by the training)
})
const { compById } = useReference()
const groups = computed(() => CATEGORIES.map((cat) => ({
  ...cat, rows: props.rows.map((r) => ({ ...r, comp: compById.value[r.competency_id] })).filter((r) => r.comp?.category === cat.key),
})).filter((g) => g.rows.length))
const gapClass = (g) => g == null ? '' : g > 0
  ? 'bg-red-50 text-red-700 ring-red-600/20 dark:bg-red-500/15 dark:text-red-300'
  : g === 0 ? 'bg-brand-50 text-brand-700 ring-brand-600/20 dark:bg-brand-700/25 dark:text-brand-300'
  : 'bg-sky-50 text-sky-800 ring-sky-600/20 dark:bg-sky-500/15 dark:text-sky-300'
</script>

<template>
  <div>
    <div class="overflow-x-auto rounded-lg border border-ink-900/15 dark:border-white/10">
      <table class="w-full min-w-[32rem] text-sm">
        <thead class="bg-paper text-ink-700 dark:bg-ink-950/50 dark:text-stone-300">
          <tr>
            <th scope="col" class="px-3 py-2 text-left font-semibold">Competency title</th>
            <th scope="col" class="w-24 px-3 py-2 text-center font-semibold">Standard competency level</th>
            <th v-if="showActual" scope="col" class="w-24 px-3 py-2 text-center font-semibold">Actual competency level</th>
            <th v-if="showActual" scope="col" class="w-28 px-3 py-2 text-center font-semibold">Gap <span class="block text-xs font-normal">(standard minus actual)</span></th>
          </tr>
        </thead>
        <tbody>
          <template v-for="g in groups" :key="g.key">
            <tr class="border-t border-ink-900/10 bg-ink-900/3 dark:border-white/8 dark:bg-white/3">
              <th :colspan="showActual ? 4 : 2" scope="colgroup" class="px-3 py-1.5 text-left text-xs font-bold tracking-wide uppercase">{{ g.label }} competencies</th>
            </tr>
            <tr v-for="r in g.rows" :key="r.competency_id" class="border-t border-ink-900/8 dark:border-white/6"
                :class="highlight.includes(r.competency_id) && 'bg-amber-50/60 dark:bg-amber-500/5'">
              <td class="px-3 py-2">• {{ r.comp.name }}
                <span v-if="highlight.includes(r.competency_id)" class="ml-1 text-xs font-medium text-amber-800 dark:text-amber-300">covered by this training</span>
              </td>
              <td class="px-3 py-2 text-center tabular-nums" :title="levelLabel(r.standard)">{{ r.standard }}</td>
              <td v-if="showActual" class="px-3 py-2 text-center tabular-nums" :title="levelLabel(r.actual)">{{ r.actual ?? '—' }}</td>
              <td v-if="showActual" class="px-3 py-2 text-center">
                <span v-if="r.gap != null" class="inline-flex min-w-9 justify-center rounded-full px-2 py-0.5 text-xs font-semibold tabular-nums ring-1 ring-inset" :class="gapClass(r.gap)">{{ r.gap > 0 ? `+${r.gap}` : r.gap }}</span>
              </td>
            </tr>
          </template>
        </tbody>
      </table>
    </div>
    <p v-if="showActual" class="mt-2 text-xs text-ink-600 dark:text-stone-400">
      Gap = standard minus actual (DOH LDNA). <span class="font-medium text-red-700 dark:text-red-300">Positive</span>: below standard, needs an intervention ·
      <span class="font-medium text-brand-700 dark:text-brand-300">0</span>: met ·
      <span class="font-medium text-sky-800 dark:text-sky-300">Negative</span>: above standard.
    </p>
  </div>
</template>

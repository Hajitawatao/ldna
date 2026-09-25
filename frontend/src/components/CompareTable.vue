<script setup>
import { computed } from 'vue'
import { useReference } from '../composables/useReference'
import { CATEGORIES, levelLabel } from '../lib/format'

// Training's competency standards vs the employee's position profile (position + area), grouped by category.
const props = defineProps({ rows: { type: Array, required: true } })
const { compById } = useReference()
const groups = computed(() => CATEGORIES.map((cat) => ({
  ...cat,
  rows: props.rows.map((r) => ({ ...r, comp: compById.value[r.competency_id] })).filter((r) => r.comp?.category === cat.key),
})).filter((g) => g.rows.length))
const badge = {
  pass: 'bg-sky-50 text-sky-800 ring-sky-600/20 dark:bg-sky-500/15 dark:text-sky-300',
  match: 'bg-brand-50 text-brand-700 ring-brand-600/20 dark:bg-brand-700/25 dark:text-brand-300',
  lack: 'bg-red-50 text-red-700 ring-red-600/20 dark:bg-red-500/15 dark:text-red-300',
}
</script>

<template>
  <div class="overflow-x-auto rounded-lg border border-ink-900/15 dark:border-white/10">
    <table class="w-full min-w-[34rem] text-sm">
      <thead class="bg-paper text-ink-700 dark:bg-ink-950/50 dark:text-stone-300">
        <tr>
          <th scope="col" class="px-3 py-2 text-left font-semibold">Competency title</th>
          <th scope="col" class="w-28 px-3 py-2 text-center font-semibold">Training requires</th>
          <th scope="col" class="w-28 px-3 py-2 text-center font-semibold">Your position has</th>
          <th scope="col" class="w-24 px-3 py-2 text-center font-semibold">Result</th>
        </tr>
      </thead>
      <tbody>
        <template v-for="g in groups" :key="g.key">
          <tr class="border-t border-ink-900/10 bg-ink-900/3 dark:border-white/8 dark:bg-white/3">
            <th colspan="4" scope="colgroup" class="px-3 py-1.5 text-left text-xs font-bold tracking-wide uppercase">{{ g.label }} competencies</th>
          </tr>
          <tr v-for="r in g.rows" :key="r.competency_id" class="border-t border-ink-900/8 dark:border-white/6">
            <td class="px-3 py-2">• {{ r.comp.name }}</td>
            <td class="px-3 py-2 text-center tabular-nums" :title="levelLabel(r.standard)">{{ r.standard }}</td>
            <td class="px-3 py-2 text-center tabular-nums" :title="r.position_level ? levelLabel(r.position_level) : ''">{{ r.position_level ?? 'None' }}</td>
            <td class="px-3 py-2 text-center">
              <span class="inline-flex rounded-full px-2 py-0.5 text-xs font-semibold capitalize ring-1 ring-inset" :class="badge[r.result]">{{ r.result }}</span>
            </td>
          </tr>
        </template>
      </tbody>
    </table>
  </div>
</template>

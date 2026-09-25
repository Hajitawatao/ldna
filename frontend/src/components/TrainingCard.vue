<script setup>
import { computed } from 'vue'
import Icon from './Icon.vue'
import { useReference } from '../composables/useReference'
import { modeClass, modeLabel, TRAINING_CATEGORIES, trainingCategoryClass } from '../lib/format'

const props = defineProps({
  training: { type: Object, required: true },
  highlight: { type: Array, default: () => [] }, // competency ids to emphasise
  reason: { type: String, default: '' },          // 'gap' | 'area' | 'designated'
})
const { compById, posById, levelText, areasByDivision } = useReference()
const comps = computed(() => props.training.competency_ids.map((id) => compById.value[id]).filter(Boolean))
const positionsLine = computed(() => props.training.all_positions === false
  ? (props.training.position_ids ?? []).map((id) => posById.value[id]?.title).filter(Boolean).join(', ')
  : 'All positions')
const areaGroups = computed(() => areasByDivision(props.training.office_ids ?? []))
const categoryLabel = computed(() => TRAINING_CATEGORIES.find((c) => c.key === (props.training.category ?? 'general'))?.label)
</script>

<template>
  <article class="flex flex-col rounded-lg border border-ink-900/10 bg-white p-4 dark:border-white/8 dark:bg-ink-900"
           :class="training.eligible === false && 'opacity-75'">
    <div class="flex flex-wrap items-start gap-2">
      <h4 class="min-w-0 flex-1 font-semibold text-ink-900 dark:text-white">{{ training.title }}</h4>
      <span class="rounded-full px-2.5 py-0.5 text-xs font-medium ring-1 ring-inset" :class="modeClass[training.mode]">{{ modeLabel(training.mode) }}</span>
    </div>
    <div class="mt-2 flex flex-wrap items-center gap-1.5 text-xs">
      <span class="rounded-full px-2 py-0.5 font-medium" :class="trainingCategoryClass[training.category ?? 'general']">{{ categoryLabel }}</span>
      <span class="rounded-full bg-ink-900/5 px-2 py-0.5 font-medium text-ink-800 dark:bg-white/8 dark:text-stone-200" :title="`Open to Level ${training.level} and up`">
        {{ levelText(training.level) }}
      </span>
    </div>
    <p class="mt-2 text-sm text-ink-600 dark:text-stone-400">
      {{ [training.type, training.provider, training.hours ? `${training.hours} hrs` : null].filter(Boolean).join(' · ') }}
    </p>
    <dl class="mt-2 space-y-1 text-xs text-ink-700 dark:text-stone-300">
      <div class="flex items-start gap-1.5"><Icon name="users" :size="14" class="mt-px shrink-0" /><dt class="sr-only">Positions</dt><dd>{{ positionsLine }}</dd></div>
      <div class="flex items-start gap-1.5">
        <Icon name="map" :size="14" class="mt-px shrink-0" /><dt class="sr-only">Areas</dt>
        <dd v-if="!areaGroups.length">All areas</dd>
        <dd v-else class="space-y-1">
          <div v-for="g in areaGroups" :key="g.division">
            <span class="block text-[0.68rem] font-semibold tracking-wide text-ink-600 uppercase dark:text-stone-400">{{ g.division }}</span>
            {{ g.areas.map((a) => a.area).join(', ') }}
          </div>
        </dd>
      </div>
    </dl>
    <p v-if="training.description" class="mt-2 text-sm text-ink-800 dark:text-stone-300">{{ training.description }}</p>
    <div v-if="comps.length" class="mt-3 flex flex-wrap gap-1.5">
      <span v-for="c in comps" :key="c.id" class="rounded-md px-2 py-0.5 text-xs"
            :class="highlight.includes(c.id) ? 'bg-amber-100 font-medium text-amber-900 dark:bg-amber-500/20 dark:text-amber-200' : 'bg-ink-900/5 text-ink-700 dark:bg-white/6 dark:text-stone-300'">
        {{ c.name }}
      </span>
    </div>
    <p v-if="reason === 'area'" class="mt-2 text-xs font-medium text-sky-700 dark:text-sky-300">Meant for this employee's area or position.</p>
    <p v-if="reason === 'designated'" class="mt-2 text-xs font-medium text-violet-700 dark:text-violet-300">Designated by CETAR for this profile.</p>
    <p v-if="training.eligible === false && training.eligibility_note" class="mt-2 flex items-start gap-1.5 text-xs font-medium text-amber-700 dark:text-amber-300">
      <Icon name="warning" :size="14" class="mt-px shrink-0" /> Not eligible: {{ training.eligibility_note }}
    </p>
    <div v-if="$slots.default" class="mt-auto pt-3"><slot /></div>
  </article>
</template>

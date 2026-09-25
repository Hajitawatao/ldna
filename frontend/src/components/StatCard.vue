<script setup>
import { computed } from 'vue'
import Icon from './Icon.vue'

const props = defineProps({
  label: String,
  value: Number,
  trend: Number,
  icon: String,
  goodWhen: { type: String, default: 'up' }, // 'up' | 'down'
})

const formatted = computed(() => new Intl.NumberFormat().format(props.value))
const isUp = computed(() => props.trend >= 0)
const isGood = computed(() => (props.goodWhen === 'down' ? !isUp.value : isUp.value))
</script>

<template>
  <article class="rounded-xl border border-ink-900/10 bg-white p-5 dark:border-white/8 dark:bg-ink-900">
    <div class="flex items-start justify-between gap-3">
      <p class="text-sm font-medium text-ink-600 dark:text-stone-400">{{ label }}</p>
      <span class="grid size-9 place-items-center rounded-lg bg-brand-50 text-brand-600 dark:bg-brand-700/25 dark:text-brand-300">
        <Icon :name="icon" />
      </span>
    </div>
    <p class="mt-2 text-3xl font-semibold tracking-tight tabular-nums text-ink-900 dark:text-white">{{ formatted }}</p>
    <p class="mt-3 flex items-center gap-1.5 text-sm">
      <span
        class="inline-flex items-center gap-1 font-medium tabular-nums"
        :class="isGood ? 'text-brand-600 dark:text-brand-300' : 'text-red-600 dark:text-red-400'"
      >
        <Icon :name="isUp ? 'trend-up' : 'trend-down'" :size="16" :stroke-width="2" />
        {{ isUp ? '+' : '' }}{{ trend.toFixed(1) }}%
      </span>
      <span class="text-ink-600/80 dark:text-stone-500">vs last cycle</span>
    </p>
  </article>
</template>

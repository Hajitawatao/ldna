<script setup>
import { computed } from 'vue'
import Icon from './Icon.vue'
import { useReference } from '../composables/useReference'
import { CATEGORIES, LEVELS, GENERIC_LEVELS, categoryClass } from '../lib/format'

// Self-rating (actual level) on a set of competencies, using the Competency Dictionary's level descriptions.
const props = defineProps({
  competencyIds: { type: Array, required: true },
  modelValue: { type: Object, required: true },          // { competency_id: 1..4 }
  showMissing: { type: Boolean, default: false },
  prefilled: { type: Object, default: () => ({}) },
})
const emit = defineEmits(['update:modelValue'])
const { compById } = useReference()
const groups = computed(() => CATEGORIES.map((cat) => ({
  ...cat, items: props.competencyIds.map((id) => compById.value[id]).filter((c) => c?.category === cat.key),
})).filter((g) => g.items.length))
const set = (id, level) => emit('update:modelValue', { ...props.modelValue, [id]: level })
</script>

<template>
  <div class="space-y-6">
    <section v-for="g in groups" :key="g.key">
      <h3 class="mb-2 flex items-center gap-2 text-sm font-semibold">
        <span class="rounded-full px-2.5 py-0.5 text-xs font-medium" :class="categoryClass[g.key]">{{ g.label }} competencies</span>
      </h3>
      <div class="space-y-4">
        <fieldset v-for="c in g.items" :id="`rate-${c.id}`" :key="c.id" class="rounded-xl border border-ink-900/10 p-4 dark:border-white/8"
                  :class="showMissing && !modelValue[c.id] && 'ring-2 ring-amber-400'">
          <legend class="sr-only">{{ c.name }}</legend>
          <div class="flex items-start gap-3">
            <div class="min-w-0 flex-1">
              <p class="font-semibold text-ink-900 dark:text-white">{{ c.name }}</p>
              <p v-if="c.definition" class="mt-0.5 text-sm text-ink-600 dark:text-stone-400">{{ c.definition }}</p>
            </div>
            <span v-if="prefilled[c.id] && modelValue[c.id] === prefilled[c.id]" class="shrink-0 rounded-full bg-sky-50 px-2 py-0.5 text-xs text-sky-800 dark:bg-sky-500/15 dark:text-sky-300">from your earlier rating</span>
            <Icon v-if="modelValue[c.id]" name="check" :size="20" :stroke-width="2" class="text-brand-600 dark:text-brand-300" />
          </div>
          <div class="mt-3 grid gap-2 sm:grid-cols-2">
            <label v-for="l in LEVELS" :key="l.value"
                   class="relative flex cursor-pointer flex-col rounded-lg border p-3 text-sm transition-colors has-[:focus-visible]:ring-2 has-[:focus-visible]:ring-brand-500"
                   :class="modelValue[c.id] === l.value ? 'border-brand-500 bg-brand-50 dark:bg-brand-700/20' : 'border-ink-900/12 hover:border-ink-900/25 dark:border-white/10 dark:hover:border-white/25'">
              <input type="radio" :name="`rate-${c.id}`" :value="l.value" class="sr-only" :checked="modelValue[c.id] === l.value" @change="set(c.id, l.value)" />
              <span class="text-xs font-semibold tracking-wide uppercase" :class="modelValue[c.id] === l.value ? 'text-brand-700 dark:text-brand-300' : 'text-ink-600 dark:text-stone-400'">{{ l.value }} · {{ l.label }}</span>
              <span class="mt-1 text-ink-800 dark:text-stone-200">{{ c.levels?.[l.value]?.description || GENERIC_LEVELS[l.value] }}</span>
              <ul v-if="c.levels?.[l.value]?.indicators?.length" class="mt-1.5 list-disc space-y-0.5 pl-4 text-xs text-ink-600 marker:text-ink-600/40 dark:text-stone-400">
                <li v-for="(ind, i) in c.levels[l.value].indicators" :key="i">{{ ind }}</li>
              </ul>
            </label>
          </div>
        </fieldset>
      </div>
    </section>
  </div>
</template>

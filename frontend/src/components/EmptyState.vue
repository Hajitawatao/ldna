<script setup>
import Icon from './Icon.vue'
defineProps({
  icon: { type: String, default: 'inbox' },
  title: { type: String, required: true },
  body: { type: String, default: '' },
})
</script>

<template>
  <div class="flex flex-col items-center rounded-xl border border-dashed border-ink-900/15 px-6 py-16 text-center dark:border-white/12">
    <span class="grid size-12 place-items-center rounded-full bg-brand-50 text-brand-600 dark:bg-brand-700/25 dark:text-brand-300">
      <Icon :name="icon" :size="24" />
    </span>
    <h2 class="mt-4 text-base font-semibold text-ink-900 dark:text-white">{{ title }}</h2>
    <p v-if="body" class="mt-1 max-w-md text-sm text-ink-600 dark:text-stone-400">{{ body }}</p>
    <div v-if="$slots.default" class="mt-5"><slot /></div>
  </div>
</template>

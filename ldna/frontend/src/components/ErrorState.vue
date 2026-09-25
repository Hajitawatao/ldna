<script setup>
import Icon from './Icon.vue'
defineProps({ message: { type: String, required: true } })
defineEmits(['retry'])
</script>

<template>
  <div role="alert" class="flex flex-col gap-3 rounded-xl border border-red-300/70 bg-red-50 p-4 sm:flex-row sm:items-center dark:border-red-500/30 dark:bg-red-950/30">
    <Icon name="warning" class="text-red-600 dark:text-red-400" :size="22" />
    <p class="flex-1 text-sm text-red-800 dark:text-red-200">{{ message }}</p>
    <button
      type="button"
      class="inline-flex items-center gap-2 self-start rounded-lg border border-red-300 bg-white px-3 py-1.5 text-sm font-medium text-red-700 hover:bg-red-100
             sm:self-auto dark:border-red-500/40 dark:bg-transparent dark:text-red-200 dark:hover:bg-red-900/30"
      @click="$emit('retry')"
    >
      <Icon name="refresh" :size="16" /> Try again
    </button>
  </div>
</template>

<script setup>
// Compact labelled <select> used in filter bars.
defineProps({ label: String, modelValue: [String, Number], options: Array, placeholder: String })
defineEmits(['update:modelValue'])
</script>

<template>
  <label class="block min-w-0">
    <span class="mb-1 block text-xs font-medium text-ink-600 dark:text-stone-400">{{ label }}</span>
    <select
      :value="modelValue"
      class="field"
      @change="$emit('update:modelValue', $event.target.value)"
    >
      <option v-if="placeholder !== undefined" value="">{{ placeholder }}</option>
      <option v-for="o in options" :key="o.value" :value="o.value">{{ o.label }}</option>
    </select>
  </label>
</template>

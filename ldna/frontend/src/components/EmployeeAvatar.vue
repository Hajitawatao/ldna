<script setup>
import { ref, computed, watch } from 'vue'

// Employee photo from the portal, falling back to initials when there is none or it fails to load.
const props = defineProps({
  employee: { type: Object, required: true },
  size: { type: Number, default: 40 },
})
const failed = ref(false)
watch(() => props.employee?.photo, () => { failed.value = false })

const initials = computed(() => `${props.employee?.first_name?.[0] ?? ''}${props.employee?.surname?.[0] ?? ''}`.toUpperCase())
const style = computed(() => ({ width: `${props.size}px`, height: `${props.size}px`, fontSize: `${Math.round(props.size * 0.34)}px` }))
</script>

<template>
  <img
    v-if="employee?.photo && !failed"
    :src="employee.photo"
    :alt="`Photo of ${employee.full_name}`"
    :style="style"
    class="shrink-0 rounded-full bg-ink-900/5 object-cover dark:bg-white/8"
    loading="lazy"
    @error="failed = true"
  />
  <span
    v-else
    :style="style"
    class="grid shrink-0 place-items-center rounded-full bg-brand-100 font-semibold text-brand-700 dark:bg-brand-700 dark:text-brand-50"
    aria-hidden="true"
  >{{ initials }}</span>
</template>

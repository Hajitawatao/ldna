<script setup>
import { ref, watch, onMounted } from 'vue'
import Icon from './Icon.vue'

// Native <dialog> wrapper: focus trapping, Escape and backdrop come for free.
const props = defineProps({
  open: Boolean,
  title: { type: String, required: true },
  size: { type: String, default: 'md' }, // md | lg | xl
})
const emit = defineEmits(['close'])
const dialog = ref(null)

function sync(open) {
  if (!dialog.value) return
  if (open && !dialog.value.open) dialog.value.showModal()
  if (!open && dialog.value.open) dialog.value.close()
}
watch(() => props.open, sync)
onMounted(() => sync(props.open))

const widths = { md: 'max-w-lg', lg: 'max-w-2xl', xl: 'max-w-4xl' }
</script>

<template>
  <dialog
    ref="dialog"
    class="modal-box m-auto w-[calc(100%-1.5rem)] overflow-clip rounded-xl border border-ink-900/10 bg-white p-0 text-ink-900 shadow-xl backdrop:bg-ink-950/60
           dark:border-white/10 dark:bg-ink-900 dark:text-stone-100"
    :class="widths[size]"
    @close="emit('close')"
    @click.self="emit('close')"
  >
    <div class="modal-box flex flex-col">
      <header class="flex items-start gap-3 border-b border-ink-900/8 px-5 py-4 dark:border-white/8">
        <div class="min-w-0 flex-1">
          <h2 class="text-lg font-semibold">{{ title }}</h2>
          <slot name="subtitle" />
        </div>
        <button type="button" class="-mr-1 rounded-md p-1.5 text-ink-600 hover:bg-ink-900/5 dark:text-stone-400 dark:hover:bg-white/8" aria-label="Close" @click="emit('close')">
          <Icon name="x" />
        </button>
      </header>
      <div class="min-h-0 flex-1 overflow-y-auto overscroll-contain px-5 py-5">
        <slot />
      </div>
      <footer v-if="$slots.footer" class="flex flex-wrap items-center justify-end gap-2 border-t border-ink-900/8 px-5 py-3 dark:border-white/8">
        <slot name="footer" />
      </footer>
    </div>
  </dialog>
</template>

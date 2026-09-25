<script setup>
import { ref, watch, nextTick } from 'vue'
import Icon from './Icon.vue'
import { apiGet } from '../composables/useApi'

// Type an ID number (or name), pick yourself from the list.
const emit = defineEmits(['select'])
defineProps({
  title: { type: String, default: 'Start your self-assessment' },
  subtitle: { type: String, default: 'Enter your employee ID number, then choose your name from the list.' },
})

const query = ref('')
const matches = ref([])
const open = ref(false)
const active = ref(0)
const loading = ref(false)
const error = ref('')
const input = ref(null)
let timer
let seq = 0

watch(query, (q) => {
  clearTimeout(timer)
  error.value = ''
  if (q.trim().length < 2) { matches.value = []; open.value = false; return }
  timer = setTimeout(async () => {
    const mine = ++seq
    loading.value = true
    try {
      const res = await apiGet(`employees.php?q=${encodeURIComponent(q.trim())}`)
      if (mine !== seq) return
      matches.value = res.matches
      active.value = 0
      open.value = true
    } catch (e) {
      if (mine === seq) error.value = e.message
    } finally {
      if (mine === seq) loading.value = false
    }
  }, 200)
})

function choose(m) {
  open.value = false
  emit('select', m.employee_id)
}
function onKey(e) {
  if (!open.value || !matches.value.length) return
  if (e.key === 'ArrowDown') { e.preventDefault(); active.value = (active.value + 1) % matches.value.length }
  else if (e.key === 'ArrowUp') { e.preventDefault(); active.value = (active.value - 1 + matches.value.length) % matches.value.length }
  else if (e.key === 'Enter') { e.preventDefault(); choose(matches.value[active.value]) }
  else if (e.key === 'Escape') { open.value = false }
}
function onBlur() { setTimeout(() => { open.value = false }, 150) }
nextTick(() => input.value?.focus())
</script>

<template>
  <div class="card mx-auto max-w-xl p-6 sm:p-8">
    <span class="grid size-12 place-items-center rounded-full bg-brand-50 text-brand-600 dark:bg-brand-700/25 dark:text-brand-300">
      <Icon name="clipboard-check" :size="24" />
    </span>
    <h2 class="mt-4 text-xl font-semibold text-ink-900 dark:text-white">{{ title }}</h2>
    <p class="mt-1 text-sm text-ink-600 dark:text-stone-400">{{ subtitle }}</p>

    <div class="relative mt-5">
      <label for="emp-lookup" class="field-label">ID number or name</label>
      <div class="relative">
        <Icon name="search" :size="18" class="pointer-events-none absolute top-1/2 left-3 -translate-y-1/2 text-ink-600/70 dark:text-stone-500" />
        <input
          id="emp-lookup"
          ref="input"
          v-model="query"
          type="search"
          autocomplete="off"
          placeholder="e.g. 20260245"
          class="field h-11 pl-9 text-base"
          role="combobox"
          aria-autocomplete="list"
          aria-controls="emp-lookup-list"
          :aria-expanded="open"
          :aria-activedescendant="open && matches.length ? `emp-opt-${active}` : undefined"
          @keydown="onKey"
          @focus="open = matches.length > 0"
          @blur="onBlur"
        />
      </div>

      <ul
        v-if="open"
        id="emp-lookup-list"
        role="listbox"
        class="absolute z-20 mt-1.5 max-h-80 w-full overflow-y-auto rounded-xl border border-ink-900/10 bg-white py-1 shadow-lg dark:border-white/10 dark:bg-ink-900"
      >
        <li v-if="!matches.length" class="px-4 py-3 text-sm text-ink-600 dark:text-stone-400">No employee found with that ID number or name.</li>
        <li
          v-for="(m, i) in matches"
          :id="`emp-opt-${i}`"
          :key="m.employee_id"
          role="option"
          :aria-selected="i === active"
          class="cursor-pointer px-4 py-2.5"
          :class="i === active ? 'bg-brand-50 dark:bg-brand-700/20' : ''"
          @mousedown.prevent="choose(m)"
          @mousemove="active = i"
        >
          <p class="text-sm font-medium text-ink-900 dark:text-white">
            <span class="tabular-nums text-ink-600 dark:text-stone-400">{{ m.employee_id }}</span> · {{ m.full_name }}
          </p>
          <p class="text-xs text-ink-600 dark:text-stone-400">{{ m.position }} · {{ m.division_code }} · {{ m.area }}</p>
        </li>
      </ul>
    </div>
    <p v-if="loading" class="mt-2 text-xs text-ink-600 dark:text-stone-500">Searching…</p>
    <p v-if="error" role="alert" class="mt-2 text-sm text-red-700 dark:text-red-300">{{ error }}</p>
  </div>
</template>

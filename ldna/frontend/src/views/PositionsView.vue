<script setup>
import { ref } from 'vue'
import ProfilesPanel from '../components/ProfilesPanel.vue'
import PositionsPanel from '../components/PositionsPanel.vue'

const tab = ref('profiles')
</script>

<template>
  <section>
    <div class="mb-4 flex flex-wrap gap-1 rounded-lg border border-ink-900/10 bg-white p-1 dark:border-white/8 dark:bg-ink-900" role="tablist">
      <button v-for="t in [{ key: 'profiles', label: 'Positions by area' }, { key: 'sg', label: 'Salary grades & levels' }]" :key="t.key" type="button" role="tab"
              :aria-selected="tab === t.key" class="rounded-md px-4 py-2 text-sm font-medium"
              :class="tab === t.key ? 'bg-ink-900 text-white dark:bg-brand-600' : 'text-ink-600 hover:bg-paper dark:text-stone-400 dark:hover:bg-white/6'"
              @click="tab = t.key">{{ t.label }}</button>
    </div>
    <ProfilesPanel v-if="tab === 'profiles'" />
    <PositionsPanel v-else />
  </section>
</template>

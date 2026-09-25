<script setup>
import { computed } from 'vue'
import Icon from './Icon.vue'
import { routes } from '../router'
import { useReference } from '../composables/useReference'
const { dataVersion } = useReference()
// eslint-disable-next-line no-undef
const buildDate = __BUILD_DATE__
import { useLayout } from '../composables/useLayout'

const { collapsed, mobileOpen, isDesktop, closeMobile } = useLayout()

// Group drawer items by meta.section, keeping route order.
const navGroups = routes
  .filter((r) => r.meta?.nav)
  .reduce((groups, r) => {
    const label = r.meta.section ?? null
    const last = groups[groups.length - 1]
    if (last && last.label === label) last.items.push(r)
    else groups.push({ label, items: [r] })
    return groups
  }, [])

// Icon-only rail applies to desktop only; the mobile drawer always shows labels.
const railMode = computed(() => isDesktop.value && collapsed.value)
</script>

<template>
  <!-- Backdrop (mobile only) -->
  <Transition
    enter-active-class="transition-opacity duration-200" enter-from-class="opacity-0"
    leave-active-class="transition-opacity duration-200" leave-to-class="opacity-0"
  >
    <div
      v-if="mobileOpen"
      class="fixed inset-0 z-40 bg-ink-950/60 lg:hidden"
      aria-hidden="true"
      @click="closeMobile"
    />
  </Transition>

  <aside
    class="fixed inset-y-0 left-0 z-50 flex w-64 flex-col bg-ink-900 text-stone-300
           transition-[transform,width] duration-200 ease-out
           lg:z-40 lg:translate-x-0"
    :class="[
      mobileOpen ? 'translate-x-0' : '-translate-x-full',
      collapsed ? 'lg:w-18' : 'lg:w-64',
    ]"
    :inert="!isDesktop && !mobileOpen"
    aria-label="Main navigation"
  >
    <!-- Logo -->
    <div class="flex h-16 shrink-0 items-center gap-3 border-b border-white/8 px-4" :class="railMode && 'justify-center px-0'">
      <RouterLink to="/" class="flex min-w-0 items-center gap-3" :title="railMode ? 'Learning and Development Needs Assessment' : undefined">
        <img src="/r1mc-logo.png" alt="Region I Medical Center" width="40" height="40" class="size-10 shrink-0 rounded-full bg-white" />
        <span v-show="!railMode" class="min-w-0 leading-tight">
          <span class="block truncate text-xs text-stone-400">Learning and Development</span>
          <span class="block truncate text-[0.95rem] font-semibold text-white">Needs Assessment</span>
        </span>
      </RouterLink>
      <button
        type="button"
        class="ml-auto rounded-md p-1.5 text-stone-400 hover:bg-white/10 hover:text-white lg:hidden"
        aria-label="Close navigation"
        @click="closeMobile"
      >
        <Icon name="x" />
      </button>
    </div>

    <!-- Nav -->
    <nav class="flex-1 overflow-y-auto px-3 py-4">
      <template v-for="(group, gi) in navGroups" :key="gi">
      <p v-if="group.label && !railMode" class="mt-6 mb-2 px-3 text-[0.7rem] font-semibold tracking-wider text-stone-500 uppercase">{{ group.label }}</p>
      <div v-else-if="group.label" class="mx-3 my-4 border-t border-white/8" aria-hidden="true" />
      <ul class="space-y-1">
        <li v-for="item in group.items" :key="item.name">
          <RouterLink
            :to="item.path"
            v-slot="{ href, navigate, isExactActive }"
            custom
          >
            <a
              :href="href"
              @click="navigate"
              :aria-current="isExactActive ? 'page' : undefined"
              :title="railMode ? item.meta.title : undefined"
              class="group relative flex h-11 items-center gap-3 rounded-lg px-3 text-sm font-medium transition-colors"
              :class="[
                isExactActive
                  ? 'bg-ink-700 text-white'
                  : 'text-stone-300 hover:bg-white/6 hover:text-white',
                railMode && 'justify-center px-0',
              ]"
            >
              <span
                v-if="isExactActive"
                class="absolute inset-y-2 left-0 w-1 rounded-r-full bg-brand-400"
                aria-hidden="true"
              />
              <Icon :name="item.meta.icon" :class="isExactActive ? 'text-brand-300' : 'text-stone-400 group-hover:text-stone-200'" />
              <span v-show="!railMode" class="truncate">{{ item.meta.title }}</span>
            </a>
          </RouterLink>
        </li>
      </ul>
      </template>
    </nav>

    <div v-show="!railMode" class="border-t border-white/8 px-4 py-3 text-xs text-stone-500">
      Version 1.0.0 · app {{ buildDate }}
      <span class="block text-[0.65rem] text-stone-600" :title="'Reference lists version'">lists {{ dataVersion || '…' }}</span>
    </div>
  </aside>
</template>

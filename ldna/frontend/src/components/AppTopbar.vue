<script setup>
import { ref, computed, onMounted, onBeforeUnmount } from 'vue'
import { useRoute } from 'vue-router'
import Icon from './Icon.vue'
import { useLayout } from '../composables/useLayout'
import { useTheme } from '../composables/useTheme'

const route = useRoute()
const { collapsed, toggleCollapsed, openMobile, searchQuery } = useLayout()
const { isDark, toggleTheme } = useTheme()

const pageTitle = computed(() => route.meta.title ?? '')
const searchPlaceholder = computed(() => route.meta.searchPlaceholder)

// Placeholder user until authentication exists.
const user = { name: 'CETAR Staff', role: 'CETAR', email: 'Signed in through the employee portal' }
const initials = user.name.split(' ').map((p) => p[0]).slice(0, 2).join('')

const hasUnread = ref(true)

// Account menu
const menuOpen = ref(false)
const menuRoot = ref(null)
function onDocClick(e) {
  if (menuOpen.value && menuRoot.value && !menuRoot.value.contains(e.target)) menuOpen.value = false
}
function onKeydown(e) {
  if (e.key === 'Escape') menuOpen.value = false
}
onMounted(() => {
  document.addEventListener('click', onDocClick)
  document.addEventListener('keydown', onKeydown)
})
onBeforeUnmount(() => {
  document.removeEventListener('click', onDocClick)
  document.removeEventListener('keydown', onKeydown)
})


const iconBtn = 'relative size-10 items-center justify-center rounded-lg text-ink-600 hover:bg-ink-900/5 hover:text-ink-900 dark:text-stone-400 dark:hover:bg-white/8 dark:hover:text-white'
</script>

<template>
  <header
    class="fixed top-0 right-0 left-0 z-30 flex h-16 items-center gap-2 border-b border-ink-900/10 bg-white/90 px-3 backdrop-blur
           transition-[left] duration-200 ease-out sm:px-4 dark:border-white/8 dark:bg-ink-950/85"
    :class="collapsed ? 'lg:left-18' : 'lg:left-64'"
  >
    <!-- Mobile: open drawer -->
    <button type="button" :class="[iconBtn, 'inline-flex lg:hidden']" aria-label="Open navigation" @click="openMobile">
      <Icon name="menu" :size="22" />
    </button>

    <!-- Desktop: collapse rail -->
    <button
      type="button"
      :class="[iconBtn, 'hidden lg:inline-flex']"
      :aria-label="collapsed ? 'Expand sidebar' : 'Collapse sidebar'"
      :aria-pressed="collapsed"
      @click="toggleCollapsed"
    >
      <Icon :name="collapsed ? 'chevron-double-right' : 'chevron-double-left'" />
    </button>

    <h1 class="min-w-0 truncate pl-1 text-base font-semibold text-ink-900 sm:text-lg dark:text-white">
      {{ pageTitle }}
    </h1>

    <div class="ml-auto flex items-center gap-1 sm:gap-2">
      <!-- Search -->
      <label class="relative hidden md:block">
        <span class="sr-only">Search</span>
        <Icon name="search" :size="18" class="pointer-events-none absolute top-1/2 left-3 -translate-y-1/2 text-ink-600/70 dark:text-stone-500" />
        <input
          v-model="searchQuery"
          type="search"
          :placeholder="searchPlaceholder ?? 'Search'"
          :disabled="!searchPlaceholder"
          :title="searchPlaceholder ? undefined : 'Search is not available on this page'"
          class="h-10 w-56 rounded-lg border border-ink-900/12 bg-paper pr-3 pl-9 text-sm text-ink-900 placeholder:text-ink-600/60
                 focus:border-brand-500 focus:bg-white focus:outline-none focus:ring-2 focus:ring-brand-500/25
                 disabled:cursor-not-allowed disabled:opacity-60 lg:w-72
                 dark:border-white/10 dark:bg-ink-900 dark:text-stone-100 dark:placeholder:text-stone-500 dark:focus:bg-ink-900"
        />
      </label>

      <!-- Theme -->
      <button
        type="button"
        :class="[iconBtn, 'inline-flex']"
        :aria-label="isDark ? 'Switch to light mode' : 'Switch to dark mode'"
        @click="toggleTheme"
      >
        <Icon :name="isDark ? 'sun' : 'moon'" />
      </button>

      <!-- Notifications -->
      <button
        type="button"
        :class="[iconBtn, 'inline-flex']"
        :aria-label="hasUnread ? 'Notifications, unread items' : 'Notifications'"
        @click="hasUnread = false"
      >
        <Icon name="bell" />
        <span
          v-if="hasUnread"
          class="absolute top-2 right-2.5 size-2 rounded-full bg-amber-500 ring-2 ring-white dark:ring-ink-950"
          aria-hidden="true"
        />
      </button>

      <!-- Account menu -->
      <div ref="menuRoot" class="relative">
        <button
          type="button"
          class="flex items-center gap-2 rounded-lg p-1 pr-2 hover:bg-ink-900/5 dark:hover:bg-white/8"
          aria-haspopup="menu"
          :aria-expanded="menuOpen"
          @click="menuOpen = !menuOpen"
        >
          <span class="grid size-8 place-items-center rounded-full bg-brand-100 text-xs font-semibold text-brand-700 dark:bg-brand-700 dark:text-brand-50">
            {{ initials }}
          </span>
          <Icon name="chevron-down" :size="16" class="hidden text-ink-600 sm:block dark:text-stone-400" />
          <span class="sr-only">Account menu for {{ user.name }}</span>
        </button>

        <Transition
          enter-active-class="transition duration-100 ease-out" enter-from-class="opacity-0 -translate-y-1"
          leave-active-class="transition duration-75 ease-in" leave-to-class="opacity-0 -translate-y-1"
        >
          <div
            v-if="menuOpen"
            role="menu"
            class="absolute right-0 mt-2 w-60 overflow-hidden rounded-xl border border-ink-900/10 bg-white py-1 shadow-lg
                   dark:border-white/10 dark:bg-ink-900"
          >
            <div class="border-b border-ink-900/8 px-4 py-3 dark:border-white/8">
              <p class="truncate text-sm font-semibold text-ink-900 dark:text-white">{{ user.name }}</p>
              <p class="truncate text-xs text-ink-600 dark:text-stone-400">{{ user.email }}</p>
            </div>
            <p class="flex items-start gap-3 px-4 py-2.5 text-xs text-ink-600 dark:text-stone-400">
              <Icon name="info" :size="16" class="mt-0.5" />
              Sign-in is handled by the employee portal.
            </p>
          </div>
        </Transition>
      </div>
    </div>
  </header>
</template>

<style scoped>
@reference "../style.css";
.menu-item {
  @apply flex w-full items-center gap-3 px-4 py-2 text-left text-sm text-ink-800 hover:bg-paper
         dark:text-stone-200 dark:hover:bg-white/6;
}
</style>

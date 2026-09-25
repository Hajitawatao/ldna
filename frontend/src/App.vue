<script setup>
import { watch, onMounted, onBeforeUnmount } from 'vue'
import { useRoute } from 'vue-router'
import AppSidebar from './components/AppSidebar.vue'
import AppTopbar from './components/AppTopbar.vue'
import { useLayout } from './composables/useLayout'

const route = useRoute()
const { collapsed, mobileOpen, closeMobile, searchQuery } = useLayout()

// Navigating closes the mobile drawer and resets the page search.
watch(() => route.fullPath, () => {
  closeMobile()
  searchQuery.value = ''
})

function onKeydown(e) {
  if (e.key === 'Escape' && mobileOpen.value) closeMobile()
}
onMounted(() => window.addEventListener('keydown', onKeydown))
onBeforeUnmount(() => window.removeEventListener('keydown', onKeydown))
</script>

<template>
  <div class="min-h-dvh">
    <a href="#main" class="sr-only focus:not-sr-only focus:fixed focus:top-2 focus:left-2 focus:z-[60] focus:rounded-md focus:bg-white focus:px-3 focus:py-2 focus:text-sm focus:shadow">
      Skip to content
    </a>

    <AppSidebar />
    <AppTopbar />

    <main
      id="main"
      class="pt-16 transition-[padding] duration-200 ease-out"
      :class="collapsed ? 'lg:pl-18' : 'lg:pl-64'"
    >
      <div class="mx-auto max-w-7xl px-4 py-6 sm:px-6 lg:px-8 lg:py-8">
        <RouterView />
      </div>
    </main>
  </div>
</template>

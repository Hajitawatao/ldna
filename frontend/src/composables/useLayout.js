import { ref, watch } from 'vue'

const COLLAPSE_KEY = 'sidebar-collapsed'

// Two independent pieces of state:
//  - collapsed:  desktop preference (icon-only rail), persisted
//  - mobileOpen: off-canvas drawer on small screens, never persisted
const collapsed = ref(localStorage.getItem(COLLAPSE_KEY) === '1')
const mobileOpen = ref(false)

// Shared value of the top-bar search box; list pages filter on it.
const searchQuery = ref('')

watch(collapsed, (v) => localStorage.setItem(COLLAPSE_KEY, v ? '1' : '0'))

// Lock page scroll while the mobile drawer is open.
watch(mobileOpen, (open) => {
  document.documentElement.classList.toggle('overflow-hidden', open)
})

// Tracks Tailwind's `lg` breakpoint so components can tell desktop from mobile.
const desktopQuery = window.matchMedia('(min-width: 1024px)')
const isDesktop = ref(desktopQuery.matches)
desktopQuery.addEventListener('change', (e) => {
  isDesktop.value = e.matches
  if (e.matches) mobileOpen.value = false
})

export function useLayout() {
  return {
    collapsed,
    mobileOpen,
    isDesktop,
    searchQuery,
    toggleCollapsed: () => { collapsed.value = !collapsed.value },
    openMobile: () => { mobileOpen.value = true },
    closeMobile: () => { mobileOpen.value = false },
  }
}

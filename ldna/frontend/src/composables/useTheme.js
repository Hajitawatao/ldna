import { ref, watch } from 'vue'

const KEY = 'theme'
const media = window.matchMedia('(prefers-color-scheme: dark)')

function readStored() {
  try { return localStorage.getItem(KEY) } catch { return null }
}

// Module-level state: every component shares one theme.
const isDark = ref(readStored() ? readStored() === 'dark' : media.matches)
let hasExplicitChoice = readStored() !== null

watch(isDark, (dark) => {
  document.documentElement.classList.toggle('dark', dark)
}, { immediate: true })

// Follow the OS until the user makes their own choice.
media.addEventListener('change', (e) => {
  if (!hasExplicitChoice) isDark.value = e.matches
})

export function useTheme() {
  function toggleTheme() {
    isDark.value = !isDark.value
    hasExplicitChoice = true
    try { localStorage.setItem(KEY, isDark.value ? 'dark' : 'light') } catch { /* private mode */ }
  }
  return { isDark, toggleTheme }
}

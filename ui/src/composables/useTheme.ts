import { ref, onMounted } from 'vue'
import type { ThemeMode } from '@/types'

export function useTheme() {
  const mode = ref<ThemeMode>('system')

  function applyTheme(theme: ThemeMode) {
    const root = document.documentElement

    if (theme === 'system') {
      const isDark = window.matchMedia('(prefers-color-scheme: dark)').matches
      root.classList.toggle('dark', isDark)
    } else {
      root.classList.toggle('dark', theme === 'dark')
    }
  }

  function setTheme(theme: ThemeMode) {
    mode.value = theme
    localStorage.setItem('api-lens-theme', theme)
    applyTheme(theme)
  }

  function toggleTheme() {
    const current = mode.value
    if (current === 'light') setTheme('dark')
    else if (current === 'dark') setTheme('system')
    else setTheme('light')
  }

  onMounted(() => {
    const stored = localStorage.getItem('api-lens-theme') as ThemeMode | null
    if (stored) {
      mode.value = stored
    }
    applyTheme(mode.value)

    // Listen for system theme changes
    window.matchMedia('(prefers-color-scheme: dark)').addEventListener('change', () => {
      if (mode.value === 'system') {
        applyTheme('system')
      }
    })
  })

  return {
    mode,
    setTheme,
    toggleTheme,
  }
}

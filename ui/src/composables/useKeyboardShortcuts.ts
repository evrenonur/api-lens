import { onMounted, onUnmounted } from 'vue'
import { useEndpointStore } from '@/stores/endpoints'

/**
 * Global keyboard shortcuts for API Lens.
 *
 * - Ctrl+K / Cmd+K  → Focus search bar
 * - ↑ / ↓           → Navigate endpoints (when search not focused)
 * - Enter           → Select highlighted endpoint
 * - Escape          → Clear selection / deselect endpoint
 */
export function useKeyboardShortcuts() {
  const store = useEndpointStore()

  function handleKeyDown(e: KeyboardEvent) {
    const target = e.target as HTMLElement
    const isInput = target.tagName === 'INPUT' || target.tagName === 'TEXTAREA' || target.isContentEditable

    // Ctrl+K / Cmd+K → Focus search
    if ((e.ctrlKey || e.metaKey) && e.key === 'k') {
      e.preventDefault()
      const searchInput = document.querySelector<HTMLInputElement>('input[placeholder*="Search"]')
      if (searchInput) {
        searchInput.focus()
        searchInput.select()
      }
      return
    }

    // Escape → Clear search or deselect endpoint
    if (e.key === 'Escape') {
      if (isInput) {
        ;(target as HTMLInputElement).blur()
        return
      }
      if (store.searchQuery) {
        store.searchQuery = ''
        return
      }
      if (store.selectedEndpoint) {
        store.selectEndpoint(null)
        return
      }
      return
    }

    // Don't handle arrow keys / Enter when typing in inputs (except search)
    const isSearchInput = target.tagName === 'INPUT' && (target as HTMLInputElement).placeholder?.includes('Search')
    if (isInput && !isSearchInput) return

    const allEndpoints = store.filteredEndpoints

    if (allEndpoints.length === 0) return

    // ↑ / ↓ → Navigate endpoints
    if (e.key === 'ArrowDown' || e.key === 'ArrowUp') {
      e.preventDefault()
      const currentIndex = store.selectedEndpoint
        ? allEndpoints.findIndex(ep => ep.uri === store.selectedEndpoint!.uri && ep.http_method === store.selectedEndpoint!.http_method)
        : -1

      let nextIndex: number
      if (e.key === 'ArrowDown') {
        nextIndex = currentIndex < allEndpoints.length - 1 ? currentIndex + 1 : 0
      } else {
        nextIndex = currentIndex > 0 ? currentIndex - 1 : allEndpoints.length - 1
      }

      store.selectEndpoint(allEndpoints[nextIndex])

      // Scroll the selected item into view
      requestAnimationFrame(() => {
        const selectedCard = document.querySelector('[data-endpoint-selected="true"]')
        selectedCard?.scrollIntoView({ block: 'nearest', behavior: 'smooth' })
      })
      return
    }

    // Enter → Select (already selected via arrows, so this is a no-op or could trigger send)
    if (e.key === 'Enter' && isSearchInput) {
      e.preventDefault()
      if (allEndpoints.length > 0 && !store.selectedEndpoint) {
        store.selectEndpoint(allEndpoints[0])
      }
      ;(target as HTMLInputElement).blur()
      return
    }
  }

  onMounted(() => {
    document.addEventListener('keydown', handleKeyDown)
  })

  onUnmounted(() => {
    document.removeEventListener('keydown', handleKeyDown)
  })
}

import { defineStore } from 'pinia'
import { ref, computed } from 'vue'
import type { Endpoint, GroupBy, SortBy, HistoryEntry, ApiLensConfig, UpdateInfo } from '@/types'
import Fuse from 'fuse.js'

export const useEndpointStore = defineStore('endpoints', () => {
  // State
  const endpoints = ref<Endpoint[]>([])
  const loading = ref(false)
  const error = ref<string | null>(null)
  const config = ref<ApiLensConfig | null>(null)
  const updateInfo = ref<UpdateInfo | null>(null)

  // Filters
  const searchQuery = ref('')
  const selectedMethods = ref<Set<string>>(new Set(['GET', 'POST', 'PUT', 'PATCH', 'DELETE']))
  const selectedGroup = ref<string | null>(null)
  const selectedControllers = ref<Set<string>>(new Set())
  const sortBy = ref<SortBy>('default')
  const groupBy = ref<GroupBy>('api_uri')

  // UI State
  const selectedEndpoint = ref<Endpoint | null>(null)
  const sidebarCollapsed = ref(false)
  const history = ref<HistoryEntry[]>([])

  // Fuse.js search instance
  const fuse = computed(() => {
    return new Fuse(endpoints.value, {
      keys: [
        { name: 'uri', weight: 0.4 },
        { name: 'http_method', weight: 0.2 },
        { name: 'controller', weight: 0.15 },
        { name: 'method', weight: 0.15 },
        { name: 'summary', weight: 0.1 },
      ],
      threshold: 0.4,
      includeScore: true,
    })
  })

  // Computed - filtered and searched endpoints
  const filteredEndpoints = computed(() => {
    let result = endpoints.value

    // Filter by HTTP method
    result = result.filter(e => selectedMethods.value.has(e.http_method))

    // Filter by controller
    if (selectedControllers.value.size > 0) {
      result = result.filter(e => {
        const ctrl = e.controller || 'Unknown'
        return selectedControllers.value.has(ctrl)
      })
    }

    // Filter by group
    if (selectedGroup.value) {
      result = result.filter(e => e.group === selectedGroup.value)
    }

    // Search
    if (searchQuery.value.trim()) {
      const searchResults = fuse.value.search(searchQuery.value.trim())
      const matchedUris = new Set(searchResults.map(r => r.item.uri + r.item.http_method))
      result = result.filter(e => matchedUris.has(e.uri + e.http_method))
    }

    return result
  })

  // Computed - unique controllers
  const uniqueControllers = computed(() => {
    const controllerSet = new Set<string>()
    endpoints.value.forEach(e => {
      controllerSet.add(e.controller || 'Unknown')
    })
    return Array.from(controllerSet).sort()
  })

  // Computed - unique groups
  const groups = computed(() => {
    const groupSet = new Set<string>()
    endpoints.value.forEach(e => {
      if (e.group) groupSet.add(e.group)
    })
    return Array.from(groupSet).sort()
  })

  // Computed - grouped endpoints
  const groupedEndpoints = computed(() => {
    const grouped: Record<string, Endpoint[]> = {}

    filteredEndpoints.value.forEach(endpoint => {
      const group = endpoint.group || 'General'
      if (!grouped[group]) grouped[group] = []
      grouped[group].push(endpoint)
    })

    return grouped
  })

  // Computed - stats
  const stats = computed(() => ({
    total: endpoints.value.length,
    filtered: filteredEndpoints.value.length,
    groups: groups.value.length,
    methods: {
      GET: endpoints.value.filter(e => e.http_method === 'GET').length,
      POST: endpoints.value.filter(e => e.http_method === 'POST').length,
      PUT: endpoints.value.filter(e => e.http_method === 'PUT').length,
      PATCH: endpoints.value.filter(e => e.http_method === 'PATCH').length,
      DELETE: endpoints.value.filter(e => e.http_method === 'DELETE').length,
    },
  }))

  // Actions
  async function fetchEndpoints() {
    loading.value = true
    error.value = null

    try {
      const apiUrl = window.__API_LENS_CONFIG__?.apiUrl || '/api-lens/api'

      const params = new URLSearchParams({
        showGet: selectedMethods.value.has('GET') ? 'true' : 'false',
        showPost: selectedMethods.value.has('POST') ? 'true' : 'false',
        showPut: selectedMethods.value.has('PUT') ? 'true' : 'false',
        showPatch: selectedMethods.value.has('PATCH') ? 'true' : 'false',
        showDelete: selectedMethods.value.has('DELETE') ? 'true' : 'false',
        showHead: 'false',
        sort: sortBy.value,
        groupby: groupBy.value,
      })

      const response = await fetch(`${apiUrl}?${params.toString()}`)

      if (!response.ok) {
        throw new Error(`HTTP ${response.status}: ${response.statusText}`)
      }

      endpoints.value = await response.json()
    } catch (err) {
      error.value = err instanceof Error ? err.message : 'Failed to fetch endpoints'
      console.error('[API Lens] Error:', err)
    } finally {
      loading.value = false
    }
  }

  async function fetchConfig() {
    try {
      const configUrl = window.__API_LENS_CONFIG__?.configUrl || '/api-lens/config'
      const response = await fetch(configUrl)
      config.value = await response.json()
    } catch (err) {
      console.error('[API Lens] Config error:', err)
    }
  }

  async function checkForUpdate() {
    try {
      const apiUrl = window.__API_LENS_CONFIG__?.apiUrl || '/api-lens/api'
      const baseUrl = apiUrl.replace(/\/api$/, '')
      const response = await fetch(`${baseUrl}/check-update`)
      updateInfo.value = await response.json()
    } catch (err) {
      console.error('[API Lens] Update check error:', err)
    }
  }

  function selectEndpoint(endpoint: Endpoint | null) {
    selectedEndpoint.value = endpoint
  }

  function toggleMethod(method: string) {
    if (selectedMethods.value.has(method)) {
      selectedMethods.value.delete(method)
    } else {
      selectedMethods.value.add(method)
    }
    selectedMethods.value = new Set(selectedMethods.value)
  }

  function toggleController(controller: string) {
    if (selectedControllers.value.has(controller)) {
      selectedControllers.value.delete(controller)
    } else {
      selectedControllers.value.add(controller)
    }
    selectedControllers.value = new Set(selectedControllers.value)
  }

  function clearControllerFilter() {
    selectedControllers.value = new Set()
  }

  function addToHistory(entry: HistoryEntry) {
    history.value.unshift(entry)
    if (history.value.length > 50) {
      history.value = history.value.slice(0, 50)
    }
    // Persist to localStorage
    try {
      localStorage.setItem('api-lens-history', JSON.stringify(history.value))
    } catch { /* ignore */ }
  }

  function loadHistory() {
    try {
      const stored = localStorage.getItem('api-lens-history')
      if (stored) history.value = JSON.parse(stored)
    } catch { /* ignore */ }
  }

  function clearHistory() {
    history.value = []
    localStorage.removeItem('api-lens-history')
  }

  return {
    // State
    endpoints,
    loading,
    error,
    config,
    updateInfo,
    searchQuery,
    selectedMethods,
    selectedGroup,
    selectedControllers,
    sortBy,
    groupBy,
    selectedEndpoint,
    sidebarCollapsed,
    history,

    // Computed
    filteredEndpoints,
    groups,
    uniqueControllers,
    groupedEndpoints,
    stats,

    // Actions
    fetchEndpoints,
    fetchConfig,
    checkForUpdate,
    selectEndpoint,
    toggleMethod,
    toggleController,
    clearControllerFilter,
    addToHistory,
    loadHistory,
    clearHistory,
  }
})

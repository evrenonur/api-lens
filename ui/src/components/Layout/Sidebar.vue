<script setup lang="ts">
import { ref, watch } from 'vue'
import { useEndpointStore } from '@/stores/endpoints'
import { useRequestStorage } from '@/composables/useRequestStorage'
import EndpointCard from '@/components/Endpoint/EndpointCard.vue'
import SearchBar from '@/components/SearchBar.vue'

const store = useEndpointStore()
const { clear: clearStorage } = useRequestStorage()
const expandedGroups = ref<Set<string>>(new Set())
const showFilters = ref(false)
const showClearConfirm = ref(false)

function handleClearStorage() {
  clearStorage()
  showClearConfirm.value = false
  window.location.reload()
}

function toggleGroup(group: string) {
  if (expandedGroups.value.has(group)) {
    expandedGroups.value.delete(group)
  } else {
    expandedGroups.value.add(group)
  }
  expandedGroups.value = new Set(expandedGroups.value)
}

// Auto-expand all groups on first load
watch(() => store.groups, (groups) => {
  if (groups.length > 0 && expandedGroups.value.size === 0) {
    groups.forEach(g => expandedGroups.value.add(g))
    expandedGroups.value = new Set(expandedGroups.value)
  }
}, { immediate: true })

const activeFilterCount = ref(0)
watch([() => store.selectedControllers.size, () => store.sortBy, () => store.groupBy], () => {
  let count = 0
  if (store.selectedControllers.size > 0) count++
  if (store.sortBy !== 'default') count++
  if (store.groupBy !== 'api_uri') count++
  activeFilterCount.value = count
})

/**
 * Extract short name from a full namespace path.
 * e.g. "App\Http\Controllers\Api\UserController" → "UserController"
 * e.g. "api/v1/users" → "api/v1/users" (unchanged for URI groups)
 */
function formatGroupName(group: string): string {
  if (group.includes('\\')) {
    return group.split('\\').pop() || group
  }
  return group
}
</script>

<template>
  <aside
    class="w-80 xl:w-96 flex-shrink-0 border-r border-gray-200 dark:border-gray-800 bg-white dark:bg-gray-900 flex flex-col overflow-hidden"
    :class="{ 'hidden': store.sidebarCollapsed }"
  >
    <!-- Search -->
    <div class="p-3 border-b border-gray-100 dark:border-gray-800">
      <SearchBar v-model="store.searchQuery" />

      <!-- Filter Toggle Button -->
      <button
        @click="showFilters = !showFilters"
        class="mt-2 w-full flex items-center justify-between px-3 py-1.5 text-xs rounded-lg transition-colors"
        :class="showFilters || activeFilterCount > 0 ? 'bg-primary-50 dark:bg-primary-900/20 text-primary-600 dark:text-primary-400' : 'bg-gray-50 dark:bg-gray-800/50 text-gray-500 dark:text-gray-400 hover:bg-gray-100 dark:hover:bg-gray-800'"
      >
        <div class="flex items-center gap-1.5">
          <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z" />
          </svg>
          <span class="font-medium">Filters</span>
          <span
            v-if="activeFilterCount > 0"
            class="bg-primary-500 text-white text-[10px] font-bold rounded-full w-4 h-4 flex items-center justify-center"
          >
            {{ activeFilterCount }}
          </span>
        </div>
        <svg
          class="w-3.5 h-3.5 transition-transform duration-200"
          :class="{ 'rotate-180': showFilters }"
          fill="none" viewBox="0 0 24 24" stroke="currentColor"
        >
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
        </svg>
      </button>
    </div>

    <!-- Filter Panel -->
    <Transition
      enter-active-class="transition-all duration-200 ease-out"
      enter-from-class="max-h-0 opacity-0"
      enter-to-class="max-h-[500px] opacity-100"
      leave-active-class="transition-all duration-150 ease-in"
      leave-from-class="max-h-[500px] opacity-100"
      leave-to-class="max-h-0 opacity-0"
    >
      <div v-show="showFilters" class="border-b border-gray-100 dark:border-gray-800 overflow-hidden">
        <div class="p-3 space-y-3">

          <!-- Controller Filter -->
          <div v-if="store.uniqueControllers.length > 1">
            <div class="flex items-center justify-between mb-1.5">
              <label class="text-[10px] font-semibold text-gray-400 dark:text-gray-500 uppercase tracking-wider">Controller</label>
              <button
                v-if="store.selectedControllers.size > 0"
                @click="store.clearControllerFilter()"
                class="text-[10px] text-primary-500 hover:text-primary-600 font-medium"
              >
                Clear
              </button>
            </div>
            <div class="max-h-36 overflow-y-auto space-y-0.5 scrollbar-thin">
              <label
                v-for="ctrl in store.uniqueControllers"
                :key="ctrl"
                class="flex items-center gap-2 px-2 py-1 rounded-md hover:bg-gray-50 dark:hover:bg-gray-800/50 cursor-pointer group"
              >
                <input
                  type="checkbox"
                  :checked="store.selectedControllers.size === 0 || store.selectedControllers.has(ctrl)"
                  @change="store.toggleController(ctrl)"
                  class="w-3.5 h-3.5 rounded border-gray-300 dark:border-gray-600 text-primary-500 focus:ring-primary-500 focus:ring-offset-0"
                />
                <span class="text-xs text-gray-600 dark:text-gray-300 truncate group-hover:text-gray-900 dark:group-hover:text-gray-100 transition-colors" :title="ctrl">
                  {{ ctrl.split('\\').pop() }}
                </span>
                <span class="ml-auto text-[10px] text-gray-400 dark:text-gray-600 font-mono flex-shrink-0">
                  {{ store.endpoints.filter(e => (e.controller || 'Unknown') === ctrl).length }}
                </span>
              </label>
            </div>
          </div>

          <!-- Sort By -->
          <div>
            <label class="text-[10px] font-semibold text-gray-400 dark:text-gray-500 uppercase tracking-wider mb-1.5 block">Sort By</label>
            <div class="flex gap-1">
              <button
                v-for="opt in [{ value: 'default', label: 'Default' }, { value: 'route_names', label: 'Route' }, { value: 'method_names', label: 'Method' }]"
                :key="opt.value"
                @click="store.sortBy = opt.value as any; store.fetchEndpoints()"
                class="flex-1 px-2 py-1 text-[10px] font-medium rounded-md transition-colors"
                :class="store.sortBy === opt.value ? 'bg-primary-500 text-white' : 'bg-gray-100 dark:bg-gray-800 text-gray-500 dark:text-gray-400 hover:bg-gray-200 dark:hover:bg-gray-700'"
              >
                {{ opt.label }}
              </button>
            </div>
          </div>

          <!-- Group By -->
          <div>
            <label class="text-[10px] font-semibold text-gray-400 dark:text-gray-500 uppercase tracking-wider mb-1.5 block">Group By</label>
            <div class="flex gap-1">
              <button
                v-for="opt in [{ value: 'api_uri', label: 'URI' }, { value: 'controller_full_path', label: 'Controller' }]"
                :key="opt.value"
                @click="store.groupBy = opt.value as any; store.fetchEndpoints()"
                class="flex-1 px-2 py-1 text-[10px] font-medium rounded-md transition-colors"
                :class="store.groupBy === opt.value ? 'bg-primary-500 text-white' : 'bg-gray-100 dark:bg-gray-800 text-gray-500 dark:text-gray-400 hover:bg-gray-200 dark:hover:bg-gray-700'"
              >
                {{ opt.label }}
              </button>
            </div>
          </div>

        </div>
      </div>
    </Transition>

    <!-- Endpoint List -->
    <div class="flex-1 overflow-y-auto">
      <div v-if="store.filteredEndpoints.length === 0" class="p-6 text-center">
        <p class="text-sm text-gray-400 dark:text-gray-600">No endpoints match your filters</p>
      </div>

      <div v-else>
        <template v-for="(endpoints, group) in store.groupedEndpoints" :key="group">
          <!-- Group Header -->
          <button
            @click="toggleGroup(String(group))"
            class="w-full flex items-center justify-between px-4 py-2.5 bg-gray-50 dark:bg-gray-800/50 border-b border-gray-100 dark:border-gray-800 hover:bg-gray-100 dark:hover:bg-gray-800 transition-colors"
          >
            <div class="flex items-center gap-2">
              <svg
                class="w-3.5 h-3.5 text-gray-400 transition-transform duration-200"
                :class="{ 'rotate-90': expandedGroups.has(String(group)) }"
                fill="currentColor"
                viewBox="0 0 20 20"
              >
                <path fill-rule="evenodd" d="M7.21 14.77a.75.75 0 01.02-1.06L11.168 10 7.23 6.29a.75.75 0 111.04-1.08l4.5 4.25a.75.75 0 010 1.08l-4.5 4.25a.75.75 0 01-1.06-.02z" clip-rule="evenodd" />
              </svg>
              <span class="text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                {{ formatGroupName(String(group)) }}
              </span>
            </div>
            <span class="text-xs text-gray-400 dark:text-gray-600 font-mono">
              {{ endpoints.length }}
            </span>
          </button>

          <!-- Endpoints in Group -->
          <div v-show="expandedGroups.has(String(group))">
            <EndpointCard
              v-for="endpoint in endpoints"
              :key="endpoint.uri + endpoint.http_method"
              :endpoint="endpoint"
              :selected="store.selectedEndpoint?.uri === endpoint.uri && store.selectedEndpoint?.http_method === endpoint.http_method"
              @click="store.selectEndpoint(endpoint)"
            />
          </div>
        </template>
      </div>
    </div>

    <!-- Footer Stats -->
    <div class="px-4 py-2 border-t border-gray-100 dark:border-gray-800 bg-gray-50/50 dark:bg-gray-900/50">
      <div class="flex items-center justify-between text-xs text-gray-400 dark:text-gray-600">
        <span>{{ store.stats.filtered }} endpoints</span>
        <div class="flex items-center gap-2">
          <button
            @click="showClearConfirm = true"
            class="p-1 rounded hover:bg-red-50 dark:hover:bg-red-900/20 hover:text-red-500 transition-colors"
            title="Clear all saved data"
          >
            <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
            </svg>
          </button>
          <span class="text-emerald-500">{{ store.stats.methods.GET }}G</span>
          <span class="text-blue-500">{{ store.stats.methods.POST }}P</span>
          <span class="text-amber-500">{{ store.stats.methods.PUT }}U</span>
          <span class="text-red-500">{{ store.stats.methods.DELETE }}D</span>
        </div>
      </div>
    </div>

    <!-- Clear Confirmation Modal -->
    <Teleport to="body">
      <Transition
        enter-active-class="transition-opacity duration-150"
        enter-from-class="opacity-0"
        enter-to-class="opacity-100"
        leave-active-class="transition-opacity duration-100"
        leave-from-class="opacity-100"
        leave-to-class="opacity-0"
      >
        <div v-if="showClearConfirm" class="fixed inset-0 z-50 flex items-center justify-center">
          <div class="absolute inset-0 bg-black/50" @click="showClearConfirm = false"></div>
          <div class="relative bg-white dark:bg-gray-800 rounded-xl shadow-2xl p-6 max-w-sm mx-4 border border-gray-200 dark:border-gray-700">
            <div class="flex items-center gap-3 mb-3">
              <div class="w-10 h-10 rounded-full bg-red-100 dark:bg-red-900/30 flex items-center justify-center flex-shrink-0">
                <svg class="w-5 h-5 text-red-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L4.082 16.5c-.77.833.192 2.5 1.732 2.5z" />
                </svg>
              </div>
              <div>
                <h3 class="text-sm font-semibold text-gray-900 dark:text-gray-100">Clear All Saved Data</h3>
                <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">This will remove all saved request bodies, headers, and cached responses for every endpoint.</p>
              </div>
            </div>
            <div class="flex justify-end gap-2 mt-4">
              <button
                @click="showClearConfirm = false"
                class="px-3 py-1.5 text-xs font-medium rounded-lg bg-gray-100 dark:bg-gray-700 text-gray-600 dark:text-gray-300 hover:bg-gray-200 dark:hover:bg-gray-600 transition-colors"
              >
                Cancel
              </button>
              <button
                @click="handleClearStorage()"
                class="px-3 py-1.5 text-xs font-medium rounded-lg bg-red-500 text-white hover:bg-red-600 transition-colors"
              >
                Clear All
              </button>
            </div>
          </div>
        </div>
      </Transition>
    </Teleport>
  </aside>
</template>

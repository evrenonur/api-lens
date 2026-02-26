<script setup lang="ts">
import { ref, watch } from 'vue'
import { useEndpointStore } from '@/stores/endpoints'
import EndpointCard from '@/components/Endpoint/EndpointCard.vue'
import SearchBar from '@/components/SearchBar.vue'

const store = useEndpointStore()
const expandedGroups = ref<Set<string>>(new Set())

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
</script>

<template>
  <aside
    class="w-80 xl:w-96 flex-shrink-0 border-r border-gray-200 dark:border-gray-800 bg-white dark:bg-gray-900 flex flex-col overflow-hidden"
    :class="{ 'hidden': store.sidebarCollapsed }"
  >
    <!-- Search -->
    <div class="p-3 border-b border-gray-100 dark:border-gray-800">
      <SearchBar v-model="store.searchQuery" />
    </div>

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
                {{ group }}
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
        <div class="flex gap-2">
          <span class="text-emerald-500">{{ store.stats.methods.GET }}G</span>
          <span class="text-blue-500">{{ store.stats.methods.POST }}P</span>
          <span class="text-amber-500">{{ store.stats.methods.PUT }}U</span>
          <span class="text-red-500">{{ store.stats.methods.DELETE }}D</span>
        </div>
      </div>
    </div>
  </aside>
</template>

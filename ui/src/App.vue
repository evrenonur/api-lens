<script setup lang="ts">
import { onMounted } from 'vue'
import { useEndpointStore } from '@/stores/endpoints'
import { useTheme } from '@/composables/useTheme'
import { useKeyboardShortcuts } from '@/composables/useKeyboardShortcuts'
import TopNav from '@/components/Layout/TopNav.vue'
import Sidebar from '@/components/Layout/Sidebar.vue'
import EndpointDetail from '@/components/Endpoint/EndpointDetail.vue'
import EmptyState from '@/components/EmptyState.vue'

const store = useEndpointStore()
const { mode, toggleTheme } = useTheme()
useKeyboardShortcuts()

onMounted(async () => {
  store.loadHistory()
  await Promise.all([store.fetchConfig(), store.fetchEndpoints()])
})
</script>

<template>
  <div class="h-screen flex flex-col overflow-hidden">
    <!-- Top Navigation -->
    <TopNav :theme="mode" @toggle-theme="toggleTheme" />

    <!-- Main Content -->
    <div class="flex flex-1 overflow-hidden">
      <!-- Sidebar -->
      <Sidebar />

      <!-- Detail Panel -->
      <main class="flex-1 overflow-y-auto bg-gray-50 dark:bg-gray-950">
        <!-- Loading State -->
        <div v-if="store.loading" class="flex items-center justify-center h-full">
          <div class="text-center">
            <div class="inline-flex items-center gap-3 text-gray-500 dark:text-gray-400">
              <svg class="animate-spin h-5 w-5" viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" fill="none" />
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z" />
              </svg>
              <span class="text-sm font-medium">Loading API endpoints...</span>
            </div>
          </div>
        </div>

        <!-- Error State -->
        <div v-else-if="store.error" class="flex items-center justify-center h-full p-8">
          <div class="card p-8 max-w-md text-center">
            <div class="w-12 h-12 rounded-full bg-red-100 dark:bg-red-900/30 flex items-center justify-center mx-auto mb-4">
              <svg class="w-6 h-6 text-red-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.732-.833-2.5 0L4.268 16.5c-.77.833.192 2.5 1.732 2.5z" />
              </svg>
            </div>
            <h3 class="text-lg font-semibold text-gray-900 dark:text-gray-100 mb-2">Connection Error</h3>
            <p class="text-sm text-gray-500 dark:text-gray-400 mb-4">{{ store.error }}</p>
            <button @click="store.fetchEndpoints()" class="btn-primary">
              Retry
            </button>
          </div>
        </div>

        <!-- Endpoint Detail -->
        <EndpointDetail v-else-if="store.selectedEndpoint" :endpoint="store.selectedEndpoint" :key="store.selectedEndpoint.http_method + ':' + store.selectedEndpoint.uri" />

        <!-- Empty State -->
        <EmptyState v-else :total="store.stats.total" />
      </main>
    </div>
  </div>
</template>

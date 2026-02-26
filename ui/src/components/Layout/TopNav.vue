<script setup lang="ts">
import { ref, onMounted, computed } from 'vue'
import { useEndpointStore } from '@/stores/endpoints'
import type { ThemeMode } from '@/types'

defineProps<{
  theme: ThemeMode
}>()

const emit = defineEmits<{
  'toggle-theme': []
}>()

const store = useEndpointStore()

const exportOpen = ref(false)
const updateDismissed = ref(false)

const currentVersion = computed(() => store.config?.version || '...')
const updateAvailable = computed(() => store.updateInfo?.update_available === true && !updateDismissed.value)
const latestVersion = computed(() => store.updateInfo?.latest_version || '')

function closeExport() {
  exportOpen.value = false
}

function exportAs(format: 'openapi' | 'postman') {
  const apiUrl = window.__API_LENS_CONFIG__?.apiUrl || '/api-lens/api'
  const baseUrl = apiUrl.replace(/\/api$/, '')
  window.open(`${baseUrl}/export/${format}`, '_blank')
  exportOpen.value = false
}

function dismissUpdate() {
  updateDismissed.value = true
  try {
    localStorage.setItem('api-lens-update-dismissed', latestVersion.value)
  } catch { /* ignore */ }
}

onMounted(() => {
  // Check for updates after a short delay to not block UI
  setTimeout(() => {
    store.checkForUpdate().then(() => {
      // If user already dismissed this version, don't show again
      try {
        const dismissed = localStorage.getItem('api-lens-update-dismissed')
        if (dismissed === latestVersion.value) {
          updateDismissed.value = true
        }
      } catch { /* ignore */ }
    })
  }, 2000)
})
</script>

<template>
  <header class="glass border-b border-gray-200 dark:border-gray-800 px-4 py-3 flex items-center justify-between z-50 sticky top-0">
    <div class="flex items-center gap-3">
      <!-- Logo -->
      <div class="flex items-center gap-2">
        <div class="w-8 h-8 bg-gradient-to-br from-primary-500 to-primary-700 rounded-lg flex items-center justify-center">
          <svg class="w-4 h-4 text-white" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
            <circle cx="11" cy="11" r="8" />
            <path d="m21 21-4.35-4.35" />
          </svg>
        </div>
        <h1 class="text-lg font-bold text-gray-900 dark:text-gray-100">
          API <span class="text-primary-600 dark:text-primary-400">Lens</span>
        </h1>
      </div>

      <!-- Separator & Stats -->
      <div class="hidden sm:flex items-center gap-2 ml-4 pl-4 border-l border-gray-200 dark:border-gray-800">
        <span class="text-xs text-gray-500 dark:text-gray-400">
          {{ store.stats.filtered }} / {{ store.stats.total }} endpoints
        </span>
        <!-- Version Badge -->
        <span class="text-[10px] font-mono px-1.5 py-0.5 rounded-full bg-gray-100 dark:bg-gray-800 text-gray-500 dark:text-gray-400">
          v{{ currentVersion }}
        </span>
      </div>
    </div>

    <div class="flex items-center gap-2">
      <!-- Method Filters -->
      <div class="hidden lg:flex items-center gap-1 mr-2">
        <button
          v-for="method in ['GET', 'POST', 'PUT', 'PATCH', 'DELETE']"
          :key="method"
          @click="store.toggleMethod(method)"
          :class="[
            'px-2 py-1 text-xs font-bold rounded-md transition-all duration-200',
            store.selectedMethods.has(method)
              ? method === 'GET' ? 'bg-emerald-500 text-white'
                : method === 'POST' ? 'bg-blue-500 text-white'
                : method === 'PUT' ? 'bg-amber-500 text-white'
                : method === 'PATCH' ? 'bg-orange-500 text-white'
                : 'bg-red-500 text-white'
              : 'bg-gray-100 dark:bg-gray-800 text-gray-400 dark:text-gray-600'
          ]"
        >
          {{ method }}
        </button>
      </div>

      <!-- Export Dropdown -->
      <div class="relative">
        <button
          @click="exportOpen = !exportOpen"
          class="btn-ghost p-2 rounded-lg flex items-center gap-1.5"
          title="Export API Documentation"
        >
          <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
          </svg>
          <span class="hidden sm:inline text-xs font-medium">Export</span>
          <svg class="w-3 h-3 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
          </svg>
        </button>

        <!-- Dropdown Menu -->
        <Transition
          enter-active-class="transition ease-out duration-100"
          enter-from-class="transform opacity-0 scale-95"
          enter-to-class="transform opacity-100 scale-100"
          leave-active-class="transition ease-in duration-75"
          leave-from-class="transform opacity-100 scale-100"
          leave-to-class="transform opacity-0 scale-95"
        >
          <div
            v-if="exportOpen"
            class="absolute right-0 mt-2 w-64 rounded-xl bg-white dark:bg-gray-900 shadow-lg ring-1 ring-gray-200 dark:ring-gray-700 z-50 overflow-hidden"
          >
            <div class="px-3 py-2 border-b border-gray-100 dark:border-gray-800">
              <p class="text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Export Documentation</p>
            </div>

            <div class="p-1.5">
              <!-- OpenAPI -->
              <button
                @click="exportAs('openapi')"
                class="w-full flex items-center gap-3 px-3 py-2.5 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-800 transition-colors group"
              >
                <div class="w-9 h-9 rounded-lg bg-emerald-50 dark:bg-emerald-900/20 flex items-center justify-center flex-shrink-0">
                  <svg class="w-5 h-5 text-emerald-600 dark:text-emerald-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                  </svg>
                </div>
                <div class="text-left">
                  <p class="text-sm font-medium text-gray-900 dark:text-gray-100 group-hover:text-emerald-600 dark:group-hover:text-emerald-400 transition-colors">OpenAPI 3.0</p>
                  <p class="text-[11px] text-gray-400">Swagger UI, Redoc, Stoplight</p>
                </div>
              </button>

              <!-- Postman -->
              <button
                @click="exportAs('postman')"
                class="w-full flex items-center gap-3 px-3 py-2.5 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-800 transition-colors group"
              >
                <div class="w-9 h-9 rounded-lg bg-orange-50 dark:bg-orange-900/20 flex items-center justify-center flex-shrink-0">
                  <svg class="w-5 h-5 text-orange-600 dark:text-orange-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10" />
                  </svg>
                </div>
                <div class="text-left">
                  <p class="text-sm font-medium text-gray-900 dark:text-gray-100 group-hover:text-orange-600 dark:group-hover:text-orange-400 transition-colors">Postman Collection</p>
                  <p class="text-[11px] text-gray-400">Import directly into Postman</p>
                </div>
              </button>
            </div>
          </div>
        </Transition>

        <!-- Click outside overlay -->
        <div v-if="exportOpen" class="fixed inset-0 z-40" @click="closeExport" />
      </div>

      <!-- Theme Toggle -->
      <button
        @click="emit('toggle-theme')"
        class="btn-ghost p-2 rounded-lg"
        :title="'Theme: ' + theme"
      >
        <svg v-if="theme === 'light'" class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 3v1m0 16v1m9-9h-1M4 12H3m15.364 6.364l-.707-.707M6.343 6.343l-.707-.707m12.728 0l-.707.707M6.343 17.657l-.707.707M16 12a4 4 0 11-8 0 4 4 0 018 0z" />
        </svg>
        <svg v-else-if="theme === 'dark'" class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20.354 15.354A9 9 0 018.646 3.646 9.003 9.003 0 0012 21a9.003 9.003 0 008.354-5.646z" />
        </svg>
        <svg v-else class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.75 17L9 20l-1 1h8l-1-1-.75-3M3 13h18M5 17h14a2 2 0 002-2V5a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
        </svg>
      </button>
    </div>
  </header>

  <!-- Update Available Banner -->
  <Transition
    enter-active-class="transition ease-out duration-300"
    enter-from-class="transform -translate-y-full opacity-0"
    enter-to-class="transform translate-y-0 opacity-100"
    leave-active-class="transition ease-in duration-200"
    leave-from-class="transform translate-y-0 opacity-100"
    leave-to-class="transform -translate-y-full opacity-0"
  >
    <div
      v-if="updateAvailable"
      class="bg-gradient-to-r from-primary-500 to-primary-600 text-white px-4 py-2 flex items-center justify-between text-sm"
    >
      <div class="flex items-center gap-2">
        <svg class="w-4 h-4 animate-pulse" fill="none" viewBox="0 0 24 24" stroke="currentColor">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 11l5-5m0 0l5 5m-5-5v12" />
        </svg>
        <span>
          A new version of API Lens is available!
          <span class="font-mono font-bold ml-1">v{{ currentVersion }}</span>
          <svg class="w-3 h-3 inline mx-1" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M13 7l5 5m0 0l-5 5m5-5H6" />
          </svg>
          <span class="font-mono font-bold">v{{ latestVersion }}</span>
        </span>
      </div>
      <div class="flex items-center gap-3">
        <a
          href="https://github.com/evrenonur/api-lens/releases"
          target="_blank"
          class="text-xs bg-white/20 hover:bg-white/30 px-3 py-1 rounded-full transition-colors font-medium"
        >
          View Release
        </a>
        <code class="hidden md:inline text-[11px] bg-white/10 px-2 py-0.5 rounded font-mono">
          composer update evrenonur/api-lens
        </code>
        <button
          @click="dismissUpdate"
          class="text-white/70 hover:text-white transition-colors ml-1"
          title="Dismiss"
        >
          <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
          </svg>
        </button>
      </div>
    </div>
  </Transition>
</template>

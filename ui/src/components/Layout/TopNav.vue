<script setup lang="ts">
import { useEndpointStore } from '@/stores/endpoints'
import type { ThemeMode } from '@/types'

defineProps<{
  theme: ThemeMode
}>()

const emit = defineEmits<{
  'toggle-theme': []
}>()

const store = useEndpointStore()
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
</template>

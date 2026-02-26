<script setup lang="ts">
defineProps<{ total: number }>()
</script>

<template>
  <div class="flex items-center justify-center h-full">
    <div class="text-center max-w-lg px-8">
      <!-- Animated Icon -->
      <div class="relative w-24 h-24 mx-auto mb-6">
        <div class="absolute inset-0 bg-primary-500/10 rounded-full animate-pulse"></div>
        <div class="absolute inset-2 bg-primary-500/20 rounded-full"></div>
        <div class="absolute inset-0 flex items-center justify-center">
          <svg class="w-10 h-10 text-primary-500" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
            <circle cx="11" cy="11" r="8" />
            <path d="m21 21-4.35-4.35" stroke-linecap="round" />
          </svg>
        </div>
      </div>

      <h2 class="text-2xl font-bold text-gray-900 dark:text-gray-100 mb-2">
        API Lens
      </h2>
      <p class="text-sm text-gray-500 dark:text-gray-400 mb-6">
        Select an endpoint from the sidebar to inspect its documentation, validation rules, response schema, and code snippets.
      </p>

      <!-- Quick Stats -->
      <div v-if="total > 0" class="inline-flex items-center gap-2 text-xs text-gray-400 dark:text-gray-600 bg-gray-100 dark:bg-gray-800 rounded-full px-4 py-2">
        <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
        </svg>
        {{ total }} endpoints discovered
      </div>

      <!-- Keyboard Shortcuts -->
      <div class="mt-8 grid grid-cols-2 gap-2 text-xs text-gray-400 dark:text-gray-600 max-w-xs mx-auto">
        <div class="flex items-center gap-2">
          <kbd class="px-1.5 py-0.5 bg-gray-100 dark:bg-gray-800 rounded border border-gray-200 dark:border-gray-700 font-mono">Ctrl+K</kbd>
          <span>Search</span>
        </div>
        <div class="flex items-center gap-2">
          <kbd class="px-1.5 py-0.5 bg-gray-100 dark:bg-gray-800 rounded border border-gray-200 dark:border-gray-700 font-mono">↑↓</kbd>
          <span>Navigate</span>
        </div>
        <div class="flex items-center gap-2">
          <kbd class="px-1.5 py-0.5 bg-gray-100 dark:bg-gray-800 rounded border border-gray-200 dark:border-gray-700 font-mono">Enter</kbd>
          <span>Select</span>
        </div>
        <div class="flex items-center gap-2">
          <kbd class="px-1.5 py-0.5 bg-gray-100 dark:bg-gray-800 rounded border border-gray-200 dark:border-gray-700 font-mono">Esc</kbd>
          <span>Close</span>
        </div>
      </div>
    </div>
  </div>
</template>

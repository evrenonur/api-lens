<script setup lang="ts">
const model = defineModel<string>({ required: true })
</script>

<template>
  <div class="relative">
    <svg class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
    </svg>
    <input
      v-model="model"
      type="text"
      placeholder="Search endpoints... (Ctrl+K)"
      class="input pl-10 pr-10 py-2.5 text-sm"
    />
    <button
      v-if="model"
      @click="model = ''"
      class="absolute right-3 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600 dark:hover:text-gray-300"
    >
      <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
      </svg>
    </button>
    <kbd v-else class="absolute right-3 top-1/2 -translate-y-1/2 text-[10px] text-gray-400 border border-gray-300 dark:border-gray-700 rounded px-1.5 py-0.5 font-mono">
      Ctrl+K
    </kbd>
  </div>
</template>

<script setup lang="ts">
import type { Endpoint } from '@/types'
import { METHOD_BADGE_COLORS } from '@/types'

defineProps<{
  endpoint: Endpoint
  selected: boolean
}>()
</script>

<template>
  <div
    class="px-4 py-3 border-b border-gray-50 dark:border-gray-800/50 cursor-pointer transition-all duration-150"
    :class="[
      selected
        ? 'bg-primary-50 dark:bg-primary-900/20 border-l-2 border-l-primary-500'
        : 'hover:bg-gray-50 dark:hover:bg-gray-800/50 border-l-2 border-l-transparent'
    ]"
    :data-endpoint-selected="selected"
  >
    <div class="flex items-start gap-3">
      <!-- Method Badge -->
      <span
        :class="[
          'method-badge flex-shrink-0 mt-0.5',
          METHOD_BADGE_COLORS[endpoint.http_method] || 'bg-gray-500 text-white'
        ]"
      >
        {{ endpoint.http_method }}
      </span>

      <!-- URI & Info -->
      <div class="flex-1 min-w-0">
        <div class="flex items-center gap-2">
          <span class="text-sm font-mono text-gray-800 dark:text-gray-200 truncate">
            /{{ endpoint.uri }}
          </span>
          <!-- Deprecated badge -->
          <span
            v-if="endpoint.deprecated_since"
            class="badge bg-yellow-100 dark:bg-yellow-900/30 text-yellow-700 dark:text-yellow-400 border-yellow-300 dark:border-yellow-700 text-[10px]"
          >
            deprecated
          </span>
          <!-- Auth badge -->
          <span
            v-if="endpoint.auth_type"
            class="text-[10px] text-gray-400 dark:text-gray-600"
          >
            🔒
          </span>
        </div>

        <!-- Summary -->
        <p v-if="endpoint.summary" class="text-xs text-gray-500 dark:text-gray-400 mt-0.5 truncate">
          {{ endpoint.summary }}
        </p>

        <!-- Meta -->
        <div class="flex items-center gap-2 mt-1">
          <span v-if="endpoint.controller" class="text-[10px] text-gray-400 dark:text-gray-600 font-mono truncate">
            {{ endpoint.controller }}@{{ endpoint.method }}
          </span>
          <span v-if="Object.keys(endpoint.rules || {}).length > 0" class="text-[10px] text-gray-400 dark:text-gray-600">
            · {{ Object.keys(endpoint.rules).length }} params
          </span>
        </div>
      </div>
    </div>
  </div>
</template>

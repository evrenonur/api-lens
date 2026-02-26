<script setup lang="ts">
import { ref, computed } from 'vue'
import type { ApiLensMetrics } from '@/types'
import hljs from 'highlight.js/lib/core'
import json from 'highlight.js/lib/languages/json'

hljs.registerLanguage('json', json)

const props = defineProps<{
  data: unknown
  status: number | null
  headers: Record<string, string>
  time: number
  metrics: ApiLensMetrics | null
  error: string | null
}>()

const activeSubTab = ref<'body' | 'headers' | 'metrics'>('body')

const formattedData = computed(() => {
  if (props.data === null || props.data === undefined) return ''
  if (typeof props.data === 'string') return props.data
  return JSON.stringify(props.data, null, 2)
})

const highlightedBody = computed(() => {
  const raw = formattedData.value
  if (!raw) return ''
  try {
    return hljs.highlight(raw, { language: 'json' }).value
  } catch {
    return raw
  }
})

const statusColor = computed(() => {
  if (!props.status) return ''
  if (props.status < 300) return 'text-emerald-500'
  if (props.status < 400) return 'text-blue-500'
  if (props.status < 500) return 'text-amber-500'
  return 'text-red-500'
})

function copyResponse() {
  navigator.clipboard.writeText(formattedData.value)
}
</script>

<template>
  <div class="space-y-4">
    <!-- No response yet -->
    <div v-if="!status && !error" class="card p-12 text-center">
      <div class="w-16 h-16 rounded-full bg-gray-100 dark:bg-gray-800 flex items-center justify-center mx-auto mb-4">
        <svg class="w-8 h-8 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M13 10V3L4 14h7v7l9-11h-7z" />
        </svg>
      </div>
      <p class="text-sm text-gray-500 dark:text-gray-400">
        Send a request to see the response here
      </p>
    </div>

    <!-- Error -->
    <div v-else-if="error" class="card p-6 border-red-200 dark:border-red-800">
      <div class="flex items-center gap-3">
        <div class="w-10 h-10 rounded-full bg-red-100 dark:bg-red-900/30 flex items-center justify-center flex-shrink-0">
          <svg class="w-5 h-5 text-red-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
          </svg>
        </div>
        <div>
          <p class="font-semibold text-red-600 dark:text-red-400 text-sm">Request Failed</p>
          <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">{{ error }}</p>
        </div>
      </div>
    </div>

    <!-- Response -->
    <div v-else>
      <!-- Status Bar -->
      <div class="flex items-center justify-between mb-4">
        <div class="flex items-center gap-4">
          <span :class="statusColor" class="text-xl font-bold">{{ status }}</span>
          <span class="text-sm text-gray-400">{{ time }}ms</span>
        </div>
        <button @click="copyResponse" class="btn-ghost text-xs">
          <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z" />
          </svg>
          Copy
        </button>
      </div>

      <!-- Sub-tabs -->
      <div class="flex gap-1 mb-4">
        <button
          v-for="tab in ['body', 'headers', 'metrics']"
          :key="tab"
          @click="activeSubTab = tab as any"
          :class="[
            activeSubTab === tab ? 'tab-active' : 'tab'
          ]"
        >
          {{ tab.charAt(0).toUpperCase() + tab.slice(1) }}
        </button>
      </div>

      <!-- Body -->
      <div v-if="activeSubTab === 'body'" class="code-block">
        <pre class="whitespace-pre-wrap"><code v-html="highlightedBody"></code></pre>
      </div>

      <!-- Headers -->
      <div v-if="activeSubTab === 'headers'" class="card overflow-hidden">
        <table class="w-full text-sm">
          <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
            <tr v-for="(value, key) in headers" :key="key">
              <td class="px-4 py-2 font-mono text-xs text-gray-600 dark:text-gray-400 w-48">{{ key }}</td>
              <td class="px-4 py-2 font-mono text-xs text-gray-800 dark:text-gray-200">{{ value }}</td>
            </tr>
          </tbody>
        </table>
      </div>

      <!-- Metrics (API Lens) -->
      <div v-if="activeSubTab === 'metrics' && metrics" class="space-y-4">
        <!-- Summary -->
        <div class="grid grid-cols-4 gap-4">
          <div class="card p-4 text-center">
            <div class="text-2xl font-bold text-gray-900 dark:text-gray-100">{{ metrics.queries_count }}</div>
            <div class="text-xs text-gray-500 mt-1">SQL Queries</div>
          </div>
          <div class="card p-4 text-center">
            <div class="text-2xl font-bold text-gray-900 dark:text-gray-100">{{ metrics.queries_time_ms }}ms</div>
            <div class="text-xs text-gray-500 mt-1">Query Time</div>
          </div>
          <div class="card p-4 text-center">
            <div class="text-2xl font-bold text-gray-900 dark:text-gray-100">{{ metrics.memory }}</div>
            <div class="text-xs text-gray-500 mt-1">Memory</div>
          </div>
          <div class="card p-4 text-center">
            <div class="text-2xl font-bold text-gray-900 dark:text-gray-100">{{ metrics.execution_ms }}ms</div>
            <div class="text-xs text-gray-500 mt-1">Total Time</div>
          </div>
        </div>

        <!-- SQL Queries -->
        <div v-if="metrics.queries?.length" class="card overflow-hidden">
          <div class="px-4 py-2 bg-gray-50 dark:bg-gray-800 border-b border-gray-100 dark:border-gray-700">
            <h4 class="text-xs font-semibold text-gray-500 uppercase">SQL Queries</h4>
          </div>
          <div class="divide-y divide-gray-100 dark:divide-gray-800">
            <div v-for="(query, i) in metrics.queries" :key="i" class="px-4 py-3">
              <code class="text-xs font-mono text-gray-800 dark:text-gray-200 block whitespace-pre-wrap">{{ query.sql }}</code>
              <div class="flex items-center gap-3 mt-1.5 text-[10px] text-gray-400">
                <span>{{ query.time }}ms</span>
                <span>{{ query.connection_name }}</span>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

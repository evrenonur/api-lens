<script setup lang="ts">
import { ref, computed, watch, onMounted } from 'vue'
import type { Endpoint, HeaderItem } from '@/types'
import { METHOD_BADGE_COLORS } from '@/types'
import { useApi } from '@/composables/useApi'
import { useRequestStorage } from '@/composables/useRequestStorage'
import { useEndpointStore } from '@/stores/endpoints'
import RequestPanel from './RequestPanel.vue'
import ResponsePanel from './ResponsePanel.vue'
import CodeSnippet from '@/components/CodeSnippet.vue'

const props = defineProps<{ endpoint: Endpoint }>()

const store = useEndpointStore()
const { load: loadSaved, save: saveToStorage } = useRequestStorage()

const visibility = computed(() => store.config?.visibility ?? { meta_data: true, sql_data: true, logs_data: true, models_data: true })

const activeTab = ref<'request' | 'response' | 'snippets' | 'schema'>('request')
const customHeaders = ref<HeaderItem[]>([{ key: '', value: '' }])
const customBody = ref<Record<string, unknown>>({})
const requestPanelRef = ref<InstanceType<typeof RequestPanel> | null>(null)

// Restore from localStorage on mount
onMounted(() => {
  const saved = loadSaved(props.endpoint.http_method, props.endpoint.uri)
  if (saved) {
    if (saved.body && Object.keys(saved.body).length > 0) {
      customBody.value = saved.body
    }
    if (saved.headers && saved.headers.length > 0) {
      customHeaders.value = [...saved.headers, { key: '', value: '' }]
    }
  }
})

// Auto-save body changes (debounced)
let saveTimer: ReturnType<typeof setTimeout> | null = null
function scheduleSave() {
  if (saveTimer) clearTimeout(saveTimer)
  saveTimer = setTimeout(() => {
    saveToStorage(props.endpoint.http_method, props.endpoint.uri, customBody.value, customHeaders.value)
  }, 500)
}

watch(customBody, scheduleSave, { deep: true })
watch(customHeaders, scheduleSave, { deep: true })

const {
  loading: requestLoading,
  responseData,
  responseStatus,
  responseHeaders,
  responseTime,
  metrics,
  error: requestError,
  sendRequest,
  reset: _reset,
} = useApi()

void _reset // suppress unused warning

// Initialize body from rules (LRD-style: nested key support, skip file fields)
const initialBody = computed(() => {
  if (props.endpoint.example_request && Object.keys(props.endpoint.example_request).length > 0) {
    return { ...props.endpoint.example_request }
  }

  const body: Record<string, unknown> = {}
  for (const [key, rules] of Object.entries(props.endpoint.rules || {})) {
    if (!rules || (Array.isArray(rules) && rules.length === 0)) continue

    const ruleStr = Array.isArray(rules) ? rules.join('|') : String(rules)

    // Skip file fields — handled by file upload zone
    if (ruleStr.includes('file') || ruleStr.includes('image') || ruleStr.includes('mimes') || ruleStr.includes('mimetypes')) {
      continue
    }

    // Handle nested keys: "user.name" => { user: { name: "" } }
    const keys = key.split('.')
    keys.reduce((current: Record<string, unknown>, part: string, index: number) => {
      const k = part === '*' ? '0' : part

      if (index === keys.length - 1) {
        // Last segment — set value based on type
        if (!isNaN(Number(k))) {
          // Array index
          return current
        }
        if (ruleStr.includes('integer') || ruleStr.includes('numeric')) {
          current[k] = 0
        } else if (ruleStr.includes('boolean')) {
          current[k] = false
        } else if (ruleStr.includes('array') || (keys[index + 1] === '*')) {
          current[k] = current[k] || []
        } else {
          current[k] = ''
        }
      } else {
        // Intermediate segment — create nested object or array
        const nextPart = keys[index + 1]
        if (ruleStr.includes('array') || nextPart === '*' || !isNaN(Number(nextPart))) {
          current[k] = current[k] || []
        } else {
          current[k] = current[k] || {}
        }
      }
      return current[k] as Record<string, unknown>
    }, body)
  }
  return body
})

function handleSend() {
  // Save before sending
  saveToStorage(props.endpoint.http_method, props.endpoint.uri, customBody.value, customHeaders.value)

  const fileParams = requestPanelRef.value?.fileParams || {}
  sendRequest(
    props.endpoint,
    customHeaders.value.filter(h => h.key.trim()),
    customBody.value,
    fileParams
  )
  activeTab.value = 'response'
}

const statusColorClass = computed(() => {
  if (!responseStatus.value) return ''
  if (responseStatus.value < 300) return 'text-emerald-500'
  if (responseStatus.value < 400) return 'text-blue-500'
  if (responseStatus.value < 500) return 'text-amber-500'
  return 'text-red-500'
})
</script>

<template>
  <div class="h-full flex flex-col">
    <!-- Header -->
    <div class="bg-white dark:bg-gray-900 border-b border-gray-200 dark:border-gray-800 px-6 py-4">
      <div class="flex items-center justify-between">
        <div class="flex items-center gap-3">
          <span
            :class="[
              'method-badge',
              METHOD_BADGE_COLORS[endpoint.http_method] || 'bg-gray-500 text-white'
            ]"
          >
            {{ endpoint.http_method }}
          </span>
          <code class="text-lg font-mono text-gray-800 dark:text-gray-200">
            /{{ endpoint.uri }}
          </code>
          <span
            v-if="endpoint.deprecated_since"
            class="badge bg-yellow-100 dark:bg-yellow-900/30 text-yellow-700 dark:text-yellow-400 border-yellow-300"
          >
            Deprecated {{ endpoint.deprecated_since }}
          </span>
          <span
            v-if="endpoint.auth_type"
            class="badge bg-purple-100 dark:bg-purple-900/30 text-purple-700 dark:text-purple-400 border-purple-300"
          >
            🔒 {{ endpoint.auth_type }}
          </span>
        </div>

        <button
          @click="handleSend"
          :disabled="requestLoading"
          class="btn-primary"
        >
          <svg v-if="requestLoading" class="animate-spin h-4 w-4" viewBox="0 0 24 24">
            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" fill="none" />
            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z" />
          </svg>
          <svg v-else class="h-4 w-4" viewBox="0 0 24 24" fill="currentColor">
            <path d="M2.01 21L23 12 2.01 3 2 10l15 2-15 2z" />
          </svg>
          Send Request
        </button>
      </div>

      <!-- Summary / DocBlock -->
      <p v-if="endpoint.summary || endpoint.doc_block" class="text-sm text-gray-500 dark:text-gray-400 mt-2">
        {{ endpoint.summary || endpoint.doc_block }}
      </p>

      <!-- Meta info -->
      <div v-if="visibility.meta_data" class="flex flex-wrap items-center gap-4 mt-3 text-xs text-gray-400 dark:text-gray-600">
        <span v-if="endpoint.controller" class="font-mono">
          {{ endpoint.controller }}@{{ endpoint.method }}
        </span>
        <span v-if="endpoint.middlewares?.length" class="flex items-center gap-1">
          <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
          </svg>
          {{ endpoint.middlewares.join(', ') }}
        </span>
        <span v-if="endpoint.rate_limit?.requests_per_minute">
          ⚡ {{ endpoint.rate_limit.requests_per_minute }} req/min
        </span>
      </div>

      <!-- Response Indicator -->
      <div v-if="responseStatus" class="flex items-center gap-4 mt-3 text-sm">
        <span :class="statusColorClass" class="font-bold">
          {{ responseStatus }}
        </span>
        <span class="text-gray-400">{{ responseTime }}ms</span>
        <span v-if="metrics && visibility.sql_data" class="text-gray-400">
          {{ metrics.queries_count }} queries ({{ metrics.queries_time_ms }}ms) · {{ metrics.memory }}
        </span>
      </div>
    </div>

    <!-- Tabs -->
    <div class="bg-white dark:bg-gray-900 border-b border-gray-200 dark:border-gray-800 px-6">
      <nav class="flex gap-1 -mb-px">
        <button
          v-for="tab in [
            { key: 'request', label: 'Request', count: Object.keys(endpoint.rules || {}).length },
            { key: 'response', label: 'Response' },
            { key: 'snippets', label: 'Code Snippets' },
            { key: 'schema', label: 'Schema' },
          ]"
          :key="tab.key"
          @click="activeTab = tab.key as any"
          :class="[
            'px-4 py-3 text-sm font-medium border-b-2 transition-colors',
            activeTab === tab.key
              ? 'border-primary-500 text-primary-600 dark:text-primary-400'
              : 'border-transparent text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-300'
          ]"
        >
          {{ tab.label }}
          <span v-if="tab.count" class="ml-1.5 text-[10px] bg-gray-100 dark:bg-gray-800 rounded-full px-1.5 py-0.5">
            {{ tab.count }}
          </span>
        </button>
      </nav>
    </div>

    <!-- Tab Content -->
    <div class="flex-1 overflow-y-auto p-6">
      <!-- Request Tab -->
      <RequestPanel
        v-if="activeTab === 'request'"
        ref="requestPanelRef"
        :endpoint="endpoint"
        v-model:headers="customHeaders"
        v-model:body="customBody"
        :initial-body="initialBody"
      />

      <!-- Response Tab -->
      <ResponsePanel
        v-if="activeTab === 'response'"
        :data="responseData"
        :status="responseStatus"
        :headers="responseHeaders"
        :time="responseTime"
        :metrics="metrics"
        :error="requestError"
      />

      <!-- Code Snippets Tab -->
      <div v-if="activeTab === 'snippets'" class="space-y-4">
        <CodeSnippet
          v-for="(snippet, lang) in endpoint.code_snippets"
          :key="lang"
          :language="String(lang)"
          :code="snippet"
        />
      </div>

      <!-- Schema Tab -->
      <div v-if="activeTab === 'schema'" class="space-y-6">
        <!-- Description -->
        <div v-if="endpoint.description || endpoint.doc_block" class="card p-4">
          <p class="text-sm text-gray-600 dark:text-gray-400 leading-relaxed">
            {{ endpoint.description || endpoint.doc_block }}
          </p>
        </div>

        <!-- Response Codes -->
        <div v-if="endpoint.responses && endpoint.responses.length > 0">
          <h3 class="text-sm font-semibold text-gray-900 dark:text-gray-100 mb-3 flex items-center gap-2">
            <svg class="w-4 h-4 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
            </svg>
            Response Codes
          </h3>
          <div class="flex flex-wrap gap-2">
            <span
              v-for="code in endpoint.responses"
              :key="code"
              :class="[
                'inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-sm font-mono font-bold border',
                Number(code) < 300
                  ? 'bg-emerald-50 dark:bg-emerald-900/20 text-emerald-700 dark:text-emerald-400 border-emerald-200 dark:border-emerald-800'
                  : Number(code) < 400
                    ? 'bg-blue-50 dark:bg-blue-900/20 text-blue-700 dark:text-blue-400 border-blue-200 dark:border-blue-800'
                    : Number(code) < 500
                      ? 'bg-amber-50 dark:bg-amber-900/20 text-amber-700 dark:text-amber-400 border-amber-200 dark:border-amber-800'
                      : 'bg-red-50 dark:bg-red-900/20 text-red-700 dark:text-red-400 border-red-200 dark:border-red-800'
              ]"
            >
              <span
                :class="[
                  'w-2 h-2 rounded-full',
                  Number(code) < 300 ? 'bg-emerald-500' : Number(code) < 400 ? 'bg-blue-500' : Number(code) < 500 ? 'bg-amber-500' : 'bg-red-500'
                ]"
              />
              {{ code }}
            </span>
          </div>
        </div>

        <!-- Example Response -->
        <div v-if="endpoint.example_response && Object.keys(endpoint.example_response).length > 0">
          <h3 class="text-sm font-semibold text-gray-900 dark:text-gray-100 mb-3 flex items-center gap-2">
            <svg class="w-4 h-4 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h7" />
            </svg>
            Example Response
          </h3>
          <div class="card overflow-hidden">
            <div class="bg-gray-50 dark:bg-gray-800 px-4 py-2 border-b border-gray-100 dark:border-gray-700">
              <span class="text-xs font-medium text-gray-500">JSON</span>
            </div>
            <pre class="p-4 bg-gray-50 dark:bg-gray-950 text-sm font-mono text-gray-800 dark:text-gray-200 overflow-x-auto leading-relaxed"><code>{{ JSON.stringify(endpoint.example_response, null, 2) }}</code></pre>
          </div>
        </div>

        <!-- Request Schema -->
        <div v-if="Object.keys(endpoint.rules || {}).length > 0">
          <h3 class="text-sm font-semibold text-gray-900 dark:text-gray-100 mb-3">Request Parameters</h3>
          <div class="card overflow-hidden">
            <table class="w-full text-sm">
              <thead class="bg-gray-50 dark:bg-gray-800">
                <tr>
                  <th class="text-left px-4 py-2 text-xs font-medium text-gray-500 uppercase">Parameter</th>
                  <th class="text-left px-4 py-2 text-xs font-medium text-gray-500 uppercase">Rules</th>
                  <th class="text-left px-4 py-2 text-xs font-medium text-gray-500 uppercase">Description</th>
                </tr>
              </thead>
              <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                <tr v-for="(rules, param) in endpoint.rules" :key="param">
                  <td class="px-4 py-2.5">
                    <code class="text-xs font-mono text-primary-600 dark:text-primary-400">{{ param }}</code>
                  </td>
                  <td class="px-4 py-2.5">
                    <div class="flex flex-wrap gap-1">
                      <span
                        v-for="rule in (Array.isArray(rules) ? rules : [rules])"
                        :key="rule"
                        class="text-[10px] px-1.5 py-0.5 rounded bg-gray-100 dark:bg-gray-800 text-gray-600 dark:text-gray-400 font-mono"
                      >
                        {{ rule }}
                      </span>
                    </div>
                  </td>
                  <td class="px-4 py-2.5 text-xs text-gray-500 dark:text-gray-400">
                    {{ endpoint.human_readable_rules?.[String(param)] || '' }}
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>

        <!-- Response Schema -->
        <div v-if="endpoint.response_schema && Object.keys(endpoint.response_schema).length > 0">
          <h3 class="text-sm font-semibold text-gray-900 dark:text-gray-100 mb-3">Response Schema</h3>
          <div class="card overflow-hidden">
            <table class="w-full text-sm">
              <thead class="bg-gray-50 dark:bg-gray-800">
                <tr>
                  <th class="text-left px-4 py-2 text-xs font-medium text-gray-500 uppercase">Field</th>
                  <th class="text-left px-4 py-2 text-xs font-medium text-gray-500 uppercase">Type</th>
                </tr>
              </thead>
              <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                <tr v-for="(schema, field) in endpoint.response_schema" :key="field">
                  <td class="px-4 py-2.5">
                    <code class="text-xs font-mono text-emerald-600 dark:text-emerald-400">{{ field }}</code>
                  </td>
                  <td class="px-4 py-2.5 text-xs text-gray-500 dark:text-gray-400 font-mono">
                    {{ typeof schema === 'object' ? schema.type : schema }}
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>

        <!-- Empty Schema State -->
        <div v-if="!endpoint.responses?.length && !Object.keys(endpoint.example_response || {}).length && !Object.keys(endpoint.rules || {}).length && !Object.keys(endpoint.response_schema || {}).length" class="text-center py-12">
          <svg class="w-12 h-12 text-gray-300 dark:text-gray-700 mx-auto mb-3" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
          </svg>
          <p class="text-sm text-gray-400 dark:text-gray-600">No schema information available for this endpoint.</p>
          <p class="text-xs text-gray-400 dark:text-gray-600 mt-1">
            Use <code class="font-mono bg-gray-100 dark:bg-gray-800 px-1 rounded">@api-lens-response 200 {"key": "value"}</code> to add example responses.
          </p>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, watch, onMounted, computed, nextTick } from 'vue'
import type { Endpoint, HeaderItem } from '@/types'
import hljs from 'highlight.js/lib/core'
import json from 'highlight.js/lib/languages/json'

hljs.registerLanguage('json', json)

const props = defineProps<{
  endpoint: Endpoint
  initialBody: Record<string, unknown>
}>()

const headers = defineModel<HeaderItem[]>('headers', { required: true })
const body = defineModel<Record<string, unknown>>('body', { required: true })

const bodyString = ref('')
const highlightedBody = ref('')
const fileParams = ref<Record<string, File[]>>({})
const dragOver = ref<string | null>(null)

// Detect file fields from rules
const fileFields = computed(() => {
  const fields: { key: string; multiple: boolean; rules: string }[] = []
  for (const [key, rules] of Object.entries(props.endpoint.rules || {})) {
    const ruleStr = Array.isArray(rules) ? rules.join('|') : String(rules)
    if (ruleStr.includes('file') || ruleStr.includes('image') || ruleStr.includes('mimes') || ruleStr.includes('mimetypes')) {
      fields.push({
        key,
        multiple: key.includes('.*'),
        rules: ruleStr,
      })
    }
  }
  return fields
})

// Check if request has file fields
const hasFiles = computed(() => fileFields.value.length > 0)

// Expose file params for parent
defineExpose({ fileParams, hasFiles })

/**
 * Deep merge: takes existing user data and adds any NEW keys from defaults
 * without overwriting existing values.
 */
function deepMergeNewKeys(existing: Record<string, unknown>, defaults: Record<string, unknown>): Record<string, unknown> {
  const result = { ...existing }
  for (const key of Object.keys(defaults)) {
    if (!(key in result)) {
      // New key — add it
      result[key] = defaults[key]
    } else if (
      typeof result[key] === 'object' && result[key] !== null && !Array.isArray(result[key]) &&
      typeof defaults[key] === 'object' && defaults[key] !== null && !Array.isArray(defaults[key])
    ) {
      // Both are objects — recurse
      result[key] = deepMergeNewKeys(result[key] as Record<string, unknown>, defaults[key] as Record<string, unknown>)
    }
    // Existing key with value — keep as-is
  }
  return result
}

function filterFileFields(obj: Record<string, unknown>): Record<string, unknown> {
  const filtered = { ...obj }
  fileFields.value.forEach(f => {
    const baseKey = f.key.replace('.*', '')
    delete filtered[baseKey]
    delete filtered[f.key]
  })
  return filtered
}

// Initialize body on mount: prefer existing user data, fallback to defaults
onMounted(() => {
  const defaults = filterFileFields(props.initialBody)

  if (body.value && Object.keys(body.value).length > 0) {
    // User already had data — merge any new fields from defaults
    const merged = deepMergeNewKeys(body.value, defaults)
    body.value = merged
    bodyString.value = JSON.stringify(merged, null, 2)
  } else {
    // First time — use defaults
    body.value = defaults
    bodyString.value = JSON.stringify(defaults, null, 2)
  }
  nextTick(highlightJson)
})

// When rules change (new field added), merge new keys into existing body
watch(() => props.initialBody, (newDefaults) => {
  const defaults = filterFileFields(newDefaults)
  const current = body.value && Object.keys(body.value).length > 0 ? body.value : {}
  const merged = deepMergeNewKeys(current, defaults)
  body.value = merged
  bodyString.value = JSON.stringify(merged, null, 2)
  nextTick(highlightJson)
})

function highlightJson() {
  try {
    highlightedBody.value = hljs.highlight(bodyString.value || ' ', { language: 'json' }).value
  } catch {
    highlightedBody.value = bodyString.value
  }
}

function updateBody() {
  try {
    body.value = JSON.parse(bodyString.value)
  } catch {
    // Invalid JSON, ignore
  }
  highlightJson()
}

function addHeader() {
  headers.value = [...headers.value, { key: '', value: '' }]
}

function removeHeader(index: number) {
  headers.value = headers.value.filter((_, i) => i !== index)
}

function handleFileSelect(key: string, event: Event) {
  const input = event.target as HTMLInputElement
  if (input.files) {
    fileParams.value[key] = Array.from(input.files)
  }
}

function handleDrop(key: string, event: DragEvent) {
  dragOver.value = null
  if (event.dataTransfer?.files) {
    fileParams.value[key] = Array.from(event.dataTransfer.files)
  }
}

function handleDragOver(key: string, event: DragEvent) {
  event.preventDefault()
  dragOver.value = key
}

function handleDragLeave() {
  dragOver.value = null
}

function removeFile(key: string, index: number) {
  fileParams.value[key] = fileParams.value[key].filter((_, i) => i !== index)
  if (fileParams.value[key].length === 0) {
    delete fileParams.value[key]
  }
}

function formatFileSize(bytes: number): string {
  if (bytes < 1024) return bytes + ' B'
  if (bytes < 1048576) return (bytes / 1024).toFixed(1) + ' KB'
  return (bytes / 1048576).toFixed(1) + ' MB'
}
</script>

<template>
  <div class="space-y-6">
    <!-- Path Parameters -->
    <div v-if="endpoint.path_parameters && Object.keys(endpoint.path_parameters).length > 0">
      <h3 class="text-sm font-semibold text-gray-900 dark:text-gray-100 mb-3 flex items-center gap-2">
        <svg class="w-4 h-4 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1" />
        </svg>
        Path Parameters
      </h3>
      <div class="card p-4 space-y-3">
        <div v-for="(rules, param) in endpoint.path_parameters" :key="param" class="flex items-center gap-3">
          <label class="text-sm font-mono text-gray-600 dark:text-gray-400 w-32">{{ param }}</label>
          <input type="text" class="input flex-1" :placeholder="'Value for :' + param" />
          <span class="text-xs text-gray-400">{{ Array.isArray(rules) ? rules.join(', ') : rules }}</span>
        </div>
      </div>
    </div>

    <!-- Headers -->
    <div>
      <h3 class="text-sm font-semibold text-gray-900 dark:text-gray-100 mb-3 flex items-center gap-2">
        <svg class="w-4 h-4 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
        </svg>
        Headers
        <span v-if="hasFiles" class="text-[10px] bg-amber-100 dark:bg-amber-900/30 text-amber-700 dark:text-amber-400 px-2 py-0.5 rounded-full font-normal">
          multipart/form-data (auto)
        </span>
      </h3>
      <div class="card p-4 space-y-2">
        <div v-for="(header, index) in headers" :key="index" class="flex items-center gap-2">
          <input
            v-model="header.key"
            type="text"
            placeholder="Header key"
            class="input flex-1"
          />
          <input
            v-model="header.value"
            type="text"
            placeholder="Header value"
            class="input flex-1"
          />
          <button @click="removeHeader(index)" class="btn-ghost p-2 text-red-400 hover:text-red-500">
            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
            </svg>
          </button>
        </div>
        <button @click="addHeader" class="btn-ghost text-xs">
          + Add Header
        </button>
      </div>
    </div>

    <!-- Request Body -->
    <div v-if="['POST', 'PUT', 'PATCH'].includes(endpoint.http_method)">
      <h3 class="text-sm font-semibold text-gray-900 dark:text-gray-100 mb-3 flex items-center gap-2">
        <svg class="w-4 h-4 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h7" />
        </svg>
        Request Body
      </h3>

      <!-- File Upload Zones -->
      <div v-if="hasFiles" class="space-y-3 mb-4">
        <div class="flex items-center gap-2 text-xs text-amber-600 dark:text-amber-400">
          <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
          </svg>
          This request contains file uploads. It will automatically be sent as <code class="font-mono bg-amber-100 dark:bg-amber-900/40 px-1 rounded">multipart/form-data</code>.
        </div>

        <div
          v-for="field in fileFields"
          :key="field.key"
          class="card overflow-hidden"
        >
          <div class="bg-gray-50 dark:bg-gray-800 px-4 py-2 border-b border-gray-100 dark:border-gray-700 flex items-center justify-between">
            <div class="flex items-center gap-2">
              <svg class="w-4 h-4 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.172 7l-6.586 6.586a2 2 0 102.828 2.828l6.414-6.586a4 4 0 00-5.656-5.656l-6.415 6.585a6 6 0 108.486 8.486L20.5 13" />
              </svg>
              <code class="text-xs font-mono text-gray-700 dark:text-gray-300">{{ field.key }}</code>
              <span class="text-[10px] px-1.5 py-0.5 rounded bg-purple-100 dark:bg-purple-900/30 text-purple-600 dark:text-purple-400 font-mono">
                {{ field.multiple ? 'multiple files' : 'single file' }}
              </span>
            </div>
            <span class="text-[10px] text-gray-400 font-mono">{{ field.rules }}</span>
          </div>

          <!-- Drop Zone -->
          <div
            @drop.prevent="handleDrop(field.key, $event)"
            @dragover="handleDragOver(field.key, $event)"
            @dragleave="handleDragLeave"
            @click="($refs[`file-${field.key}`] as HTMLInputElement[])?.[0]?.click()"
            :class="[
              'p-6 text-center cursor-pointer transition-all duration-200 border-2 border-dashed',
              dragOver === field.key
                ? 'border-primary-500 bg-primary-50 dark:bg-primary-900/20'
                : 'border-gray-200 dark:border-gray-700 hover:border-gray-300 dark:hover:border-gray-600 hover:bg-gray-50 dark:hover:bg-gray-800/50'
            ]"
          >
            <input
              :ref="`file-${field.key}`"
              type="file"
              :multiple="field.multiple"
              class="hidden"
              @change="handleFileSelect(field.key, $event)"
            />

            <!-- Uploaded files preview -->
            <div v-if="fileParams[field.key]?.length" class="space-y-2">
              <div
                v-for="(file, i) in fileParams[field.key]"
                :key="i"
                class="flex items-center justify-between bg-white dark:bg-gray-900 rounded-lg px-3 py-2 border border-gray-200 dark:border-gray-700"
              >
                <div class="flex items-center gap-2">
                  <svg class="w-4 h-4 text-emerald-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                  </svg>
                  <span class="text-sm text-gray-700 dark:text-gray-300">{{ file.name }}</span>
                  <span class="text-[10px] text-gray-400">{{ formatFileSize(file.size) }}</span>
                </div>
                <button @click.stop="removeFile(field.key, i)" class="text-gray-400 hover:text-red-500 transition-colors">
                  <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                  </svg>
                </button>
              </div>
              <p class="text-xs text-gray-400 mt-2">Click or drag to replace file</p>
            </div>

            <!-- Empty state -->
            <div v-else>
              <svg class="w-8 h-8 text-gray-300 dark:text-gray-600 mx-auto mb-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12" />
              </svg>
              <p class="text-sm text-gray-500 dark:text-gray-400">
                {{ field.multiple ? 'Drop files here or click to browse' : 'Drop a file here or click to browse' }}
              </p>
              <p class="text-[10px] text-gray-400 mt-1">{{ field.rules }}</p>
            </div>
          </div>
        </div>
      </div>

      <!-- Validation Rules Table -->
      <div v-if="Object.keys(endpoint.rules || {}).length > 0" class="card overflow-hidden mb-4">
        <table class="w-full text-sm">
          <thead class="bg-gray-50 dark:bg-gray-800">
            <tr>
              <th class="text-left px-4 py-2 text-xs font-medium text-gray-500 uppercase">Field</th>
              <th class="text-left px-4 py-2 text-xs font-medium text-gray-500 uppercase">Validation</th>
              <th class="text-left px-4 py-2 text-xs font-medium text-gray-500 uppercase">Description</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
            <tr v-for="(rules, field) in endpoint.rules" :key="field">
              <td class="px-4 py-2.5">
                <code class="text-xs font-mono text-primary-600 dark:text-primary-400">{{ field }}</code>
                <span v-if="(Array.isArray(rules) ? rules : [rules]).some(r => String(r).includes('required'))" class="text-red-500 ml-1">*</span>
                <span
                  v-if="(Array.isArray(rules) ? rules : [rules]).some(r => String(r).includes('file') || String(r).includes('image'))"
                  class="ml-1 text-[9px] bg-purple-100 dark:bg-purple-900/30 text-purple-600 dark:text-purple-400 px-1 py-0.5 rounded font-mono"
                >FILE</span>
              </td>
              <td class="px-4 py-2.5">
                <div class="flex flex-wrap gap-1">
                  <span
                    v-for="rule in (Array.isArray(rules) ? rules : [rules])"
                    :key="rule"
                    :class="[
                      'text-[10px] px-1.5 py-0.5 rounded font-mono',
                      String(rule).includes('required')
                        ? 'bg-red-50 dark:bg-red-900/20 text-red-600 dark:text-red-400'
                        : 'bg-gray-100 dark:bg-gray-800 text-gray-600 dark:text-gray-400'
                    ]"
                  >
                    {{ rule }}
                  </span>
                </div>
              </td>
              <td class="px-4 py-2.5 text-xs text-gray-500 dark:text-gray-400">
                {{ endpoint.human_readable_rules?.[String(field)] || '' }}
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <!-- JSON Editor with Syntax Highlighting -->
      <div class="card overflow-hidden">
        <div class="bg-gray-50 dark:bg-gray-800 px-4 py-2 flex items-center justify-between border-b border-gray-100 dark:border-gray-700">
          <span class="text-xs font-medium text-gray-500">JSON Body</span>
          <button
            @click="bodyString = JSON.stringify(initialBody, null, 2); updateBody()"
            class="text-xs text-primary-500 hover:text-primary-600"
          >
            Reset
          </button>
        </div>
        <div class="json-editor-wrapper relative">
          <pre
            class="json-editor-highlight p-4 m-0 font-mono text-sm leading-relaxed bg-gray-50 dark:bg-gray-950 overflow-auto pointer-events-none"
            aria-hidden="true"
          ><code v-html="highlightedBody"></code><br /></pre>
          <textarea
            v-model="bodyString"
            @input="updateBody"
            @scroll="($event.target as HTMLElement)?.previousElementSibling && (($event.target as HTMLElement).previousElementSibling!.scrollTop = ($event.target as HTMLElement).scrollTop, ($event.target as HTMLElement).previousElementSibling!.scrollLeft = ($event.target as HTMLElement).scrollLeft)"
            class="json-editor-textarea absolute inset-0 w-full h-full p-4 font-mono text-sm leading-relaxed bg-transparent text-transparent caret-gray-800 dark:caret-gray-100 border-0 focus:outline-none resize-none"
            spellcheck="false"
          /></div>
      </div>
    </div>
  </div>
</template>

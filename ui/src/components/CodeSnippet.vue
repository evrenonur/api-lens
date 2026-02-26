<script setup lang="ts">
import { ref, onMounted, watch } from 'vue'
import hljs from 'highlight.js/lib/core'
import javascript from 'highlight.js/lib/languages/javascript'
import python from 'highlight.js/lib/languages/python'
import php from 'highlight.js/lib/languages/php'
import bash from 'highlight.js/lib/languages/bash'

hljs.registerLanguage('javascript', javascript)
hljs.registerLanguage('python', python)
hljs.registerLanguage('php', php)
hljs.registerLanguage('bash', bash)
hljs.registerLanguage('curl', bash)

const props = defineProps<{
  language: string
  code: string
}>()

const copied = ref(false)
const highlightedCode = ref('')

const languageLabels: Record<string, string> = {
  curl: 'cURL',
  javascript: 'JavaScript',
  python: 'Python',
  php: 'PHP',
  axios: 'Axios',
  fetch: 'Fetch API',
  guzzle: 'Guzzle',
  http: 'Laravel HTTP',
}

const languageIcons: Record<string, string> = {
  curl: '🖥️',
  javascript: '🟡',
  python: '🐍',
  php: '🐘',
  axios: '🔄',
  fetch: '🌐',
  guzzle: '⚡',
  http: '🔵',
}

function highlight() {
  const lang = ['curl', 'guzzle', 'http'].includes(props.language) ? 'bash'
    : ['axios', 'fetch'].includes(props.language) ? 'javascript'
    : props.language

  try {
    highlightedCode.value = hljs.highlight(props.code, { language: lang }).value
  } catch {
    highlightedCode.value = props.code
  }
}

function copyCode() {
  navigator.clipboard.writeText(props.code)
  copied.value = true
  setTimeout(() => { copied.value = false }, 2000)
}

onMounted(highlight)
watch(() => props.code, highlight)
</script>

<template>
  <div class="card overflow-hidden">
    <div class="flex items-center justify-between px-4 py-2 bg-gray-50 dark:bg-gray-800 border-b border-gray-100 dark:border-gray-700">
      <div class="flex items-center gap-2">
        <span class="text-sm">{{ languageIcons[language] || '📝' }}</span>
        <span class="text-xs font-semibold text-gray-500 dark:text-gray-400">
          {{ languageLabels[language] || language }}
        </span>
      </div>
      <button
        @click="copyCode"
        class="flex items-center gap-1 text-xs text-gray-400 hover:text-gray-600 dark:hover:text-gray-300 transition-colors"
      >
        <svg v-if="!copied" class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z" />
        </svg>
        <svg v-else class="w-3.5 h-3.5 text-emerald-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
        </svg>
        {{ copied ? 'Copied!' : 'Copy' }}
      </button>
    </div>
    <pre class="p-4 overflow-x-auto bg-gray-50 dark:bg-gray-950 text-gray-800 dark:text-gray-100 border-t border-gray-100 dark:border-gray-800"><code class="text-sm font-mono leading-relaxed" v-html="highlightedCode"></code></pre>
  </div>
</template>

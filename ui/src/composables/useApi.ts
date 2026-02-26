import { ref } from 'vue'
import type { Endpoint, HeaderItem, HistoryEntry, ApiLensMetrics } from '@/types'
import { useEndpointStore } from '@/stores/endpoints'

function objectToFormData(obj: Record<string, unknown>, formData?: FormData, namespace?: string): FormData {
  formData = formData || new FormData()
  for (const property in obj) {
    if (obj[property] === undefined) continue
    const formKey = namespace ? `${namespace}[${property}]` : property
    const val = obj[property]
    if (val instanceof File) {
      formData.append(formKey, val)
    } else if (val instanceof FileList) {
      for (let i = 0; i < val.length; i++) {
        formData.append(`${formKey}[${i}]`, val[i])
      }
    } else if (typeof val === 'object' && val !== null && !(val instanceof Date)) {
      objectToFormData(val as Record<string, unknown>, formData, formKey)
    } else if (val instanceof Date) {
      formData.append(formKey, val.toISOString())
    } else {
      formData.append(formKey, String(val))
    }
  }
  return formData
}

export function useApi() {
  const loading = ref(false)
  const responseData = ref<unknown>(null)
  const responseStatus = ref<number | null>(null)
  const responseHeaders = ref<Record<string, string>>({})
  const responseTime = ref<number>(0)
  const metrics = ref<ApiLensMetrics | null>(null)
  const error = ref<string | null>(null)

  async function sendRequest(
    endpoint: Endpoint,
    customHeaders: HeaderItem[] = [],
    customBody: Record<string, unknown> = {},
    fileParams: Record<string, File[]> = {}
  ) {
    loading.value = true
    error.value = null
    responseData.value = null
    metrics.value = null

    const store = useEndpointStore()
    const baseUrl = window.__API_LENS_CONFIG__?.baseUrl || ''
    const url = `${baseUrl}/${endpoint.uri.replace(/\{[^}]+\}/g, '1')}`

    const hasFiles = Object.keys(fileParams).length > 0 && Object.values(fileParams).some(f => f.length > 0)

    const headers: Record<string, string> = {
      'Accept': 'application/json',
      'X-Api-Lens': '1',
      'X-Request-LRD': '1',
    }

    // Only set Content-Type for JSON requests (not multipart)
    if (!hasFiles) {
      headers['Content-Type'] = 'application/json'
    }

    // Add default headers from config
    if (store.config?.default_headers) {
      store.config.default_headers.forEach(h => {
        headers[h.key] = h.value
      })
    }

    // Add custom headers
    customHeaders.forEach(h => {
      if (h.key.trim()) headers[h.key] = h.value
    })

    // If has files and Content-Type was set from config/custom headers, remove it
    if (hasFiles) {
      delete headers['Content-Type']
    }

    const fetchOptions: RequestInit = {
      method: endpoint.http_method,
      headers,
    }

    // Add body for POST/PUT/PATCH
    if (['POST', 'PUT', 'PATCH'].includes(endpoint.http_method)) {
      if (hasFiles) {
        // Build FormData with body + files
        const formData = objectToFormData(customBody)
        // Append files
        for (const [key, files] of Object.entries(fileParams)) {
          const parts = key.split('.')
          const formKey = parts.reduce((current: string, part: string, index: number) => {
            if (index === parts.length - 1 && (part === '*' || !isNaN(Number(part)))) {
              return current
            }
            return !current ? part : `${current}[${part}]`
          }, '')
          if (key.includes('.*')) {
            files.forEach((file, i) => {
              formData.append(`${formKey}[${i}]`, file)
            })
          } else if (files.length > 0) {
            formData.append(formKey, files[0])
          }
        }
        fetchOptions.body = formData
      } else if (Object.keys(customBody).length > 0) {
        fetchOptions.body = JSON.stringify(customBody)
      }
    }

    const startTime = performance.now()

    try {
      const response = await fetch(url, fetchOptions)
      responseTime.value = Math.round(performance.now() - startTime)
      responseStatus.value = response.status

      // Extract headers
      const respHeaders: Record<string, string> = {}
      response.headers.forEach((value, key) => {
        respHeaders[key] = value
      })
      responseHeaders.value = respHeaders

      // Parse response
      const text = await response.text()
      try {
        const json = JSON.parse(text)

        // Extract API Lens metrics if present
        if (json._api_lens) {
          metrics.value = json._api_lens
          responseData.value = json.data
        } else {
          responseData.value = json
        }
      } catch {
        responseData.value = text
      }

      // Add to history
      const historyEntry: HistoryEntry = {
        id: crypto.randomUUID(),
        endpoint_uri: endpoint.uri,
        http_method: endpoint.http_method,
        timestamp: Date.now(),
        status_code: response.status,
        execution_ms: responseTime.value,
        request_body: customBody,
        response_body: responseData.value,
        headers: customHeaders,
      }
      store.addToHistory(historyEntry)

    } catch (err) {
      responseTime.value = Math.round(performance.now() - startTime)
      error.value = err instanceof Error ? err.message : 'Request failed'
      responseStatus.value = 0
    } finally {
      loading.value = false
    }
  }

  function reset() {
    responseData.value = null
    responseStatus.value = null
    responseHeaders.value = {}
    responseTime.value = 0
    metrics.value = null
    error.value = null
  }

  return {
    loading,
    responseData,
    responseStatus,
    responseHeaders,
    responseTime,
    metrics,
    error,
    sendRequest,
    reset,
  }
}

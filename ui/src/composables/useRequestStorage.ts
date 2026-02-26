import type { HeaderItem, ApiLensMetrics } from '@/types'

const STORAGE_KEY = 'api-lens-requests'

interface StoredResponseData {
  data: unknown
  status: number | null
  headers: Record<string, string>
  time: number
  metrics: ApiLensMetrics | null
  error: string | null
}

interface StoredRequestData {
  body: Record<string, unknown>
  headers: HeaderItem[]
  response: StoredResponseData | null
  updatedAt: number
}

type RequestRegistry = Record<string, StoredRequestData>

function getRegistry(): RequestRegistry {
  try {
    const raw = localStorage.getItem(STORAGE_KEY)
    return raw ? JSON.parse(raw) : {}
  } catch {
    return {}
  }
}

function saveRegistry(registry: RequestRegistry): void {
  try {
    localStorage.setItem(STORAGE_KEY, JSON.stringify(registry))
  } catch { /* quota exceeded etc. */ }
}

function makeKey(method: string, uri: string): string {
  return `${method}-${uri}`
}

export function useRequestStorage() {
  function load(method: string, uri: string): StoredRequestData | null {
    const registry = getRegistry()
    return registry[makeKey(method, uri)] || null
  }

  function save(method: string, uri: string, body: Record<string, unknown>, headers: HeaderItem[], response?: StoredResponseData | null): void {
    const registry = getRegistry()
    const existing = registry[makeKey(method, uri)]
    registry[makeKey(method, uri)] = {
      body,
      headers: headers.filter(h => h.key.trim() !== ''),
      response: response !== undefined ? response : (existing?.response || null),
      updatedAt: Date.now(),
    }
    saveRegistry(registry)
  }

  function saveResponse(method: string, uri: string, response: StoredResponseData): void {
    const registry = getRegistry()
    const key = makeKey(method, uri)
    if (registry[key]) {
      registry[key].response = response
      registry[key].updatedAt = Date.now()
      saveRegistry(registry)
    }
  }

  /**
   * Smart merge: keeps saved values for existing keys, adds new keys with defaults,
   * removes keys that no longer exist in the endpoint rules.
   */
  function mergeBody(saved: Record<string, unknown>, current: Record<string, unknown>): Record<string, unknown> {
    const merged: Record<string, unknown> = {}

    for (const key of Object.keys(current)) {
      if (key in saved) {
        // Key exists in both — keep user's value, but recurse if both are objects
        const savedVal = saved[key]
        const currentVal = current[key]
        if (
          savedVal && currentVal &&
          typeof savedVal === 'object' && typeof currentVal === 'object' &&
          !Array.isArray(savedVal) && !Array.isArray(currentVal)
        ) {
          merged[key] = mergeBody(savedVal as Record<string, unknown>, currentVal as Record<string, unknown>)
        } else {
          merged[key] = savedVal
        }
      } else {
        // New key — use default from current endpoint
        merged[key] = current[key]
      }
    }
    // Keys in saved but NOT in current are dropped (removed fields)
    return merged
  }

  function remove(method: string, uri: string): void {
    const registry = getRegistry()
    delete registry[makeKey(method, uri)]
    saveRegistry(registry)
  }

  function clear(): void {
    localStorage.removeItem(STORAGE_KEY)
  }

  return { load, save, saveResponse, mergeBody, remove, clear }
}

import type { HeaderItem } from '@/types'

const STORAGE_KEY = 'api-lens-requests'

interface StoredRequestData {
  body: Record<string, unknown>
  headers: HeaderItem[]
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

  function save(method: string, uri: string, body: Record<string, unknown>, headers: HeaderItem[]): void {
    const registry = getRegistry()
    registry[makeKey(method, uri)] = {
      body,
      headers: headers.filter(h => h.key.trim() !== ''),
      updatedAt: Date.now(),
    }
    saveRegistry(registry)
  }

  function remove(method: string, uri: string): void {
    const registry = getRegistry()
    delete registry[makeKey(method, uri)]
    saveRegistry(registry)
  }

  function clear(): void {
    localStorage.removeItem(STORAGE_KEY)
  }

  return { load, save, remove, clear }
}

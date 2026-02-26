/**
 * API Lens - TypeScript Type Definitions
 */

export interface Endpoint {
  uri: string
  methods: string[]
  http_method: string
  middlewares: string[]
  controller: string
  controller_full_path: string
  method: string
  rules: Record<string, string[]>
  path_parameters: Record<string, string[]>
  doc_block: string
  responses: string[]
  response_schema: Record<string, SchemaField>
  code_snippets: Record<string, string>
  human_readable_rules: Record<string, string>
  description: string | null
  summary: string | null
  tags: string[]
  deprecated_since: string | null
  auth_type: string | null
  rate_limit: RateLimit
  example_request: Record<string, unknown>
  example_response: Record<string, unknown>
  group?: string
  group_index?: number
}

export interface SchemaField {
  type: string
  nullable?: boolean
  description?: string
}

export interface RateLimit {
  requests_per_minute?: number
  requests_per_hour?: number
}

export interface ApiLensConfig {
  title: string
  version: string
  default_headers: HeaderItem[]
  code_snippets: string[]
  features: FeatureFlags
  visibility: VisibilityFlags
}

export interface VisibilityFlags {
  meta_data: boolean
  sql_data: boolean
  logs_data: boolean
  models_data: boolean
}

export interface UpdateInfo {
  current_version: string
  latest_version: string | null
  update_available: boolean
  error?: string
}

export interface FeatureFlags {
  response_schema: boolean
  code_snippets: boolean
  human_readable_rules: boolean
  rate_limit_info: boolean
  auth_detection: boolean
}

export interface HeaderItem {
  key: string
  value: string
}

export interface ApiLensMetrics {
  queries: SqlQuery[]
  queries_count: number
  queries_time_ms: number
  logs: unknown[]
  models: Record<string, Record<string, number>>
  models_timeline: ModelEvent[]
  memory: string
  execution_ms: number
}

export interface SqlQuery {
  sql: string
  bindings: unknown[]
  time: number
  connection_name: string
}

export interface ModelEvent {
  event: string
  model: string
  timestamp: number
}

export interface HistoryEntry {
  id: string
  endpoint_uri: string
  http_method: string
  timestamp: number
  status_code: number
  execution_ms: number
  request_body: Record<string, unknown>
  response_body: unknown
  headers: HeaderItem[]
}

export type HttpMethod = 'GET' | 'POST' | 'PUT' | 'PATCH' | 'DELETE' | 'HEAD'

export type ThemeMode = 'light' | 'dark' | 'system'

export type GroupBy = 'default' | 'api_uri' | 'controller_full_path' | 'tag'

export type SortBy = 'default' | 'route_names' | 'method_names'

export const METHOD_COLORS: Record<string, string> = {
  GET: 'bg-emerald-500/15 text-emerald-600 dark:text-emerald-400 border-emerald-500/30',
  POST: 'bg-blue-500/15 text-blue-600 dark:text-blue-400 border-blue-500/30',
  PUT: 'bg-amber-500/15 text-amber-600 dark:text-amber-400 border-amber-500/30',
  PATCH: 'bg-orange-500/15 text-orange-600 dark:text-orange-400 border-orange-500/30',
  DELETE: 'bg-red-500/15 text-red-600 dark:text-red-400 border-red-500/30',
  HEAD: 'bg-purple-500/15 text-purple-600 dark:text-purple-400 border-purple-500/30',
}

export const METHOD_BADGE_COLORS: Record<string, string> = {
  GET: 'bg-emerald-500 text-white',
  POST: 'bg-blue-500 text-white',
  PUT: 'bg-amber-500 text-white',
  PATCH: 'bg-orange-500 text-white',
  DELETE: 'bg-red-500 text-white',
  HEAD: 'bg-purple-500 text-white',
}

export const STATUS_COLORS: Record<string, string> = {
  '2xx': 'text-emerald-500',
  '3xx': 'text-blue-500',
  '4xx': 'text-amber-500',
  '5xx': 'text-red-500',
}

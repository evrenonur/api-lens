/// <reference types="vite/client" />

declare module '*.vue' {
  import type { DefineComponent } from 'vue'
  const component: DefineComponent<{}, {}, any>
  export default component
}

interface Window {
  __API_LENS_CONFIG__: {
    apiUrl: string
    configUrl: string
    baseUrl: string
    appName: string
  }
}

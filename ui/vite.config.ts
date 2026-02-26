import { defineConfig } from 'vite'
import vue from '@vitejs/plugin-vue'
import { resolve } from 'path'

export default defineConfig({
  plugins: [vue()],
  root: '.',
  base: './',
  build: {
    outDir: '../resources/dist',
    emptyOutDir: true,
    rollupOptions: {
      input: 'src/main.ts',
      output: {
        entryFileNames: 'assets/index.js',
        chunkFileNames: 'assets/[name]-[hash].js',
        assetFileNames: 'assets/[name].[ext]',
      },
    },
  },
  resolve: {
    alias: {
      '@': resolve(__dirname, 'src'),
    },
  },
  server: {
    port: 3100,
    proxy: {
      '/api-lens/api': {
        target: 'http://localhost:8000',
        changeOrigin: true,
      },
      '/api-lens/config': {
        target: 'http://localhost:8000',
        changeOrigin: true,
      },
    },
  },
})

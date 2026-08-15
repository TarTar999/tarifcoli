import { defineConfig } from 'vite'
import react from '@vitejs/plugin-react'
import path from 'node:path'

// Le front est compilé dans backend/public/app : PHP sert alors tout (API + interface).
export default defineConfig({
  plugins: [react()],
  resolve: { alias: { '@': path.resolve(__dirname, './src') } },
  build: { outDir: '../backend/public/app', emptyOutDir: true },
  server: {
    port: 5173,
    proxy: {
      '/api': 'http://localhost:8000',
      '/f': 'http://localhost:8000',
    },
  },
})

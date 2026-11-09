import react from '@vitejs/plugin-react'
import tailwindcss from '@tailwindcss/vite'
import { defineConfig } from 'vitest/config'

// https://vite.dev/config/
export default defineConfig({
  plugins: [react(), tailwindcss()],
  server: {
    // Accept both localhost and 127.0.0.1 so the same-origin API proxy works
    // regardless of which local address the browser uses.
    host: true,
    // Same-origin API during development: auth (session/XSRF) cookies stay
    // first-party instead of crossing the 5173 → 8000 origin boundary.
    proxy: {
      '/api': 'http://127.0.0.1:8000',
      '/sanctum': 'http://127.0.0.1:8000',
    },
  },
  test: {
    environment: 'jsdom',
    setupFiles: ['./src/tests/setup.ts'],
  },
})

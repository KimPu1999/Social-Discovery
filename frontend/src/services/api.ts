import axios from 'axios'

const configuredBaseUrl = import.meta.env.VITE_API_BASE_URL ?? '/api/v1'
const apiBaseUrl = (() => {
  if (typeof window === 'undefined') return configuredBaseUrl
  // In local development, always use Vite's same-origin proxy. This keeps
  // Sanctum's session/XSRF cookies on the frontend origin and avoids browser
  // network/CORS differences between localhost and 127.0.0.1.
  if (['localhost', '127.0.0.1', '::1'].includes(window.location.hostname)) return '/api/v1'
  try {
    const url = new URL(configuredBaseUrl, window.location.origin)
    return url.toString().replace(/\/$/, '')
  } catch { return configuredBaseUrl }
})()

export const api = axios.create({
  baseURL: apiBaseUrl,
  withCredentials: true,
  withXSRFToken: true,
  xsrfCookieName: 'XSRF-TOKEN',
  xsrfHeaderName: 'X-XSRF-TOKEN',
  headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
})

export const csrf = () => axios.get(`${api.defaults.baseURL?.replace(/\/api\/v1$/, '')}/sanctum/csrf-cookie`, { withCredentials: true, withXSRFToken: true, xsrfCookieName: 'XSRF-TOKEN', xsrfHeaderName: 'X-XSRF-TOKEN' })

api.interceptors.response.use(
  (response) => response,
  async (error) => {
    const request = error.config as (typeof error.config & { _csrfRetried?: boolean }) | undefined
    if (error.response?.status === 419 && request && !request._csrfRetried) {
      request._csrfRetried = true
      await csrf()
      return api(request)
    }
    const message = error.response?.data?.message ?? (!error.response
      ? 'Cannot reach the Social Discovery API. Start PostgreSQL and the Laravel server, then try again.'
      : 'Something went wrong. Please try again.')
    const normalized = new Error(message) as Error & { status?: number; errors?: Record<string, string[]> }
    normalized.status = error.response?.status
    normalized.errors = error.response?.data?.errors
    return Promise.reject(normalized)
  },
)

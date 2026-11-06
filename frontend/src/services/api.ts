import axios from 'axios'

export const api = axios.create({
  baseURL: import.meta.env.VITE_API_BASE_URL ?? 'http://localhost:8000/api/v1',
  withCredentials: true,
  headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
})

export const csrf = () => axios.get(`${api.defaults.baseURL?.replace(/\/api\/v1$/, '')}/sanctum/csrf-cookie`, { withCredentials: true })

api.interceptors.response.use(
  (response) => response,
  (error) => {
    const normalized = new Error(error.response?.data?.message ?? 'Something went wrong. Please try again.') as Error & { status?: number; errors?: Record<string, string[]> }
    normalized.status = error.response?.status
    normalized.errors = error.response?.data?.errors
    return Promise.reject(normalized)
  },
)

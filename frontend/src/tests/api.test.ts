import { describe, expect, it } from 'vitest'
import { api } from '../services/api'

describe('API client', () => {
  it('uses a versioned API base URL and credentialed requests', () => {
    expect(api.defaults.baseURL).toContain('/api/v1')
    expect(api.defaults.withCredentials).toBe(true)
  })
})

import { api } from './api'
import type { AdminUser, Paginated } from '../types'

export const adminService = {
  /** Server-side paginated user list. `per_page` maps to the existing admin API contract. */
  async users(page = 1, perPage: 10 | 20 | 50 = 20) {
    const { data } = await api.get<{ users: unknown }>('/admin/users', { params: { page, per_page: perPage } })
    const envelope = data.users as { data: AdminUser[]; meta?: Paginated<AdminUser>['meta'] }
    return { users: envelope.data, meta: envelope.meta ?? { current_page: page, last_page: 1, per_page: perPage, total: envelope.data.length } }
  },
  async user(id: number) { const { data } = await api.get<{ user: AdminUser }>(`/admin/users/${id}`); return data.user },
  async updateStatus(id: number, status: string) { const { data } = await api.patch<{ user: AdminUser }>(`/admin/users/${id}/status`, { status }); return data.user },
}

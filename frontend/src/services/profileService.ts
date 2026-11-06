import { api } from './api'
import type { Interest, Profile } from '../types'
export const profileService = {
  async get() { const { data } = await api.get<{ profile: Profile }>('/profile'); return data.profile },
  async update(payload: Partial<Profile>) { const { data } = await api.put<{ profile: Profile }>('/profile', payload); return data.profile },
  async interests() { const { data } = await api.get<{ interests: Interest[] }>('/profile/interests'); return data.interests },
  async updateInterests(interest_ids: number[]) { const { data } = await api.put<{ interests: Interest[] }>('/profile/interests', { interest_ids }); return data.interests },
  async upload(field: 'avatar' | 'cover', file: File) { const form = new FormData(); form.append(field, file); const { data } = await api.post<{ profile: Profile }>(`/profile/${field}`, form); return data.profile },
}
